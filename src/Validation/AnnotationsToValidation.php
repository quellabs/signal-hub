<?php
	
	namespace Services\Validation;
	
	use Services\AnnotationsReader\AnnotationsReader;
	use Services\AnnotationsReader\Annotations\Validation\Btw;
	use Services\AnnotationsReader\Annotations\Validation\Date;
	use Services\AnnotationsReader\Annotations\Validation\Email;
	use Services\AnnotationsReader\Annotations\Validation\Length;
	use Services\AnnotationsReader\Annotations\Validation\NotBlank;
	use Services\AnnotationsReader\Annotations\Validation\NotHTML;
	use Services\AnnotationsReader\Annotations\Validation\NotLongWord;
	use Services\AnnotationsReader\Annotations\Validation\PhoneNumber;
	use Services\AnnotationsReader\Annotations\Validation\RegExp;
	use Services\AnnotationsReader\Annotations\Validation\Type;
	use Services\AnnotationsReader\Annotations\Validation\ValidPassword;
	use Services\AnnotationsReader\Annotations\Validation\ValueIn;
	use Services\AnnotationsReader\Annotations\Validation\Zipcode;
	use Services\EntityManager\ReflectionHandler;
	use Services\Kernel\Kernel;
	
	class AnnotationsToValidation {
		
		/**
		 * Converteert entity-annotaties naar validatieregels.
		 * Deze functie neemt een entity-object en converteert de annotaties van zijn eigenschappen
		 * naar corresponderende validatieregels. Het gebruikt een vooraf gedefinieerde mapping
		 * tussen annotatieklassen en validatieregelklassen.
		 * @param array $annotations
		 * @return array Een array met validatieregels voor elke eigenschap van het entity
		 */
		public function convert(array $annotations): array {
			// Mapping van annotatieklassen naar validatieregelklassen
			$annotationMap = [
				Btw::class           => \Services\Validation\Rules\Btw::class,
				Date::class          => \Services\Validation\Rules\Date::class,
				Email::class         => \Services\Validation\Rules\Email::class,
				Length::class        => \Services\Validation\Rules\Length::class,
				NotBlank::class      => \Services\Validation\Rules\NotBlank::class,
				NotHTML::class       => \Services\Validation\Rules\NotHTML::class,
				NotLongWord::class   => \Services\Validation\Rules\NotLongWord::class,
				PhoneNumber::class   => \Services\Validation\Rules\PhoneNumber::class,
				RegExp::class        => \Services\Validation\Rules\RegExp::class,
				Type::class          => \Services\Validation\Rules\Type::class,
				ValidPassword::class => \Services\Validation\Rules\ValidPassword::class,
				ValueIn::class       => \Services\Validation\Rules\ValueIn::class,
				Zipcode::class       => \Services\Validation\Rules\Zipcode::class,
			];
			
			// Loop door alle eigenschappen van het entity
			$result = [];

			foreach ($annotations as $annotation) {
				$annotationClass = get_class($annotation);
				$parameters = $annotation->getParameters();
				
				// De parameter property moet aanwezig zijn. Dit is de property die gecontroleerd moet worden
				if (empty($parameters['property']) || !is_string($parameters['property'])) {
					continue;
				}
				
				// Controleer of er een corresponderende validatieregel bestaat voor deze annotatie
				if (isset($annotationMap[$annotationClass])) {
					// Voeg een nieuwe instantie van de validatieregel toe aan het resultaat
					$result[$parameters['property']] = new $annotationMap[$annotationClass]($annotation->getParameters());
				}
			}
			
			// Retourneer de array met validatieregels
			return $result;
		}
	}