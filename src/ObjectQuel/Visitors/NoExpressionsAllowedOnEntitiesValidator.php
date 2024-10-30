<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Class EntityExistenceValidator
	 * Validates the existence of entities within an AST.
	 */
	class NoExpressionsAllowedOnEntitiesValidator implements AstVisitorInterface {
		
		/**
		 * Bezoekt een node in de AST.
		 * @param AstInterface $node De node om te bezoeken.
		 * @throws QuelException
		 */
		public function visitNode(AstInterface $node) {
			if ($node instanceof AstTerm || $node instanceof AstFactor || $node instanceof AstExpression) {
				if ($node->getLeft() instanceof AstEntity || $node->getRight() instanceof AstEntity) {
					throw new QuelException("Unsupported operation on entire entities. You cannot perform arithmetic operations directly on entities. Please specify the specific fields or properties of the entities you wish to use in the calculation.");
				}
			}
		}
	}