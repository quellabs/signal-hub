<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
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
		 */
		public function accept(AstVisitorInterface $visitor) {
			$visitor->visitNode($this);
		}
	}