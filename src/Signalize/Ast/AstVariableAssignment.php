<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstVariableAssignment
	 */
	class AstVariableAssignment extends Ast {
		
		private string $name;
		private AstInterface $value;
		
		/**
		 * AstVariableAssignment constructor
		 * @param string $name
		 * @param AstInterface $value
		 */
		public function __construct(string $name, AstInterface $value) {
			$this->name = $name;
			$this->value = $value;
		}
		
		/**
		 * Accepts a visitor to perform operations on this node.
		 * The method delegates the call to the visitor, allowing it to
		 * perform some action on the node.
		 * @param AstVisitorInterface $visitor The visitor performing operations on the AST.
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void {
			$visitor->visitNode($this);
			$this->value->accept($visitor);
		}
		
		/**
		 * Returns the variable name
		 * @return string
		 */
		public function getName(): string {
			return $this->name;
		}

		/**
		 * Returns the variable value
		 * @return AstInterface
		 */
		public function getValue(): AstInterface {
			return $this->value;
		}
		
		/**
		 * Sets a new variable value
		 * @param AstInterface $ast
		 * @return void
		 */
		public function setValue(AstInterface $ast): void {
			$this->value = $ast;
		}
	}