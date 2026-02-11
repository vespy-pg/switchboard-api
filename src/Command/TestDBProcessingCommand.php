<?php

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestDBProcessingCommand extends Command
{
    public const DEBUG_LEVEL_NONE = 0;
    public const DEBUG_LEVEL_DEBUG = 1;
    public const DEBUG_LEVEL_INFO = 2;
    public const DEBUG_LEVEL_ERROR = 3;
    public const DB_NAME_FUNCTIONAL_TEST = 'gift_test_functional';
    public const DIR = 'sql/test_data/';

    public const DEBUG_LEVEL_MAPPING = [
        'NONE' => self::DEBUG_LEVEL_NONE,
        'DEBUG' => self::DEBUG_LEVEL_DEBUG,
        'INFO' => self::DEBUG_LEVEL_INFO,
        'ERROR' => self::DEBUG_LEVEL_ERROR
    ];
    protected $testDataTables = [
        'rss' => [
        ],
    ];
    protected $dbName;
    protected $dbUser;
    protected $dbHost;
    protected $dbPass;
    protected $suffix;
    protected $isDump;
    protected $isRestore;
    protected $isData;
    protected $isStructure;
    protected $debugLevel;
    protected $forceDataDump;
    protected $pgClusterDir = '';

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('ris:db-test:exec')
            // the short description shown while running "php bin/console list"
            ->setDescription('Dumps source database structure and / or data to files and restores them to the test db: ' . self::DB_NAME_FUNCTIONAL_TEST)
            // the full command description shown when running the command with
            // the "--help" option
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Updates local dump files')
            ->addOption('restore', null, InputOption::VALUE_NONE, 'Restores test database structure (wipes table data)')
            ->addOption('data', null, InputOption::VALUE_NONE, 'Dump data (use with caution - this should not be normally needed)')
            ->addOption('structure', null, InputOption::VALUE_NONE, 'Dump structure')
            ->addOption('suffix', null, InputOption::VALUE_REQUIRED, 'Suffix added to the data file (has no effect on structure)', 'dev')
            ->addOption('debug-level', null, InputOption::VALUE_REQUIRED, 'Level of debug messages (DEBUG, INFO, ERROR, NONE)', 'INFO')
            ->addOption('pg-cluster-dir', null, InputOption::VALUE_REQUIRED, 'location of pgcluster', '')
            ->addOption('force-data-dump', null, InputOption::VALUE_NONE, 'Forces data dump (USE WITH CARE) as it will most likely break many test cases.')

            ->addArgument('dbhost', InputArgument::REQUIRED, 'Source database host')
            ->addArgument('dbname', InputArgument::REQUIRED, 'Source database to dump from (No change will be done to that database)')
            ->addArgument('dbuser', InputArgument::REQUIRED, 'Source database username')
            ->addArgument('dbpass', InputArgument::REQUIRED, 'Source database password')
            ->setHelp('
For majority of cases the command will look as follows:

bin/console ris:db-test:exec --structure --data --dump --restore [host] [dbname] [user] [password]

above command will --dump --structure from [dbname], then --restore --structure and --data to ' . self::DB_NAME_FUNCTIONAL_TEST . ' database.
Note, that data is not dumped (it must not ever be or it will cause havoc). Any changes done to data should be added to the data file manually.
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $start = microtime(true);
        echo "\n";
        $this->debug('input arguments: ' . print_r($input->getArguments(), true), self::DEBUG_LEVEL_DEBUG);
        $this->debug('input options: ' . print_r($input->getOptions(), true), self::DEBUG_LEVEL_DEBUG);
        $this->dbName = $input->getArgument('dbname');
        $this->dbUser = $input->getArgument('dbuser');
        $this->dbHost = $input->getArgument('dbhost');
        $this->dbPass = $input->getArgument('dbpass');
        $this->isDump = $input->getOption('dump');
        $this->isRestore = $input->getOption('restore');
        $this->isData = $input->getOption('data');
        $this->isStructure = $input->getOption('structure');
        $this->suffix = $input->getOption('suffix');
        $this->debugLevel = self::DEBUG_LEVEL_MAPPING[$input->getOption('debug-level')];
        $this->forceDataDump = $input->getOption('force-data-dump');
        if ($input->getOption('pg-cluster-dir')) {
            $this->pgClusterDir = rtrim($input->getOption('pg-cluster-dir'), '/') . '/';
        }

        if (!file_exists(self::DIR)) {
            mkdir(self::DIR, 0777, true);
        }
        if ($this->isDump) {
            if ($this->isData && $this->forceDataDump) {
                // disabled as this command should not be normally run.
                // This should only run once per database and any additional changes should be added manually
                // Left it here as we might need to run this on rare occasions to create more data dumps (eg for PL or HU data)
                $this->dumpData();
            }
            if ($this->isStructure) {
                $this->dumpStructure();
                $this->addRolesToStructure();
            }
        }
        if ($this->isRestore) {
            if ($this->isStructure) {
                $this->disconnectUsersFromDB();
                $this->checkIfDumpIsSafe();
                $this->dropAndCreateDatabase();
                $this->restoreStructure();
            }
            if ($this->isData) {
                $this->restoreData();
            }
        }
        $this->debug('Total time: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
        $this->debug('Done');
        return Command::SUCCESS;
    }

    protected function dumpData()
    {
        $start = microtime(true);
        $this->debug('Dumping data...');
        $tablesString = '';
        foreach ($this->testDataTables as $schema => $tables) {
            foreach ($tables as $table) {
                $tablesString .= ' -t ' . $schema . '.' . $table;
            }
        }
        $command = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'pg_dump -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' --dbname=' . $this->dbName . ' --column-inserts -a ' . $tablesString . ' > ' . $this->getDataFilename();
        $this->debug('Command: ' . $command, self::DEBUG_LEVEL_DEBUG);
        shell_exec($command);
        $this->debug('Data dump complete');
        $this->debug('Time elapsed: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
    }

    protected function dumpStructure()
    {
        $start = microtime(true);
        $this->debug('Dumping structure...');
        $command = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'pg_dump --schema-only --no-owner -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' --dbname=' . $this->dbName . ' > ' . $this->getStructureFilename();
        $this->debug('Command: ' . $command, self::DEBUG_LEVEL_DEBUG);
        shell_exec($command);
        $contents = file_exists($this->getStructureFilename()) ? file_get_contents($this->getStructureFilename()) : '';
        $contents = str_replace($this->dbName, self::DB_NAME_FUNCTIONAL_TEST, $contents);
        file_put_contents($this->getStructureFilename(), $contents);
        $this->debug('Structure dump complete');
        $this->debug('Time elapsed: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
    }

    protected function addRolesToStructure()
    {
        $start = microtime(true);
        $this->debug('Adding roles to the structure...');
        $query = "SELECT DISTINCT(grantee) FROM information_schema.role_table_grants WHERE grantee <> 'PUBLIC' AND grantee <> 'tomasz' ORDER BY grantee";
        $command = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'psql -At -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' -d ' . $this->dbName . ' -c "' . $query . ';"';
        $this->debug('Command: ' . $command, self::DEBUG_LEVEL_DEBUG);
        $res = shell_exec($command);
        $roles = array_values(array_filter(explode("\n", $res)));
        $contents = file_exists($this->getStructureFilename()) ? file_get_contents($this->getStructureFilename()) : '';
        if ($roles) {
            $roleSQL = 'DO $$
    BEGIN
';
            foreach ($roles as $role) {
                $roleSQL .= "
        IF NOT EXISTS (
                SELECT FROM pg_catalog.pg_roles
                WHERE  rolname = '$role') THEN

            CREATE ROLE $role LOGIN;
        END IF;";
            }
            $roleSQL .= '
END $$;
';
            $contents = $roleSQL . $contents;
        }
        file_put_contents($this->getStructureFilename(), $contents);
        $this->debug('Added roles to the structure.');
        $this->debug('Time elapsed: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
    }

    protected function disconnectUsersFromDB()
    {
        $queries = [
            'SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = \'' . self::DB_NAME_FUNCTIONAL_TEST . '\';',
        ];
        $this->debug('Disconnecting clients from ' . self::DB_NAME_FUNCTIONAL_TEST . '...');
        foreach ($queries as $query) {
            $command = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'psql -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' -d ' . $this->dbName . ' -c "' . $query . ';"';
            $this->debug('Command: ' . $command, self::DEBUG_LEVEL_DEBUG);
            shell_exec($command);
        }
        $this->debug('Disconnection complete.');
    }

    protected function checkIfDumpIsSafe()
    {
        $this->debug('Checking if dump may harm source database...');
        $occurrences = [];
        $lines = file($this->getStructureFilename());
        foreach ($lines as $lineNumber => $line) {
            if (($pos = strpos($line, $this->dbName)) !== false) {
                $occurrences[] = $lineNumber . ':' . $pos;
            }
        }
        if ($occurrences) {
//            dump(implode("\n", $occurrences));
//            throw new Exception("Dump file might overwrite source database as it contains it\'s reference at line(s): \n{lines}", ['lines' => implode("\n", $occurrences)]);
        }
        $this->debug('Dump is safe for source database');
    }

    protected function dropAndCreateDatabase()
    {
        $start = microtime(true);
        $this->debug('Dropping DB if exists...');
        $commandDrop = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'psql -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' -d postgres ' .
            '--command="DROP DATABASE IF EXISTS '  . self::DB_NAME_FUNCTIONAL_TEST . ';"';
        $this->debug('Command: ' . $commandDrop, self::DEBUG_LEVEL_DEBUG);
        shell_exec($commandDrop);
        $this->debug('Database dropped...');

        $this->debug('Creating database...');
        $commandCreate = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'psql -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' -d postgres ' .
            '--command="CREATE DATABASE ' . self::DB_NAME_FUNCTIONAL_TEST . ';"';
        $this->debug('Command: ' . $commandCreate, self::DEBUG_LEVEL_DEBUG);
        shell_exec($commandCreate);
        $this->debug('Database created...');

        $this->debug('Database recreate completed');
        $this->debug('Time elapsed: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
    }

    protected function restoreStructure()
    {
        $start = microtime(true);
        $this->debug('Restoring structure...');

        $command = 'PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'psql -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' ' . self::DB_NAME_FUNCTIONAL_TEST . ' < ' . $this->getStructureFilename();
        $this->debug('Command: ' . $command, self::DEBUG_LEVEL_DEBUG);
        shell_exec($command);
        $this->debug('Structure restore complete');
        $this->debug('Time elapsed: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
    }

    protected function restoreData()
    {
        $start = microtime(true);
        $this->debug('Restoring data...');
        $command = 'cat ' . $this->getDataFilename() . ' | PGPASSWORD=' . $this->dbPass . ' ' .  $this->pgClusterDir . 'psql -h ' . $this->dbHost . ' -U ' . $this->dbUser . ' -d ' . self::DB_NAME_FUNCTIONAL_TEST;
        shell_exec($command);
        $this->debug('Data restore complete');
        $this->debug('Time elapsed: ' . round(microtime(true) - $start, 2) . 's', self::DEBUG_LEVEL_DEBUG);
    }

    protected function getDataFilename(): string
    {
        return self::DIR . self::DB_NAME_FUNCTIONAL_TEST . '_data_' . $this->suffix . '.sql';
    }

    protected function getStructureFilename(): string
    {
        return self::DIR . self::DB_NAME_FUNCTIONAL_TEST . '_structure.sql';
    }

    protected function debug($message, $level = self::DEBUG_LEVEL_INFO)
    {
        if (!$this->debugLevel || $level < $this->debugLevel) {
            return;
        }
        echo $message . "\n";
    }
}
