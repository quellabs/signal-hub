<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstBool
	 * Represents a boolean constant in the Abstract Syntax Tree (AST).
	 */
	class AstBindOptions extends Ast {
		
		protected AstBindVariable $bindVariable;
		
		/**
		 * AstBindOptions constructor.
		 * @param AstBindVariable $bindVariable
		 */
		public function __construct(AstBindVariable $bindVariable) {
			$this->bindVariable = $bindVariable;
		}
		
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->bindVariable->accept($visitor);
		}
		
		/**
		 * Retrieves the bind variable
		 * @return AstBindVariable The stored boolean value.
		 */
		public function getBindVariable(): AstBindVariable {
			return $this->bindVariable;
		}
	}