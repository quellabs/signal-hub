<?php
	
	namespace Quellabs\Discover\Provider;
	
	/**
	 * AbstractProvider class implements the basic functionality for a provider.
	 * This abstract class serves as a base implementation of the ProviderInterface.
	 * Specific providers should extend this class to implement their unique functionality.
	 */
	class AbstractProvider implements ProviderInterface {
		
		/**
		 * Configuration settings for the provider.
		 * @var array
		 */
		private array $config;
		
		/**
		 * The family identifier for the provider.
		 * Used to group related providers together.
		 * @var string
		 */
		private string $family;
		
		/**
		 * Retrieves metadata associated with this provider.
		 * @return array An associative array of metadata key-value pairs
		 */
		public function getMetadata(): array {
			return [];
		}
		
		/**
		 * Determines if this provider should be loaded.
		 * Can be overridden by child classes to conditionally load providers.
		 * @return bool True if the provider should be loaded, false otherwise
		 */
		public function shouldLoad(): bool {
			return true;
		}
		
		/**
		 * Returns the default configuration settings for this provider.
		 * Child classes can override this to provide their specific defaults.
		 * @return array Default configuration values
		 */
		public function getDefaults(): array {
			return [];
		}
		
		/**
		 * Returns the current configuration settings for this provider.
		 * @return array The complete configuration array for this provider
		 */
		public function getConfig(): array {
			return $this->config;
		}
		
		/**
		 * Sets the configuration for this provider.
		 * @param array $config Configuration array to apply to this provider
		 * @return void
		 */
		public function setConfig(array $config): void {
			$this->config = $config;
		}
		
		/**
		 * Gets the family identifier for this provider.
		 * @return string|null The family this provider belongs to, or null if not set
		 */
		public function getFamily(): ?string {
			return $this->family;
		}
		
		/**
		 * Sets the family identifier for this provider.
		 * @param string $family The family identifier to set
		 * @return void
		 */
		public function setFamily(string $family): void {
			$this->family = $family;
		}
	}