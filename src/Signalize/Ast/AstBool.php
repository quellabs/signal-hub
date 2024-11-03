<?php
	
	namespace Services\Signalize\Ast;
	
	/**
	 * Class AstBool
	 * Represents a boolean constant in the Abstract Syntax Tree (AST).
	 */
	class AstBool extends Ast {
		
		/**
		 * The boolean value represented by this AST node.
		 * @var bool
		 */
		protected bool $value;
		
		/**
		 * AstBool constructor.
		 * Initializes the node with a boolean value.
		 * @param bool $value
		 */
		public function __construct(bool $value) {
			$this->value = $value;
		}
		
		/**
		 * Retrieves the boolean value stored in this AST node.
		 * @return bool The stored boolean value.
		 */
		public function getValue(): bool {
			return $this->value;
		}
	}