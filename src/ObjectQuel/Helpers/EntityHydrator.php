<?php
	
	namespace Services\ObjectQuel\Helpers;
	
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\ProxyInterface;
	use Services\EntityManager\Serializers\Serializer;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	
	class EntityHydrator {
		
		private \Services\EntityManager\UnitOfWork $unitOfWork;
		private EntityManager $entityManager;
		private \Services\EntityManager\EntityStore $entityStore;
		private Serializer $serializer;
		
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
			$this->unitOfWork = $entityManager->getUnitOfWork();
			$this->entityStore = $entityManager->getEntityStore();
			$this->serializer = new Serializer($this->entityStore);
		}
		
		/**
		 * Snel controleren of er enige niet-lege waarde in de gefilterde rij is
		 * @param array $array
		 * @return bool
		 */
		private function isArrayPopulated(array $array): bool {
			foreach ($array as $val) {
				if ($val !== null) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Remove a specified range from the keys of an array.
		 * @param string $range The range to remove from the array keys.
		 * @param array $array The array to modify.
		 * @return array The modified array with the range removed from the keys.
		 */
		private function removeRangeFromRow(string $range, array $array): array {
			$rangePrefix = $range . '.';
			$rangePrefixLength = strlen($rangePrefix);
			$modifiedArray = [];
			
			foreach ($array as $key => $value) {
				// Check if key starts with the prefix before doing substring operation
				if (strncmp($key, $rangePrefix, $rangePrefixLength) === 0) {
					$modifiedArray[substr($key, $rangePrefixLength)] = $value;
				}
			}
			
			return $modifiedArray;
		}
		
		/**
		 * Initializes a proxy object with data
		 * @param ProxyInterface $proxy The proxy object to initialize
		 * @param array $data The data to populate the proxy with
		 * @return void
		 */
		private function initializeProxy(ProxyInterface $proxy, array $data): void {
			// Mark the proxy as initialized so it knows it has been loaded
			$proxy->setInitialized();
			
			// Deserialize the provided data into the proxy entity
			// This populates the proxy with all the properties from the data array
			$this->serializer->deserialize($proxy, $data);
			
			// Detach the entity from the Unit of Work
			// This allows the entity to be re-attached later as an existing entity
			// rather than being treated as a new entity to be persisted
			$this->unitOfWork->detach($proxy);
		}
		
		/**
		 * Processes a row of data into an entity object
		 * @param AstAlias $value The alias representing the entity to process
		 * @param array $filteredRow Data row containing entity properties
		 * @param array $relationCache Cache containing relationship information
		 * @return object|null The processed entity object or null if no data
		 */
		private function processEntity(AstAlias $value, array $filteredRow, array $relationCache): ?object {
			// Check if the array contains any meaningful data
			// If the array is empty or contains only null values, return null
			if (!$this->isArrayPopulated($filteredRow)) {
				return null;
			}
			
			// Extract metadata about the entity from the expression
			$expression = $value->getExpression();
			$entity = $expression->getName(); // The entity class name
			$rangeName = $expression->getRange()->getName(); // The alias/range name in the query
			
			// Remove the range prefix from column names in the row data
			// This converts prefixed column names like "range.user_id" to just "user_id"
			$filteredRow = $this->removeRangeFromRow($rangeName, $filteredRow);
			
			// Extract only the primary key values from the filtered row
			// Uses array_intersect_key for better performance than manual filtering
			$primaryKeyValues = array_intersect_key($filteredRow, $relationCache['identifiers_flipped']);
			
			// Try to find an existing entity with the same primary key values
			// This prevents duplicate entities for the same database record
			$existingEntity = $this->unitOfWork->findEntity($entity, $primaryKeyValues);
			
			if ($existingEntity !== null) {
				// If the entity exists but is a non-initialized proxy,
				// initialize it with the current data
				if ($existingEntity instanceof ProxyInterface && !$existingEntity->isInitialized()) {
					$this->initializeProxy($existingEntity, $filteredRow);
				}
				
				// Mark the entity as "existing" in the Unit of Work
				// This ensures it will be tracked for changes but not inserted as new
				$this->unitOfWork->persistExisting($existingEntity);
				
				// Return the existing entity (possibly newly initialized)
				return $existingEntity;
			}
			
			// If no existing entity was found, create a new one and
			// populate it with data from the filtered row
			$newEntity = new $entity;
			$this->serializer->deserialize($newEntity, $filteredRow);

			// Add the new entity to the Unit of Work as an existing entity
			// (not as a new entity since it came from the database)
			$this->unitOfWork->persistExisting($newEntity);
			return $newEntity;
		}
		
		/**
		 * Verwerkt een enkele waarde uit het resultaat.
		 * @param mixed $value De te verwerken waarde.
		 * @param array $row De huidige rij uit de database.
		 * @param array|null $relationCache
		 * @return mixed
		 */
		private function processValue(mixed $value, array $row, ?array $relationCache): mixed {
			// Bewaar de node
			$node = $value->getExpression();
			
			// Fetch Entity
			if ($node instanceof AstEntity) {
				// Filter de rows voor deze entity uit
				$filteredRow = array_intersect_key($row, $relationCache["keys_flipped"]);
				
				// Retourneer de entity
				return $this->processEntity($value, $filteredRow, $relationCache);
			}
			
			// Fetch identifier
			if ($node instanceof AstIdentifier) {
				$value = $row[$value->getName()];
				$annotations = $this->entityStore->getAnnotations($node->getEntityName());
				$annotationsForProperty = $annotations[$node->getName()];
				
				foreach ($annotationsForProperty as $annotation) {
					if ($annotation instanceof Column) {
						return $this->unitOfWork->getSerializer()->normalizeValue($annotation, $value);
					}
				}
				
				return null;
			}
			
			// Otherwise try to fetch the data from the row
			return $row[$value->getName()] ?? null;
		}

		private function processRow(array $ast, array $row, array $relationCache, array &$entities): array {
			$resultRow = [];
			
			foreach ($ast as $value) {
				$name = $value->getName();
				$isEntity = $value->getExpression() instanceof AstEntity;
				$rangeName = $isEntity ? $value->getExpression()->getRange()->getName() : null;
				
				$processedValue = $this->processValue(
					$value, $row,
					$isEntity ? $relationCache[$rangeName] : null
				);
				
				$resultRow[$name] = $processedValue;
				
				// Track entities for relationship loading
				if ($isEntity && ($processedValue !== null)) {
					$hash = spl_object_id($processedValue);
					
					if (!isset($entities[$hash])) {
						$entities[$hash] = $processedValue;
					}
				}
			}
			
			return $resultRow;
		}
		
		/**
		 * Initialiseer de relation cache op basis van de eerste rij en het AST.
		 * @param array $ast
		 * @param array $row
		 * @return array
		 */
		private function buildRelationCache(array $ast, array $row): array {
			$relationCache = [];
			
			foreach ($ast as $value) {
				$expression = $value->getExpression();
				
				if ($expression instanceof AstEntity) {
					$rangeName = $expression->getRange()->getName();
					$rangeNameLength = strlen($rangeName) + 1;
					$class = $expression->getName();
					
					// Check if entity is already cached
					if (!isset($relationCache[$rangeName])) {
						$keys = [];
						$identifierKeys = $this->entityStore->getIdentifierKeys($class);
						
						// Collect matching keys
						foreach ($row as $rowKey => $rowValue) {
							if (strncmp($rowKey, "{$rangeName}.", $rangeNameLength) === 0) {
								$keys[] = $rowKey;
							}
						}
						
						$relationCache[$rangeName] = [
							'identifiers'         => $identifierKeys,
							'identifiers_flipped' => array_flip($identifierKeys),
							'keys'                => $keys,
							'keys_flipped'        => array_flip($keys)
						];
					}
				}
			}
			
			return $relationCache;
		}
		
		/**
		 * Hydrates entities from raw database rows
		 * @param array $ast Abstract syntax tree nodes
		 * @param array $data Raw data rows
		 * @return array [resultRows, extractedEntities]
		 */
		public function hydrateEntities(array $ast, array $data): array {
			$first = true;
			$entities = [];
			$resultRows = [];
			$relationCache = [];
			
			// Process each row
			foreach($data as $row) {
				if ($first) {
					$relationCache = $this->buildRelationCache($ast, $row);
					$first = false;
				}
				
				$resultRow = $this->processRow($ast, $row, $relationCache, $entities);
				$resultRows[] = $resultRow;
			}
			
			return [
				'result'   => $resultRows,
				'entities' => $entities
			];
		}
	}