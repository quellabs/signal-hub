<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstRetrieve;
	
	class ExecutionPlan {
		private array $stages;
		
		/**
		 * The name of the main output stage. Will be configurable in the future
		 * @return string
		 */
		public function getMainStageName(): string {
			if (count($this->stages) == 1) {
				$firstKey = array_key_first($this->stages);
				return $this->stages[$firstKey]->getName();
			}
			
			return "main";
		}
		
		/**
		 * Adds a new stage to the execution plan
		 * @param string $name
		 * @param string $query
		 * @param array $staticParams
		 * @return void
		 */
		public function createStage(string $name, string $query, array $staticParams = []): void {
			$this->stages[] = new ExecutionStage($name, $query, $staticParams);
		}
		
		/**
		 * Get all stages in dependency-respecting execution order
		 * @return ExecutionStage[] Array of stages in execution order
		 */
		public function getStagesInOrder(): array {
			// This would implement topological sorting of the stage dependency graph
			// For now, a simple implementation assuming stages are already in order
			return $this->stages;
		}
	}