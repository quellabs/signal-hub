<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Ast\AstRangeJsonSource;
	use Services\ObjectQuel\Ast\AstRetrieve;
	
	/**
	 * ExecutionPlan class manages the execution of query stages within the EntityManager system.
	 * It maintains a collection of stages and provides methods to organize and retrieve them
	 * in the proper execution order.
	 */
	class ExecutionPlan {

		/**
		 * Collection of execution stages that make up this plan
		 * @var ExecutionStage[]
		 */
		private array $stages;
		
		/**
		 * Returns the name of the main output stage.
		 * When there's only one stage, it returns that stage's name.
		 * Otherwise, it returns the default name "main".
		 * Will be configurable in the future.
		 * @return string The name of the main output stage
		 */
		public function getMainStageName(): string {
			if (count($this->stages) == 1) {
				$firstKey = array_key_first($this->stages);
				return $this->stages[$firstKey]->getName();
			}
			
			return "main";
		}
		
		/**
		 * Adds a new execution stage to the plan with the specified parameters.
		 * @param string $name The unique identifier for this stage
		 * @param AstRetrieve $query The query to be executed in this stage
		 * @param array $staticParams Static parameters to be passed to the query execution
		 * @param AstRange|null $attachedRange The range attached to this stage. Null if none.
		 * @return void
		 */
		public function createStage(string $name, AstRetrieve $query, array $staticParams = [], ?AstRange $attachedRange=null): void {
			$this->stages[] = new ExecutionStage($name, $query, $staticParams, $attachedRange);
		}
		
		/**
		 * Returns all stages arranged in the correct execution order that respects dependencies.
		 * The order is critical to ensure that stages are executed only after their dependencies.
		 * @return ExecutionStage[] Array of stages in dependency-respecting execution order
		 * @todo Implement proper topological sorting of the stage dependency graph
		 */
		public function getStagesInOrder(): array {
			// This would implement topological sorting of the stage dependency graph
			// For now, a simple implementation assuming stages are already in order
			return $this->stages;
		}
	}