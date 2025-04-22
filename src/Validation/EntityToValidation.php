<?php
	
	namespace Quellabs\ObjectQuel\Validation;
	
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\Date;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\Email;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\Length;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\NotBlank;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\RegExp;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\Type;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\ValueIn;
	use Quellabs\ObjectQuel\AnnotationsReader\AnnotationsReader;
	use Quellabs\ObjectQuel\Kernel\Kernel;
	use Quellabs\ObjectQuel\Kernel\ReflectionHandler;
	
	class EntityToValidation {
		
		private AnnotationsReader $annotationReader;
		private ReflectionHandler $reflectionHandler;
		
		/**
		 * EntityToValidation constructor
		 * @param Kernel $kernel
		 */
		public function __construct(Kernel $kernel) {
			$this->reflectionHandler = $kernel->getService(ReflectionHandler::class);
			$this->annotationReader = $kernel->getService(AnnotationsReader::class);
		}
		
		/**
		 * Converteert entity-annotaties naar validatieregels.
		 * Deze functie neemt een entity-object en converteert de annotaties van zijn eigenschappen
		 * naar corresponderende validatieregels. Het gebruikt een vooraf gedefinieerde mapping
		 * tussen annotatieklassen en validatieregelklassen.
		 * @param object $entity Het entity-object waarvan de annotaties geconverteerd moeten worden
		 * @return array Een array met validatieregels voor elke eigenschap van het entity
		 */
		public function convert(object $entity): array {
			// Mapping van annotatieklassen naar validatieregelklassen
			$annotationMap = [
				Date::class          => \Quellabs\ObjectQuel\Validation\Rules\Date::class,
				Email::class         => \Quellabs\ObjectQuel\Validation\Rules\Email::class,
				Length::class        => \Quellabs\ObjectQuel\Validation\Rules\Length::class,
				NotBlank::class      => \Quellabs\ObjectQuel\Validation\Rules\NotBlank::class,
				RegExp::class        => \Quellabs\ObjectQuel\Validation\Rules\RegExp::class,
				Type::class          => \Quellabs\ObjectQuel\Validation\Rules\Type::class,
				ValueIn::class       => \Quellabs\ObjectQuel\Validation\Rules\ValueIn::class,
			];
			
			// Loop door alle eigenschappen van het entity
			$result = [];

			foreach ($this->reflectionHandler->getProperties($entity) as $property) {
				// Haal de annotaties op voor de huidige eigenschap
				$annotations = $this->annotationReader->getPropertyAnnotations($entity, $property);
				
				// Verwerk elke annotatie
				foreach ($annotations as $annotation) {
					$annotationClass = get_class($annotation);
					
					// Controleer of er een corresponderende validatieregel bestaat voor deze annotatie
					if (isset($annotationMap[$annotationClass])) {
						// Voeg een nieuwe instantie van de validatieregel toe aan het resultaat
						$result[$property][] = new $annotationMap[$annotationClass]($annotation->getParameters());
					}
				}
			}
			
			// Retourneer de array met validatieregels
			return $result;
		}
	}