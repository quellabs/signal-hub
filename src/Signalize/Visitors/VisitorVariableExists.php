<?php
	
	namespace Services\Signalize\Visitors;
	
	use Services\Signalize\Ast\AstIdentifier;
	use Services\Signalize\Ast\AstIf;
	use Services\Signalize\Ast\AstTokenStream;
	use Services\Signalize\Ast\AstVariableAssignment;
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	use Services\Signalize\ParserException;
	
	class VisitorVariableExists implements AstVisitorInterface {
		private array $handledAsts;
		private array $symbolTables;
		
		/**
		 * Constructor for VisitorVariableExists
		 */
		public function __construct() {
			$this->handledAsts = [];
			$this->symbolTables = [];
		}
		
		/**
		 * Checks if a variable exists in the current symbol tables
		 * @param string $name
		 * @return bool
		 */
		protected function variableExists(string $name): bool {
			foreach($this->symbolTables as $symbolTable) {
				if (isset($symbolTable[$name])) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Visits a node and processes it according to its type
		 * Throws an error if a variable is used that does not exist in the symbol table
		 * @param AstInterface $node
		 * @throws ParserException
		 */
		public function visitNode(AstInterface $node): void {
			// Skip nodes that have already been handled
			if (in_array(spl_object_id($node), $this->handledAsts)) {
				return;
			}
			
			// Handle token stream nodes
			if ($node instanceof AstTokenStream) {
				$this->symbolTables[] = $node->getDeclaredVariables();
				
				foreach($node->getTokens() as $token) {
					$token->accept($this);
				}
				
				array_pop($this->symbolTables);
			}
			
			// Handle variable assignment + identifier nodes
			if ($node instanceof AstVariableAssignment || $node instanceof AstIdentifier) {
				if (!$this->variableExists($node->getName())) {
					throw new ParserException("The variable '{$node->getName()}' is not defined in this scope.");
				}
			}
			
			// Add node to the list of handled nodes
			$this->handledAsts[] = spl_object_id($node);
		}
	}