<?php
	
	namespace Services\ObjectQuel;
	
	interface AstVisitorInterface {
		public function visitNode(AstInterface $node);
	}