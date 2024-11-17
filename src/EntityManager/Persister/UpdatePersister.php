<?php
    
    namespace Services\EntityManager\Persister;
    
    use Services\AnnotationsReader\Annotations\Orm\PostUpdate;
    use Services\AnnotationsReader\Annotations\Orm\PreUpdate;
    use Services\EntityManager\databaseAdapter;
    use Services\EntityManager\EntityStore;
    use Services\EntityManager\OrmException;
    use Services\EntityManager\UnitOfWork;
    use Services\Kernel\PropertyHandler;
    
    class UpdatePersister extends PersisterBase {
    
        protected UnitOfWork $unit_of_work;
	    protected EntityStore $entity_store;
        protected PropertyHandler $property_handler;
	    protected databaseAdapter $connection;
	    
	    /**
         * UpdatePersister constructor.
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
		 * Neemt een array, plaats een prefix voor de keys, en retourneert de nieuwe, gewijzigde array.
		 * @param array $array
		 * @param string $prefix
		 * @return array
		 */
		protected function prefixKeys(array $array, string $prefix): array {
			$newArray = [];
			
			foreach ($array as $key => $value) {
				$newKey = $prefix . $key;
				$newArray[$newKey] = $value;
			}
			
			return $newArray;
		}
		
		/**
		 * Voert voorbereidende acties uit voor het updaten van entiteiten.
		 * @param object $entity Een entiteit die behandeld moeten worden.
		 * @return void
		 */
		protected function preUpdate(object $entity): void {
			$this->handlePersist($entity, PreUpdate::class);
		}
		
		/**
		 * Voert acties uit na het updaten van entiteiten.
		 * @param object $entity Een entiteit die behandeld moeten worden.
		 * @return void
		 */
		protected function postUpdate(object $entity): void {
			$this->handlePersist($entity, PostUpdate::class);
		}
		
		/**
		 * Persisteert een entiteit naar de database.
		 * @param object $entity
		 * @return void
		 * @throws OrmException
		 */
		public function persist(object $entity): void {
			// Roept preUpdate-methode aan op de entiteit
			$this->preUpdate($entity);
			
			// Basisinformatie ophalen
			$tableName = $this->entity_store->getOwningTable($entity);
			$serializedEntity = $this->unit_of_work->serializeEntity($entity);
			$dehydratedEntity = $this->unit_of_work->convertToSQL($entity, $serializedEntity);
			$originalData = $this->unit_of_work->getOriginalEntityData($entity);
			$originalDataSQL = $this->unit_of_work->convertToSQL($entity, $originalData);
			$primaryKeyColumnNames = $this->entity_store->getIdentifierColumnNames($entity);
			
			// Primaire sleutelwaarden filteren
			$primaryKeyValues = array_intersect_key($originalDataSQL, array_flip($primaryKeyColumnNames));
			
			// Lijst van veranderde items maken
			$extractedEntityChanges = array_filter($dehydratedEntity, function ($value, $key) use ($originalDataSQL, $primaryKeyColumnNames) {
				return (in_array($key, $primaryKeyColumnNames) || ($value != $originalDataSQL[$key]));
			}, ARRAY_FILTER_USE_BOTH);
			
			// Query uitvoeren
			$sql = implode(",", array_map(fn($key) => "`{$key}`=:{$key}", array_keys($extractedEntityChanges)));
			$sqlWhere = implode(" AND ", array_map(fn($key) => "`{$key}`=:primary_key_{$key}", $primaryKeyColumnNames));
			$mergedParams = array_merge($dehydratedEntity, $this->prefixKeys($primaryKeyValues, "primary_key_"));
			
			$rs = $this->connection->Execute("
				UPDATE `{$tableName}` SET
					{$sql}
				WHERE {$sqlWhere}
			", $mergedParams);
			
			// Als de query mislukt, gooi een uitzondering
			if (!$rs) {
				throw new OrmException($this->connection->getLastErrorMessage(), $this->connection->getLastError());
			}
			
			// Roept postUpdate-methode aan op de entiteit
			$this->postUpdate($entity);
		}
    }