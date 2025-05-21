<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Discover;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Discover\Utilities\PSR4;
	
	/**
	 * Scans composer.json files to discover service providers
	 */
	class ComposerScanner implements ScannerInterface {
		
		/**
		 * @var Discover Main container
		 */
		protected Discover $discover;
		
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
		 * @param Discover $discover Discovery container
		 * @param string $configKey The key to look for in composer.json (e.g., 'discover')
		 * @param string|null $basePath
		 */
		public function __construct(Discover $discover, string $configKey = 'discover', ?string $basePath = null) {
			$this->discover = $discover;
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
			
			// Extract provider class names from the composer.json structure
			// (This calls a separate method that handles the specific extraction logic)
			$providerClasses = $this->extractProviderClasses($composer);
			
			// Initialize an empty array to store discovered provider instances
			$providers = [];
			
			// Attempt to instantiate each discovered provider class
			foreach ($providerClasses as $providerClass) {
				// Try to create an instance of the provider class
				$provider = $this->instantiateProvider($providerClass, $debug);
				
				// If successfully instantiated, add to our results
				if ($provider) {
					$providers[] = $provider;
				}
			}
			
			// Return all successfully instantiated provider objects from the project
			return $providers;
		}
		
		/**
		 * This function scans the Composer installed.json file to find packages that
		 * declare provider classes in their extra configuration. It supports both
		 * singular 'provider' and plural 'providers' declaration formats, instantiates
		 * each valid provider, and returns an array of working provider instances.
		 * @param bool $debug Whether to output debug messages when errors occur during provider instantiation
		 * @return array<ProviderInterface> Array of successfully instantiated provider objects
		 */
		protected function discoverPackageProviders(bool $debug): array {
			// Initialize an empty array to store discovered provider instances
			$providers = [];
			
			// Get the full filesystem path to Composer's installed.json file
			$installedPath = $this->utilities->getComposerJsonFilePath();
			
			// If the path couldn't be determined or the file doesn't exist, return empty array
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
			
			// Track already instantiated provider classes to prevent duplicates
			$instantiatedClasses = [];
			
			foreach ($packagesList as $package) {
				// First check for providers in plural format (array of provider classes)
				if (isset($package['extra'][$this->configKey]['providers']) && is_array($package['extra'][$this->configKey]['providers'])) {
					// Process each provider class in the array
					foreach ($package['extra'][$this->configKey]['providers'] as $providerClass) {
						// Skip if we've already instantiated this provider class
						if (!in_array($providerClass, $instantiatedClasses)) {
							// Attempt to instantiate the provider
							$provider = $this->instantiateProvider($providerClass, $debug);
							
							// If successfully instantiated, add to our results
							if ($provider) {
								$providers[] = $provider;
								$instantiatedClasses[] = $providerClass;
							}
						}
					}
					
					// Skip to next package since we've processed the providers for this one
					continue;
				}
				
				// Check for provider in singular format (single provider class)
				if (isset($package['extra'][$this->configKey]['provider'])) {
					// Get the provider class name
					$providerClass = $package['extra'][$this->configKey]['provider'];
					
					// Skip if we've already instantiated this provider class
					if (!in_array($providerClass, $instantiatedClasses)) {
						// Attempt to instantiate the provider
						$provider = $this->instantiateProvider($providerClass, $debug);
						
						// If successfully instantiated, add to our results
						if ($provider) {
							$providers[] = $provider;
							$instantiatedClasses[] = $providerClass;
						}
					}
				}
			}
			
			// Return all successfully instantiated provider objects
			return $providers;
		}
		
		/**
		 * Extract provider classes from composer.json config
		 * @param array $composerConfig
		 * @return array<string>
		 */
		protected function extractProviderClasses(array $composerConfig): array {
			// Check for plural format (providers array)
			if (isset($composerConfig['extra'][$this->configKey]['providers']) && is_array($composerConfig['extra'][$this->configKey]['providers'])) {
				return $composerConfig['extra'][$this->configKey]['providers'];
			}
			
			// Check for singular format (single provider)
			if (isset($composerConfig['extra'][$this->configKey]['provider'])) {
				return [$composerConfig['extra'][$this->configKey]['provider']];
			}
			
			return [];
		}
		
		/**
		 * This function attempts to create an instance of the specified provider class,
		 * checks if it implements the ProviderInterface, and handles any errors that occur
		 * during instantiation.
		 * @param string $providerClass The fully qualified class name of the provider to instantiate
		 * @param bool $debug Whether to output debug messages to the console when errors occur
		 * @return ProviderInterface|null Returns an instance of the provider if successful,
		 *                               or null if the class doesn't exist, doesn't implement
		 *                               the interface, or throws an exception during instantiation
		 */
		protected function instantiateProvider(string $providerClass, bool $debug): ?ProviderInterface {
			// Check if the specified provider class exists in the application
			if (!class_exists($providerClass)) {
				// Display a warning if debug mode is enabled
				if ($debug) {
					echo "[WARNING] Provider class not found: {$providerClass}\n";
				}
				
				return null;
			}
			
			try {
				// Attempt to instantiate the provider class with no constructor arguments
				$provider = new $providerClass();
				
				// Verify that the instantiated class implements the required interface
				if (!$provider instanceof ProviderInterface) {
					// Display a warning about interface mismatch if debug mode is enabled
					if ($debug) {
						echo "[WARNING] Class {$providerClass} does not implement ProviderInterface\n";
					}
					
					return null;
				}
				
				// Return the successfully instantiated provider
				return $provider;
				
			} catch (\Throwable $e) {
				// Catch any exceptions thrown during instantiation (constructor errors, etc.)
				if ($debug) {
					// If debug mode is on, output detailed error information
					echo "[ERROR] Failed to instantiate provider {$providerClass}: {$e->getMessage()}\n";
				}
				
				// Return null to indicate instantiation failure
				return null;
			}
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