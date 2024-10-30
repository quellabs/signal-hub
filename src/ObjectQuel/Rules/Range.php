<?php
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Lexer;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
	
	class Range {
		
		private Lexer $lexer;
		private LogicalExpression $logicalExpressionRule;
		
		/**
		 * Range parser
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
			$this->logicalExpressionRule = new LogicalExpression($this->lexer);
		}
		
		/**
		 * Parse een 'range' clausule in de ObjectQuel query.
		 * Een 'range' clausule definieert een alias voor een entiteit. Bijvoorbeeld: 'RANGE OF x IS y'
		 * waarbij 'x' de alias is en 'y' de naam van de entiteit.
		 * De resultaten worden opgeslagen in de meegegeven $result array.
		 * @return AstRange AstRange
		 * @throws LexerException|ParserException
		 */
		public function parse(): AstRange {
			// Verwacht en consumeert het 'RANGE' token.
			$this->lexer->match(Token::Range);
			
			// Verwacht en consumeert het 'OF' token.
			$this->lexer->match(Token::Of);
			
			// Verwacht en consumeert een 'Identifier' token voor de alias.
			$alias = $this->lexer->match(Token::Identifier);
			
			// Verwacht en consumeert het 'IS' token.
			$this->lexer->match(Token::Is);
			
			// Verwacht en consumeert een 'Identifier' token voor de naam van de entiteit.
			$entityName = $this->lexer->match(Token::Identifier)->getValue();
			
			while ($this->lexer->optionalMatch(Token::Backslash)) {
				$entityName .= "\\" . $this->lexer->match(Token::Identifier)->getValue();
			}
			
			// Parse een optioneel 'via' statement
			$viaIdentifier = null;

			if ($this->lexer->lookahead() == Token::Via) {
				$this->lexer->match(Token::Via);
				$viaIdentifier = $this->logicalExpressionRule->parse();
			}
			
			// Optionele puntkomma
			if ($this->lexer->lookahead() == Token::Semicolon) {
				$this->lexer->match(Token::Semicolon);
			}
			
			// Sla de alias en de naam van de entiteit op in de $result array.
			return new AstRange($alias->getValue(), new AstEntity($entityName), $viaIdentifier);
		}
	}