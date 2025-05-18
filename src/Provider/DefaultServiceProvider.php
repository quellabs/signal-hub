<?php
	
	namespace Quellabs\DependencyInjection\Provider;
	
	class DefaultServiceProvider extends ServiceProvider {
		
		/**
		 * Store instances to ensure singleton behavior
		 * @var array<string, object>
		 */
		protected array $instances = [];
		
		/**
		 * Supports all classes as a fallback
		 */
		public function supports(string $className): bool {
			return true;
		}
		
		/**
		 * Create instance with basic instantiation using singleton pattern
		 * @param string $className The class to instantiate
		 * @param array $dependencies Pre-resolved constructor dependencies
		 * @return object
		 */
		public function createInstance(string $className, array $dependencies): object {
			// Check if the class exists
			if (!class_exists($className)) {
				throw new \RuntimeException("Class '$className' does not exist");
			}
			
			// If the instance already exists, return it
			if (isset($this->instances[$className])) {
				return $this->instances[$className];
			}
			
			// Create a new instance using the parent method
			$instance = parent::createInstance($className, $dependencies);
			
			// Store the instance for future use
			$this->instances[$className] = $instance;
			
			// Return the instance
			return $instance;
		}
	}