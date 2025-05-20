<?php
	
	namespace Quellabs\Discover;
	
	use Quellabs\Discover\Scanner\ScannerInterface;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Discover\Config\DiscoveryConfig;
	
	class Discover {
		
		/**
		 * @var array<ScannerInterface>
		 */
		protected array $scanners = [];
		
		/**
		 * @var array<ProviderInterface>
		 */
		protected array $providers = [];
		
		/**
		 * @var DiscoveryConfig
		 */
		protected DiscoveryConfig $config;
		
		/**
		 * @var string|null Cached local json path
		 */
		protected ?string $composerJsonPathCache;
		
		/**
		 * Create a new Discover instance
		 * @param DiscoveryConfig|null $config
		 */
		public function __construct(?DiscoveryConfig $config = null) {
			$this->config = $config ?? new DiscoveryConfig();
			$this->composerJsonPathCache = null;
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
		 * Discover providers using all registered scanners
		 * @return self
		 */
		public function discover(): self {
			foreach ($this->scanners as $scanner) {
				$discoveredProviders = $scanner->scan($this->config);
				
				foreach ($discoveredProviders as $provider) {
					if ($provider instanceof ProviderInterface) {
						$this->addProvider($provider);
					}
				}
			}
			
			return $this;
		}
		
		/**
		 * This method adds a service provider to the internal providers collection,
		 * but only if a provider of the same class doesn't already exist and
		 * the provider indicates it should be loaded.
		 * @param ProviderInterface $provider The service provider instance to add
		 * @return self Returns $this for method chaining
		 */
		public function addProvider(ProviderInterface $provider): self {
			// Get the fully qualified class name of the provider
			$className = get_class($provider);
			
			// Flag to track if this provider class already exists in our collection
			$exists = false;
			
			// Check if a provider of the same class is already registered
			foreach ($this->providers as $existingProvider) {
				if (get_class($existingProvider) === $className) {
					$exists = true;
					break;
				}
			}
			
			// Only add the provider if:
			// 1. It doesn't already exist in our collection
			// 2. The provider itself indicates it should be loaded (via shouldLoad())
			if (!$exists && $provider->shouldLoad()) {
				$this->providers[] = $provider;
			}
			
			// Return $this to allow method chaining
			return $this;
		}
		
		/**
		 * Get all discovered providers
		 * @return array<ProviderInterface>
		 */
		public function getProviders(): array {
			return $this->providers;
		}
		
		/**
		 * Get providers that provide a specific service
		 * @param string $service
		 * @return array<ProviderInterface>
		 */
		public function getProvidersForService(string $service): array {
			return array_filter($this->providers, function (ProviderInterface $provider) use ($service) {
				return in_array($service, $provider->provides());
			});
		}
		
		/**
		 * Clear all discovered providers
		 * @return self
		 */
		public function clearProviders(): self {
			$this->providers = [];
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
		 * Find the path to the local composer.json file
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return string|null Path to composer.json if found, null otherwise
		 */
		public function findComposerJsonPath(?string $startDirectory = null): ?string {
			// Get the result from cache if we can
			if ($this->composerJsonPathCache !== null) {
				return $this->composerJsonPathCache;
			}
			
			// Start from provided directory or current directory if not specified
			$directory = $startDirectory ?? getcwd();
			
			// Ensure we have a valid directory
			if (!$directory || !is_dir($directory)) {
				return null;
			}
			
			// Convert to absolute path if it's not already
			$directory = realpath($directory);
			
			// Keep traversing up until we find composer.json or reach the filesystem root
			while ($directory) {
				$composerPath = $directory . DIRECTORY_SEPARATOR . 'composer.json';
				
				if (file_exists($composerPath)) {
					return $this->composerJsonPathCache = $composerPath;
				}
				
				// Get parent directory
				$parentDir = dirname($directory);
				
				// Stop if we've reached the filesystem root
				if ($parentDir === $directory) {
					break;
				}
				
				$directory = $parentDir;
			}
			
			return null;
		}
	}