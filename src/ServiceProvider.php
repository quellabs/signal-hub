<?php
	
	namespace Quellabs\Sculpt;
	
	abstract class ServiceProvider implements ServiceProviderInterface {
		
		/**
		 * Default empty implementation of boot
		 */
		public function boot(Application $app): void {
			// Default empty implementation
		}
		
		/**
		 * Helper method to register multiple commands at once
		 */
		protected function commands(Application $app, array $commands): void {
			foreach ($commands as $command) {
				$app->registerCommand(new $command());
			}
		}
	}