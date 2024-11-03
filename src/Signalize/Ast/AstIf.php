<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	class AstIf extends Ast {
		private AstInterface $expression;
		private AstTokenStream $body;
		private ?AstTokenStream $else;
		
		/**
		 * AstIf constructor
		 * @param AstInterface $expression
		 * @param AstTokenStream $body
		 * @param AstTokenStream|null $else
		 */
		public function __construct(AstInterface $expression, AstTokenStream $body, ?AstTokenStream $else=null) {
			$this->expression = $expression;
			$this->body = $body;
			$this->else = $else;
		}
		
		/**
		 * Accept a visitor to perform operations on this node.
		 * @param AstVisitorInterface $visitor The visitor to accept.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->expression->accept($visitor);
			$this->body->accept($visitor);
			
			if ($this->else !== null) {
				$this->else->accept($visitor);
			}
		}
		
		public function getExpression(): AstInterface {
			return $this->expression;
		}

		public function getBody(): AstTokenStream {
			return $this->body;
		}

		public function getElse(): ?AstTokenStream {
			return $this->else;
		}
	}