<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstTokenStream
	 */
	class AstTokenStream extends Ast {
		
		protected array $tokens;
		protected array $declaredVariables;
		
		/**
		 * AstTokenStream constructor.
		 * @param array $tokens
		 */
		public function __construct(array $tokens, array $declaredVariables) {
			$this->tokens = $tokens;
			$this->declaredVariables = $declaredVariables;
		}
		
		/**
		 * Accept a visitor to perform operations on this node.
		 * @param AstVisitorInterface $visitor The visitor to accept.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			
			foreach($this->tokens as $token) {
				$token->accept($visitor);
			}
		}
		
		/**
		 * Get the tokenstream
		 * @return array The left operand.
		 */
		public function getTokens(): array {
			return $this->tokens;
		}
		
		/**
		 * Add a token to the tokenstream
		 * @return void
		 */
		public function addToken(AstInterface $token): void {
			$this->tokens[] = $token;
		}
		
		/**
		 * Gets a list of declared variables within the tokenstream
		 * @return array The left operand.
		 */
		public function getDeclaredVariables(): array {
			return $this->declaredVariables;
		}
	}
