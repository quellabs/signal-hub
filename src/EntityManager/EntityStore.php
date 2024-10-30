<?php
    
    namespace Services\EntityManager;
    
    use Services\AnnotationsReader\AnnotationsReader;
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
	use Services\AnnotationsReader\Annotations\Orm\OneToMany;
	use Services\AnnotationsReader\Annotations\Orm\OneToOne;
	
	class EntityStore {
        protected AnnotationsReader $annotation_reader;
        protected ReflectionHandler $reflection_handler;
        protected PropertyHandler $property_handler;
        protected array $entity_properties;
        protected array $entity_table_name;
        protected array $entity_annotations;
        protected array $column_map_cache;
        protected array $identifier_keys_cache;
        protected array $identifier_columns_cache;
        protected string|bool $services_path;
        protected array|null $dependencies;
        protected array|null $topologically_sorted_entities;
		
		/**
		 * EntityStore constructor.
		 * @param AnnotationsReader $annotationReader
		 * @param ReflectionHandler $reflectionHandler
		 * @param PropertyHandler $propertyHandler
		 */
		public function __construct(AnnotationsReader $annotationReader, ReflectionHandler $reflectionHandler, PropertyHandler $propertyHandler) {
			$this->annotation_reader = $annotationReader;
			$this->reflection_handler = $reflectionHandler;
			$this->property_handler = $propertyHandler;
			$this->services_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . "..");
			$this->entity_properties = [];
			$this->entity_table_name = [];
			$this->entity_annotations = [];
			$this->column_map_cache = [];
			$this->identifier_keys_cache = [];
			$this->identifier_columns_cache = [];
			$this->dependencies = null;
			$this->topologically_sorted_entities =  null;
			
			// Deze functie initialiseert alle entiteiten in de "Entity"-directory.
			$this->initializeEntities();
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
		 * Hulpmethode voor het uitvoeren van topologische sortering op een reeks van afhankelijkheden.
		 * Gebruikt een diepte-eerste zoekmethode om entiteiten te sorteren in een lineaire volgorde.
		 * @param string $entity De huidige entiteit om te verwerken.
		 * @param array &$visited Een referentie naar een array die bijhoudt welke entiteiten zijn bezocht.
		 * @param array &$path Een referentie naar een array die het huidige pad in de diepte-eerste zoektocht bijhoudt.
		 * @param array &$sorted Een referentie naar een array waarin de gesorteerde entiteiten worden opgeslagen.
		 * @param array $dependencies Een array van entiteiten en hun afhankelijkheden.
		 * @return bool Geeft terug of de sortering succesvol is, of false als er een cyclische afhankelijkheid wordt gevonden.
		 */
		private function topologicalSortUtil(string $entity, array &$visited, array &$path, array &$sorted, array $dependencies): bool {
			// Cyclische afhankelijkheden negeren
			if (isset($path[$entity])) {
				return true;
			}
			
			// Als de entiteit al is bezocht, doe dan niets
			if (isset($visited[$entity])) {
				return true;
			}
			
			// Markeer de entiteit als bezocht in het huidige pad en algemeen bezocht
			$path[$entity] = true;
			$visited[$entity] = true;
			
			// Verwerk eerst alle afhankelijke entiteiten
			if (isset($dependencies[$entity])) {
				foreach ($dependencies[$entity] as $depEntity) {
					if (!$this->topologicalSortUtil($depEntity, $visited, $path, $sorted, $dependencies)) {
						// Als er een probleem is met een afhankelijkheid, retourneer false
						return false;
					}
				}
			}
			
			// Na het verwerken van alle afhankelijkheden, voeg de huidige entiteit toe
			$sorted[] = $entity;
			
			// Verwijder de entiteit uit het huidige pad
			unset($path[$entity]);
			
			return true;
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
		 * Haalt de kolomnaam uit een lijst van annotaties.
		 * Deze functie loopt door elke annotatie in de meegeleverde lijst. Als een Column-annotatie
		 * wordt gevonden, wordt de naam ervan geretourneerd.
		 * @param array $annotations De lijst met annotaties gekoppeld aan een eigenschap.
		 * @return string|null De naam van de kolom als een Column-annotatie wordt gevonden, anders null.
		 */
		private function getColumnNameFromAnnotations(array $annotations): ?string {
			// Loop door elke annotatie
			foreach ($annotations as $annotation) {
				// Als de annotatie een instance van Column is, retourneer dan de naam van de kolom
				if ($annotation instanceof Column) {
					return $annotation->getName();
				}
			}
			
			// Als er geen Column-annotatie is gevonden, retourneer dan null
			return null;
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
				foreach ($annotations as $annotation) {
					$this->entity_annotations[$entityName][$property][] = $annotation;
				}
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
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
			// Haal de annotaties op voor de opgegeven klasse.
			$annotationList = $this->getAnnotations($normalizedClass);
			
			// Initialiseer een array om de resultaten in op te slaan.
			$result = [];
			
			// Loop door elke annotatie om te controleren op een ManyToOne-relatie.
			foreach ($annotationList as $property => $annotations) {
				foreach ($annotations as $annotation) {
					// Als de annotatie een OneToMany-relatie aangeeft,
					// voeg deze dan toe aan de resultaat-array.
					if ($annotation instanceof $desiredAnnotationType) {
						$result[$property] = $annotation;
						
						// Ga naar de volgende iteratie van de buitenste lus.
						continue 2;
					}
				}
			}
			
			// Retourneer de resultaat-array.
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
         * Returns the PropertyHandler object
         * @return PropertyHandler
         */
        public function getPropertyHandler(): PropertyHandler {
            return $this->property_handler;
        }
		
		/**
		 * Voegt een namespace toe aan een entity-klasse indien deze nog niet aanwezig is.
		 * @param string $entityClass Naam van de entity-klasse.
		 * @return string Volledige naam van de entity-klasse, inclusief namespace.
		 */
		public function addNamespaceToEntityName(string $entityClass): string {
			// Controleer of de gegeven klasse al een namespace bevat
			if (!str_contains($entityClass, "\\")) {
				// Voeg de standaard namespace toe als deze nog niet aanwezig is
				return "Services\\Entity\\{$entityClass}";
			}
			
			// Retourneer de originele klassenaam als deze al een namespace bevat
			return $entityClass;
		}
		
		/**
		 * Normaliseert de entiteitsnaam om de basisentiteitsklasse terug te geven als de input een proxy-klasse is.
		 * @param string $class De volledige naam van de klasse die genormaliseerd moet worden.
		 * @return string De genormaliseerde naam van de klasse.
		 */
		public function normalizeEntityName(string $class): string {
			// Controleer of de klassenaam het "\\Proxies\\" subpad bevat, wat duidt op een proxy-klasse
			// Als het een proxy-klasse is, haal dan de naam van de ouderklasse (de echte entiteitsklasse) op
			if (str_contains($class, "\\Proxies\\")) {
				return $this->reflection_handler->getParent($class);
			}
			
			// Als het geen proxy-klasse is, retourneer dan gewoon de gegeven klassenaam
			return $class;
		}
		
		/**
		 * Checks if the entity or its parent exists in the entity_table_name array.
		 * @param mixed $entity The entity to check, either as an object or as a string class name.
		 * @return bool True if the entity or its parent class exists in the entity_table_name array, false otherwise.
		 */
		public function exists(mixed $entity): bool {
			// Get the class name of the entity, regardless of whether it's an object or a class name string.
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
			// Check if the entity class exists in the entity_table_name array.
			if (array_key_exists($normalizedClass, $this->entity_table_name)) {
				return true;
			}
			
			// Get the parent class name using the ReflectionHandler.
			$parentClass = $this->getReflectionHandler()->getParent($normalizedClass);
			
			// Check if the parent class exists in the entity_table_name array.
			if ($parentClass !== null && array_key_exists($parentClass, $this->entity_table_name)) {
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
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
			// Haal de table naam op
			return $this->entity_table_name[$normalizedClass] ?? null;
        }
    
		/**
		 * Deze functie haalt de primaire sleutels van een gegeven entiteit op.
		 * @param mixed $entity De entiteit waarvan de primaire sleutels worden opgehaald.
		 * @return array Een array met de namen van de eigenschappen die de primaire sleutels zijn.
		 */
		public function getIdentifierKeys(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
			// Gebruik de gecachte waarde als deze bestaat
			if (isset($this->identifier_keys_cache[$normalizedClass])) {
				return $this->identifier_keys_cache[$normalizedClass];
			}
			
			// Initialiseren van het resultaat array.
			$result = [];
			
			// Ophalen van alle annotaties voor de gegeven entiteit.
			$entityAnnotations = $this->getAnnotations($entity);
			
			// Itereren over alle annotaties gekoppeld aan eigenschappen van de entiteit.
			foreach($entityAnnotations as $property => $annotations) {
				foreach($annotations as $annotation) {
					// Ga door als de annotatie geen kolom is of geen primaire sleutel is
					if (!$annotation instanceof Column || !$annotation->isPrimaryKey()) {
						continue;
					}
					
					// Toevoegen van de eigenschap aan het resultaat array.
					$result[] = $property;
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
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
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
					// Ga door als de annotatie geen kolom is of geen primaire sleutel is
					if (!$annotation instanceof Column || !$annotation->isPrimaryKey()) {
						continue;
					}
					
					$result[] = $annotation->getName();
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
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
			// Gebruik de gecachte waarde als deze bestaat
			if (isset($this->column_map_cache[$normalizedClass])) {
				return $this->column_map_cache[$normalizedClass];
			}
			
			// Haal alle annotaties voor de entiteit op
			$annotationList = $this->getAnnotations($normalizedClass);
			
			// Initialiseer een lege array om het resultaat op te slaan
			$result = [];
			
			// Loop door alle annotaties, gekoppeld aan hun respectievelijke eigenschappen
			foreach ($annotationList as $property => $annotations) {
				// Verkrijg de kolomnaam van de annotaties
				$columnName = $this->getColumnNameFromAnnotations($annotations);
				
				// Als er een kolomnaam is, voeg deze dan toe aan het resultaat
				if ($columnName !== null) {
					$result[$property] = $columnName;
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
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// Retourneer de annotation informatie
			return $this->entity_annotations[$normalizedClass] ?? [];
        }

        /**
         * Returns the entity's annotations
         * @param mixed $entity
         * @return array
         */
        public function getProperties(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
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
		 * Retourneert alle entiteiten die afhankelijk zijn van de opgegeven entiteit.
		 * @param mixed $entity De naam van de entiteit waarvan je de afhankelijke entiteiten wilt vinden.
		 * @return array Een lijst van afhankelijke entiteiten.
		 */
		public function getDependentEntities(mixed $entity): array {
			// Bepaal de klassenaam van de entiteit
			$entityClass = is_object($entity) ? get_class($entity) : ltrim($entity, "\\");
			
			// Als de klassenaam een proxy is, haal dan de class op van de parent
			$normalizedClass = $this->normalizeEntityName($entityClass);
			
			// If the class lacks a namespace, add it
			$normalizedClass = $this->addNamespaceToEntityName($normalizedClass);
			
			// Initialiseer een lege array om de resultaten in op te slaan.
			$result = [];
			
			// Haal alle bekende entiteitsafhankelijkheden op.
			$dependencies = $this->getAllEntityDependencies();
			
			// Loop door alle entiteiten en hun afhankelijkheden.
			foreach($dependencies as $entity => $entityDependencies) {
				// Als de opgegeven klasse in de lijst met afhankelijkheden staat,
				// voeg deze dan toe aan het resultaat.
				if (in_array($normalizedClass, $entityDependencies)) {
					$result[] = $entity;
				}
			}
			
			// Retourneer de lijst met afhankelijke entiteiten.
			return $result;
		}
		
		/**
		 * Verkrijgt een topologisch gesorteerde lijst van entiteiten op basis van hun afhankelijkheden.
		 * Deze methode zorgt voor de sortering van entiteiten zodanig dat elke entiteit volgt na alle entiteiten waarvan het afhankelijk is.
		 * Maakt gebruik van de 'topologicalSortUtil' methode voor het daadwerkelijke sorteerproces.
		 * @return array Een array van topologisch gesorteerde entiteiten.
		 * @throws \Exception Als er een cyclische afhankelijkheid wordt gedetecteerd in de entiteiten.
		 */
		public function getTopologicallySortedEntities() : array {
			// Controleer of de gesorteerde entiteiten al zijn bepaald.
			if ($this->topologically_sorted_entities === null) {
				$path = []; // Houdt het huidige pad van de diepte-eerste zoektocht bij.
				$visited = []; // Houdt bij welke entiteiten zijn bezocht.
				$sorted = []; // De uiteindelijke gesorteerde lijst van entiteiten.
				$dependencies = $this->getAllEntityDependencies(); // Verkrijg alle afhankelijkheden.
				
				// Doorloop alle entiteiten en voer topologische sortering uit.
				foreach (array_keys($dependencies) as $entity) {
					// Maak gebruik van 'topologicalSortUtil' voor het sorteren.
					// Als er een cyclische afhankelijkheid wordt gedetecteerd, gooi een uitzondering.
					if (!$this->topologicalSortUtil($entity, $visited, $path, $sorted, $dependencies)) {
						throw new \Exception("Cyclische afhankelijkheid gedetecteerd");
					}
				}
				
				// Sla het resultaat op voor latere oproepen.
				$this->topologically_sorted_entities = $sorted;
			}
			
			// Retourneer de gesorteerde lijst van entiteiten.
			return $this->topologically_sorted_entities;
		}
    }