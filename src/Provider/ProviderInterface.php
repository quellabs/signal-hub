<?php
	
	namespace Quellabs\Discover\Provider;
	
	interface ProviderInterface {

		/**
		 * Get the services provided
		 * @return array<string> Array of service names or class names
		 */
		public function provides(): array;
	}