<?php
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstNull;
    use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIn;
	use Services\ObjectQuel\Ast\AstNot;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
    use Services\ObjectQuel\Ast\AstCheckNull;
    use Services\ObjectQuel\Ast\AstCheckNotNull;
    
    class FilterExpression extends GeneralExpression {
		
		/**
		 * Parses the IN() keyword
		 * @param AstInterface $expression
		 * @return AstIn
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseIn(AstInterface $expression): AstIn {
			$this->lexer->match(Token::In);
			$this->lexer->match(Token::ParenthesesOpen);
			
			$parameterList = [];
			
			do {
				$parameterList[] = parent::parse();
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			$this->lexer->match(Token::ParenthesesClose);
			return new AstIn($expression, $parameterList);
		}
		
		/**
		 * @param AstInterface $expression
		 * @return AstInterface
		 * @throws LexerException|ParserException
		 */
		protected function parseFilterExpression(AstInterface $expression): AstInterface {
			switch($this->lexer->lookahead()) {
				case Token::In:
					return $this->parseIn($expression);
				
				default:
					throw new ParserException("Expected a logical operator");
			}
		}
        
        /**
         * Analyseert de 'is' uitdrukkingen om te bepalen of de gegeven expressie null is of juist niet-null.
         * @param AstInterface $expression De expressie die geanalyseerd moet worden.
         * @return AstInterface Een AstNotNull of AstNull object, afhankelijk van de aanwezigheid van het 'not' token.
         * @throws LexerException
         */
        protected function parseIs(AstInterface $expression): AstInterface {
            // Check of er een 'not' token is, wat wijst op een 'niet-null' expressie
            if ($this->lexer->optionalMatch(Token::Not)) {
                // Match het volgende 'null' token dat nu vereist is na 'not'
                $this->lexer->match(Token::Null);
                // Retourneert een AstNotNull object, geeft aan dat de expressie niet null is
                return new AstCheckNotNull($expression);
            }
            
            // Match het 'null' token voor een normale 'null' expressie
            $this->lexer->match(Token::Null);
            // Retourneert een AstNull object, geeft aan dat de expressie null is
            return new AstCheckNull($expression);
        }
		
		/**
		 * Parse an expression, which can either be a simple term, a ternary
		 * conditional expression, or a relational expression.
		 * @param AstEntity|null $entity
		 * @return AstInterface The resulting AST node representing the parsed expression.
		 * @throws LexerException|ParserException
		 */
		public function parse(?AstEntity $entity = null): AstInterface {
			// Parse the first term in the expression
			$expression = parent::parse($entity);
	
            // After matching IS, look for 'null' or 'not null'
            if ($this->lexer->lookahead() == Token::Is) {
                $this->lexer->match(Token::Is);
                return $this->parseIs($expression);
            }
            
			// After matching NOT, ensure that a valid logical operator follows
			if ($this->lexer->lookahead() == Token::Not) {
				$this->lexer->match(Token::Not);
				return new AstNot($this->parseFilterExpression($expression));
			}
			
			// Handle logical operators that are not prefixed with NOT
			try {
				return $this->parseFilterExpression($expression);
			} catch (ParserException $e) {
				if ($e->getMessage() !== "Expected a logical operator") {
					throw $e;
				}
				
				return $expression;
			}
		}
	}