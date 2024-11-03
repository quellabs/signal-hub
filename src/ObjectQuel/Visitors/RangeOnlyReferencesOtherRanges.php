<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Class RangeOnlyReferencesOtherRanges
	 */
	class RangeOnlyReferencesOtherRanges implements AstVisitorInterface {
		
		/**
		 * Visit a node in the AST.
		 * @param AstInterface $node The node to visit.
		 * @throws QuelException
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstIdentifier) {
				if (empty($node->getEntityOrParentIdentifier()->getRange())) {
					throw new QuelException("The 'via' clause in the range '%s' directly refers to an entity. The 'via' clause must reference another range. Please review the query and ensure that the 'via' clause correctly represents the relationship between ranges.");
				}
			}
		}
	}