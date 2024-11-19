<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class AstIsNumeric
	 */
	class AstIsNumeric extends Ast {
		
		/**
		 * The value or string to check
		 * @var AstIdentifier|AstString
		 */
		protected AstIdentifier|AstString $identifierOrString;
		
		/**
		 * AstIsNumeric constructor.
		 * @param AstIdentifier|AstString $identifierOrString
		 */
		public function __construct(AstIdentifier|AstString $identifierOrString) {
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
		 * @return AstIdentifier|AstString The stored numerical value.
		 */
		public function getValue(): AstIdentifier|AstString {
			return $this->identifierOrString;
		}
	}