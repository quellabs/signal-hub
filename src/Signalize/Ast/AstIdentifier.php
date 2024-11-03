<?php
	
	namespace Services\Signalize\Ast;
	
	/**
	 * Represents an identifier node in the AST.
	 */
	class AstIdentifier extends Ast {
		
		/**
		 * @var string The actual identifier value.
		 */
		protected string $identifier;
		
		/**
		 * AstIdentifier constructor.
		 * @param string $identifier The identifier value
		 */
		public function __construct(string $identifier) {
			$this->identifier = $identifier;
		}
		
		/**
		 * Extracts and returns the property name from the identifier.
		 * @return string The property name
		 */
		public function getName(): string {
			return $this->identifier;
		}
	}