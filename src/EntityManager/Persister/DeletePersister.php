<?php
    
    namespace Services\EntityManager\Persister;
    
	use Services\AnnotationsReader\Annotations\Orm\PostDelete;
	use Services\EntityManager\DatabaseAdapter;
	use Services\EntityManager\EntityStore;
	use Services\EntityManager\OrmException;
	use Services\EntityManager\PropertyHandler;
	use Services\EntityManager\UnitOfWork;
	
	class DeletePersister extends PersisterBase {
    
        protected UnitOfWork $unit_of_work;
		protected EntityStore $entity_store;
        protected PropertyHandler $property_handler;
		protected DatabaseAdapter $connection;
		
		/**
         * DeletePersister constructor.
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
		 * Haalt de waarden van de primaire sleutels op voor een gegeven entiteit.
		 * @param object $entity De entiteit waarvan de primaire sleutelwaarden worden opgehaald.
		 * @param array $primaryKeys De lijst met primaire sleutels van de entiteit.
		 * @param array $primaryKeyColumns De kolomnamen die overeenkomen met de primaire sleutels in de database.
		 * @return array Associatieve array met kolomnamen als sleutels en bijbehorende waarden uit de entiteit.
		 */
		private function extractPrimaryKeyValueMap(object $entity, array $primaryKeys, array $primaryKeyColumns): array {
			$result = [];
			
			foreach($primaryKeys as $index => $key) {
				$result[$primaryKeyColumns[$index]] = $this->property_handler->get($entity, $key);
			}
			
			return $result;
		}
		
		/**
		 * Voert acties uit na het deleten van entiteiten.
		 * @param object $entity Een entiteit die behandeld moeten worden.
		 * @return void
		 */
		protected function postDelete(object $entity): void {
			$this->handlePersist($entity, PostDelete::class);
		}
		
		/**
		 * Verwijdert een entiteit uit de database op basis van haar primaire sleutels.
		 * Deze functie haalt eerst de benodigde tabel- en sleutelinformatie op en stelt vervolgens
		 * een DELETE SQL-query samen om de specifieke entiteit te verwijderen.
		 * @param object $entity De entiteit die verwijderd moet worden uit de database.
		 * @throws OrmException Als de DELETE operatie mislukt, wordt er een exception gegooid.
		 */
		public function persist(object $entity): void {
			// Haal de naam van de tabel op waar de entiteit in opgeslagen moet worden.
			$tableName = $this->entity_store->getOwningTable($entity);
			
			// Verkrijg de primaire sleutels en de corresponderende kolomnamen van de entiteit.
			$primaryKeys = $this->entity_store->getIdentifierKeys($entity);
			$primaryKeyColumns = $this->entity_store->getIdentifierColumnNames($entity);
			
			// Creëer een map van primaire sleutelkolomnamen naar hun waarden voor deze specifieke entiteit.
			$primaryKeyValues = $this->extractPrimaryKeyValueMap($entity, $primaryKeys, $primaryKeyColumns);
			
			// Stel de SQL-query samen voor het verwijderen van de entiteit, waarbij elke primaire sleutelwaarde
			// gebruikt wordt in de WHERE-clausule om specifiek deze entiteit te targeten.
			// Gebruikt `AND` om te zorgen dat alle voorwaarden overeen moeten komen.
			$sql = implode(" AND ", array_map(fn($key) => "`{$key}`=:{$key}", array_keys($primaryKeyValues)));
			
			// Voer de DELETE-query uit met de opgestelde voorwaarden. Gebruik de primaire sleutelwaarden
			// als parameters voor de prepared statement om SQL-injectie te voorkomen.
			if (!$this->connection->execute("DELETE FROM `{$tableName}` WHERE {$sql}", $primaryKeyValues)) {
				// Als de uitvoering mislukt, gooi een exception met het laatste foutbericht en foutcode
				// van de databaseverbinding om de fout te kunnen identificeren en op te lossen.
				throw new OrmException("Fout bij het verwijderen van entiteit: " . $this->connection->getLastErrorMessage(), $this->connection->getLastError());
			}
			
			// Roep de postDelete method van de entity aan indien aanwezig
			$this->postDelete($entity);
		}
    }