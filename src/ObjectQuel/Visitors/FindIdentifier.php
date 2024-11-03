<?php
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\AstFinderException;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	class FindIdentifier implements AstVisitorInterface{
		private string $entityName;
		private string $property;
		private ?AstIdentifier $result;
		
		/**
		 * FindIdentifier constructor
		 * @param string $entityName
		 * @param string $property
		 */
		public function __construct(string $entityName, string $property) {
			$this->entityName = $entityName;
			$this->property = $property;
		}
		
		/**
		 * @throws AstFinderException
		 */
		public function visitNode(AstInterface $node): void {
			if (!$node instanceof AstIdentifier) {
				return;
			}
			
			if ($node->getEntityName() !== $this->entityName) {
				return;
			}

			if ($node->getName() !== $this->property) {
				return;
			}
			
			throw new AstFinderException("Found identifier", 0, null, $node);
		}
	}