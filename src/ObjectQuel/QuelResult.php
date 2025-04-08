<?php
	
	namespace Services\ObjectQuel;
	
	use Services\EntityManager\entityManager;
	use Services\ObjectQuel\Ast\AstMethodCall;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Helpers\EntityHydrator;
	use Services\ObjectQuel\Helpers\RelationshipLoader;
	use Services\ObjectQuel\Helpers\ResultTransformer;
	
	/**
	 * Represents a Quel result.
	 */
	class QuelResult implements \ArrayAccess {
		private EntityHydrator $entityHydrator;
		private RelationshipLoader $relationShipLoader;
		private ResultTransformer $resultTransformer;
		private array $result;
		private int $index;
		private bool $sortInApplicationLogic;
		private ?int $window;
		private ?int $pageSize;
		
		/**
		 * @param entityManager $entityManager
		 * @param AstRetrieve $retrieve
		 * @param array $data
		 */
		public function __construct(EntityManager $entityManager, AstRetrieve $retrieve, array $data) {
			$this->entityHydrator = new EntityHydrator($entityManager);
			$this->relationShipLoader = new RelationshipLoader($entityManager, $retrieve);
			$this->resultTransformer = new ResultTransformer();
			$this->sortInApplicationLogic = $retrieve->getSortInApplicationLogic() && empty($retrieve->getDirective('InValuesAreFinal'));
			$this->index = 0;
			$this->window = $retrieve->getWindow();
			$this->pageSize = $retrieve->getPageSize();
			
			// Get AST values
			$ast = $retrieve->getValues();
			
			// Extract entities from raw data
			$result = $this->entityHydrator->hydrateEntities($ast, $data);
			
			// Store the result
			$this->result = $result['result'];
			
			// Set up entity relationships
			$this->relationShipLoader->loadRelationships($result['entities']);
			
			// Sorteer de resultaten indien aangegeven:
			// 1) Er wordt een method aangeroepen in SORT BY
			// 2) InValuesAreFinal is niet gezet. Bij InValuesAreFinal wordt er gesorteerd op de IN() lijst
			if ($this->sortInApplicationLogic) {
				$this->resultTransformer->sortResults($this->result, $retrieve->getSort());
			}
		}
		
		/**
		 * Returns the number of rows inside this recordset
		 * @return int
		 */
		public function recordCount(): int {
			return count($this->result);
		}
		
		/**
		 * Reads a row of a result set and advances the recordset pointer
		 * @return array|false
		 */
		public function fetchRow(): array|false {
			if ($this->index >= $this->recordCount()) {
				return false;
			}
			
			$result = $this->result[$this->index];
			++$this->index;
			return $result;
		}
        
        /**
         * Retourneert de waarde van $columnName voor alle rows tegelijk
         * @param string|int $columnName
         * @return array
         */
		public function fetchCol(string|int $columnName=0): array {
            if (is_int($columnName)) {
                $keys = array_keys($this->result);
                $columnName = $keys[$columnName];
            }
            
			return array_column($this->result, $columnName);
		}
		
		/**
		 * Moves the result index to the given position
		 * @param int $pos
		 * @return void
		 */
		public function seek(int $pos): void {
			$this->index = $pos;
		}
		
		/**
		 * Returns true if sort and pagination are done in application logic, false if using mysql
		 * @return bool
		 */
		public function getSortInApplicationLogic(): bool {
			return $this->sortInApplicationLogic;
		}
		
		/**
		 * Returns the pagination window, or null if there is none
		 * @return int|null
		 */
		public function getWindow(): ?int {
			return $this->window;
		}

		/**
		 * Returns the pagination page_size, or null if there is none
		 * @return int|null
		 */
		public function getPageSize(): ?int {
			return $this->pageSize;
		}
		
		/**
		 * Returns the raw data in this recordset
		 * @return array
		 */
		public function getResults(): array {
			return $this->result;
		}
		
		/**
		 * Apply a custom transformation function to the entire result set
		 * @param callable $transformer Function that transforms the entire result array
		 * @return $this Returns $this for method chaining
		 */
		public function transform(callable $transformer): self {
			$this->result = $transformer($this->result);
			return $this;
		}
		
		/**
		 * Filters the entire result set
		 * @param callable $condition Function that takes the result array and returns filtered array
		 * @return $this For method chaining
		 */
		public function filter(callable $condition): self {
			$this->result = $condition($this->result);
			return $this;
		}
		
		/**
		 * Merge another QuelResult or array of rows into this one
		 * @param array|QuelResult $otherResult The result to merge
		 * @return $this Returns $this for method chaining
		 */
		public function merge(array|QuelResult $otherResult): self {
			if ($otherResult instanceof QuelResult) {
				$this->result = array_merge($this->result, $otherResult->getResults());
			} else {
				$this->result = array_merge($this->result, $otherResult);
			}
			
			return $this;
		}
		
		/**
		 * Extract values for a specific field from the result set
		 * @param string $field The field to extract
		 * @return array Array of extracted values
		 */
		public function extractFieldValues(string $field): array {
			$values = [];
			
			// Implementation depends on how your QuelResult stores data
			// This is a generic example:
			foreach ($this->getResults() as $result) {
				if (is_object($result)) {
					// Handle entity objects using getter methods
					$getter = 'get' . ucfirst($field);
					
					if (method_exists($result, $getter)) {
						$values[] = $result->$getter();
					} elseif (property_exists($result, $field)) {
						// Try property access if getter doesn't exist
						$values[] = $result->$field;
					}
				} elseif (is_array($result) && isset($result[$field])) {
					// Handle array results
					$values[] = $result[$field];
				}
			}
			
			// Remove duplicates and null values
			return array_values(array_filter(array_unique($values)));
		}
		
		public function offsetExists(mixed $offset): bool {
			return isset($this->result[$offset]);
		}
		
		public function offsetGet(mixed $offset): mixed {
			return $this->result[$offset] ?? null;
		}
		
		public function offsetSet(mixed $offset, mixed $value): void {
			if (is_null($offset)) {
				$this->result[] = $value;
			} else {
				$this->result[$offset] = $value;
			}
		}
		
		public function offsetUnset(mixed $offset): void {
			unset($this->result[$offset]);
		}
	}