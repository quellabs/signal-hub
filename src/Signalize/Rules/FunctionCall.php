<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstFunctionCall;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class FunctionCall {
		
		protected Lexer $lexer;
		
		/**
		 * FunctionCall constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a function call
		 * @param string $functionName
		 * @return AstFunctionCall The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(string $functionName): AstFunctionCall {
			// Maak een nieuw LogicalExpression object aan met de lexer
			$logicalExpression = new LogicalExpression($this->lexer);
			
			// Match het open haakje '('
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Als er direct een sluithaakje volgt, retourneer dan een function call zonder parameters
			if ($this->lexer->optionalMatch(Token::ParenthesesClose)) {
				return new AstFunctionCall($functionName, []);
			}
			
			// Loop zolang er komma's gevonden worden
			$parameters = [];

			do {
				$parameters[] = $logicalExpression->parse();
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			// Match het sluitende haakje ')'
			$this->lexer->match(Token::ParenthesesClose);
			
			// Retourneer een nieuw AstFunctionCall object met de functienaam en parameters
			return new AstFunctionCall($functionName, $parameters);
		}
	}