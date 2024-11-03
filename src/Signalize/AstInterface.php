<?php
	
	namespace Services\Signalize;
	
	interface AstInterface {
		
		/**
		 * Valideer de AST
		 * @param AstVisitorInterface $visitor
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void;
	}