<?php
	
    namespace Services\EntityManager;
	
	use Services\ObjectQuel\ObjectQuel;
	use Services\ObjectQuel\QuelException;
	use Services\ObjectQuel\QuelResult;
	
	/**
	 * Represents an Entity Manager.
	 */
	class QueryExecutor {
        protected DatabaseAdapter $connection;
		private EntityManager $entityManager;
		private PlanExecutor $planExecutor;
		private ObjectQuel $objectQuel;
		
		/**
		 * @param EntityManager $entityManager
		 */
        public function __construct(EntityManager $entityManager) {
            $this->entityManager = $entityManager;
            $this->connection = $entityManager->getConnection();
	        $this->planExecutor = new PlanExecutor($entityManager);
	        $this->objectQuel = new ObjectQuel($entityManager);
        }
		
		/**
		 * Verwijdert dubbele objecten uit een array op basis van hun object-hash.
		 * Niet-objecten in de array worden ongewijzigd gelaten.
		 * @param array $array De input array met mogelijk dubbele objecten.
		 * @return array Een array met unieke objecten en alle oorspronkelijke niet-object elementen.
		 */
		public function deDuplicateObjects(array $array): array {
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
		 * Returns the DatabaseAdapter
		 * @return DatabaseAdapter
		 */
		public function getConnection(): DatabaseAdapter {
			return $this->connection;
		}

		/**
		 * Execute a decomposed query plan
		 * @param string $query The query to execute
		 * @param array $parameters Initial parameters for the plan
		 * @return QuelResult The results of the execution plan
		 * @throws QueryExecutionException
		 */
		public function executeQuery(string $query, array $parameters=[]): QuelResult {
			try {
				// Decompose the query
				$decomposer = new QueryDecomposer($this->entityManager);
				$executionPlan = $decomposer->decompose($query, $parameters);
				
				// Execute the returned execution plan and return the QuelResult
				return $this->planExecutor->execute($executionPlan);
			} catch (QuelException $e) {
				throw new QueryExecutionException($e->getMessage(), 0, $e);
			}
		}
		
		/**
		 * Execute a database query and return the results
		 * @param string $query The database query to execute
		 * @param array $initialParams (Optional) An array of parameters to bind to the query
		 * @return QuelResult
		 * @throws QueryExecutionException
		 */
		public function executeSimpleQuery(string $query, array $initialParams=[]): QuelResult {
			try {
				// Parse de Quel query
				$e = $this->objectQuel->parse($query);
				
				// Parse de Quel query en converteer naar SQL
				$sql = $this->objectQuel->convertToSQL($e, $initialParams);
				
				// Voer de SQL query uit
				$rs = $this->connection->execute($sql, $initialParams);
				
				// Indien de query incorrect is, gooi een exception
				if (!$rs) {
					throw new QueryExecutionException($this->connection->getLastErrorMessage(), 0, $e);
				}
				
				// Haal alle data op en stuur dit door naar QuelResult
				$result = [];
				while ($row = $rs->fetchRow()) {
					$result[] = $row;
				}
				
				// QuelResult gebruikt de AST om de ontvangen data te transformeren naar entities
				return new QuelResult($this->entityManager, $e, $result);
			} catch (QuelException $e) {
				throw new QueryExecutionException($e->getMessage(), 0, $e);
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
			
			// Controleert of de query succesvol resultaten heeft.
			if ($rs->recordCount() == 0) {
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
		 * @throws QueryExecutionException
		 */
		public function getCol(string $query, array $parameters=[]): array {
			// Voert de query uit met de opgegeven parameters.
			$rs = $this->executeQuery($query, $parameters);
			
			// Controleert of de query succesvol was en resultaten heeft.
			if ($rs->recordCount() == 0) {
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
	}