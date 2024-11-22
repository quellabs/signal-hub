<?php
	
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstConcat;
	use Services\ObjectQuel\Ast\AstCount;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstExists;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstIsEmpty;
	use Services\ObjectQuel\Ast\AstIsFloat;
	use Services\ObjectQuel\Ast\AstIsInteger;
	use Services\ObjectQuel\Ast\AstIsNumeric;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstParameter;
	use Services\ObjectQuel\Ast\AstSearch;
	use Services\ObjectQuel\Ast\AstString;
	use Services\ObjectQuel\Ast\AstUCount;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\Lexer;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
	
	class QueryFunction {
		
		private Lexer $lexer;
		private GeneralExpression $expressionRule;
		
		/**
		 * QueryFunction constructor
		 * @param GeneralExpression $expression
		 */
		public function __construct(GeneralExpression $expression) {
			$this->expressionRule = $expression;
			$this->lexer = $expression->getLexer();
		}
		
		/**
		 * Count operator
		 * @return AstCount
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseCount(): AstCount {
			$this->lexer->match(Token::ParenthesesOpen);
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();
			$this->lexer->match(Token::ParenthesesClose);
			
			if ((!$countIdentifier instanceof AstEntity) && (!$countIdentifier instanceof AstIdentifier)) {
				throw new ParserException("COUNT operator takes an entity or entity property as parameter.");
			}
			
			return new AstCount($countIdentifier);
		}
		
		/**
		 * Count operator
		 * @return AstUCount
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseUCount(): AstUCount {
			$this->lexer->match(Token::ParenthesesOpen);
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();
			$this->lexer->match(Token::ParenthesesClose);
			
			if ((!$countIdentifier instanceof AstEntity) && (!$countIdentifier instanceof AstIdentifier)) {
				throw new ParserException("UCOUNT operator takes an entity or entity property as parameter.");
			}
			
			return new AstUCount($countIdentifier);
		}
		
		/**
		 * Parses a CONCAT function in the query.
		 * @param AstEntity|null $entity The entity that owns this Concat operation.
		 * @return AstConcat An AstConcat object containing parsed parameters.
		 * @throws LexerException|ParserException
		 */
		protected function parseConcat(?AstEntity $entity=null): AstConcat {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Loop to parse each identifier inside the parentheses.
			$parameters = [];
			
			do {
				$parameters[] = $this->expressionRule->parse($entity);
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			// Match the closing parenthesis.
			$this->lexer->match(Token::ParenthesesClose);
			
			// Create and return a new AstConcat object with the parsed parameters.
			return new AstConcat($parameters);
		}
		
		/**
		 * Parse search operator
		 * @throws LexerException|ParserException
		 */
		protected function parseSearch(): AstSearch {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Parse the identifier list.
			$identifiers = $this->parseIdentifierList();
			
			if (empty($identifiers)) {
				throw new ParserException("Missing identifier list for SEARCH operator.");
			}
			
			// Parse the search string
			$searchString = $this->expressionRule->parse();
			
			if ((!$searchString instanceof AstString) && (!$searchString instanceof AstParameter)) {
				throw new ParserException("Missing search string for SEARCH operator.");
			}
			
			// Match the closing parenthesis.
			$this->lexer->match(Token::ParenthesesClose);
			
			// Return the AstSearch object
			return new AstSearch($identifiers, $searchString);
		}
		
		/**
		 * Parse 'is_empty'. This returns true if the value is falsey: null, empty string or 0
		 * @return AstIsEmpty
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseIsEmpty(): AstIsEmpty {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Fetch identifier or string to check
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();
			
			// Closing parenthesis
			$this->lexer->match(Token::ParenthesesClose);
			
			if ((!$countIdentifier instanceof AstIdentifier) && (!$countIdentifier instanceof AstString) && (!$countIdentifier instanceof AstNumber)) {
				throw new ParserException("is_empty operator takes an identifier, string or number as parameter.");
			}
			
			// Return the AST
			return new AstIsEmpty($countIdentifier);
		}
		
		/**
		 * is_numeric function. Usage: where is_numeric(x.productsId)
		 * @return AstIsNumeric
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseIsNumeric(): AstIsNumeric {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);

			// Fetch identifier or string to check
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();

			// Closing parenthesis
			$this->lexer->match(Token::ParenthesesClose);
			
			if (
				(!$countIdentifier instanceof AstIdentifier) &&
				(!$countIdentifier instanceof AstString) &&
				(!$countIdentifier instanceof AstNumber)
			) {
				throw new ParserException("is_numeric operator takes an identifier, string or number as parameter.");
			}
			
			// Return the AST
			return new AstIsNumeric($countIdentifier);
		}
		
		/**
		 * is_integer function. Usage: where is_integer(x.productsId)
		 * @return AstIsInteger
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseIsInteger(): AstIsInteger {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);

			// Fetch identifier or string to check
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();

			// Closing parenthesis
			$this->lexer->match(Token::ParenthesesClose);
			
			if (
				(!$countIdentifier instanceof AstIdentifier) &&
				(!$countIdentifier instanceof AstString) &&
				(!$countIdentifier instanceof AstNumber)
			) {
				throw new ParserException("is_integer operator takes an identifier, string or number as parameter.");
			}
			
			// Return the AST
			return new AstIsInteger($countIdentifier);
		}
		
		/**
		 * is_float function. Usage: where is_float(x.productsId)
		 * @return AstIsFloat
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseIsFloat(): AstIsFloat {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);

			// Fetch identifier or string to check
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();

			// Closing parenthesis
			$this->lexer->match(Token::ParenthesesClose);
			
			if (
				(!$countIdentifier instanceof AstIdentifier) &&
				(!$countIdentifier instanceof AstString) &&
				(!$countIdentifier instanceof AstNumber)
			) {
				throw new ParserException("is_float operator takes an identifier, string or number as parameter.");
			}
			
			// Return the AST
			return new AstIsFloat($countIdentifier);
		}
		
		/**
		 * Parse 'exists'. This will change a relation to INNER when present.
		 * @return AstExists
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseExists(): AstExists {
			// Match the opening parenthesis.
			$this->lexer->match(Token::ParenthesesOpen);
			
			// Fetch identifier or string to check
			$entity = $this->expressionRule->parseConstantOrIdentifier();
			
			// Closing parenthesis
			$this->lexer->match(Token::ParenthesesClose);
			
			if (!$entity instanceof AstEntity) {
				throw new ParserException("exists operator takes an entity as parameter.");
			}
			
			// Return the AST
			return new AstExists($entity);
		}
		
		/**
		 * Parse the identifier list
		 * @return AstIdentifier[]
		 */
		private function parseIdentifierList(): array {
			$identifiers = [];
			
			do {
				if ($this->lexer->lookahead() !== Token::Identifier) {
					break;
				}
				
				$identifiers[] = $this->expressionRule->parse();
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			return $identifiers;
		}
		
		/**
		 * @param string $command
		 * @return AstInterface
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(string $command): AstInterface {
			return match (strtolower($command)) {
				'count' => $this->parseCount(),
				'ucount' => $this->parseUCount(),
				'concat' => $this->parseConcat(),
				'search' => $this->parseSearch(),
				'is_empty' => $this->parseIsEmpty(),
				'is_numeric' => $this->parseIsNumeric(),
				'is_integer' => $this->parseIsInteger(),
				'is_float' => $this->parseIsFloat(),
				'exists' => $this->parseExists(),
				default => throw new ParserException("Command {$command} is not valid."),
			};
		}
	}