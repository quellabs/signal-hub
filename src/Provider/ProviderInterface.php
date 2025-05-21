<?php
	
	namespace Quellabs\Discover\Provider;
	
	interface ProviderInterface {
		
		/**
		 * Get the specific capabilities or services provided by this provider.
		 * This returns a list of specific features, services, or capabilities
		 * that this provider offers within its broader provider type.
		 * @return array<string> Array of service/capability identifiers
		 */
		public function getCapabilities(): array;
		
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