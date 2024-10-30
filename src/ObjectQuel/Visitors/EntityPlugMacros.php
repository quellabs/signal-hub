<?php
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Neemt macro's die zijn gedefinieerd in de column sectie en vervangt ze
	 */
	class EntityPlugMacros implements AstVisitorInterface {
		
		private $macros;
		
		/**
		 * EntityPlugMacros constructor
		 */
		public function __construct(array $macros) {
			$this->macros = $macros;
		}
		
		/**
		 * Functie om een node in de AST (Abstract Syntax Tree) te bezoeken.
		 * @param AstInterface $node
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			// Controleert of de gegeven node een van de gespecificeerde types is.
			if ($node instanceof AstFactor || $node instanceof AstTerm || $node instanceof AstExpression) {
				// Controleert of de linkerzijde een AstEntity is en of er een macro voor gedefinieerd is.
				$left = $node->getLeft();
				
				if ($left instanceof AstEntity && isset($this->macros[$left->getName()])) {
					// Vervangt de linkerzijde van de node met de corresponderende macro.
					$node->setLeft($this->macros[$left->getName()]);
				}
				
				// Doet dezelfde controle en vervanging voor de rechterzijde van de node.
				$right = $node->getRight();

				if ($right instanceof AstEntity && isset($this->macros[$right->getName()])) {
					// Vervangt de rechterzijde van de node met de corresponderende macro.
					$node->setRight($this->macros[$right->getName()]);
				}
			}
		}
	}