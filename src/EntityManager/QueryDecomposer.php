<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstBinaryOperator;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\Ast\AstUnaryOperation;
	use Services\ObjectQuel\AstInterface;
	use Services\Signalize\Ast\AstBool;
	use Services\Signalize\Ast\AstNumber;
	use Services\Signalize\Ast\AstString;
	
	/**
	 * QueryDecomposer is responsible for breaking down complex queries into simpler
	 * execution stages that can be managed by an ExecutionPlan.
	 */
	class QueryDecomposer {
		
		/**
		 * Returns database ranges
		 * @param AstRetrieve $query
		 * @return AstRange[]
		 */
		public function getDatabaseRanges(AstRetrieve $query): array {
			return array_filter($query->getRanges(), function($range) {
				return $range instanceof AstRangeDatabase;
			});
		}
		
		/**
		 * Returns only the database projections
		 * @return AstAlias[]
		 */
		public function getDatabaseProjections(AstRetrieve $query): array {
			$result = [];
			$databaseRanges = $this->getDatabaseRanges($query);
			
			foreach($query->getValues() as $value) {
				foreach($databaseRanges as $range) {
					if ($this->doesConditionInvolveRange($value, $range)) {
						$result[] = $value;
					}
				}
			}
			
			return $result;
		}
		
		/**
		 * Returns only the projections for the range
		 * @return AstAlias[]
		 */
		public function getRangeProjections(AstRetrieve $query, AstRange $range): array {
			$result = [];
			
			foreach($query->getValues() as $value) {
				if ($this->doesConditionInvolveRange($value, $range)) {
					$result[] = $value;
				}
			}
			
			return $result;
		}
		
		/**
		 * This method creates a version of the original query that only includes
		 * operations that can be handled directly by the database engine,
		 * removing any parts that would require in-memory processing.
		 * @param AstRetrieve $query The original query to be analyzed
		 * @return ExecutionStage|null The execution stage, or null if there is none
		 */
		protected function extractDatabaseOnlyQuery(AstRetrieve $query, array $staticParams=[]): ?ExecutionStage {
			// Clone the query to avoid modifying the original
			// This ensures we preserve the complete query for potential in-memory operations later
			$dbQuery = clone $query;
			
			// Get all database ranges (tables/views that exist in the database)
			// These are data sources that SQL can directly access
			$dbRanges = $this->getDatabaseRanges($query);
			
			// Return null when there are no database ranges
			if (empty($dbRanges)) {
				return null;
			}
			
			// Remove any non-database ranges (e.g., in-memory collections, JSON data)
			// The resulting query will only reference actual database tables/views
			$dbQuery->setRanges($dbRanges);
			
			// Get the database-compatible projections (columns/expressions to select)
			$dbProjections = $this->getDatabaseProjections($query);
			
			// Remove any non-database projections
			// This removes any projections that depend on in-memory operations
			$dbQuery->setValues($dbProjections);
			
			// Filter the conditions to include only those relevant to database ranges
			// This removes conditions that can't be executed by the database engine
			// and preserves the structure of AND/OR operations where possible
			$dbQuery->setConditions($this->getDatabaseOnlyConditions($query->getConditions(), $dbRanges));
			
			// Return the optimized query that can be fully executed by the database
			return new ExecutionStage(uniqid(), $dbQuery, $staticParams);
		}
		
		/**
		 * This method creates a version of the original query that only includes
		 * operations that can be handled directly by the database engine,
		 * removing any parts that would require in-memory processing.
		 * @param AstRetrieve $query The original query to be analyzed
		 * @return ExecutionStage A new query containing only database-executable operations
		 */
		protected function extractRange(AstRetrieve $query, AstRange $range, array $staticParams): ExecutionStage {
			// Clone the query to avoid modifying the original
			// This ensures we preserve the complete query for potential in-memory operations later
			$dbQuery = clone $query;
			
			// Remove any non-database ranges (e.g., in-memory collections, JSON data)
			// The resulting query will only reference actual database tables/views
			$dbQuery->setRanges([$range]);
			
			// Get the database-compatible projections (columns/expressions to select)
			$dbProjections = $this->getRangeProjections($query, $range);
			
			// Remove any non-database projections
			// This removes any projections that depend on in-memory operations
			$dbQuery->setValues($dbProjections);
			
			// Filter the conditions to include only those relevant to database ranges
			// This removes conditions that can't be executed by the database engine
			// and preserves the structure of AND/OR operations where possible
			$dbQuery->setConditions($this->extractFilterConditions($range, $query->getConditions()));
			
			// Extract join conditions
			$joinConditions = $this->extractJoinConditions($range, $query->getConditions());
			
			// Return the optimized query that can be fully executed by the database
			return new ExecutionStage(uniqid(), $dbQuery, $staticParams, $joinConditions);
		}
		
		/**
		 * Extracts conditions that can be executed directly by the database engine.
		 *
		 * This function filters a condition tree to include only expressions that can be
		 * evaluated by the database (based on the provided database ranges), removing any
		 * parts that would require in-memory processing (like JSON operations).
		 * @param AstInterface|null $condition The condition AST to filter
		 * @param array $dbRanges Array of ranges that can be handled by the database
		 * @return AstInterface|null The filtered condition AST, or null if nothing can be handled by DB
		 */
		protected function getDatabaseOnlyConditions(?AstInterface $condition, array $dbRanges): ?AstInterface {
			// Base case: if no condition provided, return null
			if ($condition === null) {
				return null;
			}
			
			// Handle unary operations (NOT, IS NULL, etc.)
			if ($condition instanceof AstUnaryOperation) {
				// Recursively process the inner expression
				$innerCondition = $this->getDatabaseOnlyConditions($condition->getExpression(), $dbRanges);
				
				// If inner expression can be handled by DB, create a new unary operation with it
				if ($innerCondition !== null) {
					return new AstUnaryOperation($innerCondition, $condition->getOperator());
				}
				
				// If inner expression can't be handled by DB, return null
				return null;
			}
			
			// Handle comparison operations (e.g., =, >, <, LIKE, etc.)
			if ($condition instanceof AstExpression) {
				// Check if either side of the expression involves database fields
				$leftInvolvesDb = $this->doesConditionInvolveAnyRange($condition->getLeft(), $dbRanges);
				$rightInvolvesDb = $this->doesConditionInvolveAnyRange($condition->getRight(), $dbRanges);
				
				// Case 1: Keep expressions where both sides involve database ranges
				// (e.g., table1.column = table2.column)
				if ($leftInvolvesDb && $rightInvolvesDb) {
					return clone $condition;
				}
				
				// Case 2: Keep expressions where left side is a DB field and right side is a literal
				// (e.g., table.column = 'value')
				if ($leftInvolvesDb && !$this->involvesAnyRange($condition->getRight())) {
					return clone $condition;
				}
				
				// Case 3: Keep expressions where right side is a DB field and left side is a literal
				// (e.g., 'value' = table.column)
				if ($rightInvolvesDb && !$this->involvesAnyRange($condition->getLeft())) {
					return clone $condition;
				}
				
				// If expression involves JSON ranges or other non-DB operations, exclude it
				return null;
			}
			
			// Handle binary operators (AND, OR)
			if ($condition instanceof AstBinaryOperator) {
				// Recursively process both sides of the operator
				$leftCondition = $this->getDatabaseOnlyConditions($condition->getLeft(), $dbRanges);
				$rightCondition = $this->getDatabaseOnlyConditions($condition->getRight(), $dbRanges);
				
				// Case 1: If both sides have valid database conditions
				// (e.g., (table1.col = 5) AND (table2.col = 'text'))
				if ($leftCondition !== null && $rightCondition !== null) {
					$newNode = clone $condition;
					$newNode->setLeft($leftCondition);
					$newNode->setRight($rightCondition);
					return $newNode;
				}
				
				// Case 2: If only left side has valid database conditions
				if ($leftCondition !== null) {
					return $leftCondition;
				}
				
				// Case 3: If only right side has valid database conditions
				if ($rightCondition !== null) {
					return $rightCondition;
				}
				
				// Case 4: If neither side has valid database conditions
				return null;
			}
			
			// For literals or other standalone expressions that don't involve any ranges.
			// These can be safely pushed to the database.
			if (!$this->involvesAnyRange($condition)) {
				return clone $condition;
			}
			
			// Default case: condition not suitable for database execution
			return null;
		}
		
		/**
		 * Extracts just the filtering conditions for a specific range (not join conditions)
		 * @param AstRange $range The range to extract filter conditions for
		 * @param AstInterface|null $whereCondition The complete WHERE condition AST
		 * @return AstInterface|null The filter conditions for this range
		 */
		protected function extractFilterConditions(AstRange $range, ?AstInterface $whereCondition): ?AstInterface {
			// Do nothing if there's no $whereCondition
			if ($whereCondition === null) {
				return null;
			}
			
			// For comparison operations, check if it's a filter condition
			if ($whereCondition instanceof AstExpression) {
				$leftInvolvesRange = $this->doesConditionInvolveRange($whereCondition->getLeft(), $range);
				$rightInvolvesRange = $this->doesConditionInvolveRange($whereCondition->getRight(), $range);
				
				// If only one side involves our range and the other doesn't involve any range,
				// it's a filter condition (e.g., x.value > 100)
				if ($leftInvolvesRange && !$this->involvesAnyRange($whereCondition->getRight())) {
					return clone $whereCondition;
				}
				
				if ($rightInvolvesRange && !$this->involvesAnyRange($whereCondition->getLeft())) {
					return clone $whereCondition;
				}
				
				// Otherwise, it might be a join condition, so we don't include it here
				return null;
			}
			
			// For binary operators (AND, OR)
			if ($whereCondition instanceof AstBinaryOperator) {
				$leftFilters = $this->extractFilterConditions($range, $whereCondition->getLeft());
				$rightFilters = $this->extractFilterConditions($range, $whereCondition->getRight());
				
				// If both sides have filters
				if ($leftFilters !== null && $rightFilters !== null) {
					$newNode = clone $whereCondition;
					$newNode->setLeft($leftFilters);
					$newNode->setRight($rightFilters);
					return $newNode;
				}
				
				// If only one side has filters
				if ($leftFilters !== null) {
					return $leftFilters;
				} elseif ($rightFilters !== null) {
					return $rightFilters;
				} else {
					return null;
				}
			}
			
			return null;
		}
		
		/**
		 * Extracts join conditions involving a specific range with any other range
		 * @param AstRange $range The range to extract join conditions for
		 * @param AstInterface|null $whereCondition The complete WHERE condition AST
		 * @return AstInterface|null The join conditions involving this range
		 */
		protected function extractJoinConditions(AstRange $range, ?AstInterface $whereCondition): ?AstInterface {
			if ($whereCondition === null) {
				return null;
			}
			
			// For comparison operations, check if it's a join condition
			if ($whereCondition instanceof AstExpression) {
				$leftInvolvesRange = $this->doesConditionInvolveRange($whereCondition->getLeft(), $range);
				$rightInvolvesRange = $this->doesConditionInvolveRange($whereCondition->getRight(), $range);
				
				// If one side involves our range and the other side involves a different range,
				// then it's a join condition
				if ($leftInvolvesRange && $this->involvesAnyRange($whereCondition->getRight()) &&
					!$rightInvolvesRange) {
					return clone $whereCondition;
				}
				
				if ($rightInvolvesRange && $this->involvesAnyRange($whereCondition->getLeft()) &&
					!$leftInvolvesRange) {
					return clone $whereCondition;
				}
				
				// Otherwise, it's not a join condition involving our range
				return null;
			}
			
			// For binary operators (AND, OR)
			if ($whereCondition instanceof AstBinaryOperator) {
				$leftJoins = $this->extractJoinConditions($range, $whereCondition->getLeft());
				$rightJoins = $this->extractJoinConditions($range, $whereCondition->getRight());
				
				// If both sides have join conditions
				if ($leftJoins !== null && $rightJoins !== null) {
					$newNode = clone $whereCondition;
					$newNode->setLeft($leftJoins);
					$newNode->setRight($rightJoins);
					return $newNode;
				}
				
				// If only one side has join conditions
				if ($leftJoins !== null) {
					return $leftJoins;
				} elseif ($rightJoins !== null) {
					return $rightJoins;
				} else {
					return null;
				}
			}
			
			return null;
		}
		
		/**
		 * Determines if an AST node involves any data range (database table or other data source).
		 * This recursive method checks whether any part of the given condition references
		 * a data range, which helps identify expressions that need database or in-memory execution.
		 * @param AstInterface $condition The AST node to check
		 * @return bool True if the condition involves any data range, false otherwise
		 */
		private function involvesAnyRange(AstInterface $condition): bool {
			// For identifiers (column names), check if they have an associated range
			if ($condition instanceof AstIdentifier) {
				// An identifier with a range represents a field from a table or other data source
				return $condition->getRange() !== null;
			}
			
			// For unary operations (NOT, IS NULL, etc.), check the inner expression
			if ($condition instanceof AstUnaryOperation) {
				// Recursively check if the inner expression involves any range
				return $this->involvesAnyRange($condition->getExpression());
			}
			
			// For binary nodes with left and right children, check both sides
			if (
				$condition instanceof AstExpression ||   // Comparison expressions (=, <, >, etc.)
				$condition instanceof AstBinaryOperator || // Logical operators (AND, OR)
				$condition instanceof AstTerm ||         // Addition, subtraction
				$condition instanceof AstFactor          // Multiplication, division
			) {
				// Return true if either the left or right side involves any range
				return
					$this->involvesAnyRange($condition->getLeft()) ||
					$this->involvesAnyRange($condition->getRight());
			}
			
			// Literals (numbers, strings) and other node types don't involve ranges
			return false;
		}
		
		/**
		 * Checks if a condition node involves a specific range.
		 * @param AstInterface $condition The condition AST node
		 * @param AstRange $range The range to check for
		 * @return bool True if the condition involves the range
		 */
		protected function doesConditionInvolveRange(AstInterface $condition, AstRange $range): bool {
			// For property access, check if the base entity matches our range
			if ($condition instanceof AstIdentifier) {
				return $condition->getRange()->getName() === $range->getName();
			}
			
			// For unary operations (NOT, etc.)
			if ($condition instanceof AstUnaryOperation) {
				return $this->doesConditionInvolveRange($condition->getExpression(), $range);
			}
			
			// For comparison operations, check each side
			if (
				$condition instanceof AstExpression ||
				$condition instanceof AstBinaryOperator ||
				$condition instanceof AstTerm ||
				$condition instanceof AstFactor
			) {
				$leftInvolves = $this->doesConditionInvolveRange($condition->getLeft(), $range);
				$rightInvolves = $this->doesConditionInvolveRange($condition->getRight(), $range);
				return $leftInvolves || $rightInvolves;
			}
			
			return false;
		}
		
		/**
		 * Checks if a condition involves any of the specified ranges
		 * @param AstInterface $condition The condition to check
		 * @param array $ranges Array of AstRange objects
		 * @return bool True if the condition involves any of the ranges
		 */
		protected function doesConditionInvolveAnyRange(AstInterface $condition, array $ranges): bool {
			foreach ($ranges as $range) {
				if ($this->doesConditionInvolveRange($condition, $range)) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Decomposes a query into separate execution stages for different data sources.
		 * This function takes a mixed-source query and creates an execution plan with
		 * appropriate stages for database and JSON sources.
		 * @param AstRetrieve $query The ObjectQuel query to decompose
		 * @param array $staticParams Optional static parameters for the query
		 * @return ExecutionPlan|null The execution plan containing all stages, or null if decomposition failed
		 */
		public function decompose(AstRetrieve $query, array $staticParams = []): ?ExecutionPlan {
			// Create a new execution plan to hold all the query stages
			$plan = new ExecutionPlan();

			// Extract the database query
			$databaseStage = $this->extractDatabaseOnlyQuery($query);
			
			// Create a new execution stage with a unique ID for this database query
			if (!empty($databaseStage)) {
				$plan->addStage($databaseStage);
			}
			
			// Process each non-database range (like JSON sources) individually
			// Each range gets its own execution stage
			foreach($query->getOtherRanges() as $otherRange) {
				// Create a copy of the query with only the range information in it
				$rangeStage = $this->extractRange($query, $otherRange, $staticParams);
				
				// Adds the execution to the list
				$plan->addStage($rangeStage);
			}
			
			// Return the complete execution plan with all stages
			return $plan;
		}
	}