<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Represents an identifier node in the AST.
	 */
	class AstIdentifier extends Ast {
		
		protected AstEntity|AstIdentifier $parentIdentifier;
		
		/**
		 * @var string The actual identifier value.
		 */
		protected string $identifier;
		
		/**
		 * Constructor.
		 * @param AstEntity|AstIdentifier $entityOrParentIdentifier
		 * @param string $identifier The identifier value
		 */
		public function __construct(AstEntity|AstIdentifier $entityOrParentIdentifier, string $identifier) {
			$this->parentIdentifier = $entityOrParentIdentifier;
			$this->identifier = $identifier;
		}
		
		/**
		 * Accepteer een bezoeker om de AST te verwerken.
		 * @param AstVisitorInterface $visitor Bezoeker object voor AST-manipulatie.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor); // Accepteer eerst de bezoeker op ouderklasse
			$this->parentIdentifier->accept($visitor); // Accepteer hem daarna op de entity AST
		}
		
		/**
		 * Returns the entity
		 * @return AstEntity|AstIdentifier The entity node
		 */
		public function getParentIdentifier(): AstEntity|AstIdentifier {
			return $this->parentIdentifier;
		}
		
		/**
		 * Extracts and returns the entity name from the identifier.
		 * @return string The entity name or the full identifier if no property specified.
		 */
		public function getEntityName(): string {
			if (!$this->parentIdentifier instanceof AstEntity) {
				return "";
			}
			
			return $this->parentIdentifier->getName();
		}
		
		/**
		 * Extracts and returns the property name from the identifier.
		 * @return string The property name or an empty string if not specified.
		 */
		public function getName(): string {
			return $this->identifier;
		}
	}