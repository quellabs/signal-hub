<?php
    
    namespace Services\EntityManager;
    
    use Services\AnnotationsReader\Annotations\Orm\Column;
    use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
    use Services\AnnotationsReader\Annotations\Orm\OneToMany;
    use Services\AnnotationsReader\Annotations\Orm\OneToOne;
    use Services\AnnotationsReader\AnnotationsReader;
    use Services\Kernel\ReflectionHandler;
    use Services\ObjectQuel\Ast\AstRetrieve;
    
    class EntityStore {
        protected AnnotationsReader $annotation_reader;
        protected ReflectionHandler $reflection_handler;
		protected ProxyGenerator $proxy_generator;
        protected array $entity_properties;
        protected array $entity_table_name;
        protected array $entity_annotations;
        protected array $column_map_cache;
        protected array $identifier_keys_cache;
        protected array $identifier_columns_cache;
        protected string|bool $services_path;
        protected array|null $dependencies;
        protected array $dependencies_cache;
        protected array $completed_entity_name_cache;
	    
	    /**
	     * EntityStore constructor.
	     */
		public function __construct() {
			$this->annotation_reader = new AnnotationsReader();
			$this->reflection_handler = new ReflectionHandler();
			$this->services_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . "..");
			$this->entity_properties = [];
			$this->entity_table_name = [];
			$this->entity_annotations = [];
			$this->column_map_cache = [];
			$this->identifier_keys_cache = [];
			$this->identifier_columns_cache = [];
			$this->dependencies = null;
			$this->dependencies_cache = [];
			$this->completed_entity_name_cache = [];
			
			// Deze functie initialiseert alle entiteiten in de "Entity"-directory.
			$this->initializeEntities();
			
			// Deze functie initialiseert de proxies
			$this->proxy_generator = new ProxyGenerator($this);
		}
		
		/**
		 * Deze functie initialiseert alle entiteiten in de "Entity"-directory.
		 * @return void
		 */
		private function initializeEntities(): void {
			// Het pad naar de "Entity"-directory.
			$entityDirectory = $this->services_path . DIRECTORY_SEPARATOR . "Entity";
			
			// Ophalen van alle bestandsnamen in de "Entity"-directory.
			$entityFiles = scandir($entityDirectory);
			
			// Itereren over alle bestanden in de "Entity"-directory.
			foreach ($entityFiles as $fileName) {
				// Overslaan als het bestand geen PHP-bestand is.
				if (!$this->isPHPFile($fileName)) {
					continue;
				}
				
				// Construeren van de entiteitsnaam op basis van de bestandsnaam.
				$entityName = $this->constructEntityName($fileName);
				
				// Overslaan als het niet als een entiteit wordt herkend.
				if (!$this->isEntity($entityName)) {
					continue;
				}
				
				// Invullen van de details van de erkende entiteit.
				$this->populateEntityDetails($entityName);
			}
		}
		
		/**
		 * Returns a list of entities and their manytoone dependencies
		 * @return array
		 */
		private function getAllEntityDependencies(): array {
			if ($this->dependencies === null) {
				$this->dependencies = [];

				foreach (array_keys($this->entity_table_name) as $class) {
					$manyToOneDependencies = $this->getManyToOneDependencies($class);
					$oneToOneDependencies = array_filter($this->getOneToOneDependencies($class), function($e) { return !empty($e->getInversedBy()); });
					
					$this->dependencies[$class] = array_unique(array_merge(
						array_map(function($e) { return $e->getTargetEntity(); }, $manyToOneDependencies),
						array_map(function($e) { return $e->getTargetEntity(); }, $oneToOneDependencies),
					));
				}
			}
			
			return $this->dependencies;
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
		 * Constructs the full entity name
		 * @param string $fileName
		 * @return string
		 */
		private function constructEntityName(string $fileName): string {
			return "Services\\Entity\\" . substr($fileName, 0, strpos($fileName, ".php"));
		}
		
		/**
		 * Checks if the entity is an ORM table
		 * @param string $entityName
		 * @return bool
		 */
		private function isEntity(string $entityName): bool {
			$annotations = $this->annotation_reader->getClassAnnotations($entityName);
			return array_key_exists("Orm\\Table", $annotations);
		}

		/**
		 * Deze functie vult de details van een gegeven entiteit.
		 * @param string $entityName De naam van de entiteit.
		 * @return void
		 */
		private function populateEntityDetails(string $entityName): void {
			// Initialiseren van de annotaties array voor de entiteit.
			$this->entity_annotations[$entityName] = [];
			
			// Ophalen en opslaan van de eigenschappen van de entiteit.
			$this->entity_properties[$entityName] = $this->reflection_handler->getProperties($entityName);
			
			// Ophalen en opslaan van de tabelnaam gekoppeld aan de entiteit.
			$this->entity_table_name[$entityName] = $this->annotation_reader->getClassAnnotations($entityName)["Orm\\Table"]->getName();
			
			// Itereren over de eigenschappen van de entiteit.
			foreach ($this->entity_properties[$entityName] as $property) {
				// Ophalen van de annotaties voor de gegeven eigenschap.
				$annotations = $this->annotation_reader->getPropertyAnnotations($entityName, $property);
				
				// Opslaan van de annotaties per eigenschap.
				$this->entity_annotations[$entityName][$property] = $annotations;
			}
		}
		
		/**
		 * Interner helper functies voor het ophalen van properties met een bepaalde annotatie
		 * @param mixed $entity De naam van de entiteit waarvoor je afhankelijkheden wilt krijgen.
		 * @param string $desiredAnnotationType Het type van de afhankelijkheid
		 * @return array
		 */
		private function internalGetDependencies(mixed $entity, string $desiredAnnotationType): array {
			// Bepaal de klassenaam van de entiteit
			if (!is_object($entity)) {
				$entityClass = ltrim($entity, "\\");
			} elseif ($entity instanceof \ReflectionClass) {
				$entityClass = $entity->getName();
			} else {
				$entityClass = get_class($entity);
			}
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Cache hash
			$md5OfQuery = hash("sha256", $normalizedClass . "##" . $desiredAnnotationType);
			
			// Haal dependencies uit cache indien mogelijk
			if (isset($this->dependencies_cache[$md5OfQuery])) {
				return $this->dependencies_cache[$md5OfQuery];
			}
			
			// Haal de annotaties op voor de opgegeven klasse.
			$annotationList = $this->getAnnotations($normalizedClass);
			
			// Loop door elke annotatie om te controleren op een relatie.
			$result = [];
			
			foreach ($annotationList as $property => $annotations) {
				foreach ($annotations as $annotation) {
					if ($annotation instanceof $desiredAnnotationType) {
						$result[$property] = $annotation;
						break; // Stop met zoeken als de gewenste annotatie is gevonden
					}
				}
			}
			
			$this->dependencies_cache[$md5OfQuery] = $result;
			return $result;
		}
		
		/**
         * Returns the annotationReader object
         * @return AnnotationsReader
         */
        public function getAnnotationReader(): AnnotationsReader {
            return $this->annotation_reader;
        }
    
        /**
         * Returns the ReflectionHandler object
         * @return ReflectionHandler
         */
        public function getReflectionHandler(): ReflectionHandler {
            return $this->reflection_handler;
        }
    
        /**
         * Returns the ProxyGenerator object
         * @return ProxyGenerator
         */
        public function getProxyGenerator(): ProxyGenerator {
            return $this->proxy_generator;
        }
    
		/**
		 * Normaliseert de entiteitsnaam om de basisentiteitsklasse terug te geven als de input een proxy-klasse is.
		 * @param string $class De volledige naam van de klasse die genormaliseerd moet worden.
		 * @return string De genormaliseerde naam van de klasse.
		 */
		public function normalizeEntityName(string $class): string {
			if (!isset($this->completed_entity_name_cache[$class])) {
				// Controleer of de klassenaam het "\\Proxies\\" subpad bevat, wat duidt op een proxy-klasse
				// Als het een proxy-klasse is, haal dan de naam van de ouderklasse (de echte entiteitsklasse) op
				if (str_contains($class, "\\Proxies\\")) {
					$this->completed_entity_name_cache[$class] = $this->reflection_handler->getParent($class);
				} elseif (str_contains($class, "\\")) {
					$this->completed_entity_name_cache[$class] = $class;
				} else {
					$this->completed_entity_name_cache[$class] = "Services\\Entity\\{$class}";
				}
			}
			
			// Als het geen proxy-klasse is, retourneer dan gewoon de gegeven klassenaam
			return $this->completed_entity_name_cache[$class];
		}
		
		/**
		 * Checks if the entity or its parent exists in the entity_table_name array.
		 * @param mixed $entity The entity to check, either as an object or as a string class name.
		 * @return bool True if the entity or its parent class exists in the entity_table_name array, false otherwise.
		 */
		public function exists(mixed $entity): bool {
			// Bepaal de klassenaam van de entiteit
			if (!is_object($entity)) {
				$entityClass = ltrim($entity, "\\");
			} elseif ($entity instanceof \ReflectionClass) {
				$entityClass = $entity->getName();
			} else {
				$entityClass = get_class($entity);
			}
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Check if the entity class exists in the entity_table_name array.
			if (isset($this->entity_table_name[$normalizedClass])) {
				return true;
			}
			
			// Get the parent class name using the ReflectionHandler.
			$parentClass = $this->getReflectionHandler()->getParent($normalizedClass);
			
			// Check if the parent class exists in the entity_table_name array.
			if ($parentClass !== null && isset($this->entity_table_name[$parentClass])) {
				return true;
			}
			
			// Return false if neither the entity nor its parent class exists in the entity_table_name array.
			return false;
		}
        
        /**
         * Returns the table name attached to the entity
         * @param mixed $entity
         * @return string|null
         */
        public function getOwningTable(mixed $entity): ?string {
			// Bepaal de klassenaam van de entiteit
			if (!is_object($entity)) {
				$entityClass = ltrim($entity, "\\");
			} elseif ($entity instanceof \ReflectionClass) {
				$entityClass = $entity->getName();
			} else {
				$entityClass = get_class($entity);
			}
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Haal de table naam op
			return $this->entity_table_name[$normalizedClass] ?? null;
        }
	    
	    /**
	     * Returns true if the identifier is nullable, false if not
	     * @param mixed $entity
	     * @param string $identifierName
	     * @return bool
	     */
		public function isNullable(mixed $entity, string $identifierName): bool {
			// Get all annotations for the entity
			$annotationList = $this->getAnnotations($entity);
			
			// Check if identifier has annotations
			if (!isset($annotationList[$identifierName])) {
				return false;
			}
			
			// Search for Column annotation to get type
			foreach ($annotationList[$identifierName] as $annotation) {
				if ($annotation instanceof Column) {
					return $annotation->isNullable();
				}
			}
			
			return false;
		}
    
		/**
		 * Deze functie haalt de primaire sleutels van een gegeven entiteit op.
		 * @param mixed $entity De entiteit waarvan de primaire sleutels worden opgehaald.
		 * @return array Een array met de namen van de eigenschappen die de primaire sleutels zijn.
		 */
		public function getIdentifierKeys(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			if (!is_object($entity)) {
				$entityClass = ltrim($entity, "\\");
			} elseif ($entity instanceof \ReflectionClass) {
				$entityClass = $entity->getName();
			} else {
				$entityClass = get_class($entity);
			}
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Gebruik de gecachte waarde als deze bestaat
			if (isset($this->identifier_keys_cache[$normalizedClass])) {
				return $this->identifier_keys_cache[$normalizedClass];
			}
			
			// Ophalen van alle annotaties voor de gegeven entiteit.
			$entityAnnotations = $this->getAnnotations($entity);
			
			// Zoek de primaire sleutels en cache het resultaat
			$result = [];
			
			foreach ($entityAnnotations as $property => $annotations) {
				foreach ($annotations as $annotation) {
					if ($annotation instanceof Column && $annotation->isPrimaryKey()) {
						$result[] = $property;
						break;
					}
				}
			}
			
			// Cache het resultaat voor toekomstig gebruik
			$this->identifier_keys_cache[$normalizedClass] = $result;
			
			// Retourneer het resultaat
			return $result;
		}

		/**
		 * Haalt de kolomnamen op die als primaire sleutel dienen voor een bepaalde entiteit.
		 * @param mixed $entity De entiteit waarvoor de primaire sleutelkolommen worden opgehaald.
		 * @return array Een array met de namen van de kolommen die als primaire sleutel dienen.
		 */
		public function getIdentifierColumnNames(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			if (!is_object($entity)) {
				$entityClass = ltrim($entity, "\\");
			} elseif ($entity instanceof \ReflectionClass) {
				$entityClass = $entity->getName();
			} else {
				$entityClass = get_class($entity);
			}
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Gebruik de gecachte waarde als deze bestaat
			if (isset($this->identifier_columns_cache[$normalizedClass])) {
				return $this->identifier_columns_cache[$normalizedClass];
			}
			
			// Haal alle annotaties op voor de gegeven entiteit
			$annotationList = $this->getAnnotations($normalizedClass);
			
			// Initialiseer een lege array om de resultaten in op te slaan
			$result = [];
			
			// Loop door alle annotaties van de entiteit
			foreach ($annotationList as $annotations) {
				foreach ($annotations as $annotation) {
					if ($annotation instanceof Column && $annotation->isPrimaryKey()) {
						$result[] = $annotation->getName();
					}
				}
			}
			
			// Cache het resultaat voor toekomstig gebruik
			$this->identifier_columns_cache[$normalizedClass] = $result;
			
			// Retourneer het resultaat
			return $result;
		}
		
		/**
		 * Verkrijgt de kaart tussen eigenschappen en kolomnamen voor een gegeven entiteit.
		 * Deze functie genereert een associatieve array die de eigenschappen van een entiteit
		 * koppelt aan hun respectievelijke kolomnamen in de database. De resultaten worden gecached
		 * om herhaalde berekeningen te voorkomen.
		 * @param mixed $entity Het object of de klassenaam van de entiteit.
		 * @return array Een associatieve array met de eigenschap als sleutel en de kolomnaam als waarde.
		 */
		public function getColumnMap(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			if (!is_object($entity)) {
				$entityClass = ltrim($entity, "\\");
			} elseif ($entity instanceof \ReflectionClass) {
				$entityClass = $entity->getName();
			} else {
				$entityClass = get_class($entity);
			}
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Gebruik de gecachte waarde als deze bestaat
			if (isset($this->column_map_cache[$normalizedClass])) {
				return $this->column_map_cache[$normalizedClass];
			}
			
			// Haal alle annotaties voor de entiteit op
			$annotationList = $this->getAnnotations($normalizedClass);
			
			// Loop door alle annotaties, gekoppeld aan hun respectievelijke eigenschappen
			$result = [];

			foreach ($annotationList as $property => $annotations) {
				// Verkrijg de kolomnaam van de annotaties
				foreach ($annotations as $annotation) {
					if ($annotation instanceof Column) {
						$result[$property] = $annotation->getName();
						break;
					}
				}
			}
			
			// Cache het resultaat voor toekomstig gebruik
			$this->column_map_cache[$normalizedClass] = $result;
			
			// Retourneer het resultaat
			return $result;
		}

		/**
         * Returns the entity's annotations
         * @param mixed $entity
         * @return array
         */
        public function getAnnotations(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = !is_object($entity) ? ltrim($entity, "\\") : get_class($entity);
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Retourneer de annotation informatie
			return $this->entity_annotations[$normalizedClass] ?? [];
        }

        /**
         * Returns the entity's properties
         * @param mixed $entity
         * @return array
         */
        public function getProperties(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = !is_object($entity) ? ltrim($entity, "\\") : get_class($entity);
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Retourneer de annotation informatie
			return $this->entity_properties[$normalizedClass] ?? [];
        }
		
		/**
		 * Haalt alle OneToOne-afhankelijkheden op voor een bepaalde entiteit.
		 * @param mixed $entity De naam van de entiteit waarvoor je de OneToOne-afhankelijkheden wilt krijgen.
		 * @return OneToOne[] Een associatieve array met als sleutel de naam van de doelentiteit en als waarde de annotatie.
		 */
		public function getOneToOneDependencies(mixed $entity): array {
			return $this->internalGetDependencies($entity, OneToOne::class);
		}
		
		/**
		 * Haal de ManyToOne afhankelijkheden op voor een gegeven entiteitsklasse.
		 * Deze functie gebruikt annotaties om te bepalen welke andere entiteiten
		 * gerelateerd zijn aan de gegeven entiteitsklasse via een ManyToOne relatie.
		 * De namen van deze gerelateerde entiteiten worden geretourneerd als een array.
		 * @param mixed $entity De naam van de entiteitsklasse om te inspecteren.
		 * @return ManyToOne[] Een array van entiteitsnamen waarmee de gegeven klasse een ManyToOne relatie heeft.
		 */
		public function getManyToOneDependencies(mixed $entity): array {
			return $this->internalGetDependencies($entity, ManyToOne::class);
		}
		
		/**
		 * Haalt alle OneToMany-afhankelijkheden op voor een bepaalde entiteit.
		 * @param mixed $entity De naam van de entiteit waarvoor je de OneToMany-afhankelijkheden wilt krijgen.
		 * @return OneToMany[] Een associatieve array met als sleutel de naam van de doelentiteit en als waarde de annotatie.
		 */
		public function getOneToManyDependencies(mixed $entity): array {
			return $this->internalGetDependencies($entity, OneToMany::class);
		}
        
        /**
         * Interner helper functies voor het ophalen van properties met een bepaalde annotatie
         * @param mixed $entity De naam van de entiteit waarvoor je afhankelijkheden wilt krijgen.
         * @return array
         */
        public function getAllDependencies(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = !is_object($entity) ? ltrim($entity, "\\") : get_class($entity);
            
            // Als de klassenaam een proxy is, haal dan de class op van de parent
            $normalizedClass = $this->normalizeEntityName($entityClass);
            
            // Cache hash
            $md5OfQuery = hash("sha256", $normalizedClass);
            
            // Haal dependencies uit cache indien mogelijk
            if (isset($this->dependencies_cache[$md5OfQuery])) {
                return $this->dependencies_cache[$md5OfQuery];
            }
            
            // Haal de annotaties op voor de opgegeven klasse.
            $annotationList = $this->getAnnotations($normalizedClass);
            
            // Loop door elke annotatie om te controleren op een relatie.
			$result = [];
			
			foreach (array_keys($annotationList) as $property) {
				foreach ($annotationList[$property] as $annotation) {
					if ($annotation instanceof OneToMany || $annotation instanceof OneToOne || $annotation instanceof ManyToOne) {
						$result[$property][] = $annotation;
						continue 2;
					}
				}
			}
            
            $this->dependencies_cache[$md5OfQuery] = $result;
            return $result;
        }
        
        /**
		 * Retourneert alle entiteiten die afhankelijk zijn van de opgegeven entiteit.
		 * @param mixed $entity De naam van de entiteit waarvan je de afhankelijke entiteiten wilt vinden.
		 * @return array Een lijst van afhankelijke entiteiten.
		 */
		public function getDependentEntities(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = !is_object($entity) ? ltrim($entity, "\\") : get_class($entity);
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Haal alle bekende entiteitsafhankelijkheden op.
			$dependencies = $this->getAllEntityDependencies();
			
			// Loop door elke entiteit en diens afhankelijkheden om te controleren op de opgegeven klasse.
			$result = [];
			
			foreach ($dependencies as $entity => $entityDependencies) {
				// Gebruik array_flip voor snellere zoekopdrachten.
				$flippedDependencies = array_flip($entityDependencies);
				
				// Als de opgegeven klasse in de omgekeerde lijst met afhankelijkheden staat,
				// voeg deze dan toe aan het resultaat.
				if (isset($flippedDependencies[$normalizedClass])) {
					$result[] = $entity;
				}
			}
			
			// Retourneer de lijst met afhankelijke entiteiten.
			return $result;
		}
		
		/**
		 * Haalt de primaire sleutel van de hoofdbereik (range) van een AstRetrieve object.
		 * Deze functie doorzoekt de ranges binnen het AstRetrieve object en retourneert de primaire sleutel
		 * van de eerste range die geen join eigenschap heeft. Dit is de hoofdentity waarop de query betrekking heeft.
		 * @param AstRetrieve $e Een verwijzing naar het AstRetrieve object dat de query representeert.
		 * @return ?array Een array met informatie over de range en de primaire sleutel, of null als er geen geschikte range gevonden is.
		 */
		public function fetchPrimaryKeyOfMainRange(AstRetrieve $e): ?array {
			foreach ($e->getRanges() as $range) {
				// Continueert of de range een join eigenschap bevat
				if ($range->getJoinProperty() !== null) {
					continue;
				}
				
				// Haalt de naam van de entiteit en de bijbehorende primaire sleutel op als de range geen join eigenschap heeft.
				$entityName = $range->getEntity()->getName();
				$entityNameIdentifierKeys = $this->getIdentifierKeys($entityName);
				
				// Retourneert de naam van de range, de entiteitnaam en de primaire sleutel van de entiteit.
				return [
					'range'      => $range,
					'entityName' => $entityName,
					'primaryKey' => $entityNameIdentifierKeys[0]
				];
			}
			
			// Retourneert null als er geen range zonder join eigenschap gevonden is.
			// Dit komt in de praktijk nooit voor, omdat een dergelijke query niet gemaakt kan worden.
			return null;
		}
    }