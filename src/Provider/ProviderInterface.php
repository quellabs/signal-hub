<?php
	
	namespace Quellabs\Discover\Provider;
	
	interface ProviderInterface {

		/**
		 * Register the provider with a container
		 * @param mixed $container The service container
		 * @return void
		 */
		public function register(mixed $container): void;
		
		/**
		 * Get the services provided
		 * @return array<string> Array of service names or class names
		 */
		public function provides(): array;
	}