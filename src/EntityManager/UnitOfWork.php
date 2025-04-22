<?php
    
    namespace Quellabs\ObjectQuel\EntityManager;

    use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm\ManyToOne;
    use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm\OneToOne;
    use Quellabs\ObjectQuel\EntityManager\Persister\DeletePersister;
    use Quellabs\ObjectQuel\EntityManager\Persister\InsertPersister;
    use Quellabs\ObjectQuel\EntityManager\Persister\UpdatePersister;
    use Quellabs\ObjectQuel\EntityManager\Serializers\SQLSerializer;
   use Quellabs\ObjectQuel\Kernel\PropertyHandler;
    
    class UnitOfWork {
	    
	    protected array $original_entity_data;
	    protected array $identity_map;
	    protected array $entity_removal_list;
	    protected array $normalizers;
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
				$manyToOneParents = $this->getEntityStore()->getManyToOneDependencies($entity);
				$oneToOneParents = $this->getEntityStore()->getOneToOneDependencies($entity);
				$oneToOneParents = array_filter($oneToOneParents, function($e) { return !empty($e->getInversedBy()); });
				
				foreach (array_merge($manyToOneParents, $oneToOneParents) as $property => $annotation) {
					$parentEntity = $this->property_handler->get($entity, $property);
					
					// Als er geen 'parent' entiteit is, sla dan deze iteratie over.
					if ($parentEntity === null) {
						continue;
					}
					
					// Als de parent entiteit een ongeinitialiseerde proxy is, sla dan de iteratie over.
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
		 * Find an entity based on its class and primary keys.
		 * @template T of object
		 * @param class-string<T> $entityType Het type van de entiteit die gezocht wordt.
		 * @param array $primaryKeys The serialized primary key data of the entity
		 * @return object|null De gevonden entiteit of null als deze niet gevonden wordt.
		 */
        public function findEntity(string $entityType, array $primaryKeys): ?object {
            // Normalize the entity name for dealing with proxies
            $normalizedEntityName = $this->getEntityStore()->normalizeEntityName($entityType);
            
            // Check if the class exists in the identity map and return null if it doesn't
            if (empty($this->identity_map[$normalizedEntityName])) {
                return null;
            }
            
            // Converteer de primary keys naar een string
			$primaryKeyString = $this->convertPrimaryKeysToString($primaryKeys);
			
			// Kijk of de entity voorkomt in de identity map
			$hash = $this->identity_map[$normalizedEntityName]['index'][$primaryKeyString] ?? null;
			return $hash !== null ? $this->identity_map[$normalizedEntityName][$hash] : null;
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
			$entityHash = spl_object_id($entity);
			
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
			$originalData = $this->getOriginalEntityData($entity);
			$serializedEntity = $this->getSerializer()->serialize($entity);
			
			if ($this->isEntityDirty($serializedEntity, $originalData)) {
				return DirtyState::Dirty;
			}
			
			// Als geen van de bovenstaande voorwaarden waar is, dan is de entiteit niet gewijzigd.
			return DirtyState::None;
		}

        /**
         * Returns the database link
         * @return \clsDB
         */
        public function getDB(): \clsDB {
            return $this->db;
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
		 * Verwerkt en synchroniseert alle geplande entiteiten met de database.
		 * Dit omvat het starten van een transactie, het uitvoeren van de nodige operaties (invoegen, bijwerken, verwijderen)
		 * op basis van de staat van elke entiteit, en het commiten van de transactie. In geval van een fout
		 * wordt de transactie teruggedraaid en de fout doorgestuurd.
		 * @param mixed|null $entity
		 * @return void
		 * @throws OrmException als er een fout optreedt tijdens het databaseproces.
		 */
		public function commit(mixed $entity = null): void {
			try {
				// Bepaal de lijst van entities om te verwerken
				if ($entity === null) {
					$sortedEntities = $this->scheduleEntities();
				} elseif (is_array($entity)) {
					$sortedEntities = $entity;
				} else {
					$sortedEntities = [$entity];
				}
				
				if (!empty($sortedEntities)) {
					// Instantieer hulp classes
					$serializer = $this->getSerializer();
					$insertPersister = new InsertPersister($this);
					$updatePersister = new UpdatePersister($this);
					$deletePersister = new DeletePersister($this);
					
					// Start een database transactie.
					$this->connection->beginTrans();
					
					// Bepaal de staat van elke entiteit en voer de overeenkomstige actie uit.
                    $changed = [];
                    $deleted = [];
                    
					foreach ($sortedEntities as $entity) {
						// Kopieer de primaire sleutels van de bovenliggende entiteit naar deze entiteit, indien beschikbaar.
						// Dit gebeurt alleen als de relatie niet zelf-referentieel is.
						foreach($this->fetchParentEntitiesPrimaryKeyData($entity) as $parentEntity) {
							$this->property_handler->set($entity, $parentEntity["property"], $parentEntity["value"]);
						}
						
						// Haal de staat van de entiteit op.
						$entityState = $this->getEntityState($entity);
						
						// Voer de overeenkomstige database-operatie uit op basis van de staat van de entiteit.
                        if ($entityState === DirtyState::Deleted) {
                            $deleted[] = $entity; // Voeg entity toe aan de deleted lijst
                            $deletePersister->persist($serializer, $entity); // Verwijderen als de entiteit gemarkeerd is voor verwijdering.
                        } elseif (($entityState === DirtyState::New) || ($entityState === DirtyState::Dirty)) {
                            $changed[] = $entity; // Voeg entity toe aan de changed lijst
                            
                            if ($entityState === DirtyState::New) {
                                $insertPersister->persist($serializer, $entity); // Invoegen als de entiteit nieuw is.
                            } else {
                                $updatePersister->persist($serializer, $entity); // Bijwerken als de entiteit gewijzigd is.
                            }
						}
					}
					
					// Commit de transactie na succesvolle verwerking.
					$this->connection->commitTrans();
					
					// Update de identity map en reset change tracking
					$this->updateIdentityMapAndResetChangeTracking($changed, $deleted);
				}
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
		}
		
		/**
		 * Adds an entity to the removal list
		 * @param object $entity
		 * @return void
		 */
		public function remove(object $entity): void {
			$this->entity_removal_list[] = spl_object_id($entity);
		}
	 
	}