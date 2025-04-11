<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\EntityManager\EntityStore;
	use Services\Kernel\ReflectionHandler;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstMethodCall;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Class EntityPropertyValidator
	 * Validates the existence of properties and methods within entities
	 */
	class EntityPropertyValidator implements AstVisitorInterface {
		
		private EntityStore $entityStore;
		private ReflectionHandler $reflectionHandler;
		
		/**
		 * EntityPropertyValidator constructor.
		 * @param EntityStore $entityStore
		 */
		public function __construct(EntityStore $entityStore) {
			$this->entityStore = $entityStore;
			$this->reflectionHandler = $this->entityStore->getReflectionHandler();
		}
		
		/**
		 * Validate the property of a given entity.
		 * This function checks if a given property name exists in the column map of a specified entity.
		 * @param string $entityName The name of the entity.
		 * @param string $propertyName The name of the property to validate.
		 * @throws QuelException Thrown when the property does not exist in the given entity.
		 */
		protected function validateProperty(string $entityName, string $propertyName): void {
			// Get the column map for this entity.
			$columnMap = $this->entityStore->getColumnMap($entityName);
			
			// Check if the property exists in the entity.
			if (!isset($columnMap[$propertyName])) {
				throw new QuelException("The property {$propertyName} does not exist in entity {$entityName}. Please check for typos or verify that the correct entity is being referenced in the query.");
			}
		}
		
		/**
		 * Visit a node in the AST (Abstract Syntax Tree).
		 * This function is responsible for visiting a node in the AST and validating it. The type of node
		 * determines what kind of validation is performed.
		 * @param AstInterface $node The node to visit.
		 * @return void
		 * @throws QuelException Thrown when validation fails.
		 */
		public function visitNode(AstInterface $node): void {
			// Validate the property if the node is of type AstIdentifier.
			if ($node instanceof AstIdentifier) {
				if ($node->hasParent() && $node->getParent() instanceof AstIdentifier) {
					$this->validateProperty($node->getParent()->getEntityName(), $node->getName());
				}
			}
		}
	}