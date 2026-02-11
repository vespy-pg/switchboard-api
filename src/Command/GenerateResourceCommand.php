<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;

class GenerateResourceCommand extends Command
{
    protected static $defaultName = 'app:make:resource';
    private string $projectDir;
    private EntityManagerInterface $entityManager;

    /**
     * Tables to exclude from constant generation (even if they have _code as PK)
     */
    private array $constantGenerationExclusions = [
        'tbl_role',
    ];

    public function __construct(EntityManagerInterface $entityManager, KernelInterface $kernel)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir    = $kernel->getProjectDir();
    }

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
        $this
            ->setDescription('Generates an entity (or view) with ApiPlatform endpoints and validation based on DB metadata.')
            ->setHelp(
                "This command reads metadata (columns, primary keys, foreign keys, unique constraints) from a schema (default 'rss') and generates an entity class.
It includes both tables and views (views are prefixed with 'v_' and are placed in App\\ApiResource; no create/update endpoints are generated for views).
For tables, it asks whether to generate create and update endpoints, and always asks whether to prefix routes with organizationId.
When organizationId is prefixed, two separate ApiResource attributes are generated with a uriTemplate built from the entity name converted to kebab-case then pluralized.
Writable properties get additional 'create' and/or 'update' groups and basic Assert validation constraints are generated.
Columns whose names contain '_tfs' are excluded.
Setters return void and if a created_datetime column exists, a constructor sets it using new DateTime().
Finally, a new question 'Generation mode?' is asked: in 'full' mode the complete file is generated and written to <root>/generated (overwriting any existing file); in 'diff' mode the command merges in any missing properties and methods into the existing file's body."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $conn = $this->entityManager->getConnection();

        // Use app schema
        $selectedSchema = 'app';

        // Verify schema exists.
        $schemaExists = $conn->fetchOne(
            'SELECT 1 FROM information_schema.schemata WHERE schema_name = ?',
            [$selectedSchema]
        );
        if (!$schemaExists) {
            $io->error("Schema '{$selectedSchema}' does not exist.");
            return Command::FAILURE;
        }

        // Fetch tables and views.
        $resources = $conn->fetchFirstColumn(
            "SELECT table_name FROM information_schema.tables 
             WHERE table_schema = ? AND table_type IN ('BASE TABLE', 'VIEW')
             ORDER BY CASE WHEN table_type = 'BASE TABLE' THEN 0 else 1 END, table_name
             ",
            [$selectedSchema]
        );
        if (empty($resources)) {
            $io->error("No tables or views found in schema '{$selectedSchema}'.");
            return Command::FAILURE;
        }

        // Build display list: for tables starting with "tbl_" remove that prefix; for views (typically starting with "v_") leave intact.
        $displayResources = array_map(function ($r) {
            return (stripos($r, 'tbl_') === 0) ? preg_replace('/^tbl_/', '', $r) : $r;
        }, $resources);

        // Ask user to choose a resource.
        $resourceQuestion = new ChoiceQuestion('Select table/view:', $displayResources);
        $selectedResourceDisplay = $io->askQuestion($resourceQuestion);
        $selectedResource = null;
        foreach ($resources as $r) {
            $disp = (stripos($r, 'tbl_') === 0) ? preg_replace('/^tbl_/', '', $r) : $r;
            if ($disp === $selectedResourceDisplay) {
                $selectedResource = $r;
                break;
            }
        }
        if (!$selectedResource) {
            $io->error("Resource '{$selectedResourceDisplay}' not found in schema '{$selectedSchema}'.");
            return Command::FAILURE;
        }

        // Determine if the resource is a view (starts with "v_").
        $isView = (stripos($selectedResource, 'v_') === 0);

        // For tables, remove "tbl_" prefix; for views, leave name intact.
        $tableBase = $isView ? $selectedResource : preg_replace('/^tbl_/', '', $selectedResource);
        $tableBaseLower = strtolower($tableBase);

        // Set namespace: tables -> App\Entity, views -> App\ApiResource.
        $entityNamespace = $isView ? 'App\\ApiResource' : 'App\\Entity';

        // Retrieve columns.
        $columns = $conn->fetchAllAssociative(
            'SELECT column_name, data_type, is_nullable, column_default, character_maximum_length, numeric_precision, numeric_scale
             FROM information_schema.columns 
             WHERE table_schema = ? AND table_name = ?',
            [$selectedSchema, $selectedResource]
        );
        if (empty($columns)) {
            $io->error("No columns found for resource '{$selectedResource}'.");
            return Command::FAILURE;
        }

        // Retrieve primary key columns.
        $primaryKeys = $conn->fetchFirstColumn(
            "SELECT kcu.column_name 
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
             WHERE tc.table_schema = ? AND tc.table_name = ? AND tc.constraint_type = 'PRIMARY KEY'",
            [$selectedSchema, $selectedResource]
        );
        if (!$primaryKeys) {
            $primaryKeys = [];
        }

        // Retrieve unique constraint columns.
        $uniqueColumns = $conn->fetchAllAssociative(
            "SELECT
                        array_agg(a.attname ORDER BY a.attnum) AS columns,
                        pg_get_expr(ix.indpred, t.oid) AS where_clause
                    FROM pg_class t
                             JOIN pg_namespace n ON n.oid = t.relnamespace
                             JOIN pg_index ix ON t.oid = ix.indrelid
                             JOIN pg_class i ON i.oid = ix.indexrelid
                             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                    WHERE t.relname = ?
                      AND n.nspname = ?
                      AND t.relkind = 'r'
                      AND ix.indisprimary = false
                    GROUP BY i.relname, ix.indisunique, ix.indisprimary, ix.indpred, t.oid
                    HAVING ix.indisunique = true;",
            [$selectedResource, $selectedSchema]
        );
        if (!$uniqueColumns) {
            $uniqueColumns = [];
        }
        // Retrieve foreign key info.
        $foreignKeysRaw = $conn->fetchAllAssociative(
            "SELECT
    att2.attname AS column_name,
    clf.relname  AS foreign_table_name,
    attf.attname AS foreign_column_name
FROM pg_constraint con
         JOIN pg_class cl            ON cl.oid = con.conrelid
         JOIN pg_namespace nsl       ON nsl.oid = cl.relnamespace
         JOIN pg_class clf           ON clf.oid = con.confrelid
         JOIN pg_namespace nsf       ON nsf.oid = clf.relnamespace
         JOIN unnest(con.conkey)  WITH ORDINALITY AS ck(attnum, ord) ON true
         JOIN unnest(con.confkey) WITH ORDINALITY AS fk(attnum, ord) ON fk.ord = ck.ord
         JOIN pg_attribute att2      ON att2.attrelid = con.conrelid  AND att2.attnum = ck.attnum
         JOIN pg_attribute attf      ON attf.attrelid = con.confrelid AND attf.attnum = fk.attnum
WHERE con.contype = 'f'
  AND nsl.nspname = ?
  AND cl.relname  = ?",
            [$selectedSchema, $selectedResource]
        );
        $foreignKeyMap = [];
        foreach ($foreignKeysRaw as $fk) {
            $foreignKeyMap[$fk['column_name']] = $fk;
        }
        // Exclude any column whose name contains "_tfs" (case-insensitive).
        foreach ($columns as $key => $col) {
            if (stripos($col['column_name'], '_tfs') !== false) {
                unset($columns[$key]);
            }
        }
        // Determine the entity class name.
        $entityClassName = $isView
            ? $this->convertToCamelCase($selectedResource, true)
            : $this->convertToCamelCase($selectedResource, true, 'tbl_');
        $tableFullName = $selectedSchema . '.' . $selectedResource;
        // Ask generation mode.
        $modeQuestion = new ChoiceQuestion(
            'Generation mode? [full] - best for new and replaced entities. [diff] - best for updating an existing entity.',
            ['full' => 'Full', 'diff' => 'Diff'],
            'full'
        );
        $mode = $io->askQuestion($modeQuestion);
        $existingColumns = $mode === 'diff' ? $this->extractExistingColumnNames('App\\' . ($isView ? 'ApiResource' : 'Entity') . '\\' . $entityClassName) : [];
        // Separate scalar and relation columns.
        $scalarColumns = [];
        $relationColumns = [];
        $containsOrganization = false;

        foreach ($columns as $col) {
            if ($col['column_name'] === 'organization_id') {
                $containsOrganization = true;
            }
            if ($mode === 'diff' && in_array($col['column_name'], $existingColumns)) {
                continue;
            }
            if (isset($foreignKeyMap[$col['column_name']])) {
                $relationColumns[] = $col;
            } else {
                $scalarColumns[] = $col;
            }
        }

        // Always ask the organizationId prefix question.
        $prefixRoutesQuestion = new ChoiceQuestion(
            'Prefix routes with organizationId?',
            ['yes' => 'yes', 'no' => 'no'],
            $containsOrganization ? 'yes' : 'no'
        );
        $prefixRoutesAnswer = $io->askQuestion($prefixRoutesQuestion);
        $prefixRoutes = ($prefixRoutesAnswer === 'yes');

        // For tables (not views), ask about create and update endpoints.
        if ($isView) {
            $generateCreate = false;
            $generateUpdate = false;
        } else {
            $createEndpointQuestion = new ChoiceQuestion(
                'Generate POST endpoint?',
                ['yes' => 'yes', 'no' => 'no'],
                'no'
            );
            $generateCreate = ($io->askQuestion($createEndpointQuestion) === 'yes');

            $updateEndpointQuestion = new ChoiceQuestion(
                'Generate PATCH endpoint?',
                ['yes' => 'yes', 'no' => 'no'],
                'no'
            );
            $generateUpdate = ($io->askQuestion($updateEndpointQuestion) === 'yes');
        }

        // Build dynamic filter definitions.
        $searchProperties = [];
        $orderProperties  = [];
        $hasCreatedDatetime = false;
        foreach ($scalarColumns as $col) {
            $removePrefix = !$isView ? $tableBaseLower . '_' : null;
            $propertyName = $this->convertToCamelCase($col['column_name'], false, $removePrefix);
            $mapping = $this->mapColumnType($col['data_type']);
            if ($col['column_name'] === 'created_datetime') {
                $hasCreatedDatetime = true;
            }
            // Use 'exact' for id fields, 'partial' for string fields, 'exact' for others
            if ($propertyName === 'id' || in_array($col['column_name'], $primaryKeys, true)) {
                $searchProperties[$propertyName] = 'exact';
            } else {
                $searchProperties[$propertyName] = ($mapping['php'] === 'string') ? 'partial' : 'exact';
            }
            $orderProperties[] = $propertyName;
        }
        foreach ($relationColumns as $col) {
            $raw = $col['column_name'];
            $propName = $this->convertToCamelCase(rtrim($raw, '_id'), false);
            $searchProperties[$propName] = 'exact';
            $orderProperties[] = $propName;
        }

        // Check if table has _code as PK and should generate constants
        $shouldGenerateConstants = false;
        $codeConstants = [];
        if (!$isView && !in_array($selectedResource, $this->constantGenerationExclusions)) {
            // Check if any primary key ends with _code
            foreach ($primaryKeys as $pk) {
                if (str_ends_with($pk, '_code')) {
                    $shouldGenerateConstants = true;
                    // Query the table to get all code values
                    $codeValues = $conn->fetchFirstColumn(
                        "SELECT $pk FROM $tableFullName ORDER BY $pk"
                    );
                    foreach ($codeValues as $codeValue) {
                        $codeConstants[] = $codeValue;
                    }
                    break;
                }
            }
        }

        // Generate the full expected content.
        $newContent = $this->generateFullContent(
            $entityNamespace,
            $tableFullName,
            $entityClassName,
            $tableBaseLower,
            $isView,
            $primaryKeys,
            $uniqueColumns,
            $scalarColumns,
            $relationColumns,
            $foreignKeyMap,
            $searchProperties,
            $orderProperties,
            $prefixRoutes,
            $generateCreate,
            $generateUpdate,
            $hasCreatedDatetime,
            $codeConstants,
        );

        // Write files to <root>/generated (we allow overwriting).
        $targetFolder = $this->projectDir . '/generated';
        if (!is_dir($targetFolder)) {
            mkdir($targetFolder, 0777, true);
        }
        $targetPath = $targetFolder . '/' . $entityClassName . '.php';

        file_put_contents($targetPath, $newContent);
        $io->success("Resource generated successfully (full mode): {$targetPath}");

        return Command::SUCCESS;
    }

    /**
     * Merges differences between existing and new content.
     * It extracts the class body (the content between the first "{" and the last "}")
     * and then adds any lines from the new body that are not already present in the existing body.
     */
    private function mergeDiff(string $existingContent, string $newContent, string $entityClassName): string
    {
        // Extract the body of the class from the existing content.
        if (!preg_match('/class\s+' . preg_quote($entityClassName, '/') . '\s*\{(.*)\}\s*$/s', $existingContent, $existingMatches)) {
            return $existingContent; // If not found, return as is.
        }
        if (!preg_match('/class\s+' . preg_quote($entityClassName, '/') . '\s*\{(.*)\}\s*$/s', $newContent, $newMatches)) {
            return $existingContent;
        }
        $existingBody = trim($existingMatches[1]);
        $newBody = trim($newMatches[1]);

        $existingLines = array_filter(array_map('trim', explode("\n", $existingBody)));
        $newLines = array_filter(array_map('trim', explode("\n", $newBody)));

        // Compute missing lines.
        $missingLines = array_diff($newLines, $existingLines);
        if (empty($missingLines)) {
            return $existingContent; // Nothing missing.
        }
        // Append missing lines at the end of the existing body (before the final "}")
        $pos = strrpos($existingContent, '}');
        if ($pos === false) {
            return $existingContent;
        }
        $before = substr($existingContent, 0, $pos);
        $after = substr($existingContent, $pos);
        $mergedBody = $before . "\n" . implode("\n", $missingLines) . "\n" . $after;
        return $mergedBody;
    }

    /**
     * Generates the full expected file content.
     * If mode is "full", all properties and methods are generated.
     * If mode is "diff", the full body is generated (to be later merged) so that missing lines can be identified.
     */
    private function generateFullContent(
        string $entityNamespace,
        string $tableFullName,
        string $entityClassName,
        string $tableBaseLower,
        bool $isView,
        array $primaryKeys,
        array $uniqueColumns,
        array $scalarColumns,
        array $relationColumns,
        array $foreignKeyMap,
        array $searchProperties,
        array $orderProperties,
        bool $prefixRoutes,
        bool $generateCreate,
        bool $generateUpdate,
        bool $hasCreatedDatetime,
        array $codeConstants = [],
    ): string {
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= "* Resource generated by:\n";
        $content .= "* bin/console app:make:resource\n";
        $content .= "**/\n\n";
        $content .= "namespace $entityNamespace;\n\n";
        $content .= "use Doctrine\\ORM\\Mapping as ORM;\n";
        $content .= "use App\\ApiPlatform\\Filter\\MultiFieldSearchFilter;\n";
        $content .= "use ApiPlatform\\Metadata\\ApiResource;\n";
        $content .= "use ApiPlatform\\Metadata\\ApiFilter;\n";
        $content .= "use ApiPlatform\\Metadata\\Get;\n";
        $content .= "use ApiPlatform\\Metadata\\GetCollection;\n";
        $content .= "use ApiPlatform\\Doctrine\\Orm\\Filter\\OrderFilter;\n";
        $content .= "use ApiPlatform\\Doctrine\\Orm\\Filter\\SearchFilter;\n";
        $content .= "use ApiPlatform\\Serializer\\Filter\\GroupFilter;\n";
        $content .= "use Symfony\\Component\\Serializer\\Attribute\\Groups;\n";
        if ($generateCreate) {
            $content .= "use ApiPlatform\\State\\CreateProvider;\n";
            $content .= "use ApiPlatform\\Metadata\\Post;\n";
        }
        if ($generateUpdate) {
            $content .= "use ApiPlatform\\Metadata\\Patch;\n";
        }
        if ($prefixRoutes) {
            $content .= "use ApiPlatform\\Metadata\\Link;\n";
        }
        $needsDateTime = false;
        $needsDateTimeInterface = false;
        $needsDateTimeImmutable = false;
        foreach ($scalarColumns as $col) {
            $mapping = $this->mapColumnType($col['data_type']);
            if ($mapping['php'] === 'DateTimeInterface') {
                $needsDateTime = true;
                break;
            }
            if ($mapping['php'] === 'DateTime') {
                $needsDateTimeInterface = true;
                break;
            }
            if ($mapping['php'] === 'DateTimeImmutable') {
                $needsDateTimeImmutable = true;
                break;
            }
        }
        if ($needsDateTime) {
            $content .= "use DateTime;\n";
        }
        if ($needsDateTimeInterface) {
            $content .= "use DateTimeInterface;\n";
        }
        if ($needsDateTimeImmutable) {
            $content .= "use DateTimeImmutable;\n";
        }
        if (($generateCreate || $generateUpdate) && !$isView) {
            $content .= "use Symfony\\Component\\Validator\\Constraints as Assert;\n";
        }
        if (!empty($uniqueColumns) && ($generateCreate || $generateUpdate)) {
            $content .= "use Symfony\\Bridge\\Doctrine\\Validator\\Constraints\\UniqueEntity;\n";
            $content .= "use App\Validator\Constraints as CustomAssert;\n";
        }
        $content .= "\n";
        $classNameUpper = $this->camelToUpperSnake($entityClassName);
        // Build operations.
        if ($prefixRoutes) {
            $routeName = str_replace('_', '-', $this->toSnakeCase($entityClassName));
            $routeName = $this->pluralize($routeName);
            $collectionOps = "new GetCollection(\n            normalizationContext: ['groups' => ['read']],\n            security: \"is_granted('{$classNameUpper}_LIST', object)\"\n        )";
            if (!$isView && $generateCreate) {
                $collectionOps .= ",\n        new Post(\n            normalizationContext: ['groups' => ['read']],\n            denormalizationContext: ['groups' => ['create']],\n            security: \"is_granted('{$classNameUpper}_CREATE', object)\",\n            validationContext: ['groups' => ['create']],\n            provider: CreateProvider::class\n        )";
            }
            $itemOps = "new Get(\n            normalizationContext: ['groups' => ['read']],\n            security: \"is_granted('{$classNameUpper}_SHOW', object)\"\n        )";
            if (!$isView && $generateUpdate) {
                $itemOps .= ",\n        new Patch(\n            normalizationContext: ['groups' => ['read']],\n            denormalizationContext: ['groups' => ['update']],\n            security: \"is_granted('{$classNameUpper}_UPDATE', object)\",\n            validationContext: ['groups' => ['update']]\n        )";
            }
            $content .= "#[ApiResource(\n";
            $content .= "    uriTemplate: '/organizations/{organizationId}/$routeName',\n";
            $content .= "    operations: [\n        $collectionOps\n    ],\n";
            $content .= "    uriVariables: [\n";
            $content .= "        'organizationId' => new Link(\n            toProperty: 'organization',\n            fromClass: Organization::class\n        ),\n";
            $content .= "    ],\n";
            $content .= ")]\n";
            $content .= "#[ApiResource(\n";
            $content .= "    uriTemplate: '/organizations/{organizationId}/$routeName/{id}',\n";
            $content .= "    operations: [\n        $itemOps\n    ],\n";
            $content .= "    uriVariables: [\n";
            $content .= "        'organizationId' => new Link(\n            toProperty: 'organization',\n            fromClass: Organization::class\n        ),\n";
            $content .= "        'id' => new Link(\n            fromClass: self::class\n        ),\n";
            $content .= "    ],\n";
            $content .= ")]\n";
        } else {
            $ops = [];
            $ops[] = "new GetCollection(\n            normalizationContext: ['groups' => ['read']],\n            security: \"is_granted('{$classNameUpper}_LIST', object)\"\n        )";
            $ops[] = "new Get(security: \"is_granted('{$classNameUpper}_SHOW', object)\")";
            if (!$isView && $generateCreate) {
                $ops[] = "new Post(\n            normalizationContext: ['groups' => ['read']],\n            denormalizationContext: ['groups' => ['create']],\n            security: \"is_granted('{$classNameUpper}_CREATE', object)\",\n            validationContext: ['groups' => ['create']],\n            provider: CreateProvider::class\n        )";
            }
            if (!$isView && $generateUpdate) {
                $ops[] = "new Patch(\n            normalizationContext: ['groups' => ['read']],\n            denormalizationContext: ['groups' => ['update']],\n            security: \"is_granted('{$classNameUpper}_UPDATE', object)\",\n            validationContext: ['groups' => ['update']]\n        )";
            }
            $operationsStr = implode(",\n        ", $ops);
            $content .= "#[ApiResource(\n";
            $content .= "    operations: [\n        $operationsStr\n    ],\n";
            $content .= "    normalizationContext: ['groups' => ['read']],\n";
            $content .= ")]\n";
        }

        $content .= "#[ORM\\Entity]\n";
        $content .= "#[ORM\\Table(name: '$tableFullName')]\n";
        $content .= "#[ApiFilter(SearchFilter::class, properties: [\n";
        foreach ($searchProperties as $prop => $filterType) {
            $content .= "    '$prop' => '$filterType',\n";
        }
        $content .= "])]\n";
        $content .= "#[ApiFilter(OrderFilter::class, properties: [\n";
        foreach ($orderProperties as $prop) {
            $content .= "    '$prop',\n";
        }
        $content .= "])]\n";

        $content .= "#[ApiFilter(MultiFieldSearchFilter::class, properties: [\n";
        foreach ($searchProperties as $prop => $filterType) {
            $content .= "    '$prop' => '$filterType',\n";
        }
        $content .= "])]\n";
        $content .= "#[ApiFilter(GroupFilter::class, arguments: ['overrideDefaultGroups' => true])]\n";
        $relationColumnNames = array_map(fn ($item) => $item['column_name'], $relationColumns);
        // Add class-level UniqueEntity attributes.
        if ($generateCreate || $generateUpdate) {
            foreach ($uniqueColumns as $uniqueCol) {
                $csv = str_replace('{', '', $uniqueCol['columns']);
                $csv = str_replace('}', '', $csv);
                $columns = explode(',', $csv);

                $uniqueProps = array_map(function ($item) use ($isView, $tableBaseLower, $relationColumnNames) {
                    if (in_array($item, $relationColumnNames)) {
                        $item = rtrim($item, '_id');
                    }
                    return '\'' . (!$isView ? $this->convertToCamelCase($item, false, $tableBaseLower . '_') : $this->convertToCamelCase($item)) . '\'';
                }, $columns);
                $groups = [];
                if ($generateCreate) {
                    $groups[] = '\'create\'';
                }
                if ($generateUpdate) {
                    $groups[] = '\'update\'';
                }
                if ($uniqueCol['where_clause']) {
                    // Parse where clause to extract conditions
                    $conditions = $this->parseWhereClauseToConditions($uniqueCol['where_clause']);
                    $conditionsStr = '';
                    if (!empty($conditions)) {
                        $conditionPairs = [];
                        foreach ($conditions as $field => $value) {
                            if ($value === null) {
                                $conditionPairs[] = "'$field' => null";
                            } elseif (is_bool($value)) {
                                $conditionPairs[] = "'$field' => " . ($value ? 'true' : 'false');
                            } elseif (is_string($value)) {
                                $conditionPairs[] = "'$field' => '$value'";
                            } else {
                                $conditionPairs[] = "'$field' => $value";
                            }
                        }
                        $conditionsStr = ",\n    conditions: [" . implode(', ', $conditionPairs) . "]";
                    }
                    $content .= '#[CustomAssert\EntityUniqueConstraint(
    fields: [' . implode(', ', $uniqueProps) . "]$conditionsStr,
    groups: [" . implode(', ', $groups) . "]
)]\n";
                } else {
                    $content .= '#[UniqueEntity(fields: [' . implode(', ', $uniqueProps) . '], groups: [' . implode(', ', $groups) . "])]\n";
                }
            }
        }

        $content .= "class $entityClassName\n{\n";

        // Generate constants for tables with _code as PK
        if (!empty($codeConstants)) {
            foreach ($codeConstants as $codeValue) {
                $constantName = $codeValue;
                $content .= "    public const $constantName = '$codeValue';\n";
            }
            $content .= "\n";
        }

        // In full mode, generate properties and methods.
        $nonWritable = ['id', 'created_datetime', 'merged_datetime', 'assigned_as_duplicated_datetime', 'merged_by', 'assigned_as_duplicated_by'];
        foreach ($scalarColumns as $key => $col) {
            $colName = $col['column_name'];
            $removePrefix = !$isView ? $tableBaseLower . '_' : null;
            $propertyName = $this->convertToCamelCase($colName, false, $removePrefix);
            $mapping = $this->mapColumnType($col['data_type']);
            $ormType = $mapping['orm'];
            $phpType = $mapping['php'];
            $nullable = ($col['is_nullable'] === 'YES') ? 'true' : 'false';
            $init = ($col['is_nullable'] === 'YES') ? ' = null' : '';
            $options = [];
            $options[] = "name: '$colName'";
            $options[] = "type: '$ormType'";
            if ($ormType === 'string' && !empty($col['character_maximum_length'])) {
                $options[] = 'length: ' . $col['character_maximum_length'];
            }
            if ($ormType === 'decimal' && !empty($col['numeric_precision'])) {
                $options[] = 'precision: ' . $col['numeric_precision'];
                if (!empty($col['numeric_scale'])) {
                    $options[] = 'scale: ' . $col['numeric_scale'];
                }
            }
            $options[] = "nullable: $nullable";
            if (!empty($col['column_default'])) {
                $default = addslashes($col['column_default']);
                $options[] = "options: ['default' => '$default']";
            }
            $columnAnnotation = '    #[ORM\\Column(' . implode(', ', $options) . ")]\n";
            if (in_array($colName, $primaryKeys, true) || (!$primaryKeys && $key === 0)) {
                $columnAnnotation = "    #[ORM\\Id]\n    #[ORM\\GeneratedValue(strategy: 'NONE')]\n" . $columnAnnotation;
            }
            $groupsArr = ['read'];
            $writable = !$isView && !in_array(strtolower($propertyName), $nonWritable);
            if ($writable) {
                if ($generateCreate) {
                    $groupsArr[] = 'create';
                }
                if ($generateUpdate) {
                    $groupsArr[] = 'update';
                }

                if (($generateCreate || $generateUpdate) && !$isView) {
                    if ($col['is_nullable'] !== 'YES') {
                        $assertion = $phpType === 'bool' ? 'NotNull' : 'NotBlank';
                        $columnAnnotation .= '    #[Assert\\' . $assertion . '(groups: [' . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
                    }
                    if ($phpType === 'string' && !empty($col['character_maximum_length'])) {
                        $columnAnnotation .= '    #[Assert\\Length(max: ' . $col['character_maximum_length'] . ', groups: [' . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
                    }
                    if (in_array($phpType, ['int', 'float'])) {
                        $columnAnnotation .= "    #[Assert\\Type(type: '$phpType', groups: [" . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
                    }
                }
            }
            if (!empty($groupsArr)) {
                $columnAnnotation .= '    #[Groups([' . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
            }
            $content .= $columnAnnotation;
            $content .= '    private ' . (($col['is_nullable'] === 'YES') ? '?' : '') . "$phpType \$$propertyName" . $init . ";\n\n";
        }
        foreach ($relationColumns as $col) {
            $colName = $col['column_name'];
            preg_match('/(_id|_code)$/', $colName, $matches);
            if (!$matches) {
                throw new \Exception("Relation column '$colName' is neither '_id' or '_code'.");
            }
            $removePrefix = !$isView ? $tableBaseLower . '_' : null;
            $propName = $this->convertToCamelCase($colName, false, $removePrefix, $matches[0]);
            $fk = $foreignKeyMap[$colName];
            $foreignTable = $fk['foreign_table_name'];
            $targetEntity = $this->convertToCamelCase($foreignTable, true, 'tbl_');
            $nullable = ($col['is_nullable'] === 'YES') ? 'true' : 'false';
            $relationGroup = $this->toSnakeCase($entityClassName) . '_' . $this->toSnakeCase($propName);
            $content .= "    #[ORM\\ManyToOne(targetEntity: {$targetEntity}::class)]\n";
            $content .= "    #[ORM\\JoinColumn(name: '$colName', referencedColumnName: '" . $fk['foreign_column_name'] . "', nullable: $nullable)]\n";
//            $content .= "    #[Groups(['$relationGroup'])]\n";
            $groupsArr = [$relationGroup];
            $writable = !$isView && !in_array(strtolower($propertyName), $nonWritable);
            $columnAnnotation = '';
            if ($writable) {
                if ($generateCreate) {
                    $groupsArr[] = 'create';
                }
                if ($generateUpdate) {
                    $groupsArr[] = 'update';
                }

                if (($generateCreate || $generateUpdate) && !$isView) {
                    if ($col['is_nullable'] !== 'YES') {
                        $columnAnnotation .= '    #[Assert\\NotBlank(groups: [' . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
                    }
                    if ($phpType === 'string' && !empty($col['character_maximum_length'])) {
                        $columnAnnotation .= '    #[Assert\\Length(max: ' . $col['character_maximum_length'] . ', groups: [' . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
                    }
                    if (in_array($phpType, ['int', 'float'])) {
                        $columnAnnotation .= "    #[Assert\\Type(type: '$phpType', groups: [" . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
                    }
                }
            }
            if (!empty($groupsArr)) {
                $columnAnnotation .= '    #[Groups([' . implode(', ', array_map(fn($g) => "'$g'", $groupsArr)) . "])]\n";
            }
            $content .= $columnAnnotation;
            $content .= '    private ?' . $targetEntity . " \$$propName = null;\n\n";
        }
        if ($hasCreatedDatetime) {
            $content .= "    public function __construct()\n    {\n";
            $content .= "        \$this->createdDatetime = new DateTime();\n";
            $content .= "    }\n\n";
        }
        foreach ($scalarColumns as $col) {
            $colName = $col['column_name'];
            $removePrefix = !$isView ? $tableBaseLower . '_' : null;
            $propertyName = $this->convertToCamelCase($colName, false, $removePrefix);
            $methodName = ucfirst($propertyName);
            $mapping = $this->mapColumnType($col['data_type']);
            $phpType = $mapping['php'];
            $nullablePrefix = ($col['is_nullable'] === 'YES') ? '?' : '';
            $content .= "    public function get$methodName(): " . $nullablePrefix . "$phpType\n    {\n";
            $content .= "        return \$this->$propertyName;\n";
            $content .= "    }\n\n";
            $content .= "    public function set$methodName(" . $nullablePrefix . "$phpType \$$propertyName): void\n    {\n";
            $content .= "        \$this->$propertyName = \$$propertyName;\n";
            $content .= "    }\n\n";
        }
        foreach ($relationColumns as $col) {
            $colName = $col['column_name'];
            preg_match('/_id|_code/', $colName, $matches);
            list($suffix) = $matches;
            $removePrefix = !$isView ? $tableBaseLower . '_' : null;
            $propName = $this->convertToCamelCase($colName, false, $removePrefix, $suffix);
            $methodName = ucfirst($propName);
            $fk = $foreignKeyMap[$colName];
            $targetEntity = $this->convertToCamelCase($fk['foreign_table_name'], true, 'tbl_');
            $content .= "    public function get$methodName(): ?$targetEntity\n    {\n";
            $content .= "        return \$this->$propName;\n";
            $content .= "    }\n\n";
            $content .= "    public function set$methodName(?$targetEntity \$$propName): void\n    {\n";
            $content .= "        \$this->$propName = \$$propName;\n";
            $content .= "    }\n\n";
            $content .= "    #[Groups(['read'])]\n";
            $suffix = ucfirst(ltrim($suffix, '_'));
            $content .= "    public function get$methodName$suffix(): ?string\n    {\n";
            $content .= "        return \$this->get$methodName()?->get$suffix();\n";
            $content .= "    }\n\n";
        }
        $content = substr($content, 0, -1);
        $content .= "}\n";
        return $content;
    }

    private function convertToCamelCase(string $string, bool $pascalCase = true, ?string $removePrefix = null, ?string $removeSuffix = null): string
    {
        if ($removePrefix && str_starts_with($string, $removePrefix)) {
            $string = substr($string, strlen($removePrefix));
        }
        if ($removeSuffix && str_ends_with($string, $removeSuffix)) {
            $string = substr($string, 0, -strlen($removeSuffix));
        }
        $str = str_replace('_', ' ', $string);
        $str = ucwords($str);
        $str = str_replace(' ', '', $str);
        if (!$pascalCase) {
            $str = lcfirst($str);
        }
        return $str;
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private function pluralize(string $word): string
    {
        if (substr($word, -1) === 's') {
            return $word;
        }
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }
        return $word . 's';
    }

    private function mapColumnType(string $dbType): array
    {
        $map = [
            'bit'                            => ['orm' => 'bit_boolean', 'php' => 'bool'],
            'boolean'                        => ['orm' => 'boolean', 'php' => 'bool'],
            'integer'                        => ['orm' => 'integer', 'php' => 'int'],
            'decimal'                        => ['orm' => 'decimal', 'php' => 'float'],
            'bigint'                         => ['orm' => 'bigint', 'php' => 'int'],
            'numeric'                        => ['orm' => 'decimal', 'php' => 'string'],
            'character varying'              => ['orm' => 'string', 'php' => 'string'],
            'varchar'                        => ['orm' => 'string', 'php' => 'string'],
            'text'                           => ['orm' => 'text', 'php' => 'string'],
            'uuid'                           => ['orm' => 'uuid', 'php' => 'string'],
            'timestamp without time zone'    => ['orm' => 'datetime_immutable', 'php' => 'DateTimeImmutable'],
            'timestamp with time zone'       => ['orm' => 'datetimetz_immutable', 'php' => 'DateTimeImmutable'],
            'date'                           => ['orm' => 'date_immutable', 'php' => 'DateTimeImmutable'],
            'real'                           => ['orm' => 'float', 'php' => 'float'],
            'jsonb'                          => ['orm' => 'json', 'php' => 'array'],
            'double precision'               => ['orm' => 'float', 'php' => 'float'],
        ];
        return $map[$dbType] ?? ['orm' => 'string', 'php' => 'string'];
    }

    private function extractExistingColumnNames(string $entityClass): array
    {
        try {
            $reflection = new ReflectionClass($entityClass);
        } catch (\ReflectionException $e) {
            return [];
        }

        $columnNames = [];

        foreach ($reflection->getProperties() as $property) {
            // Look for scalar column attribute.
            $columnAttrs = $property->getAttributes(Column::class);
            if (!empty($columnAttrs)) {
                $columnInstance = $columnAttrs[0]->newInstance();
                // Assuming the attribute has a public 'name' property.
                $columnNames[] = $columnInstance->name;
            }

            // Look for join column attribute (relations).
            $joinAttrs = $property->getAttributes(JoinColumn::class);
            if (!empty($joinAttrs)) {
                $joinInstance = $joinAttrs[0]->newInstance();
                $columnNames[] = $joinInstance->name;
            }
        }

        return $columnNames;
    }

    protected function camelToUpperSnake(string $input): string
    {
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Parse PostgreSQL where clause to extract conditions for EntityUniqueConstraint
     * Example: "(removed_at IS NULL)" => ['removedAt' => null]
     */
    private function parseWhereClauseToConditions(string $whereClause): array
    {
        $conditions = [];
        
        // Match patterns like "column_name IS NULL"
        if (preg_match('/\((\w+)\s+IS\s+NULL\)/i', $whereClause, $matches)) {
            $columnName = $matches[1];
            $propertyName = $this->convertToCamelCase($columnName, false);
            $conditions[$propertyName] = null;
        }
        
        // Match patterns like "column_name IS NOT NULL"
        if (preg_match('/\((\w+)\s+IS\s+NOT\s+NULL\)/i', $whereClause, $matches)) {
            // For IS NOT NULL, we can't easily represent this in simple conditions
            // Skip for now or handle specially
        }
        
        // Match patterns like "column_name = value"
        if (preg_match('/\((\w+)\s*=\s*(.+)\)/i', $whereClause, $matches)) {
            $columnName = $matches[1];
            $value = trim($matches[2]);
            $propertyName = $this->convertToCamelCase($columnName, false);
            
            // Parse the value
            if ($value === 'true' || $value === 'TRUE') {
                $conditions[$propertyName] = true;
            } elseif ($value === 'false' || $value === 'FALSE') {
                $conditions[$propertyName] = false;
            } elseif (is_numeric($value)) {
                $conditions[$propertyName] = (int)$value;
            } else {
                // String value - remove quotes if present
                $conditions[$propertyName] = trim($value, "'\"");
            }
        }
        
        return $conditions;
    }
}
