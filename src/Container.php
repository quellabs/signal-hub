<?php
	
	namespace Quellabs\DependencyInjection;
	
	use Quellabs\DependencyInjection\Autowiring\Autowirer;
	use Quellabs\DependencyInjection\Discovery\DiscoverBridge;
	use Quellabs\DependencyInjection\Provider\DefaultServiceProvider;
	use Quellabs\DependencyInjection\Provider\ServiceProvider;
	
	/**
	 * Container with centralized autowiring for all services
	 */
	class Container implements ContainerInterface {
		
		/**
		 * Registered service providers
		 * @var ServiceProvider[]
		 */
		protected array $providers = [];
		
		/**
		 * The autowirer instance
		 */
		protected Autowirer $autowire;
		
		/**
		 * Whether to output debug information
		 */
		protected bool $debug;
		
		/**
		 * Base path where the application is installed
		 */
		protected string $basePath;
		
		/**
		 * Dependency resolution stack to detect circular dependencies
		 */
		protected array $resolutionStack = [];
		
		/**
		 * Default service provider for classes with no dedicated provider
		 */
		protected DefaultServiceProvider $defaultProvider;
		
		/**
		 * Container constructor with automatic service discovery
		 * @param string|null $basePath Base path of the application
		 * @param bool $debug Whether to output debug information
		 * @param string $configKey The key to look for in composer.json (default: 'di')
		 */
		public function __construct(?string $basePath = null, bool $debug = false, string $configKey = 'di') {
			$this->autowire = new Autowirer($this);
			$this->debug = $debug;
			$this->basePath = $basePath ?? getcwd();
			
			// Create the default provider
			$this->defaultProvider = new DefaultServiceProvider($this);
			
			// Automatically discover and register service providers
			$this->discoverProviders($configKey);
		}
		
		/**
		 * Register a service provider
		 * @param DiscoverBridge $provider
		 * @return self
		 */
		public function register(DiscoverBridge $provider): self {
			$this->providers[get_class($provider)] = $provider;
			return $this;
		}
		
		/**
		 * Find a provider that supports the given class
		 * @param string $className
		 * @return ServiceProvider
		 */
		public function findProvider(string $className): ServiceProvider {
			foreach ($this->providers as $provider) {
				if ($provider->supports($className)) {
					return $provider;
				}
			}
			
			return $this->defaultProvider;
		}
		
		/**
		 * Get a service with centralized dependency resolution
		 * @param string $className Class name to resolve
		 * @param array $parameters Additional parameters for creation
		 * @return object|null
		 */
		public function get(string $className, array $parameters = []): ?object {
			try {
				// Check for circular dependencies
				if (in_array($className, $this->resolutionStack)) {
					throw new \RuntimeException("Circular dependency detected: " . implode(" -> ", $this->resolutionStack) . " -> {$className}");
				}
				
				// Add to resolution stack
				$this->resolutionStack[] = $className;
				
				// Resolve all constructor dependencies for the class
				// This will analyze the constructor signature and fetch required dependencies
				// Any manually provided parameters will override automatic resolution
				$dependencies = $this->autowire->getMethodArguments($className, '__construct', $parameters);
				
				// Fetch the provider
				$provider = $this->findProvider($className);
				
				// Create a new instance of the class using the resolved dependencies
				// This will invoke the constructor with the correct parameters in the right order
				$instance = $provider->createInstance($className, $dependencies);
				
				// Remove from resolution stack
				array_pop($this->resolutionStack);
				
				// Return the instance
				return $instance;
			} catch (\Throwable $e) {
				// Remove from resolution stack on error
				if (in_array($className, $this->resolutionStack)) {
					while (end($this->resolutionStack) !== $className && !empty($this->resolutionStack)) {
						array_pop($this->resolutionStack);
					}
					array_pop($this->resolutionStack);
				}
				
				if ($this->debug) {
					echo "[ERROR] Failed to resolve {$className}: {$e->getMessage()}\n";
					
					if ($e->getTraceAsString()) {
						echo $e->getTraceAsString() . "\n";
					}
				}
				
				return null;
			}
		}
		
		/**
		 * Call a method with autowired arguments
		 * @param object $instance
		 * @param string $methodName
		 * @param array $parameters
		 * @return mixed
		 */
		public function call(object $instance, string $methodName, array $parameters = []): mixed {
			// Get method arguments with all dependencies resolved
			$args = $this->autowire->getMethodArguments(get_class($instance), $methodName, $parameters);
			
			// Call the method with the resolved arguments
			return $instance->$methodName(...$args);
		}
		
		/**
		 * Discover and register service providers
		 * @param string $configKey The key to look for in composer.json
		 * @return self
		 */
		protected function discoverProviders(string $configKey): self {
			$bridge = new DiscoverBridge($this, $this->basePath, $this->debug);
			$bridge->discoverProviders($configKey);
			return $this;
		}
	}