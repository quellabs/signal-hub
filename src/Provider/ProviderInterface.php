<?php
	
	namespace Quellabs\Discover\Provider;
	
	interface ProviderInterface {

		/**
		 * Get the services provided
		 * @return array<string> Array of service names or class names
		 */
		public function provides(): array;
		
		/**
		 * This method can be overridden to conditionally load providers
		 * based on runtime conditions.
		 * @return bool
		 */
		public function shouldLoad(): bool;
	}