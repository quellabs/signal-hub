<?php
    
    namespace Quellabs\ObjectQuel\EntityManager\Persistence;
    
	use Quellabs\ObjectQuel\EntityManager\Core\UnitOfWork;
	
	class PersisterBase {
		
		protected UnitOfWork $unitOfWork;
		
		/**
		 * PersisterBase constructor
		 * @param UnitOfWork $unitOfWork
		 */
		public function __construct(UnitOfWork $unitOfWork) {
			$this->unitOfWork = $unitOfWork;
		}
		
		/**
		 * Helperfunctie om acties uit te voeren voor of na het persisten van entiteiten.
		 * @param mixed $entity De entiteit die behandeld moet worden.
		 * @param string $annotationClass De naam van de annotatieklasse die gecontroleerd moet worden.
		 */
		protected function handlePersist(mixed $entity, string $annotationClass): void {
			try {
				$reflectionClass = new \ReflectionClass($entity);
				$methods = $reflectionClass->getMethods();
				$entityStore = $this->unitOfWork->getEntityStore();
				
				foreach ($methods as $method) {
					$methodName = $method->getName();
					$annotations = $entityStore->getAnnotationReader()->getMethodAnnotations($entity, $methodName);
					
					if (empty($annotations)) {
						continue;
					}
					
					foreach ($annotations as $annotation) {
						if ($annotation instanceof $annotationClass) {
							$entity->$methodName();
						}
					}
				}
			} catch (\ReflectionException $e) {
			}
		}
	}