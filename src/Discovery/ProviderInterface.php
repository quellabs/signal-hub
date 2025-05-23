<?php
	
	namespace Quellabs\Discovery\Discovery;
	
	interface ProviderInterface {
		
		/**
		 * Retrieves metadata about the provider's capabilities and attributes.
		 * This method returns detailed information that describes the provider's
		 * functionality, supported features, version information, and other
		 * relevant configuration details needed for discovery and integration.
		 * @return array<string, mixed> Associative array of metadata key-value pairs
		 */
		public static function getMetadata(): array;
		
		/**
		 * Get default configuration
		 * @return array
		 */
		public static function getDefaults(): array;
		
		/**
		 * Returns the configuration
		 * @return array
		 */
		public function getConfig(): array;
		
		/**
		 * Sets configuration
		 * @return void
		 */
		public function setConfig(array $config): void;
	}