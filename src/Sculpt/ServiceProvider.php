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
		 * @param Application $app The Sculpt application instance
		 */
		public function register(Application $app): void {
			$this->registerCommands($app, [
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeEntityCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeEntityFromTableCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeMigrationsCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\ExecuteMigrationsCommand::class
			]);
		}
		
		/**
		 * Returns the namespace of this Service Provider
		 * @return string
		 */
		public function getNamespace(): string {
			return "objectquel";
		}
		
		/**
		 * Load and return the ObjectQuel CLI configuration
		 * @return Configuration The ObjectQuel configuration for CLI operations
		 * @throws OrmException If the configuration cannot be loaded
		 */
		public function getConfiguration(): Configuration {
			return ConfigurationLoader::loadCliConfiguration();
		}
	}