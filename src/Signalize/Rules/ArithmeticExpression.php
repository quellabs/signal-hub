<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstBindVariable;
	use Services\Signalize\Ast\AstBool;
	use Services\Signalize\Ast\AstExpression;
	use Services\Signalize\Ast\AstFactor;
	use Services\Signalize\Ast\AstIdentifier;
	use Services\Signalize\Ast\AstNegate;
	use Services\Signalize\Ast\AstNull;
	use Services\Signalize\Ast\AstNumber;
	use Services\Signalize\Ast\AstString;
	use Services\Signalize\Ast\AstTerm;
	use Services\Signalize\AstInterface;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class ArithmeticExpression {
		
		protected Lexer $lexer;
		
		/**
		 * GeneralExpression constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a factor in an arithmetic expression. A factor can either be a
		 * parenthesized expression, a constant, or a variable. Additionally, it
		 * can have multiplication (*) or division (/) operations.
		 * @return AstInterface The resulting AST node representing the parsed factor.
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseFactor(): AstInterface {
			// Check if the next token is an opening parenthesis
			if ($this->lexer->lookahead() == Token::ParenthesesOpen) {
				// If so, consume the opening parenthesis
				$this->lexer->match(Token::ParenthesesOpen);
				
				// Parse the expression inside the parentheses
				$logicalExpression = new LogicalExpression($this->lexer);
				$expression = $logicalExpression->parse();
				
				// Consume the closing parenthesis
				$this->lexer->match(Token::ParenthesesClose);
				
				// Return the parsed expression
				return $expression;
			}
			
			// Parse a constant or an identifier (like a variable)
			$constantOrVariable = $this->parseConstantOrIdentifier();
			
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
		 * Parses a constant
		 * @return AstInterface
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseConstantOrIdentifier(): AstInterface {
			$token = $this->lexer->peek();
			$tokenType = $token->getType();
			$tokenValue = $token->getValue();
			
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
					return new AstString($tokenValue);
				
				case Token::False :
					$this->lexer->match($tokenType);
					return new AstBool(false);
				
				case Token::True :
					$this->lexer->match($tokenType);
					return new AstBool(true);
				
				case Token::Null :
					$this->lexer->match($tokenType);
					return new AstNull();
				
				case Token::BindVariable :
					$this->lexer->match($tokenType);
					
					if ($this->lexer->optionalMatch(Token::Dot)) {
						do {
							if ($tokenValue !== '') {
								$tokenValue .= '.';
							}
							
							$tokenValue .= $this->lexer->match(Token::Identifier)->getValue();
						} while ($this->lexer->optionalMatch(Token::Dot));
					}
					
					// Anders is het een variabele
					return new AstBindVariable($tokenValue);
				
				case Token::Identifier :
					$this->lexer->match($tokenType);
					
					// Als de identifier één of meerdere punten bevat, dan is dit Entity.Property
					// Dit kan dan een directe property zijn, of een relatie, of een property door een
					// relatie heen. Met welke variant we hier te maken hebben, wordt nader bepaald in
					// het validatie/visitor proces.
					if ($this->lexer->optionalMatch(Token::Dot)) {
						do {
							if ($tokenValue !== '') {
								$tokenValue .= '.';
							}
							
							$tokenValue .= $this->lexer->match(Token::Identifier)->getValue();
						} while ($this->lexer->optionalMatch(Token::Dot));
						
						// Als de identifier wordt opgevolgd door parentheses, dan is het een function call
						if ($this->lexer->optionalMatch(Token::ParenthesesOpen)) {
							$functionCall = new FunctionCall($this->lexer);
							return $functionCall->parse($tokenValue);
						}

						// Anders is het een variabele
						return new AstIdentifier($tokenValue);
					}
					
					// Als de identifier wordt opgevolgd door parentheses, dan is het een function call
					if ($this->lexer->lookahead() == Token::ParenthesesOpen) {
						$functionCall = new FunctionCall($this->lexer);
						return $functionCall->parse($tokenValue);
					}
					
					// Anders is het een variabele (Identifier)
					return new AstIdentifier($tokenValue);
				
				default :
					throw new ParserException("Unexpected token '{$tokenValue}' on line {$this->lexer->getLineNumber()}");
			}
		}
		
		/**
		 * Returns the lexer instance
		 * @return Lexer
		 */
		public function getLexer(): Lexer {
			return $this->lexer;
		}
		
		/**
		 * Parse an expression, which can either be a simple term, a ternary
		 * conditional expression, or a relational expression.
		 * @return AstInterface The resulting AST node representing the parsed expression.
		 * @throws LexerException|ParserException
		 */
		public function parse(): AstInterface {
			// Parse the first factor in the term
			$factor = $this->parseFactor();
			
			// Check if the next token is either '+' or '-'
			switch($this->lexer->lookahead()) {
				case Token::Plus :
					$this->lexer->match($this->lexer->lookahead());
					return new AstTerm($factor, $this->parse(), "+");
				
				case Token::Minus :
					$this->lexer->match($this->lexer->lookahead());
					return new AstTerm($factor, $this->parse(), "-");
				
				default:
					return $factor;
			}
		}
	}