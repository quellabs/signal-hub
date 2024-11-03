<?php
	
	namespace Services\Signalize\Visitors;
	
	use Services\Signalize\Ast\AstFunctionCall;
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	use Services\Signalize\FunctionSignatures;
	use Services\Signalize\ParserException;
	
	class VisitorFunctionExists implements AstVisitorInterface {
		private FunctionSignatures $functionSignatures;
		
		/**
		 * Constructor for VisitorFunctionExists
		 */
		public function __construct() {
			$this->functionSignatures = new FunctionSignatures();
		}
		
		/**
		 * Visits a node and processes it according to its type
		 * Throws an error if a variable is used that does not exist in the symbol table
		 * @param AstInterface $node
		 * @throws ParserException
		 */
		public function visitNode(AstInterface $node): void {
			// Handle token stream nodes
			if ($node instanceof AstFunctionCall) {
				if (!$this->functionSignatures->buildInFunctionExists($node->getName())) {
					throw new ParserException("The function '{$node->getName()}' is not defined in this scope.");
				}
			}
		}
	}