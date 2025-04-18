<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\QuelException;
	use Services\ObjectQuel\QuelResult;
	
	/**
	 * Responsible for executing individual stages of a decomposed query plan
	 *
	 * The PlanExecutor handles the actual execution of ExecutionStages within an ExecutionPlan,
	 * respecting the dependencies between stages and combining their results into a final output.
	 * It manages parameter passing between stages and handles error conditions during execution.
	 */
	class PlanExecutor {
		
		/**
		 * Entity manager instance used to execute the actual queries
		 * This is the underlying engine that processes individual query strings
		 * @var QueryExecutor
		 */
		private QueryExecutor $queryExecutor;
		
		/**
		 * Create a new stage executor
		 * @param QueryExecutor $queryExecutor The entity manager to use for execution
		 */
		public function __construct(QueryExecutor $queryExecutor) {
			$this->queryExecutor = $queryExecutor;
		}
		
		/**
		 * Process an individual stage with dependencies
		 * @param ExecutionStage $stage The stage to execute
		 * @return array The result of this stage's execution
		 * @throws QuelException When dependencies cannot be satisfied or execution fails
		 */
		private function executeStage(ExecutionStage $stage): array {
			// Execute the query with combined parameters
			$result = $this->queryExecutor->executeStage($stage, $stage->getStaticParams());
			
			// Apply post-processing if specified
			if ($result && $stage->hasResultProcessor()) {
				$processor = $stage->getResultProcessor();
				$processor($result);
			}
			
			return $result;
		}
		
		/**
		 * Combines results from multiple stages into a single result object
		 * This method merges the results of all stages into the main stage's result,
		 * creating a consolidated result set that represents the complete query output.
		 * @param ExecutionPlan $plan The execution plan with stage information
		 * @param array $intermediateResults Results from all stages, indexed by stage name
		 * @return array The combined result or null if the main stage has no results
		 */
		private function combineResults(ExecutionPlan $plan, array $intermediateResults): array {
			// Get the main stage name from the plan
			$mainStageName = $plan->getMainStageName();
			
			// Find the main result
			if (!isset($intermediateResults[$mainStageName])) {
				return [];
			}
			
			// Merge all results with the main result
			$mainResult = $intermediateResults[$mainStageName];
			
			foreach ($intermediateResults as $key => $result) {
				// Skip the main result itself and any non-QuelResult values
				if ($key === $mainStageName || !($result instanceof QuelResult)) {
					continue;
				}
				
				$mainResult->merge($result);
			}
			
			// Return the enriched main result
			return $mainResult;
		}
		
		/**
		 * Execute a complete execution plan
		 *
		 * This method:
		 * 1. Retrieves stages in the correct execution order (respecting dependencies)
		 * 2. Optimizes execution for single-stage plans
		 * 3. Executes multi-stage plans in order, tracking intermediate results
		 * 4. Combines stage results into a final output
		 * @param ExecutionPlan $plan The plan containing stages to execute
		 * @return array Results from executing the plan
		 * @throws QuelException When any stage execution fails
		 */
		public function execute(ExecutionPlan $plan): array {
			// Get stages in execution order (respecting dependencies)
			$stagesInOrder = $plan->getStagesInOrder();
			
			// If there's only one stage, perform a simple query execution
			if (count($stagesInOrder) === 1) {
				return $this->queryExecutor->executeStage($stagesInOrder[0], $stagesInOrder[0]->getStaticParams());
			}
			
			// Otherwise, execute each stage in the correct order and combine the results
			$intermediateResults = [];
			
			foreach($stagesInOrder as $stage) {
				try {
					$intermediateResults[$stage->getName()] = $this->executeStage($stage);
				} catch (QuelException $e) {
					throw new QuelException("Stage '{$stage->getName()}' failed: {$e->getMessage()}");
				}
			}
			
			return $this->combineResults($plan, $intermediateResults);
		}
	}