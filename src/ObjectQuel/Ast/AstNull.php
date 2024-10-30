<?php
	
	namespace Services\ObjectQuel\Ast;
	
	class AstNull extends Ast {
		
		public function __construct() {
		}
		
		public function getValue() {
			return null;
		}
	}