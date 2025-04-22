<?php
	
	
	namespace Quellabs\ObjectQuel\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\AstInterface;
   use Quellabs\ObjectQuel\ObjectQuel\Ast\AstCheckNull;
   use Quellabs\ObjectQuel\ObjectQuel\Ast\AstIdentifier;
   use Quellabs\ObjectQuel\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class ContainsRange
	 * Throws an exception if the given range is used in the AST
	 */
	class ContainsCheckIsNullForRange implements AstVisitorInterface {
		
		private string $rangeName;
		
		/**
		 * ContainsRange constructor.
		 * @param string $rangeName
		 */
		public function __construct(string $rangeName) {
			$this->rangeName = $rangeName;
		}
		
		/**
		 * Functie om een node in de AST (Abstract Syntax Tree) te bezoeken.
		 * @param AstInterface $node
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			if (!$node instanceof AstCheckNull) {
                return;
            }
            
            if (!$node->getExpression() instanceof AstIdentifier) {
                return;
            }
            
            if ($node->getExpression()->getRange()->getName() === $this->rangeName) {
                throw new \Exception("Contains {$this->rangeName}");
			}
		}
	}