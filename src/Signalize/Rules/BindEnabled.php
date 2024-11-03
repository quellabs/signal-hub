<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstBindEnabled;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class BindEnabled {
		
		protected Lexer $lexer;
		
		/**
		 * Expression constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse an enabled bind
		 * @return AstBindEnabled The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(): AstBindEnabled {
			// Maak een nieuw LogicalExpression object aan met de lexer
			$logicalExpression = new LogicalExpression($this->lexer);
			
			// Match een identifier en een dubbele punt ':'
			$this->lexer->match(Token::Identifier);
			$this->lexer->match(Token::Colon);

			// Match het openen van een accolades '{'
			$this->lexer->match(Token::CurlyBraceOpen);
			
			// Parse de logische expressie binnen de accolades
			$ast = $logicalExpression->parse();
			
			// Match het sluiten van een accolades '}'
			$this->lexer->match(Token::CurlyBraceClose);
			
			// Als alles goed gaat, retourneer een nieuw AstBindEnabled object met de geparste AST
			return new AstBindEnabled($ast);
		}
	}