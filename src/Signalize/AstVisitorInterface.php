<?php
	
	namespace Services\Signalize;
	
	interface AstVisitorInterface {
		public function visitNode(AstInterface $node);
	}