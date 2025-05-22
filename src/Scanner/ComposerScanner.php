<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Discover\Utilities\PSR4;
	
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
		 * This is the main entry point for the provider discovery process.
		 * @param DiscoveryConfig $config Configuration object containing discovery settings
		 * @return array<string> Array of discovered provider class names
		 */
		public function scan(DiscoveryConfig $config): array {
			// Extract debug mode setting from the configuration
			$debug = $config->isDebugEnabled();
			
			// Discover provider classes declared in the project's own composer.json
			$providerClasses = $this->discoverProjectProviders($debug);
			
			// Discover provider classes declared in installed dependency packages
			$packageProviderClasses = $this->discoverPackageProviders($debug);
			
			// Return the complete collection of discovered provider class names from all sources
			return array_merge($providerClasses, $packageProviderClasses);
		}
		
		/**
		 * This function reads the project's main composer.json file to find provider classes
		 * that are directly declared in the project (as opposed to in dependencies).
		 * @param bool $debug Whether to output debug messages during the discovery process
		 * @return array<string> Array of discovered provider class names from the project
		 */
		protected function discoverProjectProviders(bool $debug): array {
			// Get the full filesystem path to the project's composer.json file
			$composerPath = $this->utilities->getComposerJsonFilePath();
			
			// If the path couldn't be determined or the file doesn't exist, return an empty array
			if (!$composerPath || !file_exists($composerPath)) {
				return [];
			}
			
			// Log provider discovery attempt if debug mode is enabled
			if ($this->familyName) {
				$this->logDebug($debug, "[INFO] Looking for providers in family '{$this->familyName}' from project: {$composerPath}");
			} else {
				$this->logDebug($debug, "[INFO] Looking for providers in all families from project: {$composerPath}");
			}
			
			// Parse the project's composer.json file into an associative array
			$composer = $this->parseJsonFile($composerPath);
			
			// If parsing failed or file is empty/invalid, return empty array
			if (!$composer) {
				return [];
			}
			
			// Extract provider classes with their configs and family names
			$providersWithConfig = $this->extractProviderClasses($composer);
			
			// Validate and collect class names
			$result = [];
			
			foreach ($providersWithConfig as $providerData) {
				$providerClass = $providerData['class'];
				
				// Validate that the class exists and implements the interface
				if ($this->validateProviderClass($providerClass, $debug)) {
					$result[] = [
						'class'  => $providerClass,
						'config' => null,
						'family' => $providerData['family']  // Include the family from extracted data
					];
				}
			}
			
			// Return all valid provider class names from the project
			return $result;
		}
		
		/**
		 * This function scans the Composer installed.json file to find packages that
		 * declare provider classes in their extra configuration.
		 * @param bool $debug Whether to output debug messages when errors occur during provider validation
		 * @return array Array of discovered provider data with class and family information
		 */
		protected function discoverPackageProviders(bool $debug): array {
			// Initialize an empty array to store discovered provider class names
			$providerClasses = [];
			
			// Get the full filesystem path to Composer's installed.json file
			$installedPath = $this->utilities->getComposerInstalledFilePath();
			
			// If the path couldn't be determined or the file doesn't exist, return an empty array
			if ($installedPath === null || !file_exists($installedPath)) {
				return $providerClasses;
			}
			
			// Log package discovery attempt if debug mode is enabled
			if ($this->familyName) {
				$this->logDebug($debug, "[INFO] Looking for providers in family '{$this->familyName}' from installed packages");
			} else {
				$this->logDebug($debug, "[INFO] Looking for providers in all families from installed packages");
			}
			
			// Parse the installed.json file into an associative array
			$packages = $this->parseJsonFile($installedPath);
			
			// If parsing failed or file is empty, return empty array
			if (!$packages) {
				return $providerClasses;
			}
			
			// Handle both formats of installed.json:
			// - Newer Composer versions use a 'packages' key containing the array of packages
			// - Older versions have the packages directly at the root level
			$packagesList = $packages['packages'] ?? $packages;
			
			// Process each package to find providers
			foreach ($packagesList as $package) {
				// Skip packages without extra configuration or the discover section
				if (!isset($package['extra']['discover'])) {
					continue;
				}
				
				// Extract providers from this package
				$packageProviders = $this->extractProviderClasses($package);
				
				// Process each provider in this package
				foreach ($packageProviders as $providerData) {
					$providerClass = $providerData['class'];
					$providerFamily = $providerData['family'];
					$configFile = $providerData['config'] ?? null;  // Extract the config file if present
					
					// Validate the provider class
					if ($this->validateProviderClass($providerClass, $debug)) {
						$providerClasses[] = [
							'class'  => $providerClass,
							'config' => $configFile,
							'family' => $providerFamily
						];
					}
				}
			}
			
			// Return all discovered provider class names from packages
			return $providerClasses;
		}
		
		/**
		 * Validates that a provider class exists and implements the required interface
		 * @param string $providerClass Fully qualified provider class name
		 * @param bool $debug Whether to output error messages to console
		 * @return bool True if the class is valid, false otherwise
		 */
		protected function validateProviderClass(string $providerClass, bool $debug): bool {
			// Check if the provider class exists in the application namespace
			if (!class_exists($providerClass)) {
				// Log warning and return false if class doesn't exist
				$this->logDebug($debug, "[WARNING] Provider class not found: {$providerClass}");
				return false;
			}
			
			// Check if the class implements the required interface
			if (!is_subclass_of($providerClass, ProviderInterface::class)) {
				// Log warning and return false if interface not implemented
				$this->logDebug($debug, "[WARNING] Class {$providerClass} does not implement ProviderInterface");
				return false;
			}
			
			return true;
		}
		
		/**
		 * Extracts service provider classes from composer configuration.
		 * Supports both single provider and multiple providers formats.
		 * Expect the structure: extra.discover.{configKey}
		 * @param array $composerConfig The parsed composer.json configuration array
		 * @return array An array of provider data with class, config, and family information
		 */
		protected function extractProviderClasses(array $composerConfig): array {
			// Access the discover section within composer.json's 'extra' section
			$discoverSection = $composerConfig['extra']['discover'] ?? [];
			
			// Verify that the discover section is a valid array
			if (!is_array($discoverSection)) {
				return [];
			}
			
			// Process all families in the discover section
			$result = [];
			
			foreach ($discoverSection as $familyKey => $configSection) {
				// If a specific family is requested, skip families that don't match
				if ($this->familyName !== null && $familyKey !== $this->familyName) {
					continue;
				}
				
				// Verify that the configuration section is a valid array
				if (!is_array($configSection)) {
					continue;
				}
				
				// Get providers from both formats for this family
				$multipleProviders = $this->extractMultipleProviders($configSection, $familyKey);
				$singularProvider = $this->extractSingularProvider($configSection, $familyKey);
				
				// Merge both results
				$result = array_merge($result, $multipleProviders, $singularProvider);
			}
			
			return $result;
		}
		
		/**
		 * Extract providers from the 'providers' array format
		 *
		 * Handles multiple providers defined in a single configuration:
		 * 1. String format: ["App\\Providers\\RedisProvider", "App\\Providers\\MemcachedProvider"]
		 * 2. Object format: [{"class": "App\\Providers\\RedisProvider", "config": "redis.php"}, {"class": "App\\Providers\\MemcachedProvider"}]
		 *
		 * @param array $config The configuration section
		 * @param string $familyName The family name for these providers
		 * @return array The extracted providers and their configurations
		 */
		protected function extractMultipleProviders(array $config, string $familyName): array {
			$providersArray = $config['providers'] ?? [];
			
			// Ensure we have a valid array to iterate over
			if (!is_array($providersArray)) {
				return [];
			}
			
			// Process each provider definition in the array
			$extractedProviders = [];
			
			foreach ($providersArray as $providerDefinition) {
				if (is_string($providerDefinition)) {
					// Format 1: Simple string - just the class name
					// Example: "App\Providers\RedisProvider"
					$extractedProviders[] = [
						'class'  => $providerDefinition,
						'config' => null, // No config file specified
						'family' => $familyName
					];
				} elseif (is_array($providerDefinition) && isset($providerDefinition['class'])) {
					// Format 2: Array with class and optional config
					// Example: ["class" => "App\Providers\RedisProvider", "config" => "redis.php"]
					$extractedProviders[] = [
						'class'  => $providerDefinition['class'],
						'config' => $providerDefinition['config'] ?? null,
						'family' => $familyName
					];
				}
				// Skip invalid formats (neither string nor array with 'class' key)
			}
			
			return $extractedProviders;
		}
		
		/**
		 * Extract provider from the singular 'provider' format
		 *
		 * Handles two formats:
		 * 1. String format: "provider" => "ClassName", "config" => "path/to/config.php"
		 * 2. Array format:  "provider" => ["class" => "ClassName", "config" => "path/to/config.php"]
		 *
		 * @param array $config The configuration section
		 * @param string $familyName The family name for this provider
		 * @return array The extracted provider and its configuration
		 */
		protected function extractSingularProvider(array $config, string $familyName): array {
			// Exit early if no provider is defined in this configuration section
			if (!isset($config['provider'])) {
				return [];
			}
			
			$providerDefinition = $config['provider'];
			$separateConfigFile = $config['config'] ?? null; // Config defined outside the provider block
			
			if (is_string($providerDefinition)) {
				// Format 1: "provider" => "App\Providers\RedisProvider", "config" => "config/redis.php"
				return [[
					'class'  => $providerDefinition,
					'config' => $separateConfigFile,
					'family' => $familyName
				]];
			}
			
			if (is_array($providerDefinition) && isset($providerDefinition['class'])) {
				// Format 2: "provider" => ["class" => "App\Providers\RedisProvider", "config" => "config/redis.php"]
				$inlineConfigFile = $providerDefinition['config'] ?? null; // Config defined inside the provider block
				
				// Inline config takes precedence over separate config
				$finalConfigFile = $inlineConfigFile ?? $separateConfigFile;
				
				return [[
					'class'  => $providerDefinition['class'],
					'config' => $finalConfigFile,
					'family' => $familyName
				]];
			}
			
			// Invalid format - return empty array
			return [];
		}
		
		/**
		 * Parse a JSON file into an associative array
		 * @param string $path The file path to the JSON file to be parsed
		 * @return array|null Returns the parsed JSON data as an associative array on success,
		 *                    or null if file reading fails or JSON is invalid
		 */
		protected function parseJsonFile(string $path): ?array {
			// Attempt to read the contents of the file
			$content = file_get_contents($path);
			
			// If file reading fails (file not found, permission issues, etc.), return null
			if ($content === false) {
				return null;
			}
			
			// Decode the JSON string into an associative array (true parameter)
			$data = json_decode($content, true);
			
			// Check if JSON decoding encountered any errors
			// If there were JSON syntax errors, return null
			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}
			
			// Return the successfully parsed JSON data as an associative array
			return $data;
		}
		
		/**
		 * Conditionally outputs debug messages to console.
		 * @param bool $debug Whether debugging is enabled
		 * @param string $message Message to display
		 * @return void
		 */
		protected function logDebug(bool $debug, string $message): void {
			if ($debug) {
				echo $message . PHP_EOL;
			}
		}
	}