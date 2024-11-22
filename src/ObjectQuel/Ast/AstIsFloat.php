<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class AstIsNumeric
	 */
	class AstIsFloat extends Ast {
		
		/**
		 * The value or string to check
		 * @var AstIdentifier|AstString|AstNumber
		 */
		protected AstIdentifier|AstString|AstNumber $identifierOrString;
		
		/**
		 * AstIsNumeric constructor.
		 * @param AstIdentifier|AstString|AstNumber $identifierOrString
		 */
		public function __construct(AstIdentifier|AstString|AstNumber $identifierOrString) {
			$this->identifierOrString = $identifierOrString;
		}
		
		/**
		 * Accept the visitor
		 * @param AstVisitorInterface $visitor
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->identifierOrString->accept($visitor);
		}
		
		/**
		 * Retrieves the numerical value stored in this AST node.
		 * @return AstIdentifier|AstString|AstNumber The stored numerical value.
		 */
		public function getValue(): AstIdentifier|AstString|AstNumber {
			return $this->identifierOrString;
		}
	}