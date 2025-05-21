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
		 * @var string
		 */
		protected string $configKey;
		
		/**
		 * Base path where the application is installed
		 * @var string
		 */
		protected string $basePath;
		
		/**
		 * @var PSR4 PSR-4 utilities
		 */
		private PSR4 $utilities;
		
		/**
		 * ComposerScanner constructor
		 * @param string $configKey The key to look for in composer.json (e.g., 'discover')
		 * @param string|null $basePath
		 */
		public function __construct(string $configKey = 'discover', ?string $basePath = null) {
			$this->configKey = $configKey;
			$this->basePath = $basePath ?? getcwd();
			$this->utilities = new PSR4();
		}
		
		/**
		 * This is the main entry point for the provider discovery process. It orchestrates
		 * the scanning of both the project's composer.json and the installed packages to
		 * find and instantiate all available service providers.
		 * @param DiscoveryConfig $config Configuration object containing discovery settings
		 * @return array<ProviderInterface> Combined array of all discovered and instantiated providers
		 */
		public function scan(DiscoveryConfig $config): array {
			// Initialize an empty array to store all discovered provider instances
			$providers = [];
			
			// Extract debug mode setting from the configuration
			$debug = $config->isDebugEnabled();
			
			// First, discover providers declared in the project's own composer.json
			// These typically have higher priority as they're defined by the project itself
			$projectProviders = $this->discoverProjectProviders($debug);
			$providers = array_merge($providers, $projectProviders);
			
			// Next, discover providers declared in installed dependency packages
			// These extend the project's functionality through third-party components
			$packageProviders = $this->discoverPackageProviders($debug);
			
			// Return the complete collection of discovered providers from all sources
			return array_merge($providers, $packageProviders);
		}
		
		/**
		 * This function reads the project's main composer.json file to find provider classes
		 * that are directly declared in the project (as opposed to in dependencies). It parses
		 * the file, extracts provider class names, and instantiates each valid provider.
		 * @param bool $debug Whether to output debug messages during the discovery process
		 * @return array<ProviderInterface> Array of successfully instantiated provider objects from the project
		 */
		protected function discoverProjectProviders(bool $debug): array {
			// Get the full filesystem path to the project's composer.json file
			$composerPath = $this->utilities->getComposerJsonFilePath();
			
			// If the path couldn't be determined or the file doesn't exist, return an empty array
			if (!$composerPath || !file_exists($composerPath)) {
				return [];
			}
			
			// Log provider discovery attempt if debug mode is enabled
			if ($debug) {
				echo "[INFO] Looking for providers in project: {$composerPath}\n";
			}
			
			// Parse the project's composer.json file into an associative array
			$composer = $this->parseJsonFile($composerPath);
			
			// If parsing failed or file is empty/invalid, return empty array
			if (!$composer) {
				return [];
			}
			
			// Extract provider classes with their configs
			$providersWithConfig = $this->extractProviderClasses($composer);
			
			// Attempt to instantiate each discovered provider class
			$result = [];

			foreach ($providersWithConfig as $providerClass => $providerConfig) {
				// Try to create an instance of the provider class
				$provider = $this->instantiateProvider($providerClass, $providerConfig, $debug);
				
				// If successfully instantiated, add to our results
				if ($provider) {
					$result[] = $provider;
				}
			}
			
			// Return all successfully instantiated provider objects from the project
			return $result;
		}
		
		/**
		 * Extracts service provider classes from composer configuration.
		 * @param array $composerConfig The parsed composer.json configuration array
		 * @return array An associative array of provider classes and their metadata (config and family)
		 */
		protected function extractProviderClasses(array $composerConfig): array {
			$result = [];
			
			// Process multiple providers in array format
			if (isset($composerConfig['extra'][$this->configKey]['providers']) && is_array($composerConfig['extra'][$this->configKey]['providers'])) {
				foreach ($composerConfig['extra'][$this->configKey]['providers'] as $provider) {
					if (is_string($provider)) {
						// Simple string format - provider class with no configuration or family
						$result[$provider] = [
							'config' => null,
							'family' => null
						];
					} elseif (is_array($provider) && isset($provider['class'])) {
						// Array format with explicit class and optional configuration and family
						$result[$provider['class']] = [
							'config' => $provider['config'] ?? null,
							'family' => $provider['family'] ?? null
						];
					}
				}
			}
			
			// Process singular provider format
			if (isset($composerConfig['extra'][$this->configKey]['provider'])) {
				$provider = $composerConfig['extra'][$this->configKey]['provider'];
				$config = $composerConfig['extra'][$this->configKey]['config'] ?? null;
				$family = $composerConfig['extra'][$this->configKey]['family'] ?? null;
				
				if (is_string($provider)) {
					// Simple string provider with separate config and family
					$result[$provider] = [
						'config' => $config,
						'family' => $family
					];
				} elseif (is_array($provider) && isset($provider['class'])) {
					// Array format provider with inline config and family
					$result[$provider['class']] = [
						'config' => $provider['config'] ?? null,
						'family' => $provider['family'] ?? null
					];
				}
			}
			
			// Return the extracted providers with their metadata
			return $result;
		}
		
		/**
		 * This function scans the Composer installed.json file to find packages that
		 * declare provider classes in their extra configuration. It processes packages
		 * and their provider declarations, extracts configuration information, and
		 * instantiates each valid provider.
		 * @param bool $debug Whether to output debug messages when errors occur during provider instantiation
		 * @return array<ProviderInterface> Array of successfully instantiated provider objects
		 */
		protected function discoverPackageProviders(bool $debug): array {
			// Initialize an empty array to store discovered provider instances
			$providers = [];
			
			// Get the full filesystem path to Composer's installed.json file
			$installedPath = $this->utilities->getComposerJsonFilePath();
			
			// If the path couldn't be determined or the file doesn't exist, return an empty array
			if ($installedPath === null) {
				return $providers;
			}
			
			// Parse the installed.json file into an associative array
			$packages = $this->parseJsonFile($installedPath);
			
			// If parsing failed or file is empty, return empty array
			if (!$packages) {
				return $providers;
			}
			
			// Handle both formats of installed.json:
			// - Newer Composer versions use a 'packages' key containing the array of packages
			// - Older versions have the packages directly at the root level
			$packagesList = $packages['packages'] ?? $packages;
			
			// Extract all provider classes and their associated configuration files
			// This transforms the raw package data into a structured mapping of
			// provider class names to their respective configuration file paths
			$providersWithConfig = $this->extractProviderClasses($packagesList);
			
			// Track already instantiated provider classes to prevent duplicates
			// which could occur if multiple packages reference the same provider
			$result = [];
			$instantiatedClasses = [];
			
			// Process each provider and its configuration from the extracted mapping
			foreach ($providersWithConfig as $providerClass => $providerConfig) {
				// Skip providers that have already been instantiated to prevent duplicates
				if (!in_array($providerClass, $instantiatedClasses)) {
					// Attempt to instantiate the provider with its associated configuration
					// The instantiateProvider method handles class validation and error handling
					$provider = $this->instantiateProvider($providerClass, $providerConfig, $debug);
					
					// If the provider was successfully instantiated (exists, implements interface, etc.)
					// add it to our results collection and mark it as instantiated
					if ($provider) {
						$result[] = $provider;
						$instantiatedClasses[] = $providerClass;
					}
				}
			}
			
			// Return all successfully instantiated provider objects
			// These will be used by the application to register services
			return $result;
		}
		
		/**
		 * Creates and validates a provider instance from a class name.
		 * @param string $providerClass Fully qualified provider class name
		 * @param array|null $metadata Provider metadata including config file path and family
		 * @param bool $debug Whether to output error messages to console
		 * @return ProviderInterface|null Provider instance or null if instantiation fails
		 */
		protected function instantiateProvider(string $providerClass, ?array $metadata, bool $debug): ?ProviderInterface {
			// Extract config and family from metadata
			$configFile = $metadata['config'] ?? null;
			$family = $metadata['family'] ?? null;
			
			// Check if the provider class exists in the application namespace
			if (!class_exists($providerClass)) {
				// Log warning and exit early if class doesn't exist
				$this->logDebug($debug, "[WARNING] Provider class not found: {$providerClass}");
				return null;
			}
			
			try {
				// Instantiate provider with no constructor arguments
				$provider = new $providerClass();
				
				// Ensure the provider implements the required interface
				if (!$provider instanceof ProviderInterface) {
					// Log warning and exit if interface not implemented
					$this->logDebug($debug, "[WARNING] Class {$providerClass} does not implement ProviderInterface");
					return null;
				}
				
				// Set the provider family if provided
				if (!empty($family)) {
					$provider->setFamily($family);
				}
				
				// Load and apply configuration if a config file was specified
				if (!empty($configFile)) {
					// Attempt to load the configuration file
					$config = $this->loadConfigFile($configFile);
					
					// Apply configuration to the provider if loading was successful
					if ($config) {
						$provider->setConfig(array_merge($provider->getDefaults(), $config));
					}
				}
				
				// Return successfully created and configured provider
				return $provider;
				
			} catch (\Throwable $e) {
				// Handle any exceptions during instantiation or configuration
				$this->logDebug($debug, "[ERROR] Failed to instantiate provider {$providerClass}: {$e->getMessage()}");
				return null;
			}
		}
		
		/**
		 * Conditionally outputs debug messages to console.
		 * Centralizes debug logging to maintain consistent format.
		 * @param bool $debug Whether debugging is enabled
		 * @param string $message Message to display
		 * @return void
		 */
		protected function logDebug(bool $debug, string $message): void {
			if ($debug) {
				echo $message . PHP_EOL;
			}
		}
		
		/**
		 * Loads a configuration file and returns its contents as an array.
		 * @param string $configFile Relative path to the configuration file from project root
		 * @return array The configuration array from the file, or empty array if the file doesn't exist
		 */
		protected function loadConfigFile(string $configFile): array {
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
	}