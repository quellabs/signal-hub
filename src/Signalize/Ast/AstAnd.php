<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstAnd
	 *
	 * Represents an AND logical expression in the AST (Abstract Syntax Tree).
	 */
	class AstAnd extends Ast {
		
		/**
		 * @var AstInterface The left-hand operand of the AND expression.
		 */
		protected AstInterface $left;
		
		/**
		 * @var AstInterface The right-hand operand of the AND expression.
		 */
		protected AstInterface $right;
		
		/**
		 * AstAnd constructor.
		 * @param AstInterface $left  The left-hand operand.
		 * @param AstInterface $right The right-hand operand.
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
