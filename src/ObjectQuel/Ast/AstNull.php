<?php
	
	namespace Services\ObjectQuel\Ast;
	
	class AstNull extends Ast {
		
		public function getValue(): null {
			return null;
		}
	}