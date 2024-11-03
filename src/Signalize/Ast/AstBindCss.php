<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstBool
	 * Represents a boolean constant in the Abstract Syntax Tree (AST).
	 */
	class AstBindCss extends Ast {
		
		protected array $values;
		
		/**
		 * AstBindCss constructor.
		 * Initializes the node with a boolean value.
		 * @param array $values
		 */
		public function __construct(array $values) {
			$this->values = $values;
		}
		
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			
			foreach($this->values as $value) {
				$value["ast"]->accept($visitor);
			}
		}
		
		/**
		 * Retrieves the expressions used for the css binding
		 * @return array The stored boolean value.
		 */
		public function getValues(): array {
			return $this->values;
		}
	}