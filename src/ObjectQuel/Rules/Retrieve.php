<?php
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\Ast\AstRegExp;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Lexer;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
	use Services\ObjectQuel\Visitors\ContainsMethodCall;
	
	class Retrieve {

		private Lexer $lexer;
		private ArithmeticExpression $expressionRule;
		private LogicalExpression $logicalExpressionRule;
		
		/**
		 * Range parser
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
			$this->expressionRule = new ArithmeticExpression($this->lexer);
			$this->logicalExpressionRule = new LogicalExpression($this->lexer);
		}
		
		/**
		 * Parses the values retrieved by lexer within a given AST structure.
		 * @param AstRetrieve $retrieve The AST retrieval instance.
		 * @return array An array of parsed values.
		 * @throws LexerException|ParserException
		 */
		protected function parseValues(AstRetrieve $retrieve): array {
			$values = [];
			
			do {
				// Bewaar de startpositie van de lexer
				$startPos = $this->lexer->getPos();
				
				// Bepaal of de huidige token een alias vertegenwoordigt
				$aliasToken = $this->lexer->peekNext() == Token::Equals ? $this->lexer->match(Token::Identifier) : null;
				
				if ($aliasToken) {
					$this->lexer->match(Token::Equals);
				}
				
				// Parse de volgende expressie
				$expression = $this->expressionRule->parse();
				
				// Reguliere expressie niet toegestaan in field lijst
				if ($expression instanceof AstRegExp) {
					throw new ParserException("Regular expressions are not allowed in the value list. Please remove the regular expression.");
				}
				
				// Haal de broncode slice op
				$sourceSlice = $this->lexer->getSourceSlice($startPos, $this->lexer->getPos() - $startPos);
				
				// Bepaal en verwerk de alias voor de huidige expressie
				if ($aliasToken === null || !$retrieve->macroExists($aliasToken->getValue())) {
					if ($aliasToken !== null) {
						$retrieve->addMacro($aliasToken->getValue(), $expression);
					}
					
					$aliasName = $aliasToken ? $aliasToken->getValue() : $sourceSlice;
					$values[] = new AstAlias(trim($aliasName), $expression);
				} else {
					throw new ParserException("Duplicate variable name detected: '{$aliasToken->getValue()}'. Please use unique names.");
				}
				
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			return $values;
		}
		
		/**
		 * Parse the 'retrieve' statement of the ObjectQuel language.
		 * @param array $directives
		 * @param AstRangeDatabase[] $ranges
		 * @return AstRetrieve
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(array $directives, array $ranges): AstRetrieve {
			// Match and consume the 'retrieve' token
			$this->lexer->match(Token::Retrieve);
			
			// Create a new AST node for the 'retrieve' operation
			$retrieve = new AstRetrieve($directives, $ranges, $this->lexer->optionalMatch(Token::Unique));
			
			// Match and consume the opening parenthesis
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Parse all values inside the parenthesis and add them to the AstRetrieve node
			foreach($this->parseValues($retrieve) as $value) {
				$retrieve->addValue($value);
			}
			
			// Match and consume the closing parenthesis
			$this->lexer->match(Token::ParenthesesClose);
			
			// Check for an optional 'where' clause and parse its conditions if present
			if ($this->lexer->optionalMatch(Token::Where)) {
				$retrieve->setConditions($this->logicalExpressionRule->parse());
			}
			
			// Sort by
			if ($this->lexer->optionalMatch(Token::Sort)) {
                $this->lexer->match(Token::By);
                
                $sortArray = [];
                
                do {
                    $sortResult = $this->expressionRule->parse();
                    
                    if ($this->lexer->optionalMatch(Token::Asc)) {
                        $order = 'asc';
                    } elseif ($this->lexer->optionalMatch(Token::Desc)) {
                        $order = 'desc';
                    } else {
						$order = '';
					}
					
                    $sortArray[] = ['ast' => $sortResult, 'order' => $order];
                } while ($this->lexer->optionalMatch(Token::Comma));
                
                $retrieve->setSort($sortArray);
			}
			
			// Window (pagination)
			if ($this->lexer->optionalMatch(Token::Window)) {
				$window = $this->lexer->match(Token::Number);
				$this->lexer->match(Token::Using);
				$this->lexer->match(Token::Pagesize);
				$pageSize = $this->lexer->match(Token::Number);
				
				$retrieve->setWindow($window->getValue());
				$retrieve->setPageSize($pageSize->getValue());
			}

			// Optionele puntkomma
			if ($this->lexer->lookahead() == Token::Semicolon) {
				$this->lexer->match(Token::Semicolon);
			}
			
			// Return the retrieve node
			return $retrieve;
		}
	}