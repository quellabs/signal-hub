<?php
	
	namespace Quellabs\DependencyInjection\Provider;
	
	use Quellabs\DependencyInjection\Container;
	
	/**
	 * Abstract base class for service providers with centralized autowiring
	 *
	 * This class serves as the foundation for all service providers in the application.
	 * It implements the ServiceProviderInterface and provides common functionality
	 * that all service providers will inherit.
	 */
	abstract class ServiceProvider implements ServiceProviderInterface {
		
		/**
		 * This property stores the dependency injection container instance
		 * that will be used to register and resolve services.
		 */
		protected Container $container;
		
		/**
		 * ServiceProvider constructor
		 * @param Container $container The dependency injection container
		 */
		public function __construct(Container $container) {
			$this->container = $container;
		}
		
		/**
		 * Defines the services that this provider makes available to the application.
		 * By default, this base provider only registers the 'di' service.
		 * Child classes should override this method to register additional services.
		 * @return string[] Array of service identifiers provided by this provider
		 */
		public function provides(): array {
			return [
				'di'
			];
		}
		
		/**
		 * Determines whether this service provider should be loaded.
		 * The base implementation always returns true, ensuring that
		 * core dependency injection services are always available.
		 * Child classes may override this to conditionally load services.
		 * @return bool True if the provider should be loaded, false otherwise
		 */
		public function shouldLoad(): bool {
			return true;
		}
		
		/**
		 * Creates a new instance of the specified class with the provided dependencies
		 * @param string $className The fully qualified class name to instantiate
		 * @param array $dependencies An array of resolved dependencies to pass to the constructor
		 * @return object The newly created instance of the specified class
		 */
		public function createInstance(string $className, array $dependencies): object {
			// Use the splat operator (...) to unpack the dependency array
			// This allows passing each dependency as a separate argument to the constructor
			// instead of passing the entire array as a single argument
			return new $className(... $dependencies);
		}
		
		/**
		 * This class provides 'di' (dependency injection)
		 * @param string $className
		 * @return bool
		 */
		abstract public function supports(string $className): bool;
	}