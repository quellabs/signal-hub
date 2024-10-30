<?php
	
	namespace Services\ObjectQuel\Rules;
	
	use Services\ObjectQuel\Ast\AstAnd;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstOr;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\Lexer;
	use Services\ObjectQuel\LexerException;
	use Services\ObjectQuel\ParserException;
	use Services\ObjectQuel\Token;
	
	class LogicalExpression	{
		
		private Lexer $lexer;
		private FilterExpression $expressionRule;
		
		/**
		 * LogicalExpression constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
			$this->expressionRule = new FilterExpression($lexer);
		}
		
		/**
		 * Parse een 'atomaire' expressie, dit kan een eenvoudige expressie zijn of een volledige
		 * logische expressie tussen haakjes.
		 * @param AstEntity|null $entity
		 * @return AstInterface De AST-node voor de atomaire expressie.
		 * @throws LexerException|ParserException
		 */
		protected function parseAtomicExpression(?AstEntity $entity = null): AstInterface {
			// Controleer of de volgende token een open haakje is.
			if ($this->lexer->lookahead() == Token::ParenthesesOpen) {
				// Verwacht een open haakje en ga verder.
				$this->lexer->match(Token::ParenthesesOpen);
				
				// Parse de logische expressie die tussen de haakjes staat.
				$expression = $this->parse($entity);
				
				// Verwacht een sluitend haakje om de expressie af te sluiten.
				$this->lexer->match(Token::ParenthesesClose);
				
				// Retourneer de geparseerde logische expressie.
				return $expression;
			}
			
			// Als er geen open haakje is, parse dan een eenvoudige expressie.
			return $this->expressionRule->parse($entity);
		}
		
		/**
		 * Parse an AND expression. This function handles chains of AND operations.
		 * @return AstInterface The resulting AST node representing the parsed AND expression.
		 * @param AstEntity|null $entity
		 * @throws LexerException|ParserException
		 */
		protected function parseAndExpression(?AstEntity $entity = null): AstInterface {
			// Parse the left-hand side of the AND expression
			$left = $this->parseAtomicExpression($entity);
			
			// Keep parsing as long as we encounter 'AND' tokens
			while ($this->lexer->lookahead() == Token::And) {
				// Consume the 'AND' token
				$this->lexer->match($this->lexer->lookahead());
				
				// Parse the right-hand side of the AND expression and combine it
				// with the left-hand side to form a new AND expression
				$left = new AstAnd($left, $this->parseAtomicExpression($entity));
			}
			
			// Return the final AND expression
			return $left;
		}
		
		/**
		 * Parse a logical expression. This function handles OR operations,
		 * and delegates to `parseAndExpression` to handle AND expressions.
		 * @param AstEntity|null $entity
		 * @return AstInterface The resulting AST node representing the parsed logical expression.
		 * @throws LexerException|ParserException
		 */
		public function parse(?AstEntity $entity = null): AstInterface {
			// Parse the left-hand side of the OR expression; this could be an AND expression
			$left = $this->parseAndExpression($entity);
			
			// Continue parsing as long as we encounter 'OR' tokens
			while ($this->lexer->lookahead() == Token::Or) {
				// Consume the 'OR' token
				$this->lexer->match($this->lexer->lookahead());
				
				// Parse the right-hand side of the OR expression and combine it
				// with the left-hand side to form a new OR expression
				$left = new AstOr($left, $this->parseAndExpression($entity));
			}
			
			// Return the final OR expression
			return $left;
		}
	}