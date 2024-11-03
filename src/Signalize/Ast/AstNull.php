<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\Ast\Ast;
	
	class AstNull extends Ast {
		
		public function __construct() {
		}
		
		public function getValue(): null {
			return null;
		}
	}