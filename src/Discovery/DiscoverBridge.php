<?php
	
	namespace Quellabs\DependencyInjection\Discovery;
	
	use Quellabs\DependencyInjection\Container;
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Discover;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Discover\Scanner\ComposerScanner;
	
	/**
	 * Uses the Quellabs\Discover library to find service providers,
	 * then instantiates them through the DI container
	 */
	class DiscoverBridge {
		
		/**
		 * @var Container
		 */
		protected Container $container;
		
		/**
		 * @var bool
		 */
		protected bool $debug;
		
		/**
		 * @var string
		 */
		protected string $basePath;
		
		/**
		 * @param Container $container
		 * @param string|null $basePath
		 * @param bool $debug
		 */
		public function __construct(Container $container, ?string $basePath = null, bool $debug = false) {
			$this->container = $container;
			$this->debug = $debug;
			$this->basePath = $basePath ?? getcwd();
		}
		
		/**
		 * Discover provider classes and register them with the container
		 * @param string $configKey The key to look for in composer.json
		 * @return self
		 */
		public function discoverProviders(string $configKey = 'di'): self {
			// Configure discovery
			$config = new DiscoveryConfig([
				'debug'    => $this->debug,
				'autoload' => false // Important: Don't autoload in Discover
			]);
			
			// Create discover instance with scanners
			$discover = new Discover($config);
			$discover->addScanner(new ComposerScanner($configKey, $this->basePath));
			
			// Run discovery process to get provider classes
			$discover->discover();
			
			// Get all discovered provider instances
			$discoveredProviders = $discover->getProviders();
			
			// For each discovered provider, instantiate with the container and register
			foreach ($discoveredProviders as $discoveredProvider) {
				$this->registerDiscoveredProvider($discoveredProvider);
			}
			
			return $this;
		}
		
		/**
		 * Register a discovered provider with the container
		 * @param ProviderInterface $discoveredProvider
		 * @return void
		 */
		protected function registerDiscoveredProvider(ProviderInterface $discoveredProvider): void {
			// Get the provider class
			$providerClass = get_class($discoveredProvider);
			
			// Check if it also implements the DI ServiceProviderInterface
			if (!is_subclass_of($providerClass, \Quellabs\DependencyInjection\Provider\ServiceProviderInterface::class)) {
				if ($this->debug) {
					echo "[WARNING] Discovered provider {$providerClass} does not implement ServiceProviderInterface\n";
				}
				return;
			}
			
			try {
				// Create a new instance through the container
				$serviceProvider = $this->container->get($providerClass);
				
				// Register the provider with the container
				$this->container->register($serviceProvider);
				
				if ($this->debug) {
					echo "[INFO] Registered provider {$providerClass}\n";
				}
			} catch (\Throwable $e) {
				if ($this->debug) {
					echo "[ERROR] Failed to register provider {$providerClass}: {$e->getMessage()}\n";
				}
			}
		}
	}