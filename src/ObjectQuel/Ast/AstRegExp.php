<?php
	
	namespace Services\ObjectQuel\Ast;
	
	/**
	 * Class AstString
	 * Represents a regular expression constant in the Abstract Syntax Tree (AST).
	 */
	class AstRegExp extends Ast {
		
		/**
		 * The string value represented by this AST node.
		 * @var string
		 */
		protected string $string;
		
		/**
		 * AstString constructor.
		 * Initializes the node with a string value.
		 * @param string $string The string value to store.
		 */
		public function __construct(string $string) {
			$this->string = $string;
		}
		
		/**
		 * Retrieves the string value stored in this AST node.
		 * @return string The stored string value.
		 */
		public function getValue(): string {
			return $this->string;
		}
	}