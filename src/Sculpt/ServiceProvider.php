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
		 * @var array List of command class references to be registered with the application
		 */
		protected array $commands;
		
		/**
		 * Initialize the service provider with available commands
		 * Prepares an array of fully qualified class names for all CLI commands
		 * that will be registered when the service provider boots.
		 */
		public function __construct() {
			$this->commands = [
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeEntityCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeEntityFromTableCommand::class,
				\Quellabs\ObjectQuel\Sculpt\Commands\MakeMigrationsCommand::class
			];
		}
		
		/**
		 * Register all ObjectQuel commands with the Sculpt application
		 * @param Application $app The Sculpt application instance
		 */
		public function register(Application $app): void {
			$this->commands($app, $this->commands);
		}
		
		/**
		 * Boot the service provider and configure all commands
		 * @param Application $app The Sculpt application instance
		 * @return void
		 * @throws OrmException If the configuration cannot be loaded
		 */
		public function boot(Application $app): void {
			parent::boot($app);
			
			$configuration = $this->getConfiguration();
			
			foreach($this->commands as $command) {
				$command->boot($configuration);
			}
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
		 * Create and configure the AnnotationReader configuration
		 * @return \Quellabs\AnnotationReader\Configuration The configured AnnotationReader settings
		 * @throws OrmException If the underlying ObjectQuel configuration cannot be loaded
		 */
		public function getAnnotationReaderConfiguration(): \Quellabs\AnnotationReader\Configuration {
			$configuration = $this->getConfiguration();
			
			$annotationReaderConfiguration = new \Quellabs\AnnotationReader\Configuration();
			$annotationReaderConfiguration->setUseAnnotationCache($configuration->useMetadataCache());
			$annotationReaderConfiguration->setAnnotationCachePath($configuration->getMetadataCachePath());
			return $annotationReaderConfiguration;
		}
	}