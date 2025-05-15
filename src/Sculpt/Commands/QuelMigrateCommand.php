<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Commands;
	
	use Phinx\Config\Config;
	use Phinx\Migration\Manager;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\OrmException;
	use Quellabs\Sculpt\CommandBase;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	use Quellabs\Sculpt\Contracts\ServiceProviderInterface;
	use Symfony\Component\Console\Input\ArrayInput;
	use Symfony\Component\Console\Output\BufferedOutput;
	
	/**
	 * ExecuteMigrationsCommand - CLI command for managing database migrations
	 *
	 * This command provides a comprehensive interface to Phinx migrations,
	 * allowing users to run migrations, roll them back, or check their status.
	 */
	class QuelMigrateCommand extends CommandBase {
		
		/**
		 * @var Configuration ObjectQuel configuration
		 */
		private Configuration $configuration;
		
		/**
		 * ExecuteMigrationsCommand constructor
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
		 * @param ConfigurationManager $config Optional parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(ConfigurationManager $config): int {
			// Create a Phinx configuration
			$phinxConfig = new Config($this->getProvider()->createPhinxConfig());
			
			// Create the manager with buffered output to capture all output
			// Always use the 'development' environment since that's all our config supports
			$env = 'development';
			$inputArgs = $this->prepareInputArgs($config);
			$input = new ArrayInput($inputArgs);
			$bufferedOutput = new BufferedOutput();
			$manager = new Manager($phinxConfig, $input, $bufferedOutput);
			
			try {
				// Determine which operation to perform based on flags
				if ($config->hasFlag('rollback')) {
					$result = $this->performRollback($manager, $env, $config);
				} elseif ($config->hasFlag('status')) {
					$result = $this->showStatus($manager, $env);
				} else {
					$result = $this->runMigrations($manager, $env, $config);
				}
				
				// Get any output from the buffered output and display it
				$outputContent = $bufferedOutput->fetch();
				
				if (!empty($outputContent)) {
					$this->output->write($outputContent);
				}
				
				return $result;
			} catch (\Exception $e) {
				$this->output->error("Migration error: " . $e->getMessage());
				return 1;
			}
		}
		
		/**
		 * Prepare input arguments for Phinx commands
		 * @param ConfigurationManager $config
		 * @return array
		 */
		private function prepareInputArgs(ConfigurationManager $config): array {
			$args = [];
			
			// Add dry-run flag if specified
			if ($config->hasFlag('dry-run') || $config->hasFlag('d')) {
				$args['--dry-run'] = true;
			}
			
			return $args;
		}
		
		/**
		 * Run migrations
		 * @param Manager $manager
		 * @param string $env
		 * @param ConfigurationManager $config
		 * @return int
		 */
		private function runMigrations(Manager $manager, string $env, ConfigurationManager $config): int {
			$this->output->writeLn("<info>Running migrations...</info>");
			
			// Check for dry run
			if ($config->hasFlag('dry-run') || $config->hasFlag('d')) {
				$this->output->writeLn("<dim>Dry run mode - no database changes will be made.</dim>");
			}
			
			// Check for target version
			$target = $config->get('target');
			if ($target) {
				$this->output->writeLn("<info>Migrating to version: {$target}</info>");
				$manager->migrate($env, $target);
			} else {
				$manager->migrate($env);
			}
			
			$this->output->writeLn("<info>Migration completed successfully.</info>");
			return 0;
		}
		
		/**
		 * Roll back migrations
		 * @param Manager $manager
		 * @param string $env
		 * @param ConfigurationManager $config
		 * @return int
		 */
		private function performRollback(Manager $manager, string $env, ConfigurationManager $config): int {
			$this->output->writeLn("<info>Rolling back migrations...</info>");
			
			// Check for dry run
			if ($config->hasFlag('dry-run') || $config->hasFlag('d')) {
				$this->output->writeLn("<dim>Dry run mode - no database changes will be made.</dim>");
			}
			
			// Get target version and steps
			$target = $config->get('target');
			$steps = (int)$config->get('steps', 1);
			
			if ($target) {
				$this->output->writeLn("<info>Rolling back to version: {$target}</info>");
				$manager->rollback($env, $target);
			} else {
				$this->output->writeLn("<info>Rolling back {$steps} migration(s)</info>");
				$manager->rollback($env, null, $steps);
			}
			
			$this->output->writeLn("<info>Rollback completed successfully.</info>");
			return 0;
		}
		
		/**
		 * Show migration status
		 * @param Manager $manager
		 * @param string $env
		 * @return int
		 */
		private function showStatus(Manager $manager, string $env): int {
			$this->output->writeLn("<info>Migration Status:</info>");
			
			// Instead of printing directly, capture the output from Phinx
			$manager->printStatus($env);
			
			return 0;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public function getSignature(): string {
			return "quel:migrate";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public function getDescription(): string {
			return "Manage database migrations: run, rollback, or check status.";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public function getHelp(): string {
			return <<<HELP
Manages database migrations for your application. Makes use of Phinx under the hood.

Usage:
  quel:migrate [options]

Options:
  --rollback           Roll back migrations instead of running them
  --status             Show migration status instead of running migrations
  --target=<version>   Target a specific migration version
  --steps=<number>     Number of migrations to roll back (default: 1)
  --dry-run, -d        Run in dry-run mode without making actual database changes

Examples:
  vendor/bin/sculpt quel:migrate                          # Run all pending migrations
  vendor/bin/sculpt quel:migrate --rollback               # Roll back the most recent migration
  vendor/bin/sculpt quel:migrate --rollback --steps=3     # Roll back the last 3 migrations
  vendor/bin/sculpt quel:migrate --status                 # Show migration status
  vendor/bin/sculpt quel:migrate --target=20230415000000  # Migrate to a specific version
  vendor/bin/sculpt quel:migrate --dry-run                # Preview migrations without applying them
HELP;
		}
	}