<?php
	
	namespace Quellabs\ObjectQuel\Persistence;
	
	use Quellabs\ObjectQuel\UnitOfWork;
	
	/**
	 * Base class for all persisters in the ObjectQuel framework
	 * A persister is responsible for saving entities to a data store
	 * This base class provides common functionality for all specific persister implementations
	 */
	class PersisterBase {
		
		/**
		 * Reference to the UnitOfWork that manages the persistence operations
		 * The UnitOfWork tracks changes to entities and coordinates their persistence
		 */
		protected UnitOfWork $unitOfWork;
		
		/**
		 * PersisterBase constructor
		 * Initializes a new persister with the provided UnitOfWork instance
		 * @param UnitOfWork $unitOfWork The UnitOfWork that will coordinate persistence operations
		 */
		public function __construct(UnitOfWork $unitOfWork) {
			$this->unitOfWork = $unitOfWork;
		}
		
		/**
		 * Helper function to execute actions before or after persisting entities
		 * This method scans the entity for methods with specific annotations and executes them
		 * @param mixed $entity The entity that needs to be processed
		 * @param string $annotationClass The name of the annotation class to check for
		 *                                (e.g., PrePersist, PostPersist)
		 */
		protected function handlePersist(mixed $entity, string $annotationClass): void {
			try {
				// Create a reflection instance to examine the entity's structure
				$reflectionClass = new \ReflectionClass($entity);
				
				// Get all methods defined in the entity class
				$methods = $reflectionClass->getMethods();
				
				// Get the entity store from the unit of work for annotation reading
				$entityStore = $this->unitOfWork->getEntityStore();
				
				// Iterate through each method to check for the specified annotation
				foreach ($methods as $method) {
					$methodName = $method->getName();
					
					// Read all annotations for the current method using the entity store's annotation reader
					$annotations = $entityStore->getAnnotationReader()->getMethodAnnotations($entity, $methodName);
					
					// Skip methods without any annotations
					if (empty($annotations)) {
						continue;
					}
					
					// Check each annotation to see if it matches the requested annotation class
					foreach ($annotations as $annotation) {
						// If the annotation is an instance of the specified class (e.g., PrePersist)
						if ($annotation instanceof $annotationClass) {
							// Execute the annotated method on the entity
							// This allows for custom logic to run at specific points in the persistence lifecycle
							$entity->$methodName();
						}
					}
				}
			} catch (\ReflectionException $e) {
				// Silently handle any reflection exceptions
				// This empty catch block could be improved by adding logging or other error handling
			}
		}
	}