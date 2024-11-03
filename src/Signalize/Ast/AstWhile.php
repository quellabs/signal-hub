<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	class AstWhile extends Ast {
		private AstInterface $expression;
		private AstTokenStream $body;
		
		/**
		 * AstWhile constructor
		 * @param AstInterface $expression
		 * @param AstTokenStream $body
		 */
		public function __construct(AstInterface $expression, AstTokenStream $body) {
			$this->expression = $expression;
			$this->body = $body;
		}
		
		/**
		 * Accept a visitor to perform operations on this node.
		 * @param AstVisitorInterface $visitor The visitor to accept.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->expression->accept($visitor);
			$this->body->accept($visitor);
		}
		
		public function getExpression(): AstInterface {
			return $this->expression;
		}

		public function getBody(): AstTokenStream {
			return $this->body;
		}
	}