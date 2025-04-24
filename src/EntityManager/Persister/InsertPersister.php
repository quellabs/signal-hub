<?php
    
    namespace Quellabs\ObjectQuel\EntityManager\Persister;
    
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm\PostPersist;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm\PrePersist;
	use Quellabs\ObjectQuel\EntityManager\DatabaseAdapter;
	use Quellabs\ObjectQuel\EntityManager\EntityStore;
	use Quellabs\ObjectQuel\EntityManager\OrmException;
	use Quellabs\ObjectQuel\EntityManager\UnitOfWork;
	use Quellabs\ObjectQuel\Kernel\PropertyHandler;
	
	class InsertPersister extends PersisterBase {
		
		protected EntityStore $entity_store;
        protected UnitOfWork $unit_of_work;
        protected PropertyHandler $property_handler;
		protected DatabaseAdapter $connection;
		
		/**
         * InsertPersister constructor.
         * @param UnitOfWork $unitOfWork
         */
        public function __construct(UnitOfWork $unitOfWork) {
			parent::__construct($unitOfWork);
            $this->unit_of_work = $unitOfWork;
            $this->entity_store = $unitOfWork->getEntityStore();
            $this->property_handler = $unitOfWork->getPropertyHandler();
            $this->connection = $unitOfWork->getConnection();
        }
		
		/**
		 * Voert voorbereidende acties uit voor het aanmaken (persisten) van entiteiten.
		 * @param mixed $entity De entiteit die behandeld moet worden.
		 */
		protected function prePersist($entity): void {
			$this->handlePersist($entity, PrePersist::class);
		}
		
		/**
		 * Voert acties uit na het aanmaken (persisten) van entiteiten.
		 * @param mixed $entity De entiteit die behandeld moet worden.
		 */
		protected function postPersist($entity): void {
			$this->handlePersist($entity, PostPersist::class);
		}
		
		/**
		 * Persisteert een entiteit naar de database.
		 * @param object $entity
		 * @throws OrmException Als de database query mislukt.
		 */
		public function persist(object $entity) {
			// Roep de prePersist-methode aan op de entiteit
			$this->prePersist($entity);
			
			// Verzamel de benodigde informatie voor de insert
			$tableName = $this->entity_store->getOwningTable($entity);
			$serializedEntity = $this->unit_of_work->getSerializer()->serialize($entity);
			$primaryKeys = $this->entity_store->getIdentifierKeys($entity);
			$primaryKeyColumnNames = $this->entity_store->getIdentifierColumnNames($entity);
			$primaryKeyValues = array_intersect_key($serializedEntity, array_flip($primaryKeyColumnNames));
			
			// Maak de SQL-query
			$sql = implode(",", array_map(fn($key) => "`{$key}`=:{$key}", array_keys($serializedEntity)));
			
			// Voer de insert-query uit
			$rs = $this->connection->Execute("INSERT INTO `{$tableName}` SET {$sql}", $serializedEntity);
			
			// Als de query mislukt, gooi een uitzondering op
			if (!$rs) {
				throw new OrmException($this->connection->getLastErrorMessage(), $this->connection->getLastError());
			}
			
			// Als de query succesvol was, voeg de auto-increment ID toe aan de entiteit, indien van toepassing
			$autoIncrementId = $this->connection->getInsertId();
			
			if ($autoIncrementId !== 0 && in_array(null, $primaryKeyValues, true)) {
				$indexOfAutoIncrementColumn = array_search(null, $primaryKeyValues, true);
				$indexOfAutoIncrementKey = array_search($indexOfAutoIncrementColumn, $primaryKeyColumnNames, true);
				$this->property_handler->set($entity, $primaryKeys[$indexOfAutoIncrementKey], $autoIncrementId);
			}
			
			// Roep de postPersist-methode aan op de entiteit
			$this->postPersist($entity);
		}
    }