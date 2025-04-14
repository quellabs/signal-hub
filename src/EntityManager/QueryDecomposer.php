<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstBinaryOperator;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRange;
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
		 * Reference to the EntityManager instance
		 * Used to access entity metadata and other resources needed for query analysis
		 * @var QueryExecutor
		 */
		private QueryExecutor $queryExecutor;
		
		/**
		 * Creates a new QueryDecomposer instance
		 * @param QueryExecutor $queryExecutor
		 */
		public function __construct(QueryExecutor $queryExecutor) {
			$this->queryExecutor = $queryExecutor;
		}
		
		/**
		 * Transforms a WHERE condition to include only parts involving specific ranges.
		 * @param array $ranges Array of AstRange objects to keep in the condition
		 * @param AstInterface|null $whereCondition The complete WHERE condition AST
		 * @return AstInterface|null The transformed condition, or null if no parts involve our ranges
		 */
		protected function getWherePartOfRange(array $ranges, ?AstInterface $whereCondition): ?AstInterface {
			if ($whereCondition === null) {
				return null;
			}

			// Check if the condition involves any of our ranges
			if (!$this->doesConditionInvolveAnyRange($whereCondition, $ranges)) {
				return null;
			}

			// If we're at a leaf node (identifier or literal)
			if (
				$whereCondition instanceof AstIdentifier ||
				$whereCondition instanceof AstString ||
				$whereCondition instanceof AstNumber ||
				$whereCondition instanceof AstBool
			) {
				return $whereCondition; // Keep the node as is
			}

			// For binary operations (expressions, operators, terms, factors)
			if (
				$whereCondition instanceof AstExpression ||
				$whereCondition instanceof AstBinaryOperator ||
				$whereCondition instanceof AstTerm ||
				$whereCondition instanceof AstFactor
			) {
				// For comparison operations, if either side involves our ranges, keep the entire expression
				if ($whereCondition instanceof AstExpression) {
					if ($this->doesConditionInvolveAnyRange($whereCondition->getLeft(), $ranges) ||
						$this->doesConditionInvolveAnyRange($whereCondition->getRight(), $ranges)) {
						return clone $whereCondition; // Keep the entire comparison expression
					}
					
					return null;
				}
				
				// Recursively process the left and right children
				$leftTransformed = $this->getWherePartOfRange($ranges, $whereCondition->getLeft());
				$rightTransformed = $this->getWherePartOfRange($ranges, $whereCondition->getRight());
				
				// If both sides have parts involving our ranges
				if ($leftTransformed !== null && $rightTransformed !== null) {
					// Create a clone to avoid modifying the original
					$newNode = clone $whereCondition;
					$newNode->setLeft($leftTransformed);
					$newNode->setRight($rightTransformed);
					return $newNode;
				} // If only left side involves our ranges
				elseif ($leftTransformed !== null) {
					return $leftTransformed;
				} // If only right side involves our ranges
				elseif ($rightTransformed !== null) {
					return $rightTransformed;
				} // If neither side involves our ranges (shouldn't happen due to initial check)
				else {
					return null;
				}
			}
			
			// For unary operations (NOT, etc.)
			if ($whereCondition instanceof AstUnaryOperation) {
				$transformedExpr = $this->getWherePartOfRange($ranges, $whereCondition->getExpression());
				
				if ($transformedExpr !== null) {
					return new AstUnaryOperation($transformedExpr, $whereCondition->getOperator());
				}
				
				return null;
			}
			
			// Return null for the rest
			return null;
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
		 *
		 * @param string $query The ObjectQuel query to decompose
		 * @param array $staticParams Optional static parameters for the query
		 * @return ExecutionPlan|null The execution plan containing all stages, or null if decomposition failed
		 */
		public function decompose(string $query, array $staticParams = []): ?ExecutionPlan {
			// Create a new execution plan to hold all the query stages
			$plan = new ExecutionPlan();
			
			// Parse the input query string into an Abstract Syntax Tree (AST)
			$ast = $this->queryExecutor->getObjectQuel()->parse($query);
			
			// Get all database ranges from the AST
			// These will be processed together in a single database query
			$databaseRanges = $ast->getDatabaseRanges();
			
			// If there are database ranges, create a stage for the database query
			if (!empty($databaseRanges)) {
				// Create a shallow clone of the original AST
				$clonedAst = clone $ast;
				
				// Extract only the WHERE conditions that involve database ranges
				// This creates a modified WHERE clause specific to database sources
				$clonedAst->setConditions($this->getWherePartOfRange($databaseRanges, $ast->getConditions()));
				
				// Create a new execution stage with a unique ID for this database query
				$plan->createStage(uniqid(), $clonedAst, $staticParams);
			}
			
			// Process each non-database range (like JSON sources) individually
			// Each range gets its own execution stage
			foreach($ast->getOtherRanges() as $otherRange) {
				// Create a shallow clone of the original AST for this range
				$clonedAst = clone $ast;
				
				// Extract only the WHERE conditions that involve this specific range
				// This creates a modified WHERE clause specific to this data source
				$clonedAst->setConditions($this->getWherePartOfRange([$otherRange], $ast->getConditions()));
				
				// Create a new execution stage with a unique ID for this range
				$plan->createStage(uniqid(), $clonedAst, $staticParams, $otherRange);
			}
			
			// Return the complete execution plan with all stages
			return $plan;
		}
	}