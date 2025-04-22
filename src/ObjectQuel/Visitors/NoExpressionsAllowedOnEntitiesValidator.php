<?php
	
	
	namespace Quellabs\ObjectQuel\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Class NoExpressionsAllowedOnEntitiesValidator
	 * Validates that no operations are used on entire entities
	 */
	class NoExpressionsAllowedOnEntitiesValidator implements AstVisitorInterface {
		
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
		 * Bezoekt een node in de AST.
		 * @param AstInterface $node De node om te bezoeken.
		 * @return void
		 * @throws QuelException
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstTerm || $node instanceof AstFactor || $node instanceof AstExpression) {
				if ($this->identifierIsEntity($node->getLeft()) || $this->identifierIsEntity($node->getRight())) {
					throw new QuelException("Unsupported operation on entire entities. You cannot perform arithmetic operations directly on entities. Please specify the specific fields or properties of the entities you wish to use in the calculation.");
				}
			}
		}
	}