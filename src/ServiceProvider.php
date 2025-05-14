<?php
	
	namespace Quellabs\Sculpt;
	
	use Quellabs\Sculpt\Contracts\ServiceProviderInterface;
	
	/**
	 * Base implementation of the ServiceProviderInterface that provides
	 * common functionality for service providers in the Sculpt framework.
	 */
	abstract class ServiceProvider implements ServiceProviderInterface {
		
		/**
		 * The boot method is called after all service providers have been registered.
		 * This allows a provider to use services registered by other providers.
		 * @param Application $app The application instance
		 */
		public function boot(Application $app): void {
			// Default empty implementation
			// Child classes can override this method to perform boot operations
		}
		
		/**
		 * Helper method to register multiple commands at once
		 * @param Application $app The application instance
		 * @param array $commands Array of command class names to register
		 */
		protected function commands(Application $app, array $commands): void {
			foreach ($commands as $command) {
				// Instantiate the command class and register it with the application
				$app->registerCommand(new $command($app->getInput(), $app->getOutput(), $this));
			}
		}
	}