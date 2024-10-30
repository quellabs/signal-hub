<?php
    
    namespace Services\EntityManager;

    use Services\AnnotationsReader\Annotations\Orm\Column;
    use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
    use Services\AnnotationsReader\Annotations\Orm\OneToOne;
    use Services\EntityManager\Persister\DeletePersister;
    use Services\EntityManager\Persister\InsertPersister;
    use Services\EntityManager\Persister\UpdatePersister;
    use Services\Kernel\Kernel;
    
    class DirtyState extends \BasicEnum {
        const None = 0;
        const Dirty = 1;
        const New = 2;
        const Deleted = 3;
        const NotManaged = 4;
    }
	
	class UnitOfWork {
		
		protected array $original_entity_data;
		protected array $identity_map;
		protected array $int_types;
		protected array $float_types;
		protected array $char_types;
		protected array $entity_removal_list;
		protected array $normalizers;
        protected PropertyHandler $property_handler;
        protected EntityStore $entity_store;
        protected ReflectionHandler $reflection_handler;
		protected ProxyGenerator $proxy_handler;
        protected EntityManager $entity_manager;
        protected ?DatabaseAdapter $connection;
        protected InsertPersister $insert_persister;
		protected UpdatePersister $update_persister;
		protected DeletePersister $delete_persister;

		/**
		 * UnitOfWork constructor.
		 * @param EntityManager $entityManager
		 * @throws \Exception
		 */
        public function __construct(EntityManager $entityManager) {
            $this->entity_manager = $entityManager;
            $this->property_handler = $entityManager->getKernel()->getService(PropertyHandler::class);
            $this->entity_store = $entityManager->getKernel()->getService(EntityStore::class);
            $this->reflection_handler = $entityManager->getKernel()->getService(ReflectionHandler::class);
            $this->proxy_handler = $entityManager->getKernel()->getService(ProxyGenerator::class);
            $this->connection = $entityManager->getConnection();
            $this->insert_persister = new InsertPersister($this);
            $this->update_persister = new UpdatePersister($this);
            $this->delete_persister = new DeletePersister($this);
            $this->original_entity_data = [];
            $this->entity_removal_list = [];
            $this->identity_map = [];
			$this->int_types = ["int", "integer", "smallint", "tinyint", "mediumint", "bigint", "bit"];
			$this->float_types = ["decimal", "numeric", "float", "double", "real"];
			$this->char_types = ['text', 'varchar','char'];
			$this->normalizers = [];
			
			$this->initializeNormalizers();
        }
		
		/**
		 * Deze functie initialiseert alle entiteiten in de "Entity"-directory.
		 * @return void
		 */
		private function initializeNormalizers(): void {
			// Ophalen van alle bestandsnamen in de "Entity"-directory.
			$normalizerFiles = scandir(dirname(__FILE__) . DIRECTORY_SEPARATOR . "Normalizer");
			
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
		 * Returns true if the entity has no populated primary keys, false if it does.
		 * @param object $entity
		 * @param array $primaryKeys
		 * @return bool
		 */
		private function hasNullPrimaryKeys(object $entity, array $primaryKeys): bool {
			return array_reduce($primaryKeys, function ($carry, $primaryKey) use ($entity) {
				return $carry || is_null($this->property_handler->get($entity, $primaryKey));
			}, false);
		}
		
		/**
		 * Returns true if any of the entity columns changed, false if not.
		 * @param array $extractedEntity
		 * @param array $originalData
		 * @return bool
		 */
		private function isEntityDirty(array $extractedEntity, array $originalData): bool {
			return !empty(array_filter($extractedEntity, function ($value, $key) use ($originalData) {
				return ($value !== $originalData[$key]);
			}, ARRAY_FILTER_USE_BOTH));
		}
		
		/**
		 * Check if an annotation is a valid Column annotation.
		 * @param mixed $annotation The annotation to check.
		 * @return bool True if valid, false otherwise.
		 */
		private function isValidColumnAnnotation(mixed $annotation): bool {
			return $annotation instanceof Column;
		}
		
		/**
		 * Controleert of een entiteit overeenkomt met de gegeven primaire sleutels.
		 * @param mixed $entity De te controleren entiteit.
		 * @param array $primaryKeys De primaire sleutels waarmee vergeleken wordt.
		 * @return bool Retourneert true als de entiteit overeenkomt, anders false.
		 */
		private function isMatchingEntity(mixed $entity, array $primaryKeys): bool {
			// Itereren over de sleutels van de primaire sleutels.
			foreach ($primaryKeys as $key => $value) {
				// Vergelijken van de waarden en bijwerken van de $isMatching variabele.
				if ($this->property_handler->get($entity, $key) !== $value) {
					return false;
				}
			}
			
			return true;
		}
		
		/**
		 * Maakt de identity map plat terwijl de unieke sleutels behouden blijven.
		 * @return array
		 */
		private function getFlattenedIdentityMap(): array {
			$result = [];
			
			foreach ($this->identity_map as $subArray) {
				foreach ($subArray as $key => $value) {
					$result[$key] = $value;
				}
			}
			
			return $result;
		}
		
		/**
		 * Sorteert een array van entiteiten op basis van hun ManyToOne-relaties.
		 * Gebruikt topologische sortering om ervoor te zorgen dat 'parent' entiteiten
		 * eerst komen en 'child' entiteiten later. Dit is noodzakelijk om de integriteit
		 * van database-relaties te handhaven bij het invoegen of bijwerken van records.
		 * @return array De gesorteerde entiteiten.
		 * @throws OrmException Wanneer er een cyclus wordt gedetecteerd in de entiteitsrelaties,
		 * wat wijst op een onoplosbare afhankelijkheid tussen entiteiten.
		 */
		private function scheduleEntities(): array {
			// Initialiseer de datastructuren voor de topologische sortering.
			$graph = [];       // Adjacency list representatie van de entiteitengrafiek.
			$inDegree = [];    // Aantal inkomende edges per entiteit, voor detectie van 'roots'.
			$flattenedIdentityMap = $this->getFlattenedIdentityMap(); // Platte map van alle entiteiten.
			
			// Voorbereiden van de grafiek en inDegree tellers voor elke entiteit.
			foreach ($flattenedIdentityMap as $hash => $entity) {
				$graph[$hash] = [];
				$inDegree[$hash] = 0;
			}
			
			// Bepaal de relaties tussen entiteiten en vul de grafiek en inDegree gegevens.
			foreach ($flattenedIdentityMap as $hash => $entity) {
				$manyToOneParents = $this->entity_store->getManyToOneDependencies($entity);
				$oneToOneParents = $this->entity_store->getOneToOneDependencies($entity);
				$oneToOneParents = array_filter($oneToOneParents, function($e) { return !empty($e->getInversedBy()); });
				
				foreach (array_merge($manyToOneParents, $oneToOneParents) as $property => $annotation) {
					$parentEntity = $this->property_handler->get($entity, $property);
					
					// Als er geen 'parent' entiteit is, sla dan deze iteratie over.
					if ($parentEntity === null) {
						continue;
					}
					
					$parentId = spl_object_hash($parentEntity);
					$graph[$parentId][] = $hash; // Voeg de huidige entiteit toe als 'child' van de 'parent'.
					$inDegree[$hash]++; // Verhoog de inDegree teller voor de 'child' entiteit.
				}
			}
			
			// Voer de topologische sortering uit met behulp van een queue.
			$queue = []; // Queue voor entiteiten met inDegree 0 (geen inkomende dependencies).
			$result = []; // Resultaatlijst van gesorteerde entiteiten.
			
			// Voeg alle 'root' entiteiten (met inDegree 0) toe aan de queue.
			foreach ($inDegree as $id => $degree) {
				if ($degree === 0) {
					$queue[] = $id;
				}
			}
			
			// Verwerk de queue tot deze leeg is.
			while (!empty($queue)) {
				$current = array_shift($queue); // Verwijder en retourneer het eerste element van de queue.
				$result[] = $current; // Voeg het toe aan het resultaat.
				
				// Verminder de inDegree van alle 'child' entiteiten en voeg toe aan de queue als inDegree 0 wordt.
				foreach ($graph[$current] as $neighbor) {
					$inDegree[$neighbor]--;
					
					if ($inDegree[$neighbor] === 0) {
						$queue[] = $neighbor;
					}
				}
			}
			
			// Controleer of er een cyclus is door te vergelijken of alle entiteiten zijn verwerkt.
			if (count($result) !== count($flattenedIdentityMap)) {
				throw new OrmException("Er is een cyclus in de entiteitsrelaties.");
			}
			
			// Converteer de gesorteerde lijst van hashes terug naar de daadwerkelijke entiteiten.
			return array_map(fn($id) => $flattenedIdentityMap[$id], $result);
		}
		
		/**
		 * Controleert of een gegeven kolomtype een integer-type is.
		 * @param string $columnType Het kolomtype om te controleren.
		 * @return bool True als het kolomtype een integer-type is, anders false.
		 */
		private function isIntColumnType(string $columnType): bool {
			return in_array($columnType, $this->int_types);
		}
		
		/**
		 * Controleert of een gegeven kolomtype een float-type is.
		 * @param string $columnType Het kolomtype om te controleren.
		 * @return bool True als het kolomtype een float-type is, anders false.
		 */
		private function isFloatColumnType(string $columnType): bool {
			return in_array($columnType, $this->float_types);
		}
		
		/**
		 * Controleert of een gegeven entiteit aanwezig is in de identity map.
		 * @param mixed $entity De entiteit om te controleren.
		 * @return bool Retourneert true als de entiteit in de identity map zit, anders false.
		 */
		private function isInIdentityMap($entity): bool {
			// Ophalen van de klassennaam van de entiteit.
			$normalizedEntityName = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Controleren of de klassennaam niet aanwezig is in de identity map.
			if (!isset($this->identity_map[$normalizedEntityName])) {
				return false;
			}
			
			// Controleren of het object zelf aanwezig is in de identity map.
			return array_key_exists(spl_object_hash($entity), $this->identity_map[$normalizedEntityName]);
		}
        
        /**
         * Haalt de kolom op waarop de relatie is gebaseerd.
         * @param string $entityType Het type entiteit waarop de relatie betrekking heeft.
         * @param object $relation Het relatie-object.
         * @return string De naam van de relatiekolom.
         */
        private function getRelationColumn(string $entityType, object $relation): string {
            // Haal de standaard relatiekolom op uit het relatie-object
            $relationColumn = $relation->getRelationColumn();
            
            // Als er geen standaard relatiekolom is ingesteld, gebruik dan het eerste identificatiekenmerk van de entiteit
            if ($relationColumn === null) {
                $keys = $this->entity_store->getIdentifierKeys($entityType);
                $relationColumn = $keys[0];
            }
            
            return $relationColumn;
        }

		/**
		 * Deze functie haalt de ouderentiteit en de bijbehorende ManyToOne annotatie op
		 * voor de meegegeven entiteit. Als de ouderentiteit niet bestaat, wordt null geretourneerd.
		 * @param mixed $entity De entiteit waarvoor de ouderentiteit en annotatie worden opgevraagd.
		 * @return array Een associatieve array met 'entity' en 'annotation' als sleutels, of null als niet gevonden.
		 */
		private function fetchParentEntitiesPrimaryKeyData($entity): array {
			// Initialiseer een lege array om de resultaten op te slaan.
			$result = [];
			
			// Haal alle annotaties op die bij de gegeven entiteit horen.
			$annotationList = $this->entity_store->getAnnotations($entity);
			
			// Loop door elke set van annotaties voor elke eigenschap van de entiteit.
			foreach ($annotationList as $property => $annotations) {
				// Loop door de annotaties voor een enkele eigenschap van de entiteit.
				foreach ($annotations as $annotation) {
					// Controleer of de huidige annotatie een ManyToOne-annotatie is.
					if (
						!($annotation instanceof ManyToOne) &&
						(!($annotation instanceof OneToOne) || is_null($annotation->getInversedBy()))
					) {
						continue;
					}
					
					// Gebruik de property_handler om de waarde van de gerelateerde ouderentiteit op te halen.
					$parentEntity = $this->property_handler->get($entity, $property);
					
					// Als de ouderentiteit bestaat, voeg deze dan toe aan het resultaat.
					if (!empty($parentEntity)) {
						$result[] = [
							'entity'     => $parentEntity, // De ouderentiteit zelf
							'property'   => $annotation->getRelationColumn(), // De naam van de eigenschap die de relatie definieert
							'value'      => $this->property_handler->get($parentEntity, $annotation->getInversedBy()) // De waarde van de inverse relatie
						];
					}
					
					// We hoeven niet meer annotaties voor deze property in te lezen.
					// Ga naar de volgende property.
					continue 2;
				}
			}
			
			// Retourneer de gevonden ouderentiteiten als een array.
			return $result;
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
		public function normalizeValue(object $annotation, $value): mixed {
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
			if (is_null($value) && $annotation->hasDefault()) {
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
		 * Find an entity based on its class and primary keys.
		 * @param string $class The fully qualified name of the class to find.
		 * @param array $primaryKeys The serialized primary key data of the entity
		 * @return mixed|null The found entity or null if not found.
		 */
		public function findEntity(string $class, array $primaryKeys): mixed {
			// Normalize the entity name for dealing with proxies
			$normalizedEntityName = $this->getEntityStore()->normalizeEntityName($class);
			
			// Check if the class exists in the identity map
			if (!array_key_exists($normalizedEntityName, $this->identity_map)) {
				return null;
			}
			
			// Loop through each entity of the given class in the identity map
			foreach ($this->identity_map[$normalizedEntityName] as $entity) {
				// Check if the entity matches the given primary keys
				if ($this->isMatchingEntity($entity, $primaryKeys)) {
					return $entity;
				}
			}
			
			// Return null if no matching entity is found
			return null;
		}
		
		/**
		 * Bepaalt de staat van een entiteit (bijv. nieuw, gewijzigd, niet beheerd, etc.).
		 * @param mixed $entity De entiteit waarvan de staat moet worden bepaald.
		 * @return int De staat van de entiteit, vertegenwoordigd als een constante uit DirtyState.
		 */
		private function getEntityState(mixed $entity): int {
			// Controleert of de entiteit niet wordt beheerd.
			if (!$this->isInIdentityMap($entity)) {
				return DirtyState::NotManaged;
			}
			
			// Class en hash van de entiteit object voor identificatie.
			$entityHash = spl_object_hash($entity);
			$entityClass = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Controleert of dit een ongeladen proxy is. Zo ja, dan mag deze niet gepersisteerd worden.
			if ($this->identity_map[$entityClass][$entityHash] instanceof ProxyInterface) {
				if (!$this->identity_map[$entityClass][$entityHash]->isInitialized()) {
					return DirtyState::None;
				}
			}
			
			// Controleert of de entiteit voorkomt in de deleted list, zo ja, dan is de state Deleted
			if (in_array($entityHash, $this->entity_removal_list)) {
				return DirtyState::Deleted;
			}
			
			// Controleert of de entiteit nieuw is op basis van de afwezigheid van originele data.
			if (!isset($this->original_entity_data[$entityHash])) {
				return DirtyState::New;
			}
			
			// Controleert of de entiteit nieuw is op basis van de afwezigheid van primaire sleutels.
			$primaryKeys = $this->entity_store->getIdentifierKeys($entity);
			
			if ($this->hasNullPrimaryKeys($entity, $primaryKeys)) {
				return DirtyState::New;
			}
			
			// Controleert of de entiteit gewijzigd is ten opzichte van de originele data.
			$serializedEntity = $this->serializeEntity($entity);
			$originalData = $this->getOriginalEntityData($entity);
			
			if ($this->isEntityDirty($serializedEntity, $originalData)) {
				return DirtyState::Dirty;
			}
			
			// Als geen van de bovenstaande voorwaarden waar is, dan is de entiteit niet gewijzigd.
			return DirtyState::None;
		}

        /**
         * Returns the database link
         * @return DatabaseAdapter
         */
        public function getConnection(): DatabaseAdapter {
            return $this->connection;
        }
    
        /**
         * Returns the property handler object
         * @return PropertyHandler
         */
        public function getPropertyHandler(): PropertyHandler {
            return $this->property_handler;
        }
    
        /**
         * Returns the entity store object
         * @return EntityStore
         */
        public function getEntityStore(): EntityStore {
            return $this->entity_store;
        }

        /**
         * Returns the entity manager object
         * @return EntityManager
         */
        public function getEntityManager(): EntityManager {
            return $this->entity_manager;
        }
		
		/**
		 * Serializes an entity object into an associative array.
		 * This function takes an entity object as input and converts it into an associative array.
		 * Each property of the entity is dehydrated based on its annotations. The resulting array
		 * is then returned, or null if the entity does not exist in the store.
		 * @param object $entity The entity object to serialize.
		 * @return array|null Returns the associative array representing the serialized entity.
		 */
		public function serializeEntity(object $entity): ?array {
			// Early return if the entity does not exist in the entity store.
			if (!$this->entity_store->exists($entity)) {
				return null;
			}
			
			// Retrieve annotations for the entity class.
			$annotationList = $this->entity_store->getAnnotations($entity);
			
			// Iterate through each property's annotations.
			$result = [];
			
			foreach ($annotationList as $property => $annotations) {
				// Check each annotation for validity.
				foreach ($annotations as $annotation) {
					// Skip this iteration if the annotation is not a valid Column annotation.
					if (!$this->isValidColumnAnnotation($annotation)) {
						continue;
					}
					
					// Get and store the property's current value.
					$result[$property] = $this->property_handler->get($entity, $property);
					
					// Skip to the next property (this ends the inner foreach loop and moves to the next property).
					continue 2;
				}
			}
			
			return $result;
		}
		
		/**
		 * Maps SQL data to an entity-compatible array using annotations.
		 * @param object $entity The entity to populate
		 * @param array $data The raw SQL data to map.
		 * @return void The mapped entity data.
		 */
		public function deserializeEntity(object $entity, array $data): void {
			// Retrieve annotations for the entity class to understand how to map properties
			$annotationList = $this->entity_store->getAnnotations($entity);
			
			// Loop through each property's annotations to check how each should be handled
			foreach ($annotationList as $property => $annotations) {
				foreach ($annotations as $annotation) {
					// Skip this property if its annotation doesn't qualify as a valid SQL Column
					if (!$this->isValidColumnAnnotation($annotation)) {
						continue;
					}
					
					// Skip this property if the provided data array doesn't contain this column name
					if (!array_key_exists($property, $data)) {
						continue 2;
					}
					
					// Normalize the value for this property based on its annotation and add it to the mapped data
					$this->property_handler->set($entity, $property, $this->normalizeValue($annotation, $data[$property]));
					
					// Skip to the next property
					continue 2;
				}
			}
		}
		
		/**
		 * Maps the given entity data to an SQL-compatible array using annotations.
		 * @param mixed $class The fully qualified class name of the entity.
		 * @param array $data The raw entity data to map.
		 * @return array The mapped entity data suitable for SQL operations.
		 */
		public function convertToSQL(mixed $class, array $data): array {
			// Initialize an empty array to store the mapped data
			$mappedData = [];
			
			// Retrieve annotations for the entity class to understand how to map properties
			$annotationList = $this->entity_store->getAnnotations($class);
			
			// Loop through each property's annotations to check how each should be handled
			foreach ($annotationList as $property => $annotations) {
				foreach ($annotations as $annotation) {
					// Skip this property if its annotation doesn't qualify as a valid SQL Column
					if (!$this->isValidColumnAnnotation($annotation)) {
						continue;
					}
					
					// Obtain the SQL column name from the annotation
					$columnName = $annotation->getName();
					
					// Skip this property if the provided data array doesn't contain this column name
					if (!array_key_exists($property, $data)) {
						continue 2;
					}
					
					// Normalize the value for this property based on its annotation and add it to the mapped data
					$mappedData[$columnName] = $this->denormalizeValue($annotation, $data[$property]);
					
					// Skip to the next property
					continue 2;
				}
			}
			
			// Return the final mapped data array suitable for SQL operations
			return $mappedData;
		}
		
		/**
		 * Gets the original data of an entity. The original data is the data that was
		 * present at the time the entity was reconstituted from the database.
		 * @param mixed $entity
		 * @return array|null
		 */
		public function getOriginalEntityData(mixed $entity): ?array {
			return $this->original_entity_data[spl_object_hash($entity)] ?? null;
		}
		
		/**
		 * Adds a new entity to the entity manager's identity map.
		 * @param mixed $entity The entity to persist.
		 * @return bool True if the entity was successfully persisted, false otherwise.
		 */
		public function persistNew(mixed $entity): bool {
			// Check if the entity exists in the entity store
			if (!$this->entity_store->exists($entity)) {
				return false;
			}
			
			// Check if the entity is already in the identity map
			if ($this->isInIdentityMap($entity)) {
				return false;
			}
			
			// Get the class name of the entity
			$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Generate a unique hash for the entity
			$hash = spl_object_hash($entity);
			
			// Add the entity to the identity map
			$this->identity_map[$class][$hash] = $entity;
			
			return true;
		}
		
		/**
		 * Adds an existing entity to the entity manager's identity map.
		 * @param mixed $entity The entity to persist.
		 * @return void
		 */
		public function persistExisting(mixed $entity): void {
			// Check if the entity exists in the entity store
			if (!$this->entity_store->exists($entity)) {
				return;
			}
			
			// Check if the entity is already in the identity map
			if ($this->isInIdentityMap($entity)) {
				return;
			}
			
			// Generate a unique hash for the entity
			$hash = spl_object_hash($entity);
			
			// Get the class name of the entity
			$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Add the entity to the identity map
			$this->identity_map[$class][$hash] = $entity;
			
			// Store the original data of the entity for later comparison
			$this->original_entity_data[$hash] = $this->serializeEntity($entity);
		}
		
		/**
		 * Verwerkt en synchroniseert alle geplande entiteiten met de database.
		 * Dit omvat het starten van een transactie, het uitvoeren van de nodige operaties (invoegen, bijwerken, verwijderen)
		 * op basis van de staat van elke entiteit, en het commiten van de transactie. In geval van een fout
		 * wordt de transactie teruggedraaid en de fout doorgestuurd.
		 * @throws OrmException als er een fout optreedt tijdens het databaseproces.
		 */
		public function flush(): void {
			// Plan de entiteiten op basis van hun relaties voor verwerking.
			$sortedEntities = $this->scheduleEntities();
			
			// Als er geen entiteiten zijn om te verwerken, maak de identiteitskaart leeg en keer terug.
			if (empty($sortedEntities)) {
				$this->clear();
				return;
			}
			
			try {
				// Start een database transactie.
				$this->connection->beginTrans();
				
				// Bepaal de staat van elke entiteit en voer de overeenkomstige actie uit.
				foreach ($sortedEntities as $entity) {
					// Kopieer de primaire sleutels van de bovenliggende entiteit naar deze entiteit, indien beschikbaar.
					// Dit gebeurt alleen als de relatie niet zelf-referentieel is.
					foreach($this->fetchParentEntitiesPrimaryKeyData($entity) as $parentEntity) {
						$this->property_handler->set($entity, $parentEntity["property"], $parentEntity["value"]);
					}
					
					// Haal de staat van de entiteit op.
					$entityState = $this->getEntityState($entity);
					
					// Voer de overeenkomstige database-operatie uit op basis van de staat van de entiteit.
					if ($entityState === DirtyState::New) {
						$this->insert_persister->persist($entity); // Invoegen als de entiteit nieuw is.
					} elseif ($entityState === DirtyState::Dirty) {
						$this->update_persister->persist($entity); // Bijwerken als de entiteit gewijzigd is.
					} elseif ($entityState === DirtyState::Deleted) {
						$this->delete_persister->persist($entity); // Verwijderen als de entiteit gemarkeerd is voor verwijdering.
					}
				}
				
				// Commit de transactie na succesvolle verwerking.
				$this->connection->commitTrans();
				
				// Maak de identiteitskaart leeg na het verwerken van de entiteiten.
				$this->clear();
			} catch (OrmException $e) {
				// Draai de transactie terug als er een fout optreedt.
				$this->connection->rollbackTrans();
				
				// Gooi de uitzondering opnieuw om afhandeling verderop mogelijk te maken.
				throw $e;
			}
		}

		/**
		 * Clear the entity map
		 * @return void
		 */
		public function clear(): void {
			$this->identity_map = [];
			$this->original_entity_data = [];
			$this->entity_removal_list = [];
		}
		
		/**
		 * Detach an entity from the EntityManager.
		 * This will remove the entity from the identity map and stop tracking its changes.
		 * @param object $entity The entity to detach.
		 * @return void
		 */
		public function detach(object $entity): void {
			// Generate a unique hash for the entity based on its object hash
			$hash = spl_object_hash($entity);
			
			// Get the class name of the entity for identity map look-up
			$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Remove the entity from the identity map, effectively detaching it
			unset($this->identity_map[$class][$hash]);
			
			// Remove stored original data for the entity, stopping any tracking of changes
			unset($this->original_entity_data[$hash]);
		}
		
		/**
		 * Adds an entity to the removal list
		 * @param object $entity
		 * @return void
		 */
		public function remove(object $entity): void {
			$this->entity_removal_list[] = spl_object_hash($entity);
		}
	}