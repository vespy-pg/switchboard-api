<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Inflector\EnglishInflector;

#[AsCommand(
    name: 'app:make:voter',
    description: 'Generates Voters from database schema.'
)]
class GenerateVoterCommand extends Command
{
    private string $projectDir;

    public function __construct(
        private Connection $connection,
        KernelInterface $kernel
    ) {
        parent::__construct();
        $this->projectDir = $kernel->getProjectDir();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schemas = $schemaManager->listSchemaNames();
        $helper = $this->getHelper('question');

        $schemaQuestion = new ChoiceQuestion(
            'Please select database schema (default: rss)',
            $schemas,
            'rss'
        );

        $selectedSchema = $helper->ask($input, $output, $schemaQuestion);
        $tables = $schemaManager->listTables();

        $outputDir = $this->projectDir . '/generated/voters';
        $filesystem = new Filesystem();
        $filesystem->mkdir($outputDir);

        foreach ($tables as $table) {
            // Only process tables in selected schema
            if ($table->getNamespaceName() !== $selectedSchema) {
                continue;
            }

            // Strip schema prefix
            $fullName = $table->getName();
            $parts = explode('.', $fullName);
            $tableName = array_pop($parts);

            $className = $this->generateClassName($tableName);
            $entityName = $this->generateEntityName($tableName);
            $filePath = "$outputDir/{$className}.php";

            $content = $this->generateVoterContent($className, $entityName);

            $filesystem->dumpFile($filePath, $content);
            $output->writeln(sprintf(
                'Generated voter for table: %s at %s',
                $tableName,
                $filePath
            ));
        }

        return Command::SUCCESS;
    }

    private function generateClassName(string $tableName): string
    {
        $inflector = new EnglishInflector();
        $singular = current($inflector->singularize($tableName));
        $base = str_replace('tbl_', '', $singular);
        $pascal = str_replace(' ', '', ucwords(str_replace('_', ' ', $base)));

        return $pascal . 'Voter';
    }

    private function generateEntityName(string $tableName): string
    {
        $inflector = new EnglishInflector();
        $singular = current($inflector->singularize($tableName));
        $base = str_replace('tbl_', '', $singular);
        $pascal = str_replace(' ', '', ucwords(str_replace('_', ' ', $base)));

        return $pascal;
    }

    private function generateVoterContent(string $className, string $entityName): string
    {
        // Convert PascalCase EntityName to SCREAMING_SNAKE_CASE
        $constBase = strtoupper(
            preg_replace('/(?<!^)([A-Z])/', '_$1', $entityName)
        );

        return <<<PHP
<?php

namespace App\\Security\\Voter;

use App\\Entity\\{$entityName};

class {$className} extends Voter
{
    public const LIST   = '{$constBase}_LIST';
    public const CREATE = '{$constBase}_CREATE';
    public const SHOW   = '{$constBase}_SHOW';
    public const UPDATE = '{$constBase}_UPDATE';
    protected array \$supportedAttributes = [self::SHOW, self::LIST];

    protected function canList({$entityName} \$subject): void
    {
    }

    protected function canCreate({$entityName} \$subject): void
    {
    }

    protected function canShow({$entityName} \$subject): void
    {
    }

    protected function canUpdate({$entityName} \$subject): void
    {
    }
}

PHP;
    }
}
