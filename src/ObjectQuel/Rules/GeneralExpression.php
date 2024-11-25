<?php
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstAnd;
	use Services\ObjectQuel\Ast\AstBool;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstMethodCall;
	use Services\ObjectQuel\Ast\AstNot;
	use Services\ObjectQuel\Ast\AstNull;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstOr;
	use Services\ObjectQuel\Ast\AstParameter;
	use Services\ObjectQuel\Ast\AstRegExp;
	use Services\ObjectQuel\Ast\AstString;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\Lexer;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
	
	class GeneralExpression {
		
		protected Lexer $lexer;
		
		/**
		 * Expression constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a factor in an arithmetic expression. A factor can either be a
		 * parenthesized expression, a constant, or a variable. Additionally, it
		 * can have multiplication (*) or division (/) operations.
		 * @param AstEntity|null $entity
		 * @return AstInterface The resulting AST node representing the parsed factor.
		 * @throws LexerException|ParserException
		 */
		protected function parseFactor(?AstEntity $entity = null): AstInterface {
			// Check if the next token is an opening parenthesis
			if ($this->lexer->lookahead() == Token::ParenthesesOpen) {
				// If so, consume the opening parenthesis
				$this->lexer->match(Token::ParenthesesOpen);
				
				// Parse the expression inside the parentheses
				$expression = $this->parse();
				
				// Consume the closing parenthesis
				$this->lexer->match(Token::ParenthesesClose);
				
				// Return the parsed expression
				return $expression;
			}
			
			// Parse a constant or an identifier (like a variable)
			$constantOrVariable = $this->parseConstantOrIdentifier($entity);
			
			// Check if the next token is either '*' or '/'
			switch($this->lexer->lookahead()) {
				case Token::Star :
					$this->lexer->match($this->lexer->lookahead());
					return new AstFactor($constantOrVariable, $this->parseFactor(), "*");
					
				case Token::Slash :
					$this->lexer->match($this->lexer->lookahead());
					return new AstFactor($constantOrVariable, $this->parseFactor(), "/");
					
				default :
					return $constantOrVariable;
					
			}
		}
		
		/**
		 * Parse a term in an arithmetic expression. A term can either be a single
		 * factor or an addition (+) or subtraction (-) operation between factors.
		 * @param AstEntity|null $entity
		 * @return AstInterface The resulting AST node representing the parsed term.
		 * @throws LexerException|ParserException
		 */
		protected function parseTerm(?AstEntity $entity = null): AstInterface {
			// Parse the first factor in the term
			$factor = $this->parseFactor($entity);
			
			// Check if the next token is either '+' or '-'
			switch($this->lexer->lookahead()) {
				case Token::Plus :
					$this->lexer->match($this->lexer->lookahead());
					return new AstTerm($factor, $this->parseTerm(), "+");

				case Token::Minus :
					$this->lexer->match($this->lexer->lookahead());
					return new AstTerm($factor, $this->parseTerm(), "-");
					
				default:
					return $factor;
			}
		}
		
		/**
		 * Parse a relational operator
		 * @param $lookahead
		 * @param AstEntity|null $entity
		 * @param AstInterface $term
		 * @return AstExpression
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseRelationalOperator($lookahead, ?AstEntity $entity, AstInterface $term): AstExpression {
			// Consume the operator token and store its value
			$operatorToken = $this->lexer->match($lookahead);
			
			// Parse right side of expression
			$rightSide = $this->parse($entity);
			
			// If the right side is a regular expression, only allow Token::Equals, Token::Unequal
			if (($rightSide instanceof AstRegExp) && !in_array($lookahead, [Token::Equals, Token::Unequal])) {
				throw new ParserException("Unsupported operator used with regular expression. Only '=' and '<>' operators are allowed for regular expression comparisons.");
			}
			
			// Create and return a new AstExpression node
			return new AstExpression($term, $rightSide, $operatorToken->getValue());
		}
		
		/**
		 * Returns the lexer instance
		 * @return Lexer
		 */
		public function getLexer(): Lexer {
			return $this->lexer;
		}
		
		/**
		 * Parses a constant
		 * @param AstEntity|null $entity
		 * @return AstInterface
		 * @throws LexerException|ParserException
		 */
		public function parseConstantOrIdentifier(?AstEntity $entity = null): AstInterface {
			$token = $this->lexer->peek();
			$tokenType = $token->getType();
			$tokenValue = $token->getValue();
			$tokenExtraData = $token->getExtraData();
			
			switch ($tokenType) {
				case Token::Plus  :
				case Token::Minus  :
					if (($this->lexer->optionalMatch(Token::Number, $resultToken))) {
						return new AstNumber(($tokenValue == "-") ? 0 - $resultToken->getValue() : $resultToken->getValue());
					}
					
					throw new ParserException("Unexpected token '{$tokenValue}' on line {$this->lexer->getLineNumber()}");
				
				case Token::Number :
					$this->lexer->match($tokenType);
					return new AstNumber($tokenValue);
				
				case Token::String :
					$this->lexer->match($tokenType);
					return new AstString($tokenValue, $tokenExtraData['char'] ?? '"');
				
				case Token::False :
					$this->lexer->match($tokenType);
					return new AstBool(false);
				
				case Token::True :
					$this->lexer->match($tokenType);
					return new AstBool(true);
				
				case Token::Null :
					$this->lexer->match($tokenType);
					return new AstNull();
				
				case Token::Parameter :
					$this->lexer->match($tokenType);
					return new AstParameter($tokenValue);
					
				case Token::Identifier :
					$this->lexer->match($tokenType);
					
					// Lees ook namespaces in
					while ($this->lexer->optionalMatch(Token::Backslash)) {
						$identifierTokenAdd = $this->lexer->match(Token::Identifier);
						$tokenValue .= "\\" . $identifierTokenAdd->getValue();
					}
					
					// Kijk of het een commando is. Zo ja, parse dan het commando.
					if ($this->lexer->lookahead() == Token::ParenthesesOpen) {
						$queryFunctionRule = new QueryFunction($this);
						return $queryFunctionRule->parse($tokenValue);
					}
					
					// Retourneer de identifier of entity
					if (!$this->lexer->optionalMatch(Token::Dot)) {
						if ($entity !== null) {
							return new AstIdentifier(clone $entity, $tokenValue);
						} else {
							return new AstEntity($tokenValue);
						}
					}
					
					// Als de identifier één of meerdere punten bevat, dan is dit Entity.Property
					// Dit kan dan een directe property zijn, of een relatie, of een property door een
					// relatie heen. Met welke variant we hier te maken hebben, wordt nader bepaald in
					// het validatie/visitor proces.
					$valueIdentifier = $this->lexer->match(Token::Identifier)->getValue();
					
					if ($this->lexer->optionalMatch(Token::ParenthesesOpen)) {
						$this->lexer->match(Token::ParenthesesClose);
						return new AstMethodCall(new AstEntity($tokenValue), $valueIdentifier);
					} else {
						return new AstIdentifier(new AstEntity($tokenValue), $valueIdentifier);
					}
				
				case Token::RegExp :
					$this->lexer->match($tokenType);
					return new AstRegExp($tokenValue);
				
				default :
					throw new ParserException("Unexpected token '{$tokenValue}' on line {$this->lexer->getLineNumber()}");
			}
		}
		
		/**
		 * Parse an expression, which can either be a simple term, a ternary
		 * conditional expression, or a relational expression.
		 * @param AstEntity|null $entity
		 * @return AstInterface The resulting AST node representing the parsed expression.
		 * @throws LexerException|ParserException
		 */
		public function parse(?AstEntity $entity = null): AstInterface {
			// Handle NOT expression
			if ($this->lexer->lookahead() == Token::Not) {
				$this->lexer->match(Token::Not);
				return new AstNot($this->parse($entity));
			}
			
			// Parse the first term in the expression
			$term = $this->parseTerm($entity);
			
			// Check for ternary operator
			switch($this->lexer->lookahead()) {
				case Token::Equals:
				case Token::Unequal:
				case Token::LargerThan:
				case Token::LargerThanOrEqualTo:
				case Token::SmallerThan:
				case Token::SmallerThanOrEqualTo:
					return $this->parseRelationalOperator($this->lexer->lookahead(), $entity, $term);
					
				default :
					return $term;
			}
		}
		
	}