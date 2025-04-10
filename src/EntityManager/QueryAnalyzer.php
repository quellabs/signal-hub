<?php
	
	namespace Services\EntityManager;
	
	use Services\EntityManager\Visitors\ContainsJsonReference;
	use Services\ObjectQuel\Ast\AstRangeJsonSource;
	use Services\ObjectQuel\Ast\AstRetrieve;
	
	class QueryAnalyzer {
		
		/**
		 * Returns true if the query is a hybrid query that mixes database and JSON sources
		 * @param AstRetrieve $ast
		 * @return bool
		 */
		public function isHybridQuery(AstRetrieve $ast): bool {
			return $this->getQueryType($ast) == "hybrid";
		}
		
		/**
		 * Determines the type of query based on the data sources used
		 * Possible return values: "hybrid", "json", or "database"
		 * @param AstRetrieve $ast
		 * @return string
		 */
		public function getQueryType(AstRetrieve $ast): string {
			$hasJsonSource = false;
			$hasDatabaseSource = false;
			
			foreach ($ast->getRanges() as $range) {
				if ($range instanceof AstRangeJsonSource) {
					$hasJsonSource = true;
				} else {
					$hasDatabaseSource = true;
				}
			}
			
			if ($hasDatabaseSource && $hasJsonSource) {
				return "hybrid";
			} elseif ($hasJsonSource) {
				return "json";
			} else {
				return "database";
			}
		}
		
		/**
		 * Determines if the query conditions contain mixed references to both JSON and database sources
		 * This method uses a visitor pattern to traverse the condition AST and check for references
		 * that span across different data source types.
		 * @param AstRetrieve $ast The abstract syntax tree of the retrieve query
		 * @return bool True if conditions reference both JSON and database sources, false otherwise
		 */
		public function queryContainsMixedReference(AstRetrieve $ast): bool {
			try {
				// If there are no conditions, we can't have mixed references
				if ($ast->getConditions() === null) {
					return false;
				}
				
				// Create a visitor that will track JSON references in the AST
				$visitor = new ContainsJsonReference();
				
				// Traverse the conditions with our visitor
				// If the visitor encounters mixed references, it will throw an exception
				$ast->getConditions()->accept($visitor);
				
				// If no exception was thrown, no mixed references were found
				return false;
			} catch (\Exception $e) {
				// An exception indicates that mixed references were detected
				return true;
			}
		}
	}