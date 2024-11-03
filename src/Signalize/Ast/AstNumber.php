<?php
	
	namespace Services\Signalize\Ast;
	
	/**
	 * Class AstNumber
	 * Represents a numerical constant in the Abstract Syntax Tree (AST).
	 */
	class AstNumber extends Ast {
		
		/**
		 * The numerical value represented by this AST node.
		 */
		protected int|float $value;
		
		/**
		 * AstNumber constructor.
		 * Initializes the node with a numerical value.
		 * @param int|float $number The numerical value to store.
		 */
		public function __construct(int|float $value) {
			$this->value = $value;
		}
		
		/**
		 * Retrieves the numerical value stored in this AST node.
		 * @return int|float The stored numerical value.
		 */
		public function getValue(): int|float {
			return $this->value;
		}
	}