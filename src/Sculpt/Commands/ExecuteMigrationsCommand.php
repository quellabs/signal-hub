<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Commands;
	
	/**
	 * Import required classes for migration generation and entity analysis
	 */
	
	use Phinx\Config\Config;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\OrmException;
	use Quellabs\Sculpt\CommandBase;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	use Quellabs\Sculpt\Contracts\ServiceProviderInterface;
	use Symfony\Component\Console\Input\ArrayInput;
	use Symfony\Component\Console\Output\BufferedOutput;
	
	/**
	 * MakeMigration - CLI command for generating database migrations
	 *
	 * This command analyzes differences between entity definitions and database schema,
	 * then creates migration files to synchronize the database with entity changes.
	 * It tracks added, modified, or removed fields and relationships to generate
	 * the appropriate SQL commands for schema updates.
	 */
	class ExecuteMigrationsCommand extends CommandBase {
		
		private Configuration $configuration;
		
		/**
		 * MakeEntityCommand constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param ServiceProviderInterface|null $provider
		 * @throws OrmException
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ServiceProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);
			$this->configuration = $provider->getConfiguration();
		}
		
		/**
		 * Execute the command
		 * @param array $parameters Optional parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(array $parameters = []): int {
			$connectionParams = $this->configuration->getConnectionParams();
			
			$configArray = [
				'paths'        => [
					'migrations' => $this->configuration->getMigrationsPath(),  // adjust this path
				],
				'environments' => [
					'default_migration_table' => 'phinxlog',
					'default_environment'     => 'development',
					'development'             => [
						'adapter' => $connectionParams['driver'],
						'host'    => $connectionParams['host'],
						'name'    => $connectionParams['database'],
						'user'    => $connectionParams['username'],
						'pass'    => $connectionParams['password'],
						'port'    => $connectionParams['port'],
						'charset' => $connectionParams['encoding'],
					],
				],
			];
			
			$config = new Config($configArray);
			$input = new ArrayInput([]); // Empty input
			$phinxOutput = new BufferedOutput(); // Or you can map it to your ConsoleOutput
			
			try {
				$this->output->writeln('<info>Running migrations...</info>');
				$manager = new \Phinx\Migration\Manager($config, $input, $phinxOutput);
				$manager->migrate('development');
			} catch (\Exception $e) {
				$this->output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
				return 1;
			}
			
			$this->output->writeln('<info>Migration completed successfully.</info>');
			return 0;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public function getSignature(): string {
			return "migrate:run";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public function getDescription(): string {
			return "Execute all pending database migrations.";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public function getHelp(): string {
			return <<<HELP
Applies all outstanding database migrations that have not yet been run.

This command reads the migration classes from the configured migration path and executes them
in order, updating the database schema accordingly. The current state of executed migrations is
tracked in the configured migration table (default: 'phinxlog').

Usage:
  migrate:run

Example:
  php cli.php migrate:run
HELP;
		}
	}