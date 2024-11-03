<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstBindStyle;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class BindStyle {
		
		protected Lexer $lexer;
		
		/**
		 * Expression constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a style bind
		 * @return AstBindStyle The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(): AstBindStyle {
			// Maak een nieuw LogicalExpression object aan met de lexer
			$logicalExpression = new LogicalExpression($this->lexer);
			
			// Match een identifier en een dubbele punt ':'
			$this->lexer->match(Token::Identifier);
			$this->lexer->match(Token::Colon);

			// Match het openen van een accolades '{'
			$this->lexer->match(Token::CurlyBraceOpen);
			
			// Parse totdat een accolade sluit '}'
			$subItems = [];

			do {
				// Probeer een string te matchen, anders match een identifier
				if (!($classNameToken = $this->lexer->optionalMatch(Token::String, $foundToken))) {
					$classNameToken = $this->lexer->match(Token::Identifier);
				}
				
				// Match een dubbele punt ':'
				$this->lexer->match(Token::Colon);
				
				// Voeg de klasse naam en de geparste logische expressie toe aan subItems array
				$subItems[] = [
					'class' => $foundToken ? $foundToken->getValue() : $classNameToken->getValue(),
					'ast'   => $logicalExpression->parse(),
				];
			} while ($this->lexer->optionalMatch(Token::Comma)); // Ga door zolang er komma's zijn
			
			// Match het sluiten van een accolades '}'
			$this->lexer->match(Token::CurlyBraceClose);
			
			// Als alles goed gaat, retourneer een nieuw AstBindStyle object met de subItems
			return new AstBindStyle($subItems);
		}
	}