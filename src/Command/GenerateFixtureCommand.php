<?php

// src/Command/GenerateFixtureCommand.php
namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

class GenerateFixtureCommand extends Command
{
    protected static $defaultName = 'app:make:fixture';
    protected static $defaultDescription = 'Generate a data fixture for all tables in a given schema';

    private Connection $connection;
    private string $projectDir;

    public function __construct(Connection $connection, KernelInterface $kernel)
    {
        parent::__construct();
        $this->connection = $connection;
        $this->projectDir = $kernel->getProjectDir();
    }

    protected function configure(): void
    {
        $this->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get available schemas.
        $schemaManager = $this->connection->createSchemaManager();
        $allSchemas = $schemaManager->listSchemaNames();
        // Filter out common system schemas.
        $schemas = array_filter($allSchemas, function ($schema) {
            return !in_array($schema, ['information_schema', 'pg_catalog']);
        });
        if (empty($schemas)) {
            $io->error('No user-defined schemas found.');
            return Command::FAILURE;
        }

        // Ask user to choose a schema.
        $schemaQuestion = new ChoiceQuestion(
            'Please select a schema:',
            $schemas
        );
        $schemaQuestion->setAutocompleterValues($schemas);
        $schemaName = $io->askQuestion($schemaQuestion);

        $io->note(sprintf('Processing all tables in schema: %s', $schemaName));

        // Find all tables in the chosen schema that follow the naming convention "tbl_<name>".
        $allTables = $schemaManager->listTableNames();
        $fixtureTables = [];
        // We assume that tables are returned as "<schema>.tbl_<name>".
        $prefix = $schemaName . '.tbl_';
        foreach ($allTables as $tableName) {
            if (strpos($tableName, $prefix) === 0) {
                // Remove the prefix for display/processing.
                $shortName = substr($tableName, strlen($prefix));
                $fixtureTables[$shortName] = $tableName;
            }
        }

        if (empty($fixtureTables)) {
            $io->warning("No tables found in schema \"$schemaName\" with prefix \"tbl_\".");
            return Command::SUCCESS;
        }

        // Process each table.
        foreach ($fixtureTables as $tableKey => $fullTableName) {
            $io->note("Generating fixture for table: $fullTableName");

            // Get table details.
            $tableDetails = $schemaManager->introspectTable($fullTableName);
            $columns = $tableDetails->getColumns();

            // Map foreign keys: local column => foreign table name.
            $foreignKeys = [];
            foreach ($tableDetails->getForeignKeys() as $fk) {
                /** @var ForeignKeyConstraint $fk */
                foreach ($fk->getLocalColumns() as $localColumn) {
                    $foreignKeys[$localColumn] = $fk->getForeignTableName();
                }
            }

            // Detect many-to-many join table by inspecting the table key.
            // For many-to-many, we expect the table key to be of the form "entityA_entityB"
            // and foreign keys named "entityA_id" and "entityB_id".
            $parts = explode('_', $tableKey);
            $isManyToMany = false;
            $manyToManyParts = [];
            if (count($parts) === 2) {
                $expectedFk1 = $parts[0] . '_id';
                $expectedFk2 = $parts[1] . '_id';
                if (isset($foreignKeys[$expectedFk1]) && isset($foreignKeys[$expectedFk2])) {
                    $isManyToMany = true;
                    $manyToManyParts = [$parts[0], $parts[1]];
                }
            }

            // Prepare fixture content.
            $entityName = $this->toCamelCase($tableKey); // e.g. "UserCustomer" for table "user_customer"
            $fixtureClassName = $entityName . 'Fixture';
            $namespace = 'App\DataFixtures';
            // Build fixed use statements.
            $useStatements = [
                "use App\\Entity\\$entityName;",
            ];
            $hasDatetime = false;
            foreach ($columns as $column) {
                // Only process columns that are NOT NULL.
                if (!$column->getNotnull()) {
                    continue;
                }
                if (stripos($column->getName(), 'datetime') !== false) {
                    $hasDatetime = true;
                }
            }
            if ($hasDatetime) {
                $useStatements[] = 'use DateTime;';
            }
            $relationUses = [];

            $lines = [];
            // Extra blank line above and below namespace.
            $lines[] = '<?php';
            $lines[] = '';
            $lines[] = "namespace $namespace;";
            $lines[] = '';
            foreach ($useStatements as $use) {
                $lines[] = $use;
            }
            // We'll add relation use statements later.
            $lines[] = '';

            // Generate class header.
            if ($isManyToMany) {
                // Many-to-many: generate method signature with parameters.
                $param1 = strtolower($manyToManyParts[0]) . 'Id';
                $param2 = strtolower($manyToManyParts[1]) . 'Id';
                $lines[] = "class $fixtureClassName extends AbstractFixture";
                $lines[] = '{';
                $lines[] = "    public function loadOne(string \$$param1 = FixtureSetup::DEFAULT_" . strtoupper($expectedFk1) . ", string \$$param2 = FixtureSetup::DEFAULT_" . strtoupper($expectedFk2) . '): void';
            } else {
                $lines[] = "class $fixtureClassName extends AbstractFixture";
                $lines[] = '{';
                $lines[] = '    public function loadOne(): void';
            }
            $lines[] = '    {';
            $lines[] = "        \$entity = new $entityName();";
            if (!$isManyToMany) {
                $lines[] = '        $rand = $this->randId();';
            }
            $lines[] = '';

            // Determine primary key columns.
            $primaryKey = $tableDetails->getPrimaryKey();
            $primaryKeyColumns = $primaryKey ? $primaryKey->getColumns() : [];

            /** @var Column $column */
            foreach ($columns as $column) {
                $colName = $column->getName();
                // Skip primary key columns.
                if (in_array($colName, $primaryKeyColumns, true)) {
                    continue;
                }
                // Only process columns that are NOT NULL.
                if (!$column->getNotnull()) {
                    continue;
                }

                // Many-to-many: if the column is one of the two expected foreign keys.
                if ($isManyToMany && in_array($colName, [$expectedFk1, $expectedFk2])) {
                    if ($colName === $expectedFk1) {
                        $setter = 'set' . $this->toCamelCase($manyToManyParts[0]);
                        $param = strtolower($manyToManyParts[0]) . 'Id';
                        $lines[] = "        \$entity->$setter(\$this->getEM()->getRepository(" . $this->toCamelCase($manyToManyParts[0]) . "::class)->find(\$$param));";
                        $relationUses[$this->toCamelCase($manyToManyParts[0])] = $this->toCamelCase($manyToManyParts[0]);
                    } elseif ($colName === $expectedFk2) {
                        $setter = 'set' . $this->toCamelCase($manyToManyParts[1]);
                        $param = strtolower($manyToManyParts[1]) . 'Id';
                        $lines[] = "        \$entity->$setter(\$this->getEM()->getRepository(" . $this->toCamelCase($manyToManyParts[1]) . "::class)->find(\$$param));";
                        $relationUses[$this->toCamelCase($manyToManyParts[1])] = $this->toCamelCase($manyToManyParts[1]);
                    }
                    continue;
                }

                // Check if this column is a foreign key (non–many-to-many).
                if (isset($foreignKeys[$colName])) {
                    $relationName = $this->removeIdSuffix($colName);
                    $setter = 'set' . $this->toCamelCase($relationName);
                    $foreignTable = $foreignKeys[$colName];
                    $foreignEntityKey = preg_replace('/^(.*\.tbl_)/', '', $foreignTable);
                    $foreignEntity = $this->toCamelCase($foreignEntityKey);
                    $relationUses[$foreignEntity] = $foreignEntity;
                    $fixtureSetupDefault = 'DEFAULT_' . strtoupper($colName);
                    if ($fixtureSetupDefault === 'DEFAULT_CREATED_BY_USER_ID') {
                        $fixtureSetupDefault = 'DEFAULT_USER_ID';
                    }
                    $lines[] = "        \$entity->$setter(\$this->getEM()->getRepository($foreignEntity::class)->find(FixtureSetup::$fixtureSetupDefault));";
                    continue;
                }

                // For non-FK columns, remove the table prefix if present.
                $processedColName = $colName;
                $tablePrefix = strtolower($tableKey) . '_';
                if (stripos($colName, $tablePrefix) === 0) {
                    $processedColName = substr($colName, strlen($tablePrefix));
                }
                $setter = 'set' . $this->toCamelCase($processedColName);
                $type = $this->getColumnTypeName($column);
                $value = '';
                if ($colName === 'is_active') {
                    $value = 'true';
                } else {
                    switch ($type) {
                        case 'boolean':
                        case 'bitboolean':
                            $value = 'false';
                            break;
                        case 'datetime':
                        case 'datetimetz':
                        case 'timestamp':
                            $value = 'new DateTime()';
                            break;
                        case 'integer':
                        case 'bigint':
                        case 'decimal':
                        case 'float':
                            $value = '$this->randId()';
                            break;
                        default:
                            $value = $isManyToMany ? "'sample'" : "'sample_' . \$rand";
                            break;
                    }
                }
                $lines[] = "        \$entity->$setter($value);";
            }

            $lines[] = '        $this->persist($entity);';
            $lines[] = '    }';
            $lines[] = '}';
            $content = implode("\n", $lines);

            // Prepend relation use statements (if any) after the fixed use statements.
            if (!empty($relationUses)) {
                $useLines = [];
                foreach ($relationUses as $relationEntity) {
                    if (array_search("use App\\Entity\\$relationEntity;", $lines) === false) {
                        $useLines[] = "use App\\Entity\\$relationEntity;";
                    }
                }
                $content = preg_replace(
                    '/(namespace\s+.+?;\n\n)((?:use\s+.+?;\n)+)/',
                    '$1$2' . implode("\n", $useLines) . "\n",
                    $content,
                    1
                );
            }
            // Append a newline at the end of the file.
            $content .= "\n";

            $content = preg_replace('/[\r\n]{3,}/', "\n\n", $content);

            $outputDir = $this->projectDir . '/generated/DataFixtures';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }
            $outputFile = $outputDir . '/' . $fixtureClassName . '.php';
            file_put_contents($outputFile, $content);
            $io->success("Fixture generated for table $fullTableName at: $outputFile");
        }

        return Command::SUCCESS;
    }

    private function getColumnTypeName(Column $column): string
    {
        $typeObject = $column->getType();
        $reflection = new \ReflectionClass($typeObject);
        $shortName = $reflection->getShortName();
        return strtolower(str_replace('Type', '', $shortName));
    }

    private function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
    }

    private function removeIdSuffix(string $string): string
    {
        if (substr($string, -3) === '_id') {
            return substr($string, 0, -3);
        }
        if (substr($string, -5) === '_code') {
            return substr($string, 0, -5);
        }
        return $string;
    }
}
