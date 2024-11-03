<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstMethodCall;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class ContainsMethodCall
	 * Throws an exception if the Ast element contains a method call
	 */
	class ContainsMethodCall implements AstVisitorInterface {
		
		/**
		 * Functie om een node in de AST (Abstract Syntax Tree) te bezoeken.
		 * @param AstInterface $node
		 * @return void
		 * @throws \Exception
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstMethodCall) {
				throw new \Exception("Contains method call");
			}
		}
	}