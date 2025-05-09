<?php
	
	/**
	 * ObjectQuel - A Sophisticated Object-Relational Mapping (ORM) System
	 *
	 * ObjectQuel is an ORM that brings a fresh approach to database interaction,
	 * featuring a unique query language, a streamlined architecture, and powerful
	 * entity relationship management. It implements the Data Mapper pattern for
	 * clear separation between domain models and underlying database structures.
	 *
	 * @author      Floris van den Berg
	 * @copyright   Copyright (c) 2025 ObjectQuel
	 * @license     MIT
	 * @version     1.0.0
	 * @package     Quellabs\ObjectQuel
	 */
	
	namespace Quellabs\ObjectQuel;

    use Quellabs\ObjectQuel\Annotations\Orm\Cascade;
    use Quellabs\ObjectQuel\Annotations\Orm\ManyToOne;
    use Quellabs\ObjectQuel\Annotations\Orm\OneToOne;
    use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
    use Quellabs\ObjectQuel\Persistence\DeletePersister;
    use Quellabs\ObjectQuel\Persistence\InsertPersister;
    use Quellabs\ObjectQuel\Persistence\UpdatePersister;
    use Quellabs\ObjectQuel\ProxyGenerator\ProxyInterface;
    use Quellabs\ObjectQuel\ReflectionManagement\PropertyHandler;
    use Quellabs\ObjectQuel\Serialization\Serializers\SQLSerializer;
    
    class UnitOfWork {
	    
	    protected array $original_entity_data;
	    protected array $identity_map;
	    protected array $entity_removal_list;
	    protected EntityManager $entity_manager;
	    protected EntityStore $entity_store;
	    protected PropertyHandler $property_handler;
	    protected ?SQLSerializer $serializer;
		protected ?DatabaseAdapter $connection;

		/**
		 * UnitOfWork constructor.
		 * @param EntityManager $entityManager
		 */
        public function __construct(EntityManager $entityManager) {
	        $this->connection = $entityManager->getConnection();
	        $this->entity_manager = $entityManager;
	        $this->entity_store = $entityManager->getEntityStore();
	        $this->property_handler = new PropertyHandler();
	        $this->serializer = new SQLSerializer($entityManager->getEntityStore());
	        $this->original_entity_data = [];
	        $this->entity_removal_list = [];
	        $this->identity_map = [];
        }
		
		/**
		 * Returns true if the entity has no populated primary keys, false if it does.
		 * @param object $entity
		 * @param array $primaryKeys
		 * @return bool
		 */
		private function hasNullPrimaryKeys(object $entity, array $primaryKeys): bool {
			foreach ($primaryKeys as $primaryKey) {
				if ($this->property_handler->get($entity, $primaryKey) === null) {
					return true;
				}
			}

			return false;
		}
		
		/**
		 * Returns true if any of the entity columns changed, false if not.
		 * @param array $extractedEntity
		 * @param array $originalData
		 * @return bool
		 */
		private function isEntityDirty(array $extractedEntity, array $originalData): bool {
			foreach ($extractedEntity as $key => $value) {
				if ($value !== $originalData[$key]) {
					return true;
				}
			}

			return false;
		}
	    
	    /**
	     * Checks if an entity is already scheduled for deletion
	     * @param int $entityId The entity's object ID
	     * @return bool
	     */
	    private function isEntityScheduledForDeletion(int $entityId): bool {
		    return isset($this->entity_removal_list[$entityId]);
	    }
		
	    /**
	     * Retrieves a property value from an entity object using getter method or property handler.
	     * @param mixed $entity    The object to extract the value from
	     * @param string $property The property name to retrieve
	     * @return mixed           The value of the requested property
	     */
	    private function getValueFromEntity(mixed $entity, string $property): mixed {
		    // Generate the getter method name by capitalizing the first letter of the property
		    $getterMethod = 'get' . ucfirst($property);
		    
		    // Check if the entity has the getter method and call it if exists
		    if (method_exists($entity, $getterMethod)) {
			    return $entity->$getterMethod();
		    }
		    
		    // Fallback to using the property handler if no getter method exists
		    // This likely accesses properties through alternative means (e.g., reflection)
		    return $this->property_handler->get(get_class($entity), $property);
	    }
		
		/**
		 * Maakt de identity map plat terwijl de unieke sleutels behouden blijven.
		 * @return array
		 */
		private function getFlattenedIdentityMap(): array {
			$result = [];
			
			foreach ($this->identity_map as $subArray) {
				foreach ($subArray as $key => $value) {
					// Gebruik de index niet
					if ($key === 'index') {
						continue;
					}
					
					// Sla niet geïnitialiseerde proxies over
					if (($value instanceof ProxyInterface) && !$value->isInitialized()) {
						continue;
					}
					
					// Gebruik deze entity
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
			$graph = []; // Adjacency list representatie van de entiteitengrafiek.
			$inDegree = []; // Aantal inkomende edges per entiteit, voor detectie van 'roots'.
			$flattenedIdentityMap = $this->getFlattenedIdentityMap(); // Platte map van alle entiteiten.
			
			// Voorbereiden van de grafiek en inDegree tellers voor elke entiteit.
			foreach ($flattenedIdentityMap as $hash => $entity) {
				$graph[$hash] = [];
				$inDegree[$hash] = 0;
			}
			
			// Bepaal de relaties tussen entiteiten en vul de grafiek en inDegree gegevens.
			foreach ($flattenedIdentityMap as $hash => $entity) {
				$manyToOneParents = $this->getEntityStore()->getManyToOneDependencies($entity);
				$oneToOneParents = $this->getEntityStore()->getOneToOneDependencies($entity);
				$oneToOneParents = array_filter($oneToOneParents, function($e) { return !empty($e->getInversedBy()); });
				
				foreach (array_merge($manyToOneParents, $oneToOneParents) as $property => $annotation) {
					$parentEntity = $this->property_handler->get($entity, $property);
					
					// Als er geen 'parent' entiteit is, sla dan deze iteratie over.
					if ($parentEntity === null) {
						continue;
					}
					
					// Als de parent entiteit een niet geïnitialiseerde proxy is, sla dan de iteratie over.
					if (($parentEntity instanceof ProxyInterface) && !$parentEntity->isInitialized()) {
						continue;
					}
					
					// Haal de hash van de parent entiteit op
					$parentId = spl_object_id($parentEntity);
					
					// Voeg de relatie toe aan de grafiek
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
		 * Controleert of een gegeven entiteit aanwezig is in de identity map.
		 * @param mixed $entity De entiteit om te controleren.
		 * @return bool Retourneert true als de entiteit in de identity map zit, anders false.
		 */
		private function isInIdentityMap(mixed $entity): bool {
			// Ophalen van de klassennaam van de entiteit.
			$normalizedEntityName = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Controleren of de klassennaam niet aanwezig is in de identity map.
			if (!isset($this->identity_map[$normalizedEntityName])) {
				return false;
			}
			
			// Controleren of het object zelf aanwezig is in de identity map.
			return isset($this->identity_map[$normalizedEntityName][spl_object_id($entity)]);
		}
        
        /**
         * Update de tracking informatie
         * @param array $changed List of changed entities
         * @param array $deleted List of deleted entities
         * @return void
         */
		private function updateIdentityMapAndResetChangeTracking(array $changed, array $deleted): void {
			foreach ($changed as $entity) {
				// Grab object id
				$hash = spl_object_id($entity);
				
				// Get the class name of the entity
				$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
				
				// Add primary key to index cache for easy lookup
				$primaryKeys = $this->getIdentifiers($entity);
				$primaryKeysString = $this->convertPrimaryKeysToString($primaryKeys);
				$this->identity_map[$class]['index'][$primaryKeysString] = $hash;
				
				// Store the original data of the entity for later comparison
				$this->original_entity_data[$hash] = $this->getSerializer()->serialize($entity);
			}
            
            foreach($deleted as $entity) {
                $this->detach($entity);
            }
        }
		
		/**
		 * Deze functie haalt de ouderentiteit en de bijbehorende ManyToOne annotatie op
		 * voor de meegegeven entiteit. Als de ouderentiteit niet bestaat, wordt null geretourneerd.
		 * @param mixed $entity De entiteit waarvoor de ouderentiteit en annotatie worden opgevraagd.
		 * @return array Een associatieve array met 'entity' en 'annotation' als sleutels, of null als niet gevonden.
		 */
		private function fetchParentEntitiesPrimaryKeyData(mixed $entity): array {
			// Initialiseer een lege array om de resultaten op te slaan.
			$result = [];
			
			// Haal alle annotaties op die bij de gegeven entiteit horen.
			$annotationList = $this->getEntityStore()->getAnnotations($entity);
			
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
		 * Retrieves the identifiers (primary keys) of the given entity.
		 * @param mixed $entity The entity from which to retrieve the primary keys.
		 * @return array An associative array where the keys are the primary key names and
		 *               the values are their corresponding values from the entity.
		 */
		private function getIdentifiers(mixed $entity): array {
			// Fetch the primary key names from the entity store
			$primaryKeys = $this->getEntityStore()->getIdentifierKeys($entity);
			
			// Initialize the result array to hold key-value pairs of primary keys
			$result = [];
			
			// Loop through each primary key name
			foreach ($primaryKeys as $key) {
				// Fetch the corresponding value for each primary key from the entity using the property handler
				$result[$key] = $this->property_handler->get($entity, $key);
			}
			
			// Return the array of primary key names and their corresponding values
			return $result;
		}
		
	    /**
	     * Process cascading deletions for dependent entities
	     * @param object $entity The parent entity
	     * @return void
	     */
	    private function processCascadingDeletions(object $entity): void {
		    $entityClass = get_class($entity);
		    $normalizedClass = $this->entity_store->normalizeEntityName($entityClass);
		    $dependentEntityClasses = $this->entity_store->getDependentEntities($entity);
		    
		    foreach ($dependentEntityClasses as $dependentEntityClass) {
			    $this->processDependentEntityClass($dependentEntityClass, $normalizedClass, $entity);
		    }
	    }
	    
	    /**
	     * Process a specific dependent entity class for cascading deletion
	     * @param string $dependentEntityClass Class name of the dependent entity
	     * @param string $normalizedClass Normalized class name of the parent entity
	     * @param object $entity The parent entity object
	     * @return void
	     */
	    private function processDependentEntityClass(string $dependentEntityClass, string $normalizedClass, object $entity): void {
		    // Get relationships using existing methods in entity_store
		    $manyToOneDependencies = $this->entity_store->getManyToOneDependencies($dependentEntityClass);
		    $oneToOneDependencies = $this->entity_store->getOneToOneDependencies($dependentEntityClass);
		    $oneToOneDependencies = array_filter($oneToOneDependencies, function ($e) { return !empty($e->getInversedBy()); });
		    
		    // Process both relationship types
		    foreach (array_merge($manyToOneDependencies, $oneToOneDependencies) as $property => $annotation) {
			    // Skip if annotation doesn't target our entity
			    if ($annotation->getTargetEntity() !== $normalizedClass) {
				    continue;
			    }
			    
				// Fetch cascade annotation information
			    $cascadeInfo = $this->getCascadeInfo($dependentEntityClass, $property);
			    
			    // Skip if no cascade configuration found or if cascade remove not enabled
			    if (!$cascadeInfo || !$this->shouldCascadeRemove($cascadeInfo)) {
				    continue;
			    }
			    
			    $this->cascadeDeleteDependentObjects($dependentEntityClass, $annotation->getRelationColumn(), $entity);
		    }
	    }
	    
	    /**
	     * Get cascade configuration for a property
	     * @param string $entityClass Entity class name
	     * @param string $property Property name
	     * @return object|null The cascade annotation if found, null otherwise
	     */
	    private function getCascadeInfo(string $entityClass, string $property): ?object {
		    // Retrieve all annotations for the specified entity class from the entity store
		    $entityAnnotations = $this->entity_store->getAnnotations($entityClass);
		    
		    // Check if the specified property exists in the entity annotations
		    // If not, return null immediately since no cascade can exist
		    if (!isset($entityAnnotations[$property])) {
			    return null;
		    }
		    
		    // Filter annotations to find only those that are instances of the Cascade class
		    // This separates cascade annotations from other annotation types
		    $cascadeAnnotations = array_filter($entityAnnotations[$property], function ($a) {
			    return $a instanceof Cascade;
		    });
		    
		    // If no cascade annotations were found for this property, return null
		    if (empty($cascadeAnnotations)) {
			    return null;
		    }
		    
		    // Return the first cascade annotation found
		    // The reset() function returns the first element of an array without affecting the internal pointer
		    return reset($cascadeAnnotations);
	    }
	    
	    /**
	     * Determines if cascading removal should be performed
	     * @param object $cascadeAnnotation The cascade annotation object
	     * @return bool True if cascading removal should be performed
	     */
	    private function shouldCascadeRemove(object $cascadeAnnotation): bool {
		    // Skip if 'remove' operation not present in the cascade operations list
		    if (!in_array('remove', $cascadeAnnotation->getOperations())) {
			    return false;
		    }
		    
		    // Skip database-level cascades (handled by DB itself)
		    if ($cascadeAnnotation->getStrategy() === 'database') {
			    return false;
		    }
		    
		    // Yes, cascading removal should be performed
		    return true;
	    }
		
	    /**
	     * Determines if cascading persist should be performed
	     * @param object $cascadeAnnotation The cascade annotation object
	     * @return bool True if cascading removal should be performed
	     */
	    private function shouldCascadePersist(object $cascadeAnnotation): bool {
		    return in_array('persist', $cascadeAnnotation->getOperations());
	    }
	    
	    /**
	     * Find and schedule deletion of dependent objects
	     * @param string $dependentEntityClass Class name of dependent entity
	     * @param string $property Property name with the relationship
	     * @param object $parentEntity The parent entity object
	     * @return void
	     */
	    private function cascadeDeleteDependentObjects(string $dependentEntityClass, string $property, object $parentEntity): void {
		    // Get the relationship value from the parent entity
		    $propertyValue = $this->getValueFromEntity($parentEntity, $property);
		    
		    // Find dependent objects using the property value
		    $dependentObjects = $this->entity_manager->findBy($dependentEntityClass, [
			    $property => $propertyValue
		    ]);
		    
		    // Schedule each dependent object for deletion
		    foreach ($dependentObjects as $dependentObject) {
			    $this->scheduleForDelete($dependentObject);
		    }
	    }
	    
	    /**
	     * Find an entity based on its class and primary keys.
	     * @template T of object
	     * @param class-string<T> $entityType The type of entity being searched for.
	     * @param array $primaryKeys The serialized primary key data of the entity
	     * @return object|null The found entity or null if it is not found.
	     */
	    public function findEntity(string $entityType, array $primaryKeys): ?object {
		    // Normalize the entity name for dealing with proxies
		    $normalizedEntityName = $this->getEntityStore()->normalizeEntityName($entityType);
		    
		    // Check if the class exists in the identity map and return null if it doesn't
		    if (empty($this->identity_map[$normalizedEntityName])) {
			    return null;
		    }
		    
		    // Convert the primary keys to a string
		    $primaryKeyString = $this->convertPrimaryKeysToString($primaryKeys);
		    
		    // Check if the entity exists in the identity map
		    $hash = $this->identity_map[$normalizedEntityName]['index'][$primaryKeyString] ?? null;
		    return $hash !== null ? $this->identity_map[$normalizedEntityName][$hash] : null;
	    }
	    
	    /**
	     * Determines the state of an entity (e.g., new, modified, not managed, etc.).
	     * @param mixed $entity The entity whose state needs to be determined.
	     * @return int The state of the entity, represented as a constant from DirtyState.
	     */
	    private function getEntityState(mixed $entity): int {
		    // Checks if the entity is not being managed.
		    if (!$this->isInIdentityMap($entity)) {
			    return DirtyState::NotManaged;
		    }
		    
		    // Class and hash of the entity object for identification.
		    $entityHash = spl_object_id($entity);
		    
		    // Checks if the entity appears in the deleted list, if so, then the state is Deleted
		    if ($this->isEntityScheduledForDeletion($entityHash)) {
			    return DirtyState::Deleted;
		    }
		    
		    // Checks if the entity is new based on the absence of original data.
		    if (!isset($this->original_entity_data[$entityHash])) {
			    return DirtyState::New;
		    }
		    
		    // Checks if the entity is new based on the absence of primary keys.
		    $primaryKeys = $this->entity_store->getIdentifierKeys($entity);
		    
		    if ($this->hasNullPrimaryKeys($entity, $primaryKeys)) {
			    return DirtyState::New;
		    }
		    
		    // Checks if the entity has been modified compared to the original data.
		    $originalData = $this->getOriginalEntityData($entity);
		    $serializedEntity = $this->getSerializer()->serialize($entity);
		    
		    if ($this->isEntityDirty($serializedEntity, $originalData)) {
			    return DirtyState::Dirty;
		    }
		    
		    // If none of the above conditions are true, then the entity is not modified.
		    return DirtyState::None;
	    }

        /**
         * Returns the property handler object
         * @return PropertyHandler
         */
        public function getPropertyHandler(): PropertyHandler {
            return $this->property_handler;
        }
    
        /**
         * Returns the entity manager object
         * @return EntityManager
         */
        public function getEntityManager(): EntityManager {
            return $this->entity_manager;
        }
    
        /**
         * Returns the entity store object
         * @return EntityStore
         */
        public function getEntityStore(): EntityStore {
            return $this->entity_store;
        }

		/**
		 * Returns the serializer
		 * @return SQLSerializer
		 */
		public function getSerializer(): SQLSerializer {
			return $this->serializer;
		}
		
		/**
		 * Convert primary keys to a string
		 * @param array $primaryKeys
		 * @return string
		 */
		private function convertPrimaryKeysToString(array $primaryKeys): string {
			// Ensure consistent order
			ksort($primaryKeys);
			
			// Use http_build_query for performance and simplicity
			return str_replace(['&', '='], [';', ':'], http_build_query($primaryKeys));
		}
	    
	    /**
	     * Returns the database adapter
	     * @return DatabaseAdapter|null
	     */
	    public function getConnection(): ?DatabaseAdapter {
		    return $this->connection;
	    }
	    
	    /**
	     * Gets the original data of an entity. The original data is the data that was
	     * present at the time the entity was reconstituted from the database.
	     * @param mixed $entity
	     * @return array|null
	     */
	    public function getOriginalEntityData(mixed $entity): ?array {
		    return $this->original_entity_data[spl_object_id($entity)] ?? null;
	    }
	    
	    /**
		 * Adds an existing entity to the entity manager's identity map.
		 * @param mixed $entity The entity to persist.
		 * @return void
		 */
		public function persistExisting(mixed $entity): void {
			// Check if the entity exists in the entity store
			if (!$this->getEntityStore()->exists($entity)) {
				return;
			}
			
			// Check if the entity is already in the identity map
			if ($this->isInIdentityMap($entity)) {
				return;
			}
			
			// Get the class name of the entity
			$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Index entity by primary key string for quick lookup
			if (!isset($this->identity_map[$class]['index'])) {
				$this->identity_map[$class]['index'] = [];
			}
			
			$hash = spl_object_id($entity);
			$primaryKeys = $this->getIdentifiers($entity);
			$primaryKeysString = $this->convertPrimaryKeysToString($primaryKeys);
			$this->identity_map[$class]['index'][$primaryKeysString] = $hash;
			
			// Add the entity to the identity map
			$this->identity_map[$class][$hash] = $entity;
			
			// Store the original data of the entity for later comparison
			$this->original_entity_data[$hash] = $this->getSerializer()->serialize($entity);
		}
		
		/**
		 * Adds a new entity to the entity manager's identity map.
		 * @param mixed $entity The entity to persist.
		 * @return bool True if the entity was successfully persisted, false otherwise.
		 */
		public function persistNew(mixed $entity): bool {
			// Check if the entity is already in the identity map
			if ($this->isInIdentityMap($entity)) {
				return false;
			}
			
			// Check if the entity exists in the entity store
			if (!$this->getEntityStore()->exists($entity)) {
				return false;
			}
			
			// Get the class name of the entity
			$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Generate hash and primary keys
			$hash = spl_object_id($entity);
			$primaryKeys = $this->getIdentifiers($entity);
			$primaryKeysString = $this->convertPrimaryKeysToString($primaryKeys);
			
			// Add the entity to the identity map
			$this->identity_map[$class][$hash] = $entity;
			
			// Index entity by primary key string for quick lookup
			if (!empty($primaryKeysString)) {
				$this->identity_map[$class]['index'] ??= [];
				$this->identity_map[$class]['index'][$primaryKeysString] = $hash;
			}
			
			return true;
		}
	    
	    /**
	     * Processes and synchronizes all scheduled entities with the database.
	     * This includes starting a transaction, performing the necessary operations (insert, update, delete)
	     * based on the state of each entity, and committing the transaction. In case of an error,
	     * the transaction is rolled back and the error is forwarded.
	     * @param mixed|null $entity
	     * @return void
	     * @throws OrmException if an error occurs during the database process.
	     */
	    public function commit(mixed $entity = null): void {
		    try {
			    // Determine the list of entities to process
			    if ($entity === null) {
				    $sortedEntities = $this->scheduleEntities();
			    } elseif (is_array($entity)) {
				    $sortedEntities = $entity;
			    } else {
				    $sortedEntities = [$entity];
			    }
			    
			    if (!empty($sortedEntities)) {
				    // Instantiate helper classes
				    $insertPersister = new InsertPersister($this);
				    $updatePersister = new UpdatePersister($this);
				    $deletePersister = new DeletePersister($this);
				    
				    // Start a database transaction.
				    $this->connection->beginTrans();
				    
				    // Determine the state of each entity and perform the corresponding action.
				    $changed = [];
				    $deleted = [];
				    
				    foreach ($sortedEntities as $entity) {
					    // Copy the primary keys from the parent entity to this entity, if available.
					    // This only happens if the relationship is not self-referential.
					    foreach($this->fetchParentEntitiesPrimaryKeyData($entity) as $parentEntity) {
						    $this->property_handler->set($entity, $parentEntity["property"], $parentEntity["value"]);
					    }
					    
						// Perform the corresponding database operation based on the state of the entity.
					    switch ($this->getEntityState($entity)) {
						    case DirtyState::New:
							    $changed[] = $entity; // Add entity to the changed list
							    $insertPersister->persist($entity); // Insert if the entity is new.
							    break;
						    
						    case DirtyState::Dirty:
							    $changed[] = $entity; // Add entity to the changed list
							    $updatePersister->persist($entity); // Update if the entity has been modified.
							    break;

						    case DirtyState::Deleted:
							    $deleted[] = $entity; // Add entity to the deleted list
							    $deletePersister->persist($entity); // Delete if the entity is marked for deletion.
							    break;
					    }
				    }
				    
				    // Commit the transaction after successful processing.
				    $this->connection->commitTrans();
				    
				    // Update the identity map and reset change tracking
				    $this->updateIdentityMapAndResetChangeTracking($changed, $deleted);
			    }
		    } catch (OrmException $e) {
			    // Roll back the transaction if an error occurs.
			    $this->connection->rollbackTrans();
			    
			    // Re-throw the exception to allow handling elsewhere.
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
			$hash = spl_object_id($entity);
			
			// Get the class name of the entity for identity map look-up
			$class = $this->getEntityStore()->normalizeEntityName(get_class($entity));
			
			// Remove the entity from the identity map, effectively detaching it
			unset($this->identity_map[$class][$hash]);
			
			// Remove the entity from the identity map index
			$index = array_search($hash, $this->identity_map[$class]['index']);
			
			if ($index !== false) {
				unset($this->identity_map[$class]['index'][$index]);
			}
			
			// Remove stored original data for the entity, stopping any tracking of changes
			unset($this->original_entity_data[$hash]);
			
			// Remove deleted items
			unset($this->entity_removal_list[$hash]);
		}
	    
	    /**
	     * Adds an entity to the removal list and handles cascading delete operations
	     * @param object $entity The entity to schedule for deletion
	     * @return void
	     */
	    public function scheduleForDelete(object $entity): void {
		    $entityId = spl_object_id($entity);
		    
		    // Skip if already scheduled for deletion to prevent duplicate processing
		    if ($this->isEntityScheduledForDeletion($entityId)) {
			    return;
		    }
		    
		    // Mark entity for deletion first (prevents infinite recursion with circular references)
		    $this->entity_removal_list[$entityId] = true;
		    
		    // Process dependent entities that should be cascade deleted
		    $this->processCascadingDeletions($entity);
	    }
	}