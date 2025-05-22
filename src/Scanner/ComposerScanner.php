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
		 * @param string $familyName The family name for providers
		 * @param string|null $basePath
		 */
		public function __construct(string $familyName = 'default', ?string $basePath = null) {
			$this->configKey = $familyName;
			$this->basePath = $basePath ?? getcwd();
			$this->utilities = new PSR4();
		}
		
		/**
		 * This is the main entry point for the provider discovery process.
		 * @param DiscoveryConfig $config Configuration object containing discovery settings
		 * @return array<ProviderInterface> Combined array of all discovered and instantiated providers
		 */
		public function scan(DiscoveryConfig $config): array {
			// Extract debug mode setting from the configuration
			$debug = $config->isDebugEnabled();
			
			// Discover providers declared in the project's own composer.json
			$providers = $this->discoverProjectProviders($debug);
			
			// Discover providers declared in installed dependency packages
			$packageProviders = $this->discoverPackageProviders($debug);
			
			// Return the complete collection of discovered providers from all sources
			return array_merge($providers, $packageProviders);
		}
		
		/**
		 * This function reads the project's main composer.json file to find provider classes
		 * that are directly declared in the project (as opposed to in dependencies).
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
			$this->logDebug($debug, "[INFO] Looking for providers in family '{$this->configKey}' from project: {$composerPath}");
			
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
		 * This function scans the Composer installed.json file to find packages that
		 * declare provider classes in their extra configuration.
		 * @param bool $debug Whether to output debug messages when errors occur during provider instantiation
		 * @return array<ProviderInterface> Array of successfully instantiated provider objects
		 */
		protected function discoverPackageProviders(bool $debug): array {
			// Initialize an empty array to store discovered provider instances
			$providers = [];
			
			// Get the full filesystem path to Composer's installed.json file
			$installedPath = $this->utilities->getComposerInstalledFilePath();
			
			// If the path couldn't be determined or the file doesn't exist, return an empty array
			if ($installedPath === null || !file_exists($installedPath)) {
				return $providers;
			}
			
			// Log package discovery attempt if debug mode is enabled
			$this->logDebug($debug, "[INFO] Looking for providers in family '{$this->configKey}' from installed packages");
			
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
			
			// Track already instantiated provider classes to prevent duplicates
			$instantiatedClasses = [];
			
			// Process each package to find providers
			foreach ($packagesList as $package) {
				// Skip packages without extra configuration or the discover section
				if (!isset($package['extra']['discover'])) {
					continue;
				}
				
				// Extract providers from this package
				$packageProviders = $this->extractProviderClasses($package);
				
				// Process each provider in this package
				foreach ($packageProviders as $providerClass => $providerConfig) {
					// Skip providers that have already been instantiated to prevent duplicates
					if (in_array($providerClass, $instantiatedClasses)) {
						continue;
					}
					
					// Attempt to instantiate the provider
					$provider = $this->instantiateProvider($providerClass, $providerConfig, $debug);
					
					// If the provider was successfully instantiated, add it to our results
					if ($provider) {
						$providers[] = $provider;
						$instantiatedClasses[] = $providerClass;
					}
				}
			}
			
			// Return all discovered and instantiated providers from packages
			return $providers;
		}
		
		/**
		 * Extracts service provider classes from composer configuration.
		 * Supports both single provider and multiple providers formats.
		 * Now expects the structure: extra.discover.{configKey}
		 * @param array $composerConfig The parsed composer.json configuration array
		 * @return array An associative array of provider classes and their configuration
		 */
		protected function extractProviderClasses(array $composerConfig): array {
			// Access the discover section within composer.json's 'extra' section
			$discoverSection = $composerConfig['extra']['discover'] ?? [];
			
			// Verify that the discover section is a valid array
			if (!is_array($discoverSection)) {
				return [];
			}
			
			// Access our specific configuration section within the discover section
			// The $this->configKey determines which specific section we're targeting
			// (e.g., 'default', 'laravel', 'symfony', etc.)
			$configSection = $discoverSection[$this->configKey] ?? [];
			
			// Verify that our configuration section is a valid array
			// If not, return empty result immediately
			if (!is_array($configSection)) {
				return [];
			}
			
			// Get providers from both formats
			$multipleProviders = $this->extractMultipleProviders($configSection);
			$singularProvider = $this->extractSingularProvider($configSection);
			
			// Return the complete mapping of provider classes to their configurations
			// Keys are fully qualified class names, values are null or configuration arrays
			return array_merge($multipleProviders, $singularProvider);
		}
		
		/**
		 * Extract providers from the 'providers' array format
		 * Handles multiple providers defined in a single configuration
		 * @param array $config The configuration section
		 * @return array The extracted providers and their configurations
		 */
		protected function extractMultipleProviders(array $config): array {
			// Initialize the result array
			$result = [];
			
			// Get the provider array or default to an empty array if not set
			$providers = $config['providers'] ?? [];
			
			// Ensure we have a valid array to iterate over
			if (!is_array($providers)) {
				return $result;
			}
			
			// Process each provider in the array
			foreach ($providers as $provider) {
				if (is_string($provider)) {
					// Handle a simple string format: ProviderClass::class
					// No configuration is provided for these providers
					$result[$provider] = null;
				} elseif (is_array($provider) && isset($provider['class'])) {
					// Handle array format: ['class' => ProviderClass::class, 'config' => [...]]
					// Configuration is optional and stored in the 'config' key
					$result[$provider['class']] = $provider['config'] ?? null;
				}
			}
			
			return $result;
		}
		
		/**
		 * Extract provider from the singular 'provider' format
		 * @param array $config The configuration section
		 * @return array The extracted provider and its configuration
		 */
		protected function extractSingularProvider(array $config): array {
			// Exit early if no provider is defined in this configuration section
			if (!isset($config['provider'])) {
				return [];
			}
			
			// Initialize the result array
			$result = [];
			
			// Extract the provider and its configuration
			$provider = $config['provider'];
			
			// Use null coalescing operator to get config or null if not set
			$providerConfig = $config['config'] ?? null;
			
			if (is_string($provider)) {
				// Handle string format: 'provider' => 'Namespace\ProviderClass'
				// In this case, configuration is defined separately as 'config' => [...]
				$result[$provider] = $providerConfig;
			} elseif (is_array($provider) && isset($provider['class'])) {
				// Handle array format: 'provider' => ['class' => 'Namespace\ProviderClass', 'config' => [...]]
				// Configuration can be defined inline in the provider array or in the separate 'config' key
				// Inline config takes precedence over separate config if both exist
				$result[$provider['class']] = $provider['config'] ?? $providerConfig;
			}
			
			return $result;
		}
		
		/**
		 * Creates and validates a provider instance from a class name.
		 * @param string $providerClass Fully qualified provider class name
		 * @param string|null $configFile Path to a configuration file (optional)
		 * @param bool $debug Whether to output error messages to console
		 * @return ProviderInterface|null Provider instance or null if instantiation fails
		 */
		protected function instantiateProvider(string $providerClass, ?string $configFile, bool $debug): ?ProviderInterface {
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
				
				// Set the family to the scanner's configKey
				$provider->setFamily($this->configKey);
				
				// Load and apply configuration
				if (!empty($configFile)) {
					// Attempt to load the configuration file
					$config = $this->loadConfigFile($configFile);
					
					// Apply configuration to the provider if loading was successful
					$provider->setConfig(array_merge($provider->getDefaults(), $config));
				} else {
					$provider->setConfig($provider->getDefaults());
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