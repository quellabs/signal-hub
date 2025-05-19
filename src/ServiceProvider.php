<?php
	
	namespace Quellabs\Sculpt;
	
	use Quellabs\Discover\Provider\ProviderInterface;
	
	/**
	 * Base implementation of the ServiceProviderInterface that provides
	 * common functionality for service providers in the Sculpt framework.
	 */
	abstract class ServiceProvider implements ProviderInterface {
		
		/**
		 * This service provider provides sculpt commands
		 * @return string[]
		 */
		public function provides(): array {
			return [
				'sculpt'
			];
		}
		
		/**
		 * Always load sculpt commands.
		 * The service itself may override this.
		 * @return bool
		 */
		public function shouldLoad(): bool {
			return true;
		}
		
		/**
		 * Helper method to register multiple commands at once
		 * @param Application $app The application instance
		 * @param array $commands Array of command class names to register
		 */
		protected function registerCommands(Application $app, array $commands): void {
			foreach ($commands as $command) {
				// Instantiate the command class and register it with the application
				$app->registerCommand(new $command($app->getInput(), $app->getOutput(), $this));
			}
		}
		
		/**
		 * Register the service provider with the Sculpt application.
		 * @param Application $application The Sculpt application instance
		 * @return void
		 */
		abstract public function register(Application $application): void;
	}