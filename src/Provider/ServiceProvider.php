<?php
	
	namespace Quellabs\DependencyInjection\Provider;
	
	use Quellabs\DependencyInjection\Container;
	
	/**
	 * Abstract base class for service providers with centralized autowiring
	 */
	abstract class ServiceProvider implements ServiceProviderInterface {
		
		/**
		 * Reference to the container
		 */
		protected Container $container;
		
		/**
		 * ServiceProvider constructor
		 * @param Container $container
		 */
		public function __construct(Container $container) {
			$this->container = $container;
		}
		
		/**
		 * This class provides 'di' (dependency injection)
		 * @return string[]
		 */
		public function provides(): array {
			return [
				'di'
			];
		}
		
		/**
		 * Always load this provider
		 * @return bool
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