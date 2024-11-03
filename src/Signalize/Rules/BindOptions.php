<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstBindOptions;
	use Services\Signalize\Ast\AstBindVariable;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class BindOptions {
		
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
		 * @return AstBindOptions The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(): AstBindOptions {
			// Match een identifier en een dubbele punt ':'
			$this->lexer->match(Token::Identifier);
			$this->lexer->match(Token::Colon);

			// Match het openen van een accolades '{'
			$this->lexer->match(Token::CurlyBraceOpen);
			
			// Probeer een string te matchen, anders match een identifier
			$foundToken = $this->lexer->match(Token::BindVariable);
			$tokenValue = $foundToken->getValue();
			
			if ($this->lexer->optionalMatch(Token::Dot)) {
				do {
					if ($tokenValue !== '') {
						$tokenValue .= '.';
					}
					
					$tokenValue .= $this->lexer->match(Token::Identifier)->getValue();
				} while ($this->lexer->optionalMatch(Token::Dot));
			}

			// Match het sluiten van een accolades '}'
			$this->lexer->match(Token::CurlyBraceClose);
			
			// Als alles goed gaat, retourneer een nieuw AstBindOptions object met de subItems
			return new AstBindOptions(new AstBindVariable($tokenValue));
		}
	}