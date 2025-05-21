<?php
	
	namespace Quellabs\Discover\Provider;
	
	interface ProviderInterface {
		
		/**
		 * Retrieves metadata about the provider's capabilities and attributes.
		 * This method returns detailed information that describes the provider's
		 * functionality, supported features, version information, and other
		 * relevant configuration details needed for discovery and integration.
		 * @return array<string, mixed> Associative array of metadata key-value pairs
		 */
		public function getMetadata(): array;
		
		/**
		 * This method can be overridden to conditionally load providers
		 * based on runtime conditions.
		 * @return bool
		 */
		public function shouldLoad(): bool;
		
		/**
		 * Get default configuration
		 * @return array
		 */
		public function getDefaults(): array;
		
		/**
		 * Sets configuration
		 * @return void
		 */
		public function setConfig(array $config): void;
		
		/**
		 * Get the family this provider belongs to
		 * @return string|null The provider family or null if not categorized
		 */
		public function getFamily(): ?string;
		
		/**
		 * Set the family for this provider
		 * @param string $family The provider family
		 * @return void
		 */
		public function setFamily(string $family): void;
	}