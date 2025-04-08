<?php
	
	namespace Services\ObjectQuel\Helpers;
	
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\ProxyInterface;
	use Services\EntityManager\Serializers\Serializer;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstMethodCall;
	
	class ResultTransformer {
		
		/**
		 * Sorteert het resultaat
		 * @return void
		 */
		public function sortResults(array &$results, array $sortItems): void {
			usort($results, function ($a, $b) use ($sortItems) {
				foreach ($sortItems as $sortItem) {
					$ast = $sortItem['ast'];
					$order = $sortItem['order'];
					$entity = $ast->getEntityOrParentIdentifier();
					$range = $entity->getRange()->getName();
					
					if ($ast instanceof AstMethodCall) {
						$methodName = $ast->getName();
						$aValue = $a[$range]->{$methodName}();
						$bValue = $b[$range]->{$methodName}();
					} else {
						$aValue = $a[$range];
						$bValue = $b[$range];
					}
					
					if ($aValue < $bValue) {
						return $order === 'desc' ? 1 : -1;
					} elseif ($aValue > $bValue) {
						return $order === 'desc' ? -1 : 1;
					}
				}
				
				return 0;
			});
		}
	}