<?php
	
	namespace Services\Signalize\Visitors;
	
	use Services\Signalize\AstFinderException;
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	class VisitorAstFinder implements AstVisitorInterface {
		private string $ast;
		
		/**
		 * Constructor for VisitorVariableExists
		 */
		public function __construct(string $ast) {
			$this->ast = $ast;
		}
		
		/**
		 * Throws the desired node
		 * @param AstInterface $node
		 * @throws AstFinderException
		 */
		public function visitNode(AstInterface $node): void {
			if (get_class($node) === $this->ast) {
				throw new AstFinderException("foundNode", 0, null, $node);
			}
		}
	}