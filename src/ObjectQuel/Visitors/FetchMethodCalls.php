<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstMethodCall;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class FetchMethodCalls
	 * Accumulates method calls used in the AST
	 */
	class FetchMethodCalls implements AstVisitorInterface {
		
		private array $calls;
		
		/**
		 * Functie om een node in de AST (Abstract Syntax Tree) te bezoeken.
		 * @param AstInterface $node
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstMethodCall) {
				$this->calls[] = $node;
			}
		}
		
		/**
		 * Fetch the information FetchMethodCalls gathered
		 * @return array
		 */
		public function getResult(): array {
			return array_unique($this->calls);
		}
	}