<?php
	
	namespace Services\Signalize\Ast;
	
	/**
	 * Class AstNumber
	 * Represents a numerical constant in the Abstract Syntax Tree (AST).
	 */
	class AstString extends Ast {
		
		/**
		 * The numerical value represented by this AST node.
		 */
		protected string $value;
		
		/**
		 * AstNumber constructor.
		 * Initializes the node with a numerical value.
		 * @param string $value
		 */
		public function __construct(string $value) {
			$this->value = $value;
		}
		
		/**
		 * Retrieves the numerical value stored in this AST node.
		 * @return string The stored numerical value.
		 */
		public function getValue(): string {
			return $this->value;
		}
	}