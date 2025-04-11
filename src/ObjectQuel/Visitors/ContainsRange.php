<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class ContainsRange
	 * Throws an exception if the given range is used in the AST
	 */
	class ContainsRange implements AstVisitorInterface {
		
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
		 * @throws \Exception
		 */
		public function visitNode(AstInterface $node): void {
			if (!$node instanceof AstIdentifier) {
				return;
			}
			
			if ($node->getRange() === null) {
				return;
			}
			
			if ($node->getRange()->getName() !== $this->rangeName) {
				return;
			}
			
			throw new \Exception("Contains {$this->rangeName}");
		}
	}