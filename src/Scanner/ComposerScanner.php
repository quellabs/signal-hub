<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Utilities\PSR4;
	use Quellabs\Discovery\Discovery\ProviderInterface;
	
	/**
	 * Scans composer.json files to discover service providers
	 */
	class ComposerScanner implements ScannerInterface {
		
		/**
		 * The key to look for in composer.json extra section
		 * This also serves as the family name for discovered providers
		 * @var string|null
		 */
		protected ?string $familyName;
		
		/**
		 * @var PSR4 PSR-4 utilities
		 */
		private PSR4 $utilities;
		
		/**
		 * ComposerScanner constructor
		 * @param string|null $familyName The family name for providers, or null to discover all families
		 */
		public function __construct(?string $familyName = null) {
			$this->familyName = $familyName;
			$this->utilities = new PSR4();
		}
		
		/**
		 * Main entry point for provider discovery
		 * @param DiscoveryConfig $config Configuration object containing discovery settings
		 * @return array|ProviderInterface[] Array of discovered provider instances
		 */
		public function scan(DiscoveryConfig $config): array {
			// Extract debug flag from configuration for consistent logging behavior
			// across all discovery operations
			$debug = $config->isDebugEnabled();
			
			// Discover providers defined within the current project structure
			// These are typically application-specific providers in src/ or app/ directories
			$projectProviders = $this->discoverProjectProviders($debug);
			
			// Discover providers from installed packages/dependencies
			// These are usually third-party providers from vendor/ directory
			$packageProviders = $this->discoverPackageProviders($debug);
			
			// Combine both sets of providers into a single array
			// Project providers are placed first, potentially allowing them to
			// override or take precedence over package providers
			return array_merge($projectProviders, $packageProviders);
		}
		
		/**
		 * Scans the current project's composer.json file to find provider definitions
		 * in the "extra" section or other configured locations. This method handles
		 * the project-specific provider discovery as opposed to third-party packages.
		 * @param bool $debug Whether to enable debug logging
		 * @return array Array of discovered provider instances
		 */
		protected function discoverProjectProviders(bool $debug): array {
			// Resolve the absolute path to the project's composer.json file
			// Uses utility method to handle different project structures and locations
			$composerPath = $this->utilities->getComposerJsonFilePath();
			
			// Validate that composer.json exists and is accessible
			// Early return prevents unnecessary processing and error conditions
			if (!$composerPath || !file_exists($composerPath)) {
				return [];
			}
			
			// Log the start of project provider discovery when debug mode is enabled
			// Helps with troubleshooting and understanding the discovery flow
			$this->logDiscoveryStart($debug, 'project', $composerPath);
			
			// Parse the composer.json file into a PHP array structure
			// Handles JSON decoding and potential file reading errors gracefully
			$composerData = $this->parseJsonFile($composerPath);
			
			// Validate that composer.json was parsed successfully
			// Corrupted or invalid JSON will result in null/false return value
			if (!$composerData) {
				return [];
			}
			
			// Extract provider definitions from composer data and instantiate them
			// This method handles validation, class loading, and provider instantiation
			return $this->extractAndValidateProviders($composerData, $debug);
		}
		
		/**
		 * Scans Composer's installed.json file to find provider definitions from
		 * third-party packages. This method processes all installed dependencies
		 * and extracts providers that have been configured for auto-discovery.
		 * @param bool $debug Whether to enable debug logging
		 * @return array Array of discovered provider instances
		 */
		protected function discoverPackageProviders(bool $debug): array {
			// Resolve the path to Composer's installed.json file
			// This file contains metadata about all installed packages and dependencies
			$installedPath = $this->utilities->getComposerInstalledFilePath();
			
			// Validate that installed.json exists and is accessible
			// Missing file typically indicates Composer hasn't been run or project issues
			if (!$installedPath || !file_exists($installedPath)) {
				return [];
			}
			
			// Log the start of package provider discovery when debug mode is enabled
			// Note: No path logged here as it's always the standard installed.json location
			$this->logDiscoveryStart($debug, 'packages');
			
			// Parse the installed.json file into a PHP array structure
			// This file can be quite large as it contains all package metadata
			$packagesData = $this->parseJsonFile($installedPath);
			
			// Validate that installed.json was parsed successfully
			// Corrupted file would prevent access to any package provider information
			if (!$packagesData) {
				return [];
			}
			
			// Handle different installed.json format versions for compatibility
			// Newer Composer versions wrap packages in a 'packages' key,
			// while older versions use the root array directly
			$packagesList = $packagesData['packages'] ?? $packagesData;
			
			// Initialize collection for all discovered providers across packages
			$allProviders = [];
			
			// Iterate through each installed package to check for provider definitions
			foreach ($packagesList as $package) {
				// Check if package has opted into auto-discovery via 'extra.discover' section
				// This is the standard convention for packages that want their providers discovered
				if (isset($package['extra']['discover'])) {
					// Extract and validate providers from this specific package
					// Uses the same validation logic as project providers
					$packageProviders = $this->extractAndValidateProviders($package, $debug);
					
					// Merge discovered providers into the main collection
					// Maintains order of discovery across packages
					$allProviders = array_merge($allProviders, $packageProviders);
				}
			}
			
			// Return all providers discovered from installed packages
			return $allProviders;
		}
		/**
		 * This method performs a two-stage process: first extracting provider class
		 * definitions from composer configuration data, then validating each provider
		 * to ensure it's properly implemented and can be instantiated. Only valid
		 * providers are returned to prevent runtime errors during application bootstrap.
		 * @param array $composerConfig Complete composer.json data array
		 * @param bool $debug Whether to enable debug logging
		 * @return array Array of validated provider data structures
		 */
		private function extractAndValidateProviders(array $composerConfig, bool $debug): array {
			// Extract raw provider class definitions and their configurations
			// from the composer config's discovery section (typically extra.discover)
			$providersWithConfig = $this->extractProviderClasses($composerConfig);
			
			// Initialize collection for providers that pass validation
			$validProviders = [];
			
			// Validate each discovered provider class individually
			foreach ($providersWithConfig as $providerData) {
				// Perform comprehensive validation on the provider class:
				// - Check if class exists and can be autoloaded
				// - Verify it implements required ProviderInterface
				// - Ensure constructor is compatible with dependency injection
				// - Log validation failures when debug mode is enabled
				if ($this->validateProviderClass($providerData['class'], $debug)) {
					// Only include providers that pass all validation checks
					// This prevents runtime errors during provider instantiation
					$validProviders[] = $providerData;
				}
			}
			
			// Return only the providers that are confirmed to be valid and usable
			return $validProviders;
		}
		
		/**
		 * Performs essential validation checks to ensure a provider class is properly
		 * defined and can be safely instantiated. This prevents runtime errors that
		 * would occur if invalid providers were included in the application bootstrap.
		 * @param string $providerClass Fully qualified class name of the provider to validate
		 * @param bool $debug Whether to log validation failures for troubleshooting
		 * @return bool True if provider is valid and can be used, false if validation fails
		 */
		protected function validateProviderClass(string $providerClass, bool $debug): bool {
			// Verify that the provider class can be found and autoloaded
			// This catches typos in class names, missing files, or autoloader issues
			if (!class_exists($providerClass)) {
				// Log missing class warning to help developers identify configuration issues
				$this->logDebug($debug, "[WARNING] Provider class not found: {$providerClass}");
				return false;
			}
			
			// Ensure the provider class implements the required ProviderInterface contract
			// This guarantees the class has all necessary methods for provider functionality
			if (!is_subclass_of($providerClass, ProviderInterface::class)) {
				// Log interface violation to help developers fix implementation issues
				$this->logDebug($debug, "[WARNING] Class {$providerClass} does not implement ProviderInterface");
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
			$discoverSection = $composerConfig['extra']['discover'] ?? [];
			
			// Validate that the discover section is properly formatted as an array
			// Malformed configuration should be ignored rather than causing errors
			if (!is_array($discoverSection)) {
				return [];
			}
			
			// Initialize collection for all discovered providers across families
			$allProviders = [];
			
			// Process each provider family within the discovery section
			// Families group related providers (e.g., 'services', 'middleware', 'commands')
			foreach ($discoverSection as $familyKey => $configSection) {
				// Apply family filtering if a specific family name has been configured
				// This allows selective discovery of only certain provider types
				if ($this->familyName !== null && $familyKey !== $this->familyName) {
					continue;
				}
				
				// Skip malformed family configurations that aren't arrays
				// Each family section should contain provider definitions
				if (!is_array($configSection)) {
					continue;
				}
				
				// Extract providers using multiple supported configuration formats:
				
				// Handle array format: multiple providers listed in an array
				// Format: "family": ["Provider1", "Provider2", ...]
				$multipleProviders = $this->extractMultipleProviders($configSection, $familyKey);
				
				// Handle object format: single provider with additional configuration
				// Format: "family": {"provider": "ProviderClass", "config": {...}}
				$singularProvider = $this->extractSingularProvider($configSection, $familyKey);
				
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
		 * Log discovery start message
		 * @param bool $debug
		 * @param string $source
		 * @param string|null $path
		 * @return void
		 */
		private function logDiscoveryStart(bool $debug, string $source, ?string $path = null): void {
			if ($this->familyName) {
				$familyMsg = "providers in family '{$this->familyName}'";
			} else {
				$familyMsg = "providers in all families";
			}
			
			if ($source === 'project') {
				$locationMsg = "from project: {$path}";
			} else {
				$locationMsg = "from installed packages";
			}
			
			$this->logDebug($debug, "[INFO] Looking for {$familyMsg} {$locationMsg}");
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
		
		/**
		 * Conditionally output debug messages
		 * @param bool $debug
		 * @param string $message
		 * @return void
		 */
		protected function logDebug(bool $debug, string $message): void {
			if ($debug) {
				echo $message . PHP_EOL;
			}
		}
	}