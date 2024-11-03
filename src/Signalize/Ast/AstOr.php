<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	class AstOr extends Ast {
		
		protected AstInterface $left;
		protected AstInterface $right;
		
		/**
		 * AstOr constructor
		 * @param AstInterface $left
		 * @param AstInterface $right
		 */
		public function __construct(AstInterface $left, AstInterface $right) {
			$this->left = $left;
			$this->right = $right;
		}
		
		/**
		 * Accept a visitor to perform operations on this node.
		 * @param AstVisitorInterface $visitor The visitor to accept.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->left->accept($visitor);
			$this->right->accept($visitor);
		}
		
		/**
		 * Get the left-hand operand of the AND expression.
		 * @return AstInterface The left operand.
		 */
		public function getLeft(): AstInterface {
			return $this->left;
		}
		
		/**
		 * Updates the left side with a new AST
		 * @param AstInterface $ast
		 * @return void
		 */
		public function setLeft(AstInterface $ast): void {
			$this->left = $ast;
		}
		
		/**
		 * Get the right-hand operand of the AND expression.
		 * @return AstInterface The right operand.
		 */
		public function getRight(): AstInterface {
			return $this->right;
		}
		
		/**
		 * Updates the right side with a new AST
		 * @param AstInterface $ast
		 * @return void
		 */
		public function setRight(AstInterface $ast): void {
			$this->right = $ast;
		}
	}