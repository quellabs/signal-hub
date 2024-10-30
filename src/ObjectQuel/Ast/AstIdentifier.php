<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Represents an identifier node in the AST.
	 */
	class AstIdentifier extends Ast {
		
		/**
		 * @var $entity AstEntity
		 */
		protected $entity;
		
		/**
		 * @var string The actual identifier value.
		 */
		protected string $identifier;
		
		/**
		 * Constructor.
		 * @param AstEntity $entity The entity the identifier value can be found in
		 * @param string $identifier The identifier value
		 */
		public function __construct(AstEntity $entity, string $identifier) {
			$this->entity = $entity;
			$this->identifier = $identifier;
		}
		
		/**
		 * Accepteer een bezoeker om de AST te verwerken.
		 * @param AstVisitorInterface $visitor Bezoeker object voor AST-manipulatie.
		 */
		public function accept(AstVisitorInterface $visitor) {
			parent::accept($visitor); // Accepteer eerst de bezoeker op ouderklasse
			$this->entity->accept($visitor); // Accepteer hem daarna op de entity AST
		}
		
		/**
		 * Returns the entity
		 * @return AstEntity The entity node
		 */
		public function getEntity(): AstEntity {
			return $this->entity;
		}
		
		/**
		 * Extracts and returns the entity name from the identifier.
		 * @return string The entity name or the full identifier if no property specified.
		 */
		public function getEntityName(): string {
			return $this->entity->getName();
		}
		
		/**
		 * Updates the entity name
		 * @return void
		 */
		public function setEntityName(string $entityName): void {
			$this->entity->setName($entityName);
		}
		
		/**
		 * Extracts and returns the property name from the identifier.
		 * @return string The property name or an empty string if not specified.
		 */
		public function getPropertyName(): string {
			return $this->identifier;
		}
	}