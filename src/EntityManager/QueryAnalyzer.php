<?php
	
	namespace Services\EntityManager;
	
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
		
	}