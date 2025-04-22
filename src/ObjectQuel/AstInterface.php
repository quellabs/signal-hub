<?php
	
	namespace Quellabs\ObjectQuel\ObjectQuel;
	
	interface AstInterface {
		
		/**
		 * Valideer de AST
		 * @param AstVisitorInterface $visitor
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void;
		
		/**
		 * Returns the return type of the AST
		 * @return string|null
		 */
		public function getReturnType(): ?string;
	}
	