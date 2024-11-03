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
	
	class ComparisonExpression {
		
		protected Lexer $lexer;
		
		/**
		 * GeneralExpression constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a relational operator
		 * @param $lookahead
		 * @param AstInterface $term
		 * @return AstExpression
		 * @throws LexerException|ParserException
		 */
		protected function parseRelationalOperator($lookahead, AstInterface $term): AstExpression {
			// Consume the operator token and store its value
			$operatorToken = $this->lexer->match($lookahead);
			
			// Parse right side of expression
			$rightSide = $this->parse();
			
			// Decide which operator we use
			if ($operatorToken->getValue() != "<>") {
				$operatorType = $operatorToken->getValue();
			} else {
				$operatorType = '!=';
			}
			
			// Create and return a new AstExpression node
			return new AstExpression($term, $rightSide, $operatorType);
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
			// Load parser for arithmetic expressions
			$arithmeticExpression = new ArithmeticExpression($this->lexer);

			// Parse the first term in the expression
			$expression = $arithmeticExpression->parse();
			
			// Check for ternary operator
			switch($this->lexer->lookahead()) {
				case Token::Equal:
				case Token::Unequal:
				case Token::LargerThan:
				case Token::LargerThanOrEqualTo:
				case Token::SmallerThan:
				case Token::SmallerThanOrEqualTo:
					return $this->parseRelationalOperator($this->lexer->lookahead(), $expression);
					
				default :
					return $expression;
			}
		}
	}