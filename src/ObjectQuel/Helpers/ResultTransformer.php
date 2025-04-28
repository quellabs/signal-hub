<?php
	
	namespace Quellabs\ObjectQuel\ObjectQuel\Helpers;
	
	class ResultTransformer {
		
		/**
		 * Sort the result
		 * @return void
		 */
		public function sortResults(array &$results, array $sortItems): void {
			usort($results, function ($a, $b) use ($sortItems) {
				foreach ($sortItems as $sortItem) {
					$ast = $sortItem['ast'];
					$order = $sortItem['order'];
					$entity = $ast->getParentIdentifier();
					$range = $entity->getRange()->getName();
					
					if ($a[$range] < $b[$range]) {
						return $order === 'desc' ? 1 : -1;
					} elseif ($a[$range] > $b[$range]) {
						return $order === 'desc' ? -1 : 1;
					}
				}
				
				return 0;
			});
		}
	}