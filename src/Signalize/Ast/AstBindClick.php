<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstBindText
	 */
	class AstBindClick extends Ast {
		
		protected AstInterface $tokenStream;
		
		/**
		 * AstBindText constructor.
		 * @param AstInterface $expression
		 */
		public function __construct(AstInterface $expression) {
			$this->tokenStream = $expression;
		}
		
		/**
		 * Visitor stuff
		 * @param AstVisitorInterface $visitor
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->tokenStream->accept($visitor);
		}
		
		/**
		 * Retrieves the expression used for the visible binding
		 * @return AstInterface The stored boolean value.
		 */
		public function getTokenStream(): AstInterface {
			return $this->tokenStream;
		}
	}