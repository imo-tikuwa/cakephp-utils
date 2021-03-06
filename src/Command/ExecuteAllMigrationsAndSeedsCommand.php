<?php
declare(strict_types=1);

namespace Utilities\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Migrations\Command\MigrationsMigrateCommand;
use Migrations\Command\MigrationsSeedCommand;

/**
 * ExecuteAllMigrationsAndSeeds command.
 */
class ExecuteAllMigrationsAndSeedsCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOption('connection', [
            'short' => 'c',
            'default' => 'default',
            'help' => 'The datasource connection to get data from.',
        ])->addOption('clean', [
            'help' => 'When this option is specified, all existing tables will be deleted before performing migration.',
            'default' => false,
            'boolean' => true,
        ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $io->out("ExecuteAllMigrationsAndSeedsCommand start.");

        $connection = ConnectionManager::get($args->getOption('connection'));
        $table_names = $connection->getSchemaCollection()->listTables();

        // 全テーブル削除
        if ($args->getOption('clean')) {
            foreach ($table_names as $table_name) {
                $connection->execute("DROP TABLE IF EXISTS {$table_name}");
            }
        }

        $io->out("execute 「migrations migrate」 command.");
        $this->executeCommand(MigrationsMigrateCommand::class, [], $io);

        $io->out("execute 「migrations seed」 command.");
        foreach ($table_names as $table_name) {
            // skip phinxlog table
            if ($table_name === 'phinxlog') {
                continue;
            }

            // seed name
            $seed_name = Inflector::camelize($table_name) . "Seed";

            // check
            if (!file_exists(CONFIG . 'Seeds' . DS . $seed_name . '.php')) {
                $io->out("The {$table_name} table seed file does not exist and will be skipped.");
                continue;
            }

            $io->out("execute {$table_name}'s seed.");
            $this->executeCommand(MigrationsSeedCommand::class, ['--quiet', '--seed', $seed_name]);
        }

        $io->out("ExecuteAllMigrationsAndSeedsCommand end.");

        return self::CODE_SUCCESS;
    }
}
