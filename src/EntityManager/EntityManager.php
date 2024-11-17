<?php
	
    namespace Services\EntityManager;
	
	use Services\Kernel\Kernel;
	use Services\Kernel\ServiceInterface;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ObjectQuel;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\QuelException;
	use Services\ObjectQuel\QuelResult;
	use Services\Validation\EntityToValidation;
	
	/**
	 * Represents an Entity Manager.
	 */
	class EntityManager implements ServiceInterface {
		protected Kernel $kernel;
        protected DatabaseAdapter $connection;
        protected UnitOfWork $unit_of_work;
        protected ObjectQuel $object_quel;
		protected EntityStore $entity_store;
		protected QueryBuilder $query_builder;
		protected ?string $error_message;
		
		/**
		 * @param Kernel $kernel
		 * @throws \Exception
		 */
        public function __construct(Kernel $kernel) {
            $this->kernel = $kernel;
            $this->connection = new DatabaseAdapter($kernel->getConfiguration());
	        $this->entity_store = new EntityStore();
            $this->unit_of_work = new UnitOfWork($this);
			$this->object_quel = new ObjectQuel($this);
			$this->query_builder = new QueryBuilder($this->entity_store);
            $this->error_message = null;
        }
		
		/**
		 * Normaliseert de primaire sleutel tot een array.
		 * Deze functie controleert of de gegeven primaire sleutel al een array is.
		 * Zo niet, dan wordt de primaire sleutel omgezet naar een array met de juiste sleutel
		 * op basis van de entiteitstype.
		 * @param mixed $primaryKey De primaire sleutel die moet worden genormaliseerd.
		 * @param string $entityType Het type van de entiteit waarvoor de primaire sleutel nodig is.
		 * @return array Een genormaliseerde weergave van de primaire sleutel als een array.
		 */
		private function normalizePrimaryKey(mixed $primaryKey, string $entityType): array {
			// Als de primaire sleutel al een array is, retourneer deze direct.
			if (is_array($primaryKey)) {
				return $primaryKey;
			}
			
			// Zo niet, haal de identifier keys op en maak een array met de juiste sleutel en waarde.
			$identifierKeys = $this->entity_store->getIdentifierKeys($entityType);
			return [$identifierKeys[0] => $primaryKey];
		}
		
		/**
		 * Verwijdert dubbele objecten uit een array op basis van hun object-hash.
		 * Niet-objecten in de array worden ongewijzigd gelaten.
		 * @param array $array De input array met mogelijk dubbele objecten.
		 * @return array Een array met unieke objecten en alle oorspronkelijke niet-object elementen.
		 */
		private function deDuplicateObjects(array $array): array {
			// Opslag voor de hashes van objecten die al zijn gezien.
			$objectKeys = [];
			
			// Gebruik array_filter om door de array te gaan en dubbele objecten te verwijderen.
			return array_filter($array, function($item) use (&$objectKeys) {
				// Als het item geen object is, behoud het in de array.
				if (!is_object($item)) {
					return true;
				}
				
				// Bereken de unieke hash van het object.
				$hash = spl_object_hash($item);
				
				// Controleer of de hash al in de lijst van gezien objecten staat.
				if (in_array($hash, $objectKeys)) {
					// Als ja, filter dit object uit de array.
					return false;
				}
				
				// Voeg de hash toe aan de lijst van gezien objecten en behoud het item in de array.
				$objectKeys[] = $hash;
				return true;
			});
		}
		
		/**
		 * Returns the Kernel
		 * @return Kernel
		 */
		public function getKernel(): Kernel {
			return $this->kernel;
		}
		
		/**
		 * Returns the DatabaseAdapter
		 * @return DatabaseAdapter
		 */
		public function getConnection(): DatabaseAdapter {
			return $this->connection;
		}
		
		/**
		 * Returns the unit of work
		 * @return UnitOfWork
		 */
		public function getUnitOfWork(): UnitOfWork {
			return $this->unit_of_work;
		}
		
		/**
		 * Returns the entity store
		 * @return EntityStore
		 */
		public function getEntityStore(): EntityStore {
			return $this->entity_store;
		}
		
		/**
		 * Returns true if the entity exists, false if not
		 * @param string $entityName
		 * @return bool
		 */
		public function entityExists(string $entityName): bool {
			return $this->entity_store->exists($entityName);
		}
		
        /**
         * Adds an entity to the entity manager list
         * @param $entity
         * @return bool
         */
        public function persist(&$entity): bool {
            if (!is_object($entity)) {
				return false;
			}
			
			return $this->unit_of_work->persistNew($entity);
        }
    
		/**
		 * Flush all changed entities to the database
		 * If an error occurs, an OrmException is thrown.
		 * @param mixed|null $entity
		 * @return void
		 * @throws OrmException
		 */
        public function flush(mixed $entity = null): void {
            $this->unit_of_work->commit($entity);
        }
		
		/**
		 * Detach an entity from the EntityManager.
		 * This will remove the entity from the identity map and stop tracking its changes.
		 * @param object $entity The entity to detach.
		 */
		public function detach(object $entity): void {
			$this->unit_of_work->detach($entity);
		}
		
		/**
		 * Retourneert de laatste query fout
		 * @return null|string
		 */
		public function getLastErrorMessage(): ?string {
			return $this->error_message;
		}
		
		/**
		 * Execute a database query and return the results
		 * @param string $query The database query to execute
		 * @param array $parameters (Optional) An array of parameters to bind to the query
		 * @return QuelResult|null
         */
		public function executeQuery(string $query, array $parameters=[]): ?QuelResult {
			try {
				// Parse de Quel query en converteer naar SQL
				$e = $this->object_quel->parse($query);
				$sql = $this->object_quel->convertToSQL($e, $parameters);
				
				// Voer de SQL query uit
				$rs = $this->connection->execute($sql, $parameters);
				
				if (!$rs) {
					$this->error_message = $this->connection->getLastErrorMessage();
					return null;
				}
				
				// Haal alle data op en stuur dit door naar QuelResult
				$result = [];
				while ($row = $rs->fetchRow()) {
					$result[] = $row;
				}
				
				return new QuelResult($this, $e, $result);
			} catch (ParserException|LexerException|QuelException $e) {
				$this->error_message = $e->getMessage();
				return null;
			}
		}
		
		/**
		 * Haalt alle resultaten van een uitgevoerde ObjectQuel-query op.
		 * @param string $query
		 * @param array $parameters
		 * @return array
		 * @throws \Exception
		 */
		public function getAll(string $query, array $parameters=[]): array {
			// Voert de query uit met de opgegeven parameters.
			$rs = $this->executeQuery($query, $parameters);
			
			// Controleert of de query succesvol was en resultaten heeft.
			if (!$rs || $rs->recordCount() == 0) {
				return [];
			}
			
			// Loopt door alle rijen van het resultaat.
			$result = [];
			while ($row = $rs->fetchRow()) {
				$result[] = $row;
			}
			
			return $result;
		}
		
		/**
		 * Voert een ObjectQuel-query uit en retourneert een array met objecten uit de
		 * eerste kolom van elk resultaat, waarbij duplicaten verwijderd zijn.
		 * @param string $query De ObjectQuel-query om uit te voeren.
		 * @param array $parameters Optionele parameters voor de query.
		 * @return array Een array met unieke objecten uit de eerste kolom van de queryresultaten.
		 */
		public function getCol(string $query, array $parameters=[]): array {
			// Voert de query uit met de opgegeven parameters.
			$rs = $this->executeQuery($query, $parameters);
			
			// Controleert of de query succesvol was en resultaten heeft.
			if (!$rs || $rs->recordCount() == 0) {
				return [];
			}
			
			// Haal resultaat op
			$result = [];
			$keys = null;
			
			while ($row = $rs->fetchRow()) {
				// Bepaalt de sleutels (kolomnamen) van de eerste rij, indien nog niet bepaald.
				if ($keys === null) {
					$keys = array_keys($row);
				}
				
				// Voegt de waarde van de eerste kolom toe aan het resultaat.
				$result[] = $row[$keys[0]];
			}
			
			// Retourneert ontdubbelde resultaten.
			return $this->deDuplicateObjects($result);
		}
		
		/**
		 * Zoekt entiteiten op basis van het gegeven entiteitstype en de primaire sleutel.
		 * @template T
		 * @param class-string<T> $entityType De fully qualified class name van de container
		 * @param mixed $primaryKey De primaire sleutel van de entiteit
		 * @return T[] De gevonden entiteiten
		 * @throws \Exception
		 */
		public function findBy(string $entityType, mixed $primaryKey): array {
			// Normaliseer de primaire sleutel.
			$primaryKeys = $this->normalizePrimaryKey($primaryKey, $entityType);
			
			// Bereid een query voor als de entiteit niet gevonden is.
			$query = $this->query_builder->prepareQuery($entityType, $primaryKeys);
			
			// Voer query uit en haal resultaat op
			$result = $this->getAll($query, $primaryKeys);
			
			// Haal de main column uit het resultaat
			$filteredResult = array_column($result, "main");
			
			// Retourneer ontdubbelde resultaten
			return $this->deDuplicateObjects($filteredResult);
		}
		
		/**
		 * Zoekt een entiteit op basis van het gegeven entiteitstype en primaire sleutel.
		 * @template T
		 * @param class-string<T> $entityType De fully qualified class name van de container
		 * @param mixed $primaryKey De primaire sleutel van de entiteit
		 * @return T|null De gevonden entiteit of null als deze niet gevonden wordt
		 */
		public function find(string $entityType, $primaryKey): ?object {
			// Normaliseer de primaire sleutel.
			$primaryKeys = $this->normalizePrimaryKey($primaryKey, $entityType);
			
			// Probeer de entiteit te vinden in de huidige unit of work.
			$existingEntity = $this->unit_of_work->findEntity($entityType, $primaryKeys);
			
			// Als de entiteit bestaat en geïnitialiseerd is, retourneer deze.
			if (!empty($existingEntity) && !($existingEntity instanceof ProxyInterface && !$existingEntity->isInitialized())) {
				return $existingEntity;
			}
			
			// Haal resultaat op
			$result = $this->findBy($entityType, $primaryKey);
			
			// Als de query geen resultaat geeft, retourneer null.
			if (empty($result)) {
				return null;
			}
			
			// Haal de resultaten op van de query en retourneer de hoofdentiteit.
			return $result[0] ?? null; // Gebruik null-coalescing voor veilige toegang.
		}
		
		/**
		 * Verwijdert een entiteit
		 * @param object $entity
		 * @return void
		 */
		public function remove(object $entity): void {
			$this->unit_of_work->remove($entity);
		}
		
		/**
		 * Returns the valiation rules of a given entity
		 * @param object $entity
		 * @return array
		 */
		public function getValidationRules(object $entity): array {
			$validate = new EntityToValidation($this->kernel);
			return $validate->convert($entity);
		}
		
		/**
		 * Checks if the Service supports the given class
		 * @param class-string $class
		 * @return bool
		 */
		public function supports(string $class): bool {
			return false;
		}
		
		/**
		 * Returns an instance of the requested class
		 * @param class-string $class
		 * @param array<string, mixed> $parameters Currently unused, but kept for interface compatibility
		 * @return object|null The requested instance or null if class is not supported
		 */
		public function getInstance(string $class, array $parameters = []): ?object {
			return null;
		}
	}