<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstIf;
	use Services\Signalize\Ast\AstWhile;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class WhileStatement {
		
		protected Lexer $lexer;
		
		/**
		 * IfStatement constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a while statement
		 * @return AstWhile The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(): AstWhile {
			// Maak een nieuw LogicalExpression + $tokenStream object aan met de lexer
			$tokenStream = new TokenStream($this->lexer);
			$logicalExpression = new LogicalExpression($this->lexer);
			
			// Match 'if' token at the start
			$this->lexer->match(Token::While);
			
			// Match opening parenthesis '('
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Parse the logical expression inside the parentheses
			$expression = $logicalExpression->parse();
			
			// Match closing parenthesis ')'
			$this->lexer->match(Token::ParenthesesClose);
			
			// Parse the body of the 'if' block until the closing curly brace '}'
			$this->lexer->match(Token::CurlyBraceOpen);
			$body = $tokenStream->parse(Token::CurlyBraceClose);
			$this->lexer->match(Token::CurlyBraceClose);
			
			// Return a new AstIf object containing the parsed expression, body, and optional else block
			return new AstWhile($expression, $body);
		}
	}