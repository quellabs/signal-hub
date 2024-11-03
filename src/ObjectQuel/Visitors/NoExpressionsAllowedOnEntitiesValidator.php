<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
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
		 * Bezoekt een node in de AST.
		 * @param AstInterface $node De node om te bezoeken.
		 * @return void
		 * @throws QuelException
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstTerm || $node instanceof AstFactor || $node instanceof AstExpression) {
				if ($node->getLeft() instanceof AstEntity || $node->getRight() instanceof AstEntity) {
					throw new QuelException("Unsupported operation on entire entities. You cannot perform arithmetic operations directly on entities. Please specify the specific fields or properties of the entities you wish to use in the calculation.");
				}
			}
		}
	}