<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstBool
	 * Represents a boolean constant in the Abstract Syntax Tree (AST).
	 */
	class AstBindVisible extends Ast {
		
		protected AstInterface $expression;
		
		/**
		 * AstBindVisible constructor.
		 * Initializes the node with a boolean value.
		 * @param AstInterface $expression
		 */
		public function __construct(AstInterface $expression) {
			$this->expression = $expression;
		}
		
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->expression->accept($visitor);
		}
		
		/**
		 * Retrieves the expression used for the visible binding
		 * @return AstInterface The stored boolean value.
		 */
		public function getExpression(): AstInterface {
			return $this->expression;
		}

		/**
		 * Sets a new expression
		 * @param AstInterface $expression
		 * @return void
		 */
		public function setExpression(AstInterface $expression): void {
			$this->expression = $expression;
		}
	}