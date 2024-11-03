<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Represents an identifier node in the AST.
	 */
	class AstMethodCall extends Ast {
		
		protected AstEntity|AstMethodCall $entityOrParentIdentifier;
		
		/**
		 * @var string The actual identifier value.
		 */
		protected string $methodName;
		
		/**
		 * Constructor.
		 * @param AstEntity|AstMethodCall|AstIdentifier $entityOrParentIdentifier
		 * @param string $methodName The identifier value
		 */
		public function __construct(AstEntity|AstMethodCall|AstIdentifier $entityOrParentIdentifier, string $methodName) {
			$this->entityOrParentIdentifier = $entityOrParentIdentifier;
			$this->methodName = $methodName;
		}
		
		/**
		 * Accepteer een bezoeker om de AST te verwerken.
		 * @param AstVisitorInterface $visitor Bezoeker object voor AST-manipulatie.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			$this->entityOrParentIdentifier->accept($visitor); // Accepteer de parent identifier
			parent::accept($visitor); // Accepteer daarna de bezoeker op de ouderklasse
		}
		
		/**
		 * Returns the entity
		 * @return AstEntity|AstIdentifier The entity node
		 */
		public function getEntityOrParentIdentifier(): AstEntity|AstMethodCall {
			return $this->entityOrParentIdentifier;
		}
		
		/**
		 * Extracts and returns the entity name from the identifier.
		 * @return string The entity name or the full identifier if no property specified.
		 */
		public function getEntityName(): string {
			return $this->entityOrParentIdentifier->getName();
		}
		
		/**
		 * Extracts and returns the property name from the identifier.
		 * @return string The property name or an empty string if not specified.
		 */
		public function getName(): string {
			return $this->methodName;
		}
	}