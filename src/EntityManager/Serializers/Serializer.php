<?php
	
	namespace Services\EntityManager\Serializers;
	
	use Services\AnnotationsReader\AnnotationReader;
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\AnnotationsReader\Annotations\SerializationGroups;
	use Services\AnnotationsReader\AnnotationsReader;
	use Services\EntityManager\EntityStore;
	use Services\EntityManager\PropertyHandler;
	use Services\EntityManager\ReflectionHandler;
	
	class Serializer {
		
		protected array $normalizers;
		protected array $int_types;
		protected array $float_types;
		protected array $char_types;
		protected string $serialization_group_name;
		protected EntityStore $entityStore;
		protected PropertyHandler $propertyHandler;
		protected ReflectionHandler $reflectionHandler;
		protected AnnotationsReader $annotationReader;
		
		/**
		 * Serializer constructor
		 * Initialiseert de benodigde handlers en readers.
		 * @param EntityStore $entityStore
		 * @param string $serializationGroupName
		 */
		public function __construct(EntityStore $entityStore, string $serializationGroupName="") {
			$this->entityStore = $entityStore;
			$this->propertyHandler = new PropertyHandler();
			$this->reflectionHandler = $entityStore->getReflectionHandler();
			$this->annotationReader = $entityStore->getAnnotationReader();
			
			$this->serialization_group_name = $serializationGroupName;
			$this->normalizers = [];
			$this->int_types = ["int", "integer", "smallint", "tinyint", "mediumint", "bigint", "bit"];
			$this->float_types = ["decimal", "numeric", "float", "double", "real"];
			$this->char_types = ['text', 'varchar','char'];

			$this->initializeNormalizers();
		}
		
		/**
		 * Deze functie initialiseert alle entiteiten in de "Entity"-directory.
		 * @return void
		 */
		private function initializeNormalizers(): void {
			// Ophalen van alle bestandsnamen in de "Entity"-directory.
			$normalizerFiles = scandir(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Normalizer");
			
			// Itereren over alle bestanden in de "Entity"-directory.
			foreach ($normalizerFiles as $fileName) {
				// Overslaan als het bestand geen PHP-bestand is.
				if (($fileName == 'NormalizerInterface.php') || !$this->isPHPFile($fileName)) {
					continue;
				}
				
				// Construeren van de entiteitsnaam op basis van de bestandsnaam.
				$this->normalizers[] = strtolower(substr($fileName, 0, strpos($fileName, "Normalizer")));
			}
		}
		
		/**
		 * Controleert of het opgegeven bestand een PHP-bestand is.
		 * @param string $fileName Naam van het bestand.
		 * @return bool True als het een PHP-bestand is, anders false.
		 */
		private function isPHPFile(string $fileName): bool {
			$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
			return ($fileExtension === 'php');
		}
		
		/**
		 * Controleert of een gegeven kolomtype een integer-type is.
		 * @param string $columnType Het kolomtype om te controleren.
		 * @return bool True als het kolomtype een integer-type is, anders false.
		 */
		private function isIntColumnType(string $columnType): bool {
			return isset($this->int_types[$columnType]);
		}
		
		/**
		 * Controleert of een gegeven kolomtype een float-type is.
		 * @param string $columnType Het kolomtype om te controleren.
		 * @return bool True als het kolomtype een float-type is, anders false.
		 */
		private function isFloatColumnType(string $columnType): bool {
			return isset($this->float_types[$columnType]);
		}
		
		/**
		 * Controleert of een gegeven kolomtype een char-type is.
		 * @param string $columnType Het kolomtype om te controleren.
		 * @return bool True als het kolomtype een char-type is, anders false.
		 */
		private function isCharColumnType(string $columnType): bool {
			return isset($this->char_types[$columnType]);
		}
		
		/**
		 * Convert a string to camelcase
		 * @param string $input
		 * @param string $separator
		 * @return string
		 */
		protected function camelCase(string $input, string $separator = '_'): string {
			$array = explode($separator, $input);
			$parts = array_map('ucfirst', $array);
			return implode('', $parts);
		}
		
		/**
		 * Convert a string to snake case
		 * @url https://stackoverflow.com/questions/40514051/using-preg-replace-to-convert-camelcase-to-snake-case
		 * @param string $string
		 * @return string
		 */
		protected function snakeCase(string $string): string {
			return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
		}
		
		/**
		 * Convert a string to kebab case
		 * @url https://ideone.com/Mr9wN5
		 * @param string $string
		 * @return string
		 */
		protected function kebabCase(string $string): string {
			return strtolower(preg_replace('/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/', "-", $string));
		}
		
		/**
		 * Normalizes a value based on its column type annotation.
		 * The function first checks if the column type is in the exclusion list.
		 * If it is not, the appropriate normalizer class is used to normalize the value.
		 * Otherwise, basic type casting is applied based on the column type.
		 * @param object $annotation The annotation object that contains the column type.
		 * @param mixed $value The value to be normalized.
		 * @return mixed The normalized value.
		 */
		public function normalizeValue(object $annotation, mixed $value): mixed {
			// Retrieve the column type from the annotation
			$columnType = $annotation->getType();
			
			// Check if the column type is a type known to need normalization. If not, return the value as-is.
			if (in_array(strtolower($columnType), $this->normalizers)) {
				$normalizerClass = "\\Services\\EntityManager\\Normalizer\\" . ucfirst($columnType) . "Normalizer";
				$normalizer = new $normalizerClass($value, $annotation);
				return $normalizer->normalize();
			}
			
			// Cast to int if the column type is an integer
			if ($this->isIntColumnType($columnType)) {
				return (int)$value;
			}
			
			// Cast to float if the column type is a float
			if ($this->isFloatColumnType($columnType)) {
				return (float)$value;
			}
			
			// Return the value as-is if no normalizer or type casting is applicable
			return $value;
		}
		
		/**
		 * Denormalize the given value based on its annotation and column type.
		 * @param object $annotation The annotation object describing the column's metadata.
		 * @param mixed $value The value to be denormalized.
		 * @return mixed The denormalized value.
		 */
		public function denormalizeValue(object $annotation, mixed $value): mixed {
			// Retrieve the column type from the annotation
			$columnType = $annotation->getType();
			
			// If there's no value, but there's a default, grab the default
			if (($value === null) && $annotation->hasDefault()) {
				return $annotation->getDefault();
			}
			
			// Check if the column type is a type known to need denormalization. If not, return the value as-is.
			if (in_array(strtolower($columnType), $this->normalizers)) {
				$normalizerClass = "\\Services\\EntityManager\\Normalizer\\" . ucfirst($columnType) . "Normalizer";
				$normalizer = new $normalizerClass($value, $annotation);
				return $normalizer->denormalize();
			}
			
			// If no specific denormalization logic applies, return the value as-is
			return $value;
		}
		
		/**
		 * Controleert of een eigenschap tot een specifieke serialisatiegroep behoort.
		 * Deze functie bepaalt of een gegeven eigenschap moet worden opgenomen in de serialisatie
		 * op basis van de gespecificeerde serialisatiegroep en de annotaties van de eigenschap.
		 * @param array $annotations Een array van annotaties geassocieerd met de eigenschap.
		 * @return bool True als de eigenschap moet worden geserialiseerd, anders false.
		 */
		public function propertyInSerializeGroup(array $annotations): bool {
			// Als er geen serialisatiegroep is opgegeven, includeren we alle eigenschappen
			if (empty($this->serialization_group_name)) {
				return true;
			}
			
			// Zoek naar de SerializeGroup annotatie
			foreach ($annotations as $annotation) {
				if ($annotation instanceof SerializationGroups) {
					// Controleer of de huidige serialisatiegroep in de annotatie voorkomt
					return in_array($this->serialization_group_name, $annotation->getGroups(), true);
				}
			}
			
			// Als er geen SerializeGroup annotatie is gevonden, dan includeren we de eigenschap
			return true;
		}
		
		/**
		 * Extraheert alle waarden uit de entiteit die gemarkeerd zijn als Column.
		 * @param object $entity De entiteit waaruit de waarden geëxtraheerd moeten worden.
		 * @return array Een array met property namen als keys en hun waarden.
		 */
		public function serialize(object $entity): array {
			// Early return if the entity does not exist in the entity store.
			if (!$this->entityStore->exists($entity)) {
				return [];
			}
			
			// Retrieve annotations for the entity class.
			$annotationList = $this->entityStore->getAnnotations($entity);
			
			// Iterate through each property's annotations.
			$result = [];
			
			foreach ($annotationList as $property => $annotations) {
				// Find the first valid annotation.
				foreach ($annotations as $annotation) {
					if ($annotation instanceof Column) {
						// Check if the property is part of a serialization group. If not, skip the property
						if (!$this->propertyInSerializeGroup($annotations)) {
							break;
						}
						
						// Fetch value
						$value = $this->propertyHandler->get($entity, $property);
						
						// Denormalize the value
						$valueDenormalized = $this->denormalizeValue($annotation, $value);
						
						// Get and store the property's current value.
						$result[$property] = $valueDenormalized;
						
						// Skip to the next property.
						break;
					}
				}
			}
			
			return $result;
		}
		
		/**
		 * Injecteert de gegeven waarden in de entiteit.
		 * @param object $entity De entiteit waarin de waarden geïnjecteerd moeten worden.
		 * @param array $values De te injecteren waarden, met property namen als keys.
		 * @return void
		 */
		public function deserialize(object $entity, array $values): void {
			// Retrieve annotations for the entity class to understand how to map properties
			$annotationList = $this->entityStore->getAnnotations($entity);
			
			// Loop through each property's annotations to check how each should be handled
			foreach ($annotationList as $property => $annotations) {
				// Skip this property if the provided data array doesn't contain this column name
				if (!array_key_exists($property, $values)) {
					continue;
				}
				
				// Find the first valid annotation.
				foreach ($annotations as $annotation) {
					if ($annotation instanceof Column) {
						// Fetch value
						$value = $values[$property];
						
						// Normalize the value
						$normalizedValue = $this->normalizeValue($annotation, $value);
						
						// Determine the setter method name
						$setterMethod = 'set' . ucfirst($this->camelCase($property));
						
						// Set the property using the setter method. If that doesn't exist, use reflection
						if (method_exists($entity, $setterMethod)) {
							$entity->$setterMethod($normalizedValue);
						} else {
							$this->propertyHandler->set($entity, $property, $normalizedValue);
						}

						// Skip to the next property
						break;
					}
				}
			}
		}
	}