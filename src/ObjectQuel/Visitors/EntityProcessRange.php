<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class EntityProcessRange
	 * If a given entity is a range, fetch the attached entity and
	 * store the range in the AstEntity node.
	 */
	class EntityProcessRange implements AstVisitorInterface {
		
		/**
		 * Array of ranges (aliases)
		 */
		private array $ranges;
		
		/**
		 * EntityProcessRange constructor.
		 * @param array $ranges Table of ranges (should contain AstRangeDatabase objects)
		 */
		public function __construct(array $ranges) {
			$this->ranges = $ranges;
		}
		
		/**
		 * Returns true if the given entity name is a range
		 * @param string $range The name of the range to search for
		 * @return AstRangeDatabase|null Returns the matching range object or null if not found
		 */
		protected function getRange(string $range): ?AstRangeDatabase {
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
			if ($node instanceof AstIdentifier) {
				$entityName = $node->getName();
				$range = $this->getRange($entityName);
				
				if ($range !== null) {
					$node->setRange($range);
				}
			}
		}
	}