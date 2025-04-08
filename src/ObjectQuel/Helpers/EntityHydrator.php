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
			$rangeLength = strlen($range) + 1; // Bereken de lengte van de te verwijderen prefix inclusief de punt
			$modifiedArray = [];
			
			foreach ($array as $key => $value) {
				$newKey = substr($key, $rangeLength);
				$modifiedArray[$newKey] = $value;
			}
			
			return $modifiedArray;
		}
		
		/**
		 * Verwerkt een entity op basis van de gegeven waarde en gefilterde rij.
		 * Retourneert `null` als de rij geen waarden bevat, wat duidt op een mislukte LEFT JOIN.
		 * Creëert een nieuwe entity als deze niet bestaat, of retourneert de bestaande.
		 * @param AstAlias $value De alias met de expressie voor entity naam en bereik.
		 * @param array $filteredRow De gefilterde rijgegevens van de database.
		 * @param array $relationCache
		 * @return object|null De gevonden of nieuw aangemaakte entity, of `null`.
		 */
		private function processEntity(AstAlias $value, array $filteredRow, array $relationCache): ?object {
			// Kijk of de array wel gevuld is
			if (!$this->isArrayPopulated($filteredRow)) {
				return null;
			}
			
			// Haal de expression en entity informatie eenmalig op
			$expression = $value->getExpression();
			$entity = $expression->getName();
			$rangeName = $expression->getRange()->getName();
			
			// Filter de primary key waarden uit de rij
			$filteredRow = $this->removeRangeFromRow($rangeName, $filteredRow);
			
			// Gebruik array_intersect_key voor efficiëntere filtering van primaryKeyValues
			$primaryKeyValues = array_intersect_key($filteredRow, $relationCache['identifiers_flipped']);
			
			// Kijk of we de entiteit al ingelezen hebben. Zoja, retourneer dan de al bestaande entiteit.
			$existingEntity = $this->unitOfWork->findEntity($entity, $primaryKeyValues);
			
			if ($existingEntity !== null) {
				if ($existingEntity instanceof ProxyInterface && !$existingEntity->isInitialized()) {
					// Markeer de proxy als geïnitialiseerd
					$existingEntity->setInitialized();
					
					// Neem de gegevens in de entity over en geef aan dat de proxy nu ingeladen is
					$this->serializer->deserialize($existingEntity, $filteredRow);
					
					// Ontkoppel de entity zodat deze weer als bestaande entity kan worden toegevoegd
					$this->unitOfWork->detach($existingEntity);
				}
				
				// Persist de entity voor latere flushes
				$this->unitOfWork->persistExisting($existingEntity);
				
				// Bestaande entity teruggeven.
				return $existingEntity;
			}
			
			// Nieuwe entity aanmaken en teruggeven.
			$newEntity = new $entity;
			$this->serializer->deserialize($newEntity, $filteredRow);
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