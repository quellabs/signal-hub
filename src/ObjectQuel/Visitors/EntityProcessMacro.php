<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class EntityProcessRange
	 * If a given entity is a range, fetch the attached entity and
	 * store it in the AstEntity node.
	 */
	class EntityProcessMacro implements AstVisitorInterface {
		
		/**
		 * Array of macros
		 * @var array $ranges
		 */
		private array $macros;
		
		/**
		 * EntityProcessMacro constructor.
		 * @param array $macros
		 */
		public function __construct(array $macros) {
			$this->macros = $macros;
		}
		
		/**
		 * Returns true if the identifier is an entity, false if not
		 * @param AstInterface $ast
		 * @return bool
		 */
		protected function identifierIsEntity(AstInterface $ast): bool {
			return (
				$ast instanceof AstIdentifier &&
				$ast->getRange() instanceof AstRangeDatabase &&
				!$ast->hasNext()
			);
		}
		
		/**
		 * Visit a node in the AST.
		 * @param AstInterface $node The node to visit.
		 * @eeturn void
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstIdentifier) {
				$entityName = $node->getEntityName();
				
				if ($entityName === null) {
					return;
				}
				
				if (array_key_exists($entityName, $this->macros) && $this->identifierIsEntity($this->macros[$entityName])) {
					$node->setName($this->macros[$entityName]->getName());
					$node->setRange($this->macros[$entityName]->getRange());
				}
			}
		}
	}