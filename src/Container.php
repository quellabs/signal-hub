<?php
	
	namespace Quellabs\DependencyInjection;
	
	use Quellabs\DependencyInjection\Autowiring\Autowirer;
	use Quellabs\DependencyInjection\Provider\DefaultServiceProvider;
	use Quellabs\DependencyInjection\Provider\ServiceProvider;
	use Quellabs\Discover\Discover;
	use Quellabs\Discover\Scanner\ComposerScanner;
	
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
		 * Service discovery
		 * @var Discover
		 */
		private Discover $discovery;
		
		/**
		 * @var array|string[]
		 */
		private array $defaultContext = [];
		
		/**
		 * Container constructor with automatic service discovery
		 * @param string|null $basePath Base path of the application
		 * @param string $familyName The key to look for in composer.json (default: 'di')
		 */
		public function __construct(?string $basePath = null, string $familyName = 'di') {
			$this->autowire = new Autowirer($this);
			$this->basePath = $basePath ?? getcwd();
			
			// Create the default provider
			$this->defaultProvider = new DefaultServiceProvider();
			
			// Create the service discoverer
			$this->discovery = new Discover();
			$this->discovery->addScanner(new ComposerScanner($familyName));
			
			// Automatically discover and register service providers
			$this->discover();
		}
		
		/**
		 * Register a service provider
		 * @param ServiceProvider $provider
		 * @return self
		 */
		public function register(ServiceProvider $provider): self {
			$this->providers[get_class($provider)] = $provider;
			return $this;
		}
		
		/**
		 * Set context for subsequent get() calls
		 * @param string|array $context
		 * @return $this
		 */
		public function for(string|array $context): self {
			$clone = clone $this;
			
			if (is_string($context)) {
				$clone->defaultContext = ['provider' => $context];
			} else {
				$clone->defaultContext = $context;
			}
			
			return $clone;
		}
		
		/**
		 * Find a provider that supports the given class
		 * @param string $className
		 * @return ServiceProvider
		 */
		public function findProvider(string $className): ServiceProvider {
			foreach ($this->providers as $provider) {
				if ($provider->supports($className, $this->defaultContext)) {
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
				
				return null;
			}
		}
		
		/**
		 * Invoke a method with autowired arguments
		 * @param object $instance
		 * @param string $methodName
		 * @param array $parameters
		 * @return mixed
		 */
		public function invoke(object $instance, string $methodName, array $parameters = []): mixed {
			// Get method arguments with all dependencies resolved
			$args = $this->autowire->getMethodArguments(get_class($instance), $methodName, $parameters);
			
			// Call the method with the resolved arguments
			return $instance->$methodName(...$args);
		}
		
		/**
		 * Discover and register service providers
		 * @return self
		 */
		protected function discover(): self {
			// Register each discovered provider with the container
			foreach ($this->discovery->getProviders() as $provider) {
				if ($provider instanceof Provider\ServiceProviderInterface) {
					$this->register($provider);
				}
			}
			
			return $this;
		}
	}