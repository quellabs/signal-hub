<?php
	
	namespace Services\EntityManager;
	
	class QueryDecomposer {
		
		private EntityManager $entity_manager;
		
		public function __construct(EntityManager $entityManager) {
			$this->entity_manager = $entityManager;
		}

		public function decompose(string $query, array $staticParams = []): ?ExecutionPlan {
			$plan = new ExecutionPlan();

			$plan->createStage(uniqid(), $query, $staticParams);
			
			return $plan;
		}
	}