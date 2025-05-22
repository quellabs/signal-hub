<?php
	
	namespace Quellabs\Discover;
	
	use Composer\Autoload\ClassLoader;
	use Quellabs\Discover\Scanner\ScannerInterface;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Utilities\PSR4;
	use RuntimeException;
	
	class Discover {
		
		/**
		 * @var array<ScannerInterface>
		 */
		protected array $scanners = [];
		
		/**
		 * @var DiscoveryConfig
		 */
		protected DiscoveryConfig $config;
		
		/**
		 * @var PSR4 PSR-4 Utility Class
		 */
		private PSR4 $utilities;
		
		/**
		 * @var array Provider definitions indexed by unique keys
		 */
		protected array $providerDefinitions = [];
		
		/**
		 * @var array Map of instantiated providers by definition key
		 */
		protected array $instantiatedProviders = [];
		
		/**
		 * Create a new Discover instance
		 * @param DiscoveryConfig|null $config
		 */
		public function __construct(?DiscoveryConfig $config = null) {
			$this->config = $config ?? new DiscoveryConfig();
			$this->utilities = new PSR4();
		}
		
		/**
		 * Discover providers using all registered scanners
		 * @return self
		 */
		public function discover(): self {
			// Clear any previously discovered providers to start fresh
			$this->clearProviders();
			
			// Iterate through each registered scanner to discover providers
			foreach ($this->scanners as $scanner) {
				// Use the scanner to find provider classes based on configuration
				$discoveredClasses = $scanner->scan($this->config);
				
				// Process each discovered class returned by the scanner
				foreach ($discoveredClasses as $classData) {
					// Check if the discovered class data is in array format
					// (contains structured metadata about the provider)
					if (is_array($classData)) {
						// Register the discovered provider with its metadata
						// Pass the config file from scanner data
						$this->addProviderDefinition(
							$classData['class'],                   // The fully qualified class name
							$classData['family'],                  // The provider family/category
							$classData['config'] ?? null  // Optional config file path (null if not provided)
						);
					}
				}
			}
			
			// Return self to enable method chaining
			return $this;
		}
		
		/**
		 * Export current provider definitions for caching
		 * @return array Cacheable provider definitions
		 */
		public function exportForCache(): array {
			// Initialize cache data structure with metadata and organized provider storage
			$cacheData = [
				'timestamp' => time(),  // Record when this cache snapshot was created
				'providers' => []       // Will hold providers organized by family type
			];
			
			// Transform flat provider definitions into a family-grouped structure for efficient caching
			// This organization makes cache lookups and provider discovery faster
			foreach ($this->providerDefinitions as $definitionKey => $definition) {
				// Extract the provider family, defaulting to 'unknown' for safety
				// This ensures all providers get categorized even if family is missing
				$family = $definition['family'] ?? 'unknown';
				
				// Create family group in cache structure if it doesn't exist yet
				// This lazy initialization approach only creates groups as needed
				if (!isset($cacheData['providers'][$family])) {
					$cacheData['providers'][$family] = [];
				}
				
				// Add this provider definition to its appropriate family group
				// The definition contains all data needed to reconstruct the provider later
				$cacheData['providers'][$family][] = $definition;
			}
			
			// Return the complete cache-ready data structure with timestamp and organized providers
			return $cacheData;
		}
		
		/**
		 * Import provider definitions from cache
		 * @param array $cacheData Previously exported provider data
		 * @return self
		 */
		public function importDefinitionsFromCache(array $cacheData): self {
			// Clear existing state to ensure clean import from cache
			// Reset both definitions and any previously instantiated providers
			$this->clearProviders();
			
			// Validate cache data structure before processing
			// Ensure providers key exists and contains array data to prevent errors
			if (!isset($cacheData['providers']) || !is_array($cacheData['providers'])) {
				return $this;
			}
			
			// Process the family-grouped provider data from cache
			// Iterate through each provider family and its associated providers
			foreach ($cacheData['providers'] as $family => $familyProviders) {
				// Process each provider definition within this family
				foreach ($familyProviders as $providerData) {
					// Extract class name as the primary identifier for the provider
					// This is essential for generating unique definition keys
					$className = $providerData['class'] ?? null;
					
					// Only process providers with valid class names
					if ($className) {
						// Generate a unique definition key combining family and class name
						// Format: "family::className" ensures uniqueness across families
						$definitionKey = $family . '::' . $className;
						
						// Store the complete provider definition using the generated key
						// This recreates the flat storage structure from the hierarchical cache
						$this->providerDefinitions[$definitionKey] = $providerData;
					}
				}
			}
			
			// Return self to enable method chaining
			return $this;
		}
		
		/**
		 * Get all providers (instantiates all definitions - use carefully!)
		 * @return array<ProviderInterface>
		 */
		public function getProviders(): array {
			// Initialize collection to store all successfully instantiated providers
			$providers = [];
			
			// Iterate through every registered provider definition
			// WARNING: This will instantiate ALL providers, which can be expensive
			foreach ($this->providerDefinitions as $definitionKey => $definition) {
				// Attempt to get or create a provider instance from the definition
				// Uses lazy instantiation helper that handles caching and reconstruction
				$provider = $this->getOrInstantiateProvider($definitionKey, $definition);
				
				// Only include successfully instantiated providers in the result
				// Filters out any providers that failed to instantiate properly
				if ($provider) {
					// Add the valid provider to our collection
					$providers[] = $provider;
				}
			}
			
			// Return array containing all successfully instantiated provider instances
			// Note: This could be a large collection depending on registered definitions
			return $providers;
		}
		
		/**
		 * Clear all providers and definitions
		 * @return self
		 */
		public function clearProviders(): self {
			$this->providerDefinitions = [];
			$this->instantiatedProviders = [];
			return $this;
		}
		
		/**
		 * Get the current configuration
		 * @return DiscoveryConfig
		 */
		public function getConfig(): DiscoveryConfig {
			return $this->config;
		}
		
		/**
		 * Set a new configuration
		 * @param DiscoveryConfig $config
		 * @return self
		 */
		public function setConfig(DiscoveryConfig $config): self {
			$this->config = $config;
			return $this;
		}
		
		/**
		 * Add a scanner
		 * @param ScannerInterface $scanner
		 * @return self
		 */
		public function addScanner(ScannerInterface $scanner): self {
			$this->scanners[] = $scanner;
			return $this;
		}

		/**
		 * Get all available provider types (no instantiation needed)
		 * @return array<string> Array of unique provider types
		 */
		public function getProviderTypes(): array {
			$types = [];

			foreach ($this->providerDefinitions as $definition) {
				// Safely extract the family type, handling cases where it might not be defined
				// Uses null coalescing to avoid undefined key errors
				$family = $definition['family'] ?? null;
				
				// Only process valid family types and ensure uniqueness in the result set
				// Skip null values and duplicates to maintain a clean list of distinct types
				if ($family !== null && !in_array($family, $types)) {
					// Add this unique family type to our collection
					$types[] = $family;
				}
			}
			
			// Return array of all distinct provider family types found in definitions
			return $types;
		}
		
		/**
		 * Get metadata from all providers without instantiation
		 * @return array<string, array> Provider metadata indexed by class name
		 */
		public function getAllProviderMetadata(): array {
			// Initialize a collection to store metadata from all registered providers.
			// Class name will index this for easy lookup and identification.
			$metadata = [];
			
			// Iterate through all cached provider definitions to extract metadata
			// This approach avoids instantiating providers, making it very efficient
			foreach ($this->providerDefinitions as $definition) {
				// Extract the class name as the unique identifier for this provider
				// Use null coalescing to safely handle definitions without 'class' key
				$className = $definition['class'] ?? null;
				
				// Only process providers with valid class names to ensure data integrity
				if ($className) {
					// Extract and store the provider's metadata using class name as key
					// Default to an empty array if metadata is not defined in the cached definition
					$metadata[$className] = $definition['metadata'] ?? [];
				}
			}
			
			// Return the complete collection of provider metadata indexed by class name
			// This enables efficient capability inspection without provider instantiation
			return $metadata;
		}
		
		/**
		 * Find providers by metadata using a filter function (with lazy instantiation)
		 * @param callable $metadataFilter Function that receives metadata and returns bool
		 * @return array<ProviderInterface>
		 */
		public function findProvidersByMetadata(callable $metadataFilter): array {
			$providers = [];

			foreach ($this->providerDefinitions as $definitionKey => $definition) {
				// Extract metadata from the definition, defaulting to empty array if not present
				// This ensures the filter function always receives a valid array parameter
				$metadata = $definition['metadata'] ?? [];
				
				// Apply the custom filter function to determine if this provider's metadata matches
				// The callback receives the metadata array and should return true/false
				if ($metadataFilter($metadata)) {
					// Only instantiate providers that pass the metadata filter test
					// This lazy approach avoids creating objects for non-matching providers
					$provider = $this->getOrInstantiateProvider($definitionKey, $definition);
					
					// Add to results only if instantiation succeeded
					// Protects against potential instantiation errors or null returns
					if ($provider) {
						$providers[] = $provider;
					}
				}
			}
			
			// Return the collection of providers whose metadata satisfied the filter criteria
			return $providers;
		}
		
		/**
		 * Find all providers of a specific family type (with lazy instantiation)
		 * @param string $family The family type to filter by
		 * @return array<ProviderInterface>
		 */
		public function findProvidersByFamily(string $family): array {
			$providers = [];

			foreach ($this->providerDefinitions as $definitionKey => $definition) {
				// Check if the provider definition matches the requested family type
				// Uses null coalescing operator to safely handle missing 'family' key
				if (($definition['family'] ?? null) === $family) {
					// Lazily instantiate the provider only when we have a family match
					// This defers object creation until we know the provider is needed
					$provider = $this->getOrInstantiateProvider($definitionKey, $definition);
					
					// Only add successfully instantiated providers to the result set
					// Guards against instantiation failures or null returns
					if ($provider) {
						$providers[] = $provider;
					}
				}
			}
			
			// Return array of all provider instances that match the specified family
			return $providers;
		}

		/**
		 * Find providers that match a specific family and metadata filter (with lazy instantiation)
		 * @param string $family The family to filter by
		 * @param callable $metadataFilter Function that receives metadata and returns bool
		 * @return array<ProviderInterface>
		 */
		public function findProvidersByFamilyAndMetadata(string $family, callable $metadataFilter): array {
			$providers = [];

			foreach ($this->providerDefinitions as $definitionKey => $definition) {
				// Check if this provider definition belongs to the requested family
				if (($definition['family'] ?? null) === $family) {
					// Extract metadata from the definition (default to empty array if not set)
					$metadata = $definition['metadata'] ?? [];
					
					// Apply the custom metadata filter function to determine if this provider matches
					if ($metadataFilter($metadata)) {
						// Lazily instantiate the provider only when it matches our criteria
						// This avoids unnecessary object creation for non-matching providers
						$provider = $this->getOrInstantiateProvider($definitionKey, $definition);
						
						// Only add to results if instantiation was successful
						if ($provider) {
							$providers[] = $provider;
						}
					}
				}
			}
			
			// Return the collection of matching provider instances
			return $providers;
		}
		
		/**
		 * Gets the Composer autoloader instance
		 * @return ClassLoader
		 * @throws RuntimeException If autoloader can't be found
		 */
		public function getComposerAutoloader(): ClassLoader {
			return $this->utilities->getComposerAutoloader();
		}
		
		/**
		 * Find directory containing composer.json by traversing up from the given directory
		 * @param string|null $directory Directory to start searching from (defaults to current directory)
		 * @return string|null Directory containing composer.json if found, null otherwise
		 */
		public function getProjectRoot(?string $directory = null): ?string {
			return $this->utilities->getProjectRoot($directory);
		}
		
		/**
		 * Find the path to the local composer.json file
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return string|null Path to composer.json if found, null otherwise
		 */
		public function getComposerJsonFilePath(?string $startDirectory = null): ?string {
			return $this->utilities->getComposerJsonFilePath($startDirectory);
		}
		
		/**
		 * Find the path to installed.json
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return string|null Path to composer.json if found, null otherwise
		 */
		public function getComposerInstalledFilePath(?string $startDirectory = null): ?string {
			return $this->utilities->getComposerInstalledFilePath($startDirectory);
		}
		
		/**
		 * Maps a directory path to a namespace based on PSR-4 rules.
		 * This method attempts to determine the correct namespace for a directory by:
		 * 1. First checking against registered autoloader PSR-4 mappings (for dependencies)
		 * 2. Then checking against the main project's composer.json PSR-4 mappings if necessary
		 * @param string $directory Directory path to map to a namespace
		 * @return string|null The corresponding namespace if found, null otherwise
		 */
		public function resolveNamespaceFromPath(string $directory): ?string {
			return $this->utilities->resolveNamespaceFromPath($directory);
		}
		
		/**
		 * Recursively scans a directory and maps files to namespaced classes based on PSR-4 rules
		 * @param string $directory Directory to scan
		 * @param callable|null $filter Optional callback function to filter classes (receives className as parameter)
		 * @return array<string> Array of fully qualified class names
		 */
		public function findClassesInDirectory(string $directory, ?callable $filter = null): array {
			return $this->utilities->findClassesInDirectory($directory, $filter);
		}
		
		/**
		 * Get or instantiate a provider from its definition
		 * @param string $definitionKey Unique key for the provider definition
		 * @param array $definition Provider definition data
		 * @return ProviderInterface|null
		 */
		protected function getOrInstantiateProvider(string $definitionKey, array $definition): ?ProviderInterface {
			// Check if we already have a cached instance for this provider definition
			// This implements lazy instantiation - providers are only created when first needed
			if (isset($this->instantiatedProviders[$definitionKey])) {
				return $this->instantiatedProviders[$definitionKey];
			}
			
			// No cached instance exists, so create a new provider from the definition data
			// Delegate the complex instantiation logic to the specialized reconstruction method
			$provider = $this->instantiateProvider($definition);
			
			// If instantiation was successful, cache the new provider instance
			if ($provider) {
				// Store in cache using the definition key for future lookups
				// This ensures subsequent calls for the same provider return the same instance
				$this->instantiatedProviders[$definitionKey] = $provider;
			}
			
			// Return either the newly created provider or null if instantiation failed
			return $provider;
		}
		
		/**
		 * Add a provider definition from a class name
		 * @param class-string<ProviderInterface> $className The provider class name
		 * @param string $family The family name for this provider
		 * @param string|null $configFile Optional path to a config file
		 * @return void
		 */
		protected function addProviderDefinition(string $className, string $family, ?string $configFile = null): void {
			// Create a cache key
			$definitionKey = $family . '::' . $className;
			
			// Skip if already exists
			if (isset($this->providerDefinitions[$definitionKey])) {
				return;
			}
			
			// Add it to the list
			$this->providerDefinitions[$definitionKey] = [
				'class'    => $className,
				'family'   => $family,
				'config'   => $configFile,
				'metadata' => $className::getMetadata(),
				'defaults' => $className::getDefaults(),
			];
		}
		
		/**
		 * Instantiate and configure a provider from definition data
		 * Creates a new provider instance, loads its configuration from file (if specified),
		 * merges it with defaults, and applies the final configuration to the provider.
		 * @param array $providerData Provider definition containing class, config file path, and family
		 * @return ProviderInterface|null Successfully instantiated and configured provider or null on failure
		 */
		protected function instantiateProvider(array $providerData): ?ProviderInterface {
			// Extract essential provider information from cached data
			// Use null coalescing to handle missing keys gracefully
			$className = $providerData['class'] ?? null;
			$configFile = $providerData['config'] ?? null;
			
			// Perform upfront validation to ensure we have the minimum required data
			// Check both that class name exists and that the class is actually loadable
			if (!$className || !class_exists($className)) {
				return null;
			}
			
			try {
				// Attempt to create a new instance using the cached class name
				// This uses dynamic instantiation based on the stored class reference
				$provider = new $className();
				
				// Verify the instantiated object conforms to our expected interface
				// This type check protects against cache corruption or invalid class definitions
				if (!$provider instanceof ProviderInterface) {
					return null;
				}
				
				// Load configuration from file if specified, otherwise use empty array
				$loadedConfig = $this->loadConfigFile($configFile);
				
				// Merge defaults with loaded config and apply to provider
				$provider->setConfig(array_merge($className::getDefaults(), $loadedConfig));
				
				// Return the fully reconstructed and configured provider instance
				return $provider;
				
			} catch (\Throwable $e) {
				// Catch any errors during instantiation, configuration, or method calls
				// Return null to indicate reconstruction failure rather than throwing exceptions
				return null;
			}
		}
		
		/**
		 * Loads a configuration file and returns its contents as an array.
		 * @param string|null $configFile Relative path to the configuration file from project root
		 * @return array The configuration array from the file, or empty array if the file doesn't exist
		 */
		protected function loadConfigFile(?string $configFile): array {
			// Return empty config when no file given
			if ($configFile === null) {
				return [];
			}
			
			// Get the project's root directory
			$rootDir = $this->utilities->getProjectRoot();
			
			// Build the absolute path to the configuration file
			$completeDir = $rootDir . DIRECTORY_SEPARATOR . $configFile;
			
			// Make sure the file exists before attempting to load it
			if (!file_exists($completeDir)) {
				return [];
			}
			
			// Include the file and return its contents
			// This works because PHP's include statement returns the result of the included file,
			// which should be an array if the file contains 'return []' or similar
			return include $completeDir;
		}
	}