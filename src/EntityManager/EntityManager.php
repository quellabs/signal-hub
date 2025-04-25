<?php
	
    namespace Quellabs\ObjectQuel\EntityManager;
	
	use Quellabs\ObjectQuel\Kernel\Kernel;
	use Quellabs\ObjectQuel\Kernel\PropertyHandler;
	use Quellabs\ObjectQuel\ObjectQuel\QuelException;
	use Quellabs\ObjectQuel\ObjectQuel\QuelResult;
	use Quellabs\ObjectQuel\Validation\EntityToValidation;
	
	/**
	 * Represents an Entity Manager.
	 */
	class EntityManager {
		protected Kernel $kernel;
        protected DatabaseAdapter $connection;
        protected UnitOfWork $unit_of_work;
		protected EntityStore $entity_store;
		protected QueryBuilder $query_builder;
		protected PropertyHandler $property_handler;
		protected QueryExecutor $query_executor;
		
		/**
		 * EntityManager constructor
		 * @param Configuration $configuration
		 */
        public function __construct(Configuration $configuration) {
            $this->connection = new DatabaseAdapter($configuration);
	        $this->entity_store = new EntityStore($configuration);
            $this->unit_of_work = new UnitOfWork($this);
			$this->query_builder = new QueryBuilder($this->entity_store);
			$this->query_executor = new QueryExecutor($this);
			$this->property_handler = new PropertyHandler();
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
		 * Returns the property handler
		 * @return PropertyHandler
		 */
		public function getPropertyHandler(): PropertyHandler {
			return $this->property_handler;
		}
		
		/**
		 * Returns the kernel object
		 * @return Kernel
		 */
		public function getKernel(): Kernel {
			return $this->kernel;
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
		 * Execute a decomposed query plan
		 * @param string $query The query to execute
		 * @param array $parameters Initial parameters for the plan
		 * @return QuelResult|null The results of the execution plan
		 * @throws QuelException
		 */
		public function executeQuery(string $query, array $parameters=[]): ?QuelResult {
			return $this->query_executor->executeQuery($query, $parameters);
		}
		
		/**
		 * Haalt alle resultaten van een uitgevoerde ObjectQuel-query op.
		 * @param string $query
		 * @param array $parameters
		 * @return array
		 * @throws QuelException
		 */
		public function getAll(string $query, array $parameters=[]): array {
			return $this->query_executor->getAll($query, $parameters);
		}
		
		/**
		 * Voert een ObjectQuel-query uit en retourneert een array met objecten uit de
		 * eerste kolom van elk resultaat, waarbij duplicaten verwijderd zijn.
		 * @param string $query De ObjectQuel-query om uit te voeren.
		 * @param array $parameters Optionele parameters voor de query.
		 * @return array Een array met unieke objecten uit de eerste kolom van de queryresultaten.
		 */
		public function getCol(string $query, array $parameters=[]): array {
			return $this->query_executor->getCol($query, $parameters);
		}
		
		/**
		 * Zoekt entiteiten op basis van het gegeven entiteitstype en de primaire sleutel.
		 * @template T
		 * @param class-string<T> $entityType De fully qualified class name van de container
		 * @param mixed $primaryKey De primaire sleutel van de entiteit
		 * @return T[] De gevonden entiteiten
		 * @throws QuelException
		 */
		public function findBy(string $entityType, mixed $primaryKey): array {
			// Normaliseer de primaire sleutel.
			$primaryKeys = $this->entity_store->normalizePrimaryKey($primaryKey, $entityType);
			
			// Bereid een query voor als de entiteit niet gevonden is.
			$query = $this->query_builder->prepareQuery($entityType, $primaryKeys);
			
			// Voer query uit en haal resultaat op
			$result = $this->query_executor->getAll($query, $primaryKeys);
			
			// Haal de main column uit het resultaat
			$filteredResult = array_column($result, "main");
			
			// Retourneer ontdubbelde resultaten
			return $this->query_executor->deDuplicateObjects($filteredResult);
		}
		
		/**
		 * Zoekt een entiteit op basis van het gegeven entiteitstype en primaire sleutel.
		 * @template T
		 * @param class-string<T> $entityType De fully qualified class name van de container
		 * @param mixed $primaryKey De primaire sleutel van de entiteit
		 * @return T|null De gevonden entiteit of null als deze niet gevonden wordt
		 * @throws QuelException
		 */
		public function find(string $entityType, mixed $primaryKey): ?object {
			// Normaliseer de primaire sleutel.
			$primaryKeys = $this->entity_store->normalizePrimaryKey($primaryKey, $entityType);
			
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
			$this->unit_of_work->scheduleForDelete($entity);
		}
		
		/**
		 * Returns the validation rules of a given entity
		 * @param object $entity
		 * @return array
		 */
		public function getValidationRules(object $entity): array {
			$validate = new EntityToValidation();
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