<?php
	
	namespace Quellabs\Sculpt\Contracts;
	
	use Quellabs\Sculpt\Application;
	
	interface ServiceProviderInterface {
		
		/**
		 * Register services with the Sculpt application
		 */
		public function register(Application $app): void;
		
		/**
		 * Bootstrap services after all providers are registered
		 */
		public function boot(Application $app): void;
	}