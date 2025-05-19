<?php
	
	namespace Quellabs\ObjectQuel\Sculpt;
	
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\ConfigurationLoader;
	use Quellabs\ObjectQuel\OrmException;
	use Quellabs\Sculpt\Application;
	
	/**
	 * ObjectQuel service provider for the Sculpt framework
	 */
	class ServiceProvider extends \Quellabs\Sculpt\ServiceProvider {
		
		/**
		 * Register all ObjectQuel commands with the Sculpt application
		 * @param mixed $container
		 */
		public function register(mixed $container): void {
			// Do nothing when $container is not of type Application
			if (!$container instanceof Application) {
				return;
			}
			
			// Register the commands into the Sculpt application
			$this->registerCommands($container, [
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeEntityCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeEntityFromTableCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeMigrationsCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\QuelMigrateCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\QuelCreatePhinxConfigCommand::class,
			]);
		}
		
		/**
		 * Load and return the ObjectQuel CLI configuration
		 * @return Configuration The ObjectQuel configuration for CLI operations
		 * @throws OrmException If the configuration cannot be loaded
		 */
		public function getConfiguration(): Configuration {
			return ConfigurationLoader::loadCliConfiguration();
		}
		
		/**
		 * Returns a Phinx configuration array
		 * @return array
		 */
		public function createPhinxConfig(): array {
			$configuration = $this->getConfiguration();
			$connectionParams = $configuration->getConnectionParams();
			
			return [
				'paths'        => [
					'migrations' => $configuration->getMigrationsPath(),
				],
				'environments' => [
					'default_migration_table' => 'phinxlog',
					'default_environment'     => 'development',
					'development'             => [
						'adapter'   => $connectionParams['driver'],
						'host'      => $connectionParams['host'],
						'name'      => $connectionParams['database'],
						'user'      => $connectionParams['username'],
						'pass'      => $connectionParams['password'],
						'port'      => $connectionParams['port'] ?? 3306,
						'charset'   => $connectionParams['encoding'] ?? 'utf8mb4',
						'collation' => $connectionParams['collation'] ?? 'utf8mb4_unicode_ci',
					],
				],
			];
		}
	}