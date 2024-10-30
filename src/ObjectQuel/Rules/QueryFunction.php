<?php
	
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstConcat;
	use Services\ObjectQuel\Ast\AstCount;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
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
		 * Expression constructor
		 * @param Lexer $lexer
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
				throw new ParserException("Count operator takes an entity or entity property as parameter.");
			}
			
			return new AstCount($countIdentifier);
		}

		/**
		 * Count operator
		 * @param string $operator
		 * @return AstUCount
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseUCount(): AstUCount {
			$this->lexer->match(Token::ParenthesesOpen);
			$countIdentifier = $this->expressionRule->parseConstantOrIdentifier();
			$this->lexer->match(Token::ParenthesesClose);
			
			if ((!$countIdentifier instanceof AstEntity) && (!$countIdentifier instanceof AstIdentifier)) {
				throw new ParserException("Count operator takes an entity or entity property as parameter.");
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
				$parameters[] = $this->parse($entity);
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			// Match the closing parenthesis.
			$this->lexer->match(Token::ParenthesesClose);
			
			// Create and return a new AstConcat object with the parsed parameters.
			return new AstConcat($parameters);
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
				default => throw new ParserException("Command {$command} is not valid."),
			};
		}
	}