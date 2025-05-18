<?php
	
	namespace Quellabs\DependencyInjection\Discovery;
	
	use Quellabs\DependencyInjection\Container;
	use Quellabs\DependencyInjection\Provider\ServiceProviderInterface;
	
	/**
	 * Discovers and registers service providers from composer.json files
	 */
	class ServiceDiscoverer {
		
		/**
		 * The container instance
		 */
		protected Container $container;
		
		/**
		 * Base path where the application is installed
		 */
		private string $basePath;
		
		/**
		 * Whether to output debug information
		 */
		protected bool $debug;
		
		/**
		 * Registered service providers
		 * @var ServiceProviderInterface[]
		 */
		protected array $serviceProviders = [];
		
		/**
		 * ServiceDiscoverer constructor
		 * @param Container $container
		 * @param string|null $basePath
		 * @param bool $debug
		 */
		public function __construct(Container $container, ?string $basePath = null, bool $debug = false) {
			$this->container = $container;
			$this->basePath = $basePath ?? getcwd();
			$this->debug = $debug;
		}
		
		/**
		 * Get all registered service providers
		 * @return ServiceProviderInterface[]
		 */
		public function getProviders(): array {
			return $this->serviceProviders;
		}
		
		/**
		 * Discover and register service providers
		 * @param string $configKey The key to look for in composer.json (e.g., 'di')
		 * @return self
		 */
		public function discover(string $configKey = 'di'): self {
			// Discover project providers
			$this->discoverProjectProviders($configKey);
			
			// Discover package providers
			$this->discoverPackageProviders($configKey);
			
			return $this;
		}
		
		/**
		 * Discover providers from the project's composer.json
		 * @param string $configKey
		 * @return void
		 */
		protected function discoverProjectProviders(string $configKey): void {
			// Get the path to composer.json
			$composerPath = $this->getProjectComposerPath();
			
			if (!$composerPath || !file_exists($composerPath)) {
				return;
			}
			
			if ($this->debug) {
				echo "[INFO] Looking for providers in project: {$composerPath}\n";
			}
			
			// Parse composer.json
			$composer = json_decode(file_get_contents($composerPath), true);
			
			if (!$composer) {
				return;
			}
			
			// Extract provider classes
			$providers = $this->extractProviderClasses($composer, $configKey);
			
			// Register each provider
			foreach ($providers as $providerClass) {
				$this->registerProvider($providerClass);
			}
		}
		
		/**
		 * Discover providers from installed packages
		 * @param string $configKey
		 * @return void
		 */
		protected function discoverPackageProviders(string $configKey): void {
			// Get path to installed.json
			$installedPath = $this->getComposerInstalledPath();
			
			if (!$installedPath || !file_exists($installedPath)) {
				if (empty($this->serviceProviders) && $this->debug) {
					echo "[WARNING] No providers found in project or packages\n";
				}
				
				return;
			}
			
			// Parse installed.json
			$packages = json_decode(file_get_contents($installedPath), true);
			
			if (!$packages) {
				return;
			}
			
			// Handle both formats of installed.json
			$packagesList = $packages['packages'] ?? $packages;
			
			// Get list of already registered providers
			$registeredProviders = array_map(
				fn($provider) => get_class($provider),
				$this->serviceProviders
			);
			
			// Check each package for providers
			foreach ($packagesList as $package) {
				// Check for providers in plural format
				if (isset($package['extra'][$configKey]['providers']) && is_array($package['extra'][$configKey]['providers'])) {
					foreach ($package['extra'][$configKey]['providers'] as $providerClass) {
						if (!in_array($providerClass, $registeredProviders)) {
							$this->registerProvider($providerClass, 'package');
						}
					}
					
					continue;
				}
				
				// Check for provider in singular format
				if (isset($package['extra'][$configKey]['provider'])) {
					$providerClass = $package['extra'][$configKey]['provider'];
					
					if (!in_array($providerClass, $registeredProviders)) {
						$this->registerProvider($providerClass, 'package');
					}
				}
			}
		}
		
		/**
		 * Extract provider classes from composer.json config
		 * @param array $composerConfig
		 * @param string $configKey
		 * @return array
		 */
		protected function extractProviderClasses(array $composerConfig, string $configKey): array {
			// Check for plural format (providers array)
			if (isset($composerConfig['extra'][$configKey]['providers']) && is_array($composerConfig['extra'][$configKey]['providers'])) {
				return $composerConfig['extra'][$configKey]['providers'];
			}
			
			// Check for singular format (single provider)
			if (isset($composerConfig['extra'][$configKey]['provider'])) {
				return [$composerConfig['extra'][$configKey]['provider']];
			}
			
			return [];
		}
		
		/**
		 * Register a provider with the container
		 * @param string $providerClass
		 * @return void
		 */
		protected function registerProvider(string $providerClass): void {
			// Check if class exists
			if (!class_exists($providerClass)) {
				if ($this->debug) {
					echo "[WARNING] Provider class not found: {$providerClass}\n";
				}

				return;
			}
			
			try {
				// Instantiate the provider
				$provider = new $providerClass();
				
				// Check if it implements the interface
				if (!$provider instanceof ServiceProviderInterface) {
					if ($this->debug) {
						echo "[WARNING] Class {$providerClass} does not implement ServiceProviderInterface\n";
					}
					
					return;
				}
				
				// Store the provider in ServiceDiscoverer
				$this->serviceProviders[] = $provider;
				
				// Store the provider in the container
				$this->container->register($provider);
				
				// The Container's register method should call $provider->register($this)
				// So we don't need this line anymore:
				// $provider->register($this->container);
				
			} catch (\Throwable $e) {
				if ($this->debug) {
					echo "[ERROR] Failed to register provider {$providerClass}: {$e->getMessage()}\n";
				}
			}
		}
		
		/**
		 * Get the path to the project's composer.json
		 * @return string|null
		 */
		protected function getProjectComposerPath(): ?string {
			// Check if running as standalone or as dependency
			if (!str_contains($this->basePath, '/vendor/')) {
				$composerPath = $this->basePath . '/composer.json'; // Running directly
			} else {
				$composerPath = $this->findComposerPathInDependencyMode(); // Running as a dependency
			}
			
			return file_exists($composerPath) ? $composerPath : null;
		}
		
		/**
		 * Find composer.json when running as a dependency
		 * @return string
		 */
		protected function findComposerPathInDependencyMode(): string {
			// Navigate up to find vendor directory
			$path = $this->basePath;
			
			while ($path !== '/' && basename(dirname($path)) !== 'vendor') {
				$path = dirname($path);
			}
			
			// Go up two levels to reach project root
			$projectRoot = dirname($path, 2);
			return $projectRoot . '/composer.json';
		}
		
		/**
		 * Get the path to installed.json
		 * @return string|null
		 */
		protected function getComposerInstalledPath(): ?string {
			// Possible locations of installed.json
			$possiblePaths = [
				// When running directly
				$this->basePath . '/vendor/composer/installed.json',
				
				// When installed as a dependency
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