<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Provider\ProviderDefinition;
	use Quellabs\Discover\Utilities\PSR4;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	/**
	 * Scans composer.json files to discover service providers
	 * Includes static file caching to avoid re-reading same files
	 */
	class ComposerScanner implements ScannerInterface {
		
		/**
		 * The key to look for in composer.json extra section
		 * This also serves as the family name for discovered providers
		 * @var string|null
		 */
		protected ?string $familyName;
		
		/**
		 * The top-level key in composer.json's extra section that contains discovery configuration.
		 * Defaults to 'discover' but can be customized to use a different section name.
		 * @var string
		 */
		private string $discoverySection;
		
		/**
		 * @var PSR4 PSR-4 utilities
		 */
		private PSR4 $utilities;
		
		/**
		 * Static cache for composer.json file contents
		 * Key: file path, Value: parsed array data
		 * @var array<string, array|null>
		 */
		private static array $composerFileCache = [];
		
		/**
		 * Static cache for installed.json file contents
		 * Key: file path, Value: parsed array data
		 * @var array<string, array|null>
		 */
		private static array $installedFileCache = [];
		
		/**
		 * ComposerScanner constructor
		 * @param string|null $familyName The family name for providers, or null to discover all families
		 * @param string|null $discoverySection The top-level key in composer.json's extra section
		 */
		public function __construct(?string $familyName = null, ?string $discoverySection="discover") {
			$this->familyName = $familyName;
			$this->discoverySection = $discoverySection;
			$this->utilities = new PSR4();
		}
		
		/**
		 * Main entry point for provider discovery
		 * @return array<ProviderDefinition> Array of provider definitions
		 */
		public function scan(): array {
			// Discover providers defined within the current project structure
			// These are typically application-specific providers in src/ or app/ directories
			$projectProviders = $this->discoverProjectProviders();
			
			// Discover providers from installed packages/dependencies
			// These are usually third-party providers from vendor/ directory
			$packageProviders = $this->discoverPackageProviders();
			
			// Combine both sets of providers into a single array
			// Project providers are placed first, potentially allowing them to
			// override or take precedence over package providers
			return array_merge($projectProviders, $packageProviders);
		}
		
		/**
		 * Scans the current project's composer.json file to find provider definitions
		 * in the "extra" section or other configured locations. This method handles
		 * the project-specific provider discovery as opposed to third-party packages.
		 * @return array Array of discovered provider instances
		 */
		protected function discoverProjectProviders(): array {
			// Resolve the absolute path to the project's composer.json file
			// Uses utility method to handle different project structures and locations
			$composerPath = $this->utilities->getComposerJsonFilePath();
			
			// Validate that composer.json exists and is accessible
			// Early return prevents unnecessary processing and error conditions
			if (!$composerPath || !file_exists($composerPath)) {
				return [];
			}
			
			// Parse the composer.json file using cached method
			// This avoids re-reading the same file multiple times
			$composerData = $this->parseComposerFile($composerPath);
			
			// Validate that composer.json was parsed successfully
			// Corrupted or invalid JSON will result in null return value
			if (!$composerData) {
				return [];
			}
			
			// Extract provider definitions from composer data and instantiate them
			// This method handles validation, class loading, and provider instantiation
			return $this->extractAndValidateProviders($composerData);
		}
		
		/**
		 * Scans Composer's installed.json file to find provider definitions from
		 * third-party packages. This method processes all installed dependencies
		 * and extracts providers that have been configured for auto-discovery.
		 * @return array Array of discovered provider instances
		 */
		protected function discoverPackageProviders(): array {
			// Resolve the path to Composer's installed.json file
			// This file contains metadata about all installed packages and dependencies
			$installedPath = $this->utilities->getComposerInstalledFilePath();
			
			// Validate that installed.json exists and is accessible
			// Missing file typically indicates Composer hasn't been run or project issues
			if (!$installedPath || !file_exists($installedPath)) {
				return [];
			}
			
			// Parse the installed.json file using cached method
			// This avoids re-reading the potentially large file multiple times
			$packagesData = $this->parseInstalledFile($installedPath);
			
			// Validate that installed.json was parsed successfully
			// Corrupted file would prevent access to any package provider information
			if (!$packagesData) {
				return [];
			}
			
			// Handle different installed.json format versions for compatibility
			// Newer Composer versions wrap packages in a 'packages' key,
			// while older versions use the root array directly
			$packagesList = $packagesData['packages'] ?? $packagesData;
			
			// Iterate through each installed package to check for provider definitions
			$definitions = [];

			foreach ($packagesList as $package) {
				// Check if package has opted into auto-discovery via 'extra.discover' section
				// This is the standard convention for packages that want their providers discovered
				if (isset($package['extra'][$this->discoverySection])) {
					// Extract and validate providers from this specific package
					// Uses the same validation logic as project providers
					$packageProviders = $this->extractAndValidateProviders($package);
					
					// Merge discovered providers into the main collection
					// Maintains order of discovery across packages
					$definitions = array_merge($definitions, $packageProviders);
				}
			}
			
			// Return all providers discovered from installed packages
			return $definitions;
		}
		
		/**
		 * Parse composer.json file with static caching
		 * @param string $path Absolute path to composer.json file
		 * @return array|null Parsed composer data or null if invalid
		 */
		protected function parseComposerFile(string $path): ?array {
			// Check if this file has already been parsed and cached
			if (array_key_exists($path, self::$composerFileCache)) {
				// Return cached result (may be null if file was invalid)
				return self::$composerFileCache[$path];
			}
			
			// File not in cache, parse it and store the result
			$data = $this->parseJsonFile($path);
			
			// Cache the result (including null for invalid files)
			// This prevents re-attempting to parse known invalid files
			return self::$composerFileCache[$path] = $data;
		}
		
		/**
		 * Parse installed.json file with static caching
		 *
		 * Caches the parsed content to avoid re-reading and re-parsing the same
		 * installed.json file multiple times. This is especially beneficial since
		 * installed.json can be quite large (1-10MB in big projects).
		 *
		 * @param string $path Absolute path to installed.json file
		 * @return array|null Parsed installed data or null if invalid
		 */
		protected function parseInstalledFile(string $path): ?array {
			// Check if this file has already been parsed and cached
			if (array_key_exists($path, self::$installedFileCache)) {
				// Return cached result (may be null if file was invalid)
				return self::$installedFileCache[$path];
			}
			
			// File not in cache, parse it and store the result
			$data = $this->parseJsonFile($path);
			
			// Cache the result (including null for invalid files)
			// This prevents re-attempting to parse known invalid files
			return self::$installedFileCache[$path] = $data;
		}
		
		/**
		 * This method performs a two-stage process: first extracting provider class
		 * definitions from composer configuration data, then validating each provider
		 * to ensure it's properly implemented and can be instantiated. Only valid
		 * providers are returned to prevent runtime errors during application bootstrap.
		 * @param array $composerConfig Complete composer.json data array
		 * @return array Array of validated provider data structures
		 */
		private function extractAndValidateProviders(array $composerConfig): array {
			// Extract raw provider class definitions and their configurations
			// from the composer config's discovery section (typically extra.discover)
			$providersWithConfig = $this->extractProviderClasses($composerConfig);
			
			// Validate each discovered provider class individually
			$validProviders = [];

			foreach ($providersWithConfig as $providerData) {
				// Perform comprehensive validation on the provider class:
				// - Check if class exists and can be autoloaded
				// - Verify it implements required ProviderInterface
				// - Ensure constructor is compatible with dependency injection
				if ($this->validateProviderClass($providerData['class'])) {
					try {
						// Only include providers that pass all validation checks
						// This prevents runtime errors during provider instantiation
						$validProviders[] = $this->createProviderDefinition($providerData);
					} catch (\InvalidArgumentException $e) {
						// Skip invalid provider definitions
						continue;
					}
				}
			}
			
			// Return only the providers that are confirmed to be valid and usable
			return $validProviders;
		}
		
		/**
		 * Create a ProviderDefinition from provider data
		 * @param array $providerData Raw provider data
		 * @return ProviderDefinition
		 */
		private function createProviderDefinition(array $providerData): ProviderDefinition {
			// Get class name
			$className = $providerData['class'];
			
			// Get metadata and defaults - interface guarantees these methods exist
			$metadata = $className::getMetadata();
			$defaults = $className::getDefaults();
			
			return new ProviderDefinition(
				className: $className,
				family: $providerData['family'],
				configFile: $providerData['config'] ?? null,
				metadata: $metadata,
				defaults: $defaults
			);
		}
		
		/**
		 * Performs essential validation checks to ensure a provider class is properly
		 * defined and can be safely instantiated. This prevents runtime errors that
		 * would occur if invalid providers were included in the application bootstrap.
		 * @param string $providerClass Fully qualified class name of the provider to validate
		 * @return bool True if provider is valid and can be used, false if validation fails
		 */
		protected function validateProviderClass(string $providerClass): bool {
			// Verify that the provider class can be found and autoloaded
			// This catches typos in class names, missing files, or autoloader issues
			if (!class_exists($providerClass)) {
				return false;
			}
			
			// Ensure the provider class implements the required ProviderInterface contract
			// This guarantees the class has all necessary methods for provider functionality
			if (!is_subclass_of($providerClass, ProviderInterface::class)) {
				return false;
			}
			
			// The provider passed all validation checks and is safe to instantiate
			return true;
		}
		
		/**
		 * Parses the composer.json 'extra.discover' section to extract provider class
		 * definitions. Supports multiple configuration formats and can filter by provider
		 * family. This method handles the complexity of different discovery formats while
		 * maintaining backward compatibility.
		 * @param array $composerConfig Complete composer.json configuration array
		 * @return array Array of provider data structures
		 */
		protected function extractProviderClasses(array $composerConfig): array {
			// Extract the discovery configuration section from composer's extra data
			// This is the standardized location where packages define their discoverable providers
			$discoverSection = $composerConfig['extra'][$this->discoverySection] ?? [];
			
			// Validate that the discover section is properly formatted as an array
			// Malformed configuration should be ignored rather than causing errors
			if (!is_array($discoverSection)) {
				return [];
			}
			
			// Initialize the collection for all discovered providers across families
			$allProviders = [];
			
			// Process each provider family within the discovery section
			// Families group related providers (e.g., 'services', 'middleware', 'commands')
			foreach ($discoverSection as $familyName => $configSection) {
				// Apply family filtering if a specific family name has been configured
				// This allows selective discovery of only certain provider types
				if ($this->familyName !== null && $familyName !== $this->familyName) {
					continue;
				}
				
				// Skip malformed family configurations that aren't arrays
				// Each family section should contain provider definitions
				if (!is_array($configSection)) {
					continue;
				}
				
				// Handle array format: multiple providers listed in an array
				// Format: "family": ["Provider1", "Provider2", ...]
				$multipleProviders = $this->extractMultipleProviders($configSection, $familyName);
				
				// Handle object format: single provider with additional configuration
				// Format: "family": {"provider": "ProviderClass", "config": {...}}
				$singularProvider = $this->extractSingularProvider($configSection, $familyName);
				
				// Combine all providers found in this family into the main collection
				// Order is preserved: multiple providers first, then singular provider
				$allProviders = array_merge($allProviders, $multipleProviders, $singularProvider);
			}
			
			// Return all discovered providers from all processed families
			return $allProviders;
		}
		
		/**
		 * Extract providers from 'providers' array format
		 *
		 * Handles multiple provider definitions within a single family configuration.
		 * Supports both simple string format for basic providers and complex array
		 * format for providers that require additional configuration files or parameters.
		 *
		 * Handles: ["Class1", "Class2"] or [{"class": "Class1", "config": "file.php"}]
		 *
		 * @param array $config Family configuration section containing providers array
		 * @param string $familyName Name of the provider family (e.g., 'services', 'middleware')
		 * @return array Array of normalized provider data structures with class, config, and family
		 */
		protected function extractMultipleProviders(array $config, string $familyName): array {
			// Extract the 'providers' array from the family configuration
			// This array contains multiple provider definitions in various formats
			$providersArray = $config['providers'] ?? [];
			
			// Validate that providers section is properly formatted as an array
			// Non-array values indicate configuration errors and should be ignored
			if (!is_array($providersArray)) {
				return [];
			}
			
			// Process each provider definition within the providers array
			$result = [];
			
			foreach ($providersArray as $definition) {
				// Handle simple string format: just the provider class name
				// Example: "App\Providers\RedisProvider"
				if (is_string($definition)) {
					// Normalize simple string definitions into standard structure
					$result[] = [
						'class' => $definition,             // Fully qualified class name
						'config' => null,                   // No additional configuration
						'family' => $familyName             // Associate with current family
					];
					
					continue;
				}
				
				// Handle complex array format: provider with additional configuration
				// Example: {"class": "App\Providers\RedisProvider", "config": "redis.php"}
				if (is_array($definition) && isset($definition['class'])) {
					// Extract class name and optional configuration from array definition
					$result[] = [
						'class' => $definition['class'],                    // Required: provider class
						'config' => $definition['config'] ?? null,         // Optional: config file/data
						'family' => $familyName                            // Associate with current family
					];
				}
				
				// Skip malformed definitions that don't match expected formats
				// This prevents errors while allowing other valid providers to be processed
			}
			
			// Return all successfully processed provider definitions
			return $result;
		}
		
		/**
		 * Extract provider from singular 'provider' format
		 *
		 * Handles single provider definitions within a family configuration, supporting
		 * both simple string format and complex object format. Also manages configuration
		 * precedence when config can be specified in multiple locations (inline vs separate).
		 *
		 * Handles: "provider" => "Class" or "provider" => {"class": "Class", "config": "file.php"}
		 *
		 * @param array $config Family configuration section that may contain a single provider
		 * @param string $familyName Name of the provider family for categorization
		 * @return array|array[] Array containing single provider data structure, or empty array
		 *                       if no valid provider found. Wrapped in array for consistency
		 *                       with extractMultipleProviders method
		 */
		protected function extractSingularProvider(array $config, string $familyName): array {
			// Check if this family configuration contains a singular provider definition
			// The 'provider' key (singular) is used for single provider configurations
			if (!isset($config['provider'])) {
				return [];
			}
			
			// Extract the provider definition and any separate configuration
			$definition = $config['provider'];
			
			// Check for configuration defined at the family level (separate from provider)
			// Format: {"provider": "Class", "config": "separate-config.php"}
			$separateConfig = $config['config'] ?? null;
			
			// Handle simple string format: provider defined as just the class name
			// Example: "provider" => "App\Providers\RedisProvider"
			if (is_string($definition)) {
				// Return normalized provider structure with separate config if available
				return [[
					'class' => $definition,          // Provider class name
					'config' => $separateConfig,     // Use family-level config if present
					'family' => $familyName          // Associate with current family
				]];
			}
			
			// Handle complex array format: provider with inline configuration
			// Example: "provider" => {"class": "App\Providers\RedisProvider", "config": "redis.php"}
			if (is_array($definition) && isset($definition['class'])) {
				// Extract inline configuration from provider definition
				$inlineConfig = $definition['config'] ?? null;
				
				// Resolve configuration precedence: inline config overrides separate config
				// This allows for more specific configuration at the provider level
				$finalConfig = $inlineConfig ?? $separateConfig;
				
				return [[
					'class' => $definition['class'],  // Required: provider class name
					'config' => $finalConfig,         // Resolved configuration with precedence
					'family' => $familyName           // Associate with current family
				]];
			}
			
			// Return an empty array if provider definition doesn't match expected formats
			// This maintains consistency and prevents errors with malformed configurations
			return [];
		}
		
		/**
		 * Safely reads and parses a JSON file with comprehensive error handling.
		 * This utility method handles both file system errors (missing/unreadable files)
		 * and JSON parsing errors (malformed JSON syntax) to prevent crashes during
		 * the provider discovery process.
		 * @param string $path Absolute file path to the JSON file to be parsed
		 * @return array|null Parsed JSON data as associative array, or null if
		 *                    file cannot be read or JSON is invalid
		 */
		protected function parseJsonFile(string $path): ?array {
			// Attempt to read the entire file contents into memory
			// This may fail if file doesn't exist, is unreadable, or has permission issues
			$content = file_get_contents($path);
			
			// Check if file reading was successful
			// file_get_contents returns false on failure (missing file, permissions, etc.)
			if ($content === false) {
				return null;
			}
			
			// Parse JSON content into PHP associative array
			// The 'true' parameter ensures objects are converted to arrays, not stdClass
			$data = json_decode($content, true);
			
			// Validate that JSON parsing completed without errors
			// json_last_error() returns JSON_ERROR_NONE (0) only if parsing was successful
			// This catches syntax errors, encoding issues, and other JSON format problems
			return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
		}
	}