<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class Ast
	 * Abstract Syntax Tree (AST) Node base class.
	 * Serves as the base class for all specific AST nodes.
	 */
	abstract class Ast implements AstInterface {
		
		/**
		 * Accepts a visitor to perform operations on this node.
		 * The method delegates the call to the visitor, allowing it to
		 * perform some action on the node.
		 * @param AstVisitorInterface $visitor The visitor performing operations on the AST.
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void {
			$visitor->visitNode($this);
		}
		
		/**
		 * Returns the return type of the AST
		 * @return string|null
		 */
		public function getReturnType(): ?string {
			return null;
		}
	}