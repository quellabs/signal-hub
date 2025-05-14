<?php
	
	namespace Quellabs\Sculpt;
	
	use Quellabs\Sculpt\Console\ConsoleOutput;
	
	/**
	 * Responsible for discovering, registering, and booting service providers
	 * from both the project and installed packages.
	 */
	class ServiceDiscoverer {
		
		/**
		 * Array of registered service provider instances
		 */
		protected array $serviceProviders = [];
		
		/**
		 * Base path where the application is installed
		 */
		private string $basePath;
		
		/**
		 * Console output handler for displaying results to the user
		 */
		protected ConsoleOutput $output;
		
		/**
		 * Reference to the main application instance
		 */
		protected Application $app;
		
		/**
		 * PluginDiscoverer constructor
		 * @param Application $app Reference to the application instance
		 * @param ConsoleOutput $output Handler for writing to console
		 * @param string $basePath Base path of the application
		 */
		public function __construct(Application $app, ConsoleOutput $output, string $basePath) {
			$this->app = $app;
			$this->output = $output;
			$this->basePath = $basePath;
		}
		
		/**
		 * Get all registered service providers
		 * @return array Array of service provider instances
		 */
		public function getProviders(): array {
			return $this->serviceProviders;
		}
		
		/**
		 * Discover and register service providers from installed packages
		 * @return void
		 */
		public function discoverProviders(): void {
			// First discover and register project providers
			$this->discoverProjectProviders();
			
			// Then discover and register package providers
			$this->discoverPackageProviders();
			
			// Finally, boot all registered providers
			$this->bootServiceProviders();
		}
		
		/**
		 * This method reads the project's root composer.json file to find service providers
		 * that are defined directly in the application, rather than in third-party packages.
		 * Project-level providers are registered first to ensure they have priority over
		 * package providers if there are conflicts.
		 * @return void
		 */
		private function discoverProjectProviders(): void {
			// Get the path to the project's root composer.json file
			// This is typically in the root directory of the application
			$projectComposerPath = $this->getProjectComposerPath();
			
			// Exit early if the file doesn't exist or the path couldn't be determined
			// This prevents errors when trying to read a non-existent file
			if (!$projectComposerPath || !file_exists($projectComposerPath)) {
				return;
			}
			
			// Log that we're searching for providers in the project's composer.json
			// This is useful for debugging provider discovery issues
			$this->output->writeLn("Looking for providers in parent project: " . $projectComposerPath);
			
			// Read and parse the composer.json file into a PHP array
			$projectComposer = json_decode(file_get_contents($projectComposerPath), true);
			
			// Exit if the file couldn't be parsed (e.g., invalid JSON)
			if (!$projectComposer) {
				return;
			}
			
			// Extract provider class names from composer.json
			// This uses a dedicated method to handle different provider definition formats
			$projectProviders = $this->extractProviderClasses($projectComposer);
			
			// Register each discovered provider
			// We iterate through each provider class name and register them individually
			foreach ($projectProviders as $providerClass) {
				// The "project" source identifier helps with debugging and logging
				// It distinguishes project providers from package providers
				$this->registerProvider($providerClass, "project");
			}
		}
		
		/**
		 * Parses the composer.json configuration to extract service provider class names.
		 * Supports both single provider and multiple providers formats to maintain
		 * backward compatibility with different package structures.
		 * @param array $composerConfig The parsed composer.json configuration as an associative array
		 * @return array An array of fully qualified class names for service providers
		 */
		private function extractProviderClasses(array $composerConfig): array {
			// Check if the config has defined sculpt providers or sculpt provider
			if (isset($composerConfig['extra']['sculpt']['providers']) && is_array($composerConfig['extra']['sculpt']['providers'])) {
				// Plural format: multiple providers defined in an array under 'providers' key
				// This is the preferred format for packages that need to register multiple providers
				return $composerConfig['extra']['sculpt']['providers'];
			}
			
			if (isset($composerConfig['extra']['sculpt']['provider'])) {
				// Singular format: single provider defined as a string under 'provider' key
				// This is for backward compatibility with older packages or simpler use cases
				// We wrap the single provider in an array to maintain consistent return type
				return [$composerConfig['extra']['sculpt']['provider']];
			}
			
			// Return an empty array
			return [];
		}
		
		/**
		 * This method reads the Composer's installed.json file to find third-party packages
		 * that have registered Sculpt service providers, allowing external packages to
		 * extend the application's functionality.
		 * @return void
		 */
		private function discoverPackageProviders(): void {
			// Get the path to Composer's installed.json file
			// This file contains metadata about all installed dependencies
			$composerFile = $this->getComposerInstalledPath();
			
			// Check if the file exists and is readable
			if (!$composerFile || !file_exists($composerFile)) {
				// If no installed.json file is found and no providers have been registered yet,
				// log a warning to indicate that no providers were found anywhere
				if (empty($this->serviceProviders)) {
					$this->output->warning("No providers found in project or installed packages");
				}
				
				// Exit early since there are no package providers to discover
				return;
			}
			
			// Read the contents of the installed.json file
			$packagesJson = file_get_contents($composerFile);
			
			// Exit if the file couldn't be read
			if (!$packagesJson) {
				return;
			}
			
			// Parse the JSON content into a PHP array
			$packages = json_decode($packagesJson, true);
			
			// Exit if the JSON could not be parsed
			if (!$packages) {
				return;
			}
			
			// Handle both older and newer formats of installed.json
			// Newer Composer versions have packages nested under a 'packages' key
			// Older versions have packages directly at the root level
			$packagesList = $packages['packages'] ?? $packages;
			
			// Get list of already registered provider classes to avoid duplicates
			// This is important to prevent registering the same provider twice if it was
			// already registered from the project's composer.json
			$registeredProviders = array_map(
				fn($provider) => get_class($provider),
				$this->serviceProviders
			);
			
			// Iterate through each installed package to find and register providers
			foreach ($packagesList as $package) {
				// Check if the package has defined a Sculpt service provider
				// Packages without a provider entry are skipped
				if (!isset($package['extra']['sculpt']['provider'])) {
					continue;
				}
				
				// Get the fully qualified class name of the provider
				$providerClass = $package['extra']['sculpt']['provider'];
				
				// Skip this provider if it's already been registered
				// This prevents duplicates and potential conflicts
				if (in_array($providerClass, $registeredProviders)) {
					continue;
				}
				
				// Register this provider, marking it as coming from a package
				// This helps with debugging and error identification
				$this->registerProvider($providerClass, "package");
			}
		}
		
		/**
		 * Register a single service provider
		 * @param string $providerClass The fully qualified class name of the provider
		 * @param string $source The source of the provider (e.g., "project" or "package")
		 * @return void
		 */
		private function registerProvider(string $providerClass, string $source): void {
			// Log the discovery of this provider for debugging purposes
			$this->output->writeLn("Found {$source} provider: $providerClass");
			
			// Check if the provider class actually exists and can be instantiated
			if (class_exists($providerClass)) {
				// Instantiate the provider, passing the current application instance
				// This allows the provider to access application services
				$provider = new $providerClass($this->app);
				
				// Add the provider instance to the internal collection of registered providers
				// This collection will be used later during the boot phase
				$this->serviceProviders[] = $provider;
				
				// Call the register method on the provider
				// This allows the provider to register bindings in the service container
				// but should not perform any actions that require other services to be available
				$provider->register($this->app);
			} else {
				// If the class doesn't exist, log a warning but continue execution
				// This prevents one bad provider from breaking the entire application
				$this->output->warning("Provider class not found: $providerClass");
			}
		}
		
		/**
		 * Boot all registered service providers
		 * @return void
		 */
		private function bootServiceProviders(): void {
			// Iterate through all providers that were successfully registered
			foreach ($this->serviceProviders as $provider) {
				// Call the boot method on each provider
				// At this point, all services should be registered and available for use
				// Providers can now perform actions that depend on other services
				$provider->boot($this->app);
			}
		}
		
		/**
		 * Determines the path to the project's composer.json file.
		 * @return string|null The full path to composer.json or null if not found
		 */
		protected function getProjectComposerPath(): ?string {
			/**
			 * This method handles two scenarios:
			 * 1. Sculpt installed as a dependency in vendor/quellabs/sculpt
			 * 2. Sculpt running directly in development mode
			 */
			
			// Determine the composer.json path based on installation context
			if (!str_contains($this->basePath, '/vendor/')) {
				// Running directly in development mode
				$composerPath = $this->basePath . '/composer.json';
			} else {
				// Running as a dependency - find project root
				$composerPath = $this->findComposerPathInDependencyMode();
			}
			
			// Verify that the composer.json file actually exists
			if (file_exists($composerPath)) {
				return $composerPath;
			}
			
			return null;
		}
		
		/**
		 * Finds the composer.json path when running as a package dependency.
		 * This traverses up from the current path to locate the project root
		 * where composer.json should be located.
		 * @return string The expected path to composer.json
		 */
		protected function findComposerPathInDependencyMode(): string {
			// Keep going up the directory structure until we find the vendor directory itself
			$path = $this->basePath;
			while ($path !== '/' && basename(dirname($path)) !== 'vendor') {
				$path = dirname($path);
			}
			
			// Once we've found the vendor directory, go up two levels to reach project root:
			// - One level up: from package directory to vendor directory
			// - Second level up: from vendor directory to project root
			$projectRoot = dirname($path, 2);
			
			// The composer.json should be located directly in the project root
			return $projectRoot . '/composer.json';
		}
		
		/**
		 * Get the path to the Composer's installed.json file
		 * Handles both direct usage and installation as a dependency
		 * @return string|null Path to the installed.json file or null if not found
		 */
		protected function getComposerInstalledPath(): ?string {
			// Possible locations of the installed.json file
			$possiblePaths = [
				// When running directly (development mode)
				$this->basePath . '/vendor/composer/installed.json',
				
				// When installed as a dependency (package inside vendor dir)
				dirname($this->basePath, 2) . '/composer/installed.json',
				
				// When running in a project that uses the package
				dirname($this->basePath, 3) . '/composer/installed.json'
			];
			
			// Return the first path that exists
			foreach ($possiblePaths as $path) {
				if (file_exists($path)) {
					return $path;
				}
			}
			
			return null;
		}
	}