<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\QuelException;
	use Services\ObjectQuel\QuelResult;
	
	/**
	 * Responsible for executing individual stages of a decomposed query plan
	 */
	class PlanExecutor {
		/**
		 * @var EntityManager
		 */
		private EntityManager $entityManager;
		
		/**
		 * Create a new stage executor
		 * @param EntityManager $entityManager The entity manager to use for execution
		 */
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
		}
		
		/**
		 * Process an individual stage with dependencies
		 * @param ExecutionStage $stage The stage to execute
		 * @param array $intermediateResults Results from previous stages
		 * @return QuelResult|null The result of this stage
		 * @throws QuelException
		 */
		private function executeStage(ExecutionStage $stage, array $intermediateResults): ?QuelResult {
			// Prepare parameters by combining static params with values from previous stages
			$params = $stage->getStaticParams();
			
			foreach ($stage->getDependentParams() as $paramName => $source) {
				$sourceStage = $source['sourceStage'];
				$sourceField = $source['sourceField'];
				
				if (!isset($intermediateResults[$sourceStage])) {
					throw new \RuntimeException("Stage '{$stage->getName()}' depends on stage '{$sourceStage}' which has not been executed yet");
				}
				
				$sourceResults = $intermediateResults[$sourceStage];
				
				if ($sourceField !== null && $sourceResults instanceof QuelResult) {
					$params[$paramName] = $sourceResults->extractFieldValues($sourceField);
				} else {
					$params[$paramName] = $sourceResults;
				}
			}
			
			// Execute the query with combined parameters
			$result = $this->entityManager->executeSimpleQuery($stage->getQuery(), $params);
			
			if (!$result) {
				throw new QuelException("Execution of stage '{$stage->getName()}' failed: " .
					$this->entityManager->getLastErrorMessage());
			}
			
			// Apply post-processing if specified
			if ($stage->hasResultProcessor()) {
				$processor = $stage->getResultProcessor();
				$processor($result);
			}
			
			return $result;
		}
		
		private function combineResults(ExecutionPlan $plan, array $intermediateResults): ?QuelResult {
			// Get the main stage name from the plan
			$mainStageName = $plan->getMainStageName();
			
			// Find the main result
			if (!isset($intermediateResults[$mainStageName])) {
				return null;
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
		 * Execute a query stage with the given intermediate results
		 * @param ExecutionPlan $plan
		 * @return QuelResult|null Results from executing the plan
		 * @throws QuelException
		 */
		public function execute(ExecutionPlan $plan): ?QuelResult {
			$intermediateResults = [];
			
			// Get stages in execution order (respecting dependencies)
			$stagesInOrder = $plan->getStagesInOrder();
			
			// Execute each stage in the correct order
			foreach($stagesInOrder as $stage) {
				try {
					$intermediateResults[$stage->getName()] = $this->executeStage($stage, $intermediateResults);
				} catch (\Exception $e) {
					// Log error and handle failure
					// For now, just throw it up
					throw $e;
				}
			}
			
			return $this->combineResults($plan, $intermediateResults);
		}
	}