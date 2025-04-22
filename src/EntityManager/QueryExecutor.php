<?php
	
	namespace Quellabs\ObjectQuel\EntityManager;
	
	use Flow\JSONPath\JSONPathException;
	use Services\ObjectQuel\Ast\AstRangeJsonSource;
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
		private ConditionEvaluator $conditionEvaluator;
		
		/**
		 * @param EntityManager $entityManager
		 */
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
			$this->connection = $entityManager->getConnection();
			$this->conditionEvaluator = new ConditionEvaluator();
			$this->planExecutor = new PlanExecutor($this, $this->conditionEvaluator);
			$this->objectQuel = new ObjectQuel($entityManager);
		}
		
		/**
		 * Returns the entity manager object
		 * @return EntityManager
		 */
		public function getEntityManager(): EntityManager {
			return $this->entityManager;
		}
		
		/**
		 * Returns the ObjectQuel parser
		 * @return ObjectQuel
		 */
		public function getObjectQuel(): ObjectQuel {
			return $this->objectQuel;
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
			return array_filter($array, function ($item) use (&$objectKeys) {
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
		 * Transforms a Quel query to SQL, executes the SQL and returns the result
		 * @param ExecutionStage $stage The parsed query (AST)
		 * @param array $initialParams Parameters for this query
		 * @return array The QuelResult object
		 * @throws QuelException
		 */
		protected function executeSimpleQueryDatabase(ExecutionStage $stage, array $initialParams = []): array {
			// Converteer de query naar SQL
			$sql = $this->objectQuel->convertToSQL($stage->getQuery(), $initialParams);
			
			// Voer de SQL query uit
			$rs = $this->connection->execute($sql, $initialParams);
			
			// Indien de query incorrect is, gooi een exception
			if (!$rs) {
				throw new QuelException($this->connection->getLastErrorMessage());
			}
			
			// Haal alle data op en stuur dit door naar QuelResult
			$result = [];
			while ($row = $rs->fetchRow()) {
				$result[] = $row;
			}
			
			return $result;
		}
		
		/**
		 * Load and filter a JSON file from a JSON source
		 * @param AstRangeJsonSource $source
		 * @return array
		 * @throws QuelException
		 */
		protected function loadAndFilterJsonFile(AstRangeJsonSource $source): array {
			// Load the JSON file
			$contents = file_get_contents($source->getPath());
			
			if ($contents === false) {
				throw new QuelException("JSON file {$source->getName()} not found");
			}
			
			// Decode the JSON file
			$decoded = json_decode($contents, true);
			
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new QuelException("Error decoding JSON file {$source->getName()}: " . json_last_error_msg());
			}
			
			// If a JSONPath was given, use it to filter the output
			if (!empty($source->getExpression())) {
				try {
					$decoded = (new \Flow\JSONPath\JSONPath($decoded))->find($source->getExpression())->getData();
				} catch (JSONPathException $e) {
					throw new QuelException($e->getMessage(), $e->getCode(), $e);
				}
			}
			
			// Prefix all items with the range alias
			$result = [];
			$alias = $source->getName();
			
			foreach ($decoded as $row) {
				$line = [];
				
				foreach ($row as $key => $value) {
					$line["{$alias}.{$key}"] = $value;
				}
				
				$result[] = $line;
			}
			
			return $result;
		}
		
		/**
		 * Execute a JSON query and returns the result
		 * @param ExecutionStage $stage
		 * @param array $initialParams
		 * @return array
		 * @throws QuelException
		 */
		protected function executeSimpleQueryJson(ExecutionStage $stage, array $initialParams = []): array {
			// Load the JSON file and perform initial filtering
			$contents = $this->loadAndFilterJsonFile($stage->getRange());
			
			// Use the conditions to further filter the file
			$result = [];
			
			foreach ($contents as $row) {
				if ($stage->getQuery()->getConditions() === null ||
					$this->conditionEvaluator->evaluate($stage->getQuery()->getConditions(), $row, $initialParams)) {
					$result[] = $row;
				}
			}
			
			return $result;
		}
		
		/**
		 * Execute a database query and return the results
		 * @param ExecutionStage $stage
		 * @param array $initialParams (Optional) An array of parameters to bind to the query
		 * @return array
		 * @throws QuelException
		 */
		public function executeStage(ExecutionStage $stage, array $initialParams = []): array {
			$queryType = $stage->getRange() instanceof AstRangeJsonSource ? 'json' : 'database';
			
			return match ($queryType) {
				'json' => $this->executeSimpleQueryJson($stage, $initialParams),
				'database' => $this->executeSimpleQueryDatabase($stage, $initialParams),
			};
		}
		
		/**
		 * Haalt alle resultaten van een uitgevoerde ObjectQuel-query op.
		 * @param string $query
		 * @param array $parameters
		 * @return array
		 * @throws QuelException
		 */
		public function getAll(string $query, array $parameters = []): array {
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
		 * @throws QuelException
		 */
		public function getCol(string $query, array $parameters = []): array {
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
		
		/**
		 * Execute a decomposed query plan
		 * @param string $query The query to execute
		 * @param array $parameters Initial parameters for the plan
		 * @return QuelResult The results of the execution plan
		 * @throws QuelException
		 */
		public function executeQuery(string $query, array $parameters = []): QuelResult {
			// Parse the input query string into an Abstract Syntax Tree (AST)
			$ast = $this->getObjectQuel()->parse($query);
			
			// Decompose the query
			$decomposer = new QueryDecomposer();
			$executionPlan = $decomposer->buildExecutionPlan($ast, $parameters);
			
			// Execute the returned execution plan and return the QuelResult
			$result = $this->planExecutor->execute($executionPlan);
			
			// QuelResult gebruikt de AST om de ontvangen data te transformeren naar entities
			return new QuelResult($this->entityManager, $ast, $result);
		}
	}