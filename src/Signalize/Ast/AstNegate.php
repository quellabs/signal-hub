<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstNumber
	 * Represents a numerical constant in the Abstract Syntax Tree (AST).
	 */
	class AstNegate extends Ast {
		
		protected AstInterface $nodeToNegate;
		
		/**
		 * AstNegate constructor.
		 * @param AstInterface $nodeToNegate
		 */
		public function __construct(AstInterface $nodeToNegate) {
			$this->nodeToNegate = $nodeToNegate;
		}
		
		/**
		 * Accept a visitor to perform operations on this node.
		 * @param AstVisitorInterface $visitor The visitor to accept.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->nodeToNegate->accept($visitor);
		}
		
		/**
		 * Retrieves the numerical value stored in this AST node.
		 * @return AstInterface The stored numerical value.
		 */
		public function getNodeToNegate(): AstInterface {
			return $this->nodeToNegate;
		}
	}