<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstVariableAssignment;
	use Services\Signalize\Lexer;
	
	class VariableAssignment {
		
		protected Lexer $lexer;
		private LogicalExpression $logicalExpression;
		
		/**
		 * TokenStream constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
			$this->logicalExpression = new LogicalExpression($lexer);
		}
		
		/**
		 * Parse variable assignment
		 * @param string $name
		 * @return AstVariableAssignment The resulting AST node representing the parsed expression.
		 */
		public function parse(string $name): AstVariableAssignment {
			return new AstVariableAssignment($name, $this->logicalExpression->parse());
		}
	}