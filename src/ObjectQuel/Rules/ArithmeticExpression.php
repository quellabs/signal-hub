<?php
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstBool;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstNull;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstParameter;
	use Services\ObjectQuel\Ast\AstRegExp;
	use Services\ObjectQuel\Ast\AstString;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\Ast\AstUnaryOperation;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\Lexer;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
	
	class ArithmeticExpression {
		
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
		 * @return AstInterface The resulting AST node representing the parsed factor.
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseFactor(): AstInterface {
			// Parse a constant or an identifier (like a variable)
			$unaryExpression = $this->parseUnaryExpression();
			
			// Check if the next token is either '*' or '/'
			switch($this->lexer->lookahead()) {
				case Token::Star :
					$this->lexer->match($this->lexer->lookahead());
					return new AstFactor($unaryExpression, $this->parseFactor(), "*");
					
				case Token::Slash :
					$this->lexer->match($this->lexer->lookahead());
					return new AstFactor($unaryExpression, $this->parseFactor(), "/");
					
				default :
					return $unaryExpression;
					
			}
		}
		
		/**
		 * Parse a term in an arithmetic expression. A term can either be a single
		 * factor or an addition (+) or subtraction (-) operation between factors.
		 * @return AstInterface The resulting AST node representing the parsed term.
		 * @throws LexerException|ParserException
		 */
		protected function parseTerm(): AstInterface {
			// Parse the first factor in the term
			$factor = $this->parseFactor();
			
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
		 * Returns the lexer instance
		 * @return Lexer
		 */
		public function getLexer(): Lexer {
			return $this->lexer;
		}
		
		/**
		 * Parse a chain of properties with optional namespace in the first element
		 * @return AstIdentifier The root identifier with linked chain
		 */
		public function parsePropertyChain(): AstIdentifier {
			// Parse the first identifier in the chain, which may include namespace
			$token = $this->lexer->match(Token::Identifier);
			$tokenValue = $token->getValue();
			
			// Handle any namespace segments in the first identifier
			while ($this->lexer->optionalMatch(Token::Backslash)) {
				$namespaceToken = $this->lexer->match(Token::Identifier);
				$tokenValue .= "\\" . $namespaceToken->getValue();
			}
			
			// Create the root identifier with the full (potentially namespaced) value
			$rootIdentifier = new AstIdentifier($tokenValue);
			$currentIdentifier = $rootIdentifier;
			
			// Continue parsing the property chain with dot notation
			while ($this->lexer->optionalMatch(Token::Dot)) {
				// Parse the next property name
				$token = $this->lexer->match(Token::Identifier);
				$nextIdentifier = new AstIdentifier($token->getValue());
				
				// Link it to the current identifier
				$currentIdentifier->setNext($nextIdentifier);
				
				// And also link back
				$nextIdentifier->setParent($currentIdentifier);
				
				// Move to the new current identifier for potential next iteration
				$currentIdentifier = $nextIdentifier;
			}
			
			return $rootIdentifier;
		}
		
		/**
		 * Parses a constant
		 * @return AstInterface
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parsePrimaryExpression(): AstInterface {
			$token = $this->lexer->peek();
			$tokenType = $token->getType();
			$tokenValue = $token->getValue();
			$tokenExtraData = $token->getExtraData();
			
			switch ($tokenType) {
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
					$node = $this->parsePropertyChain();
					
					// Kijk of het een commando is. Zo ja, parse dan het commando.
					if ($this->lexer->lookahead() === Token::ParenthesesOpen) {
						$queryFunctionRule = new QueryFunction($this);
						return $queryFunctionRule->parse($node->getCompleteName());
					}
					
					// Anders, retourneer de property keten
					return $node;
				
				case Token::RegExp :
					$this->lexer->match($tokenType);
					return new AstRegExp($tokenValue);
				
				case Token::ParenthesesOpen:
					// Handle parenthesized expressions
					$this->lexer->match(Token::ParenthesesOpen);
					$logicalExpression = new LogicalExpression($this->lexer);
					$expression = $logicalExpression->parse();
					$this->lexer->match(Token::ParenthesesClose);
					return $expression;
					
				default :
					throw new ParserException("Unexpected token '{$tokenValue}' on line {$this->lexer->getLineNumber()}");
			}
		}
		
		/**
		 * Parse unary expressions (-, +, *, &, etc.)
		 * @param bool $isJumpTarget Whether this is used as a jump target
		 * @return AstInterface
		 */
		protected function parseUnaryExpression(bool $isJumpTarget = false): AstInterface {
			$token = $this->lexer->peek();
			$tokenType = $token->getType();
			$tokenValue = $token->getValue();
			
			switch ($tokenType) {
				case Token::Plus:
				case Token::Minus:
					$this->lexer->match($tokenType);
					
					// Handle +/- followed by a number as a literal with sign
					if ($this->lexer->optionalMatch(Token::Number, $resultToken)) {
						$number = ($tokenValue == "-") ? 0 - $resultToken->getValue() : $resultToken->getValue();
						return new AstNumber($number);
					}
					
					// Otherwise, it's a unary operator applied to an expression
					$operand = $this->parseUnaryExpression();
					return new AstUnaryOperation($operand, $tokenValue);
					
				default:
					// If not a unary operator, parse a primary expression
					return $this->parsePrimaryExpression();
			}
		}
		
		/**
		 * Parse an expression, which can either be a simple term, a ternary
		 * conditional expression, or a relational expression.
		 * @return AstInterface The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(): AstInterface {
			return $this->parseTerm();
		}
		
	}