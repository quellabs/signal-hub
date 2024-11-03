<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class EntityProcessRange
	 * If a given entity is a range, fetch the attached entity and
	 * store it in the AstEntity node.
	 */
	class EntityProcessRange implements AstVisitorInterface {
		
		/**
		 * Array of ranges (aliases)
		 */
		private array $ranges;

		/**
		 * EntityProcessRange constructor.
		 * @param array $ranges Table of ranges
		 */
		public function __construct(array $ranges) {
			$this->ranges = $ranges;
		}
		
		/**
		 * Returns the entity name from the range array
		 * @param string $range
		 * @return AstEntity|null
		 */
		protected function getEntityFromRange(string $range): ?AstEntity {
			foreach($this->ranges as $astRange) {
				if ($astRange->getName() == $range) {
					return $astRange->getEntity();
				}
			}
			
			return null;
		}
		
		/**
		 * Returns true if the given entity name is a range
		 * @param string $range
		 * @return AstRange|null
		 */
		protected function getRange(string $range): ?AstRange {
			foreach($this->ranges as $astRange) {
				if ($astRange->getName() == $range) {
					return $astRange;
				}
			}
			
			return null;
		}
		
		/**
		 * Visit a node in the AST.
		 * @param AstInterface $node The node to visit.
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstEntity) {
				$entityName = $node->getName();
				$range = $this->getRange($entityName);
				
				if ($range !== null) {
					$node->setRange($range);
					$node->setName($this->getEntityFromRange($entityName)->getName());
				}
			}
		}
	}