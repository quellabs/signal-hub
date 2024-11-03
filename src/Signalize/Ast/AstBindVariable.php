<?php
	
	namespace Services\Signalize\Ast;
	
	/**
	 * Represents an identifier node in the AST.
	 */
	class AstBindVariable extends Ast {
		
		/**
		 * @var string The actual identifier value.
		 */
		protected string $name;
		
		/**
		 * AstBindVariable constructor.
		 * @param string $name
		 */
		public function __construct(string $name) {
			$this->name = $name;
		}
		
		/**
		 * Returns the bind variable name
		 * @return string The property name
		 */
		public function getName(): string {
			return $this->name;
		}
	}