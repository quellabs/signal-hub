<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Class EntityExistenceValidator
	 * Validates the existence of entities within an AST.
	 */
	class EntityExistenceValidator implements AstVisitorInterface {
		
		/**
		 * The EntityStore for storing and fetching entity metadata.
		 * @var $entityStore EntityStore
		 */
		private EntityStore $entityStore;
		
		/**
		 * EntityExistenceValidator constructor.
		 * @param EntityStore $entityStore
		 */
		public function __construct(EntityStore $entityStore) {
			$this->entityStore = $entityStore;
		}
		
		/**
		 * Bezoekt een node in de AST.
		 * @param AstInterface $node De node om te bezoeken.
		 * @throws QuelException
		 */
		public function visitNode(AstInterface $node): void {
			if ($node instanceof AstEntity) {
				$entityName = basename(str_replace('\\', '/', $node->getName()));
				
				if (!$this->entityStore->exists($entityName)) {
					throw new QuelException("The entity or range {$entityName} referenced in the query does not exist. Please check the query for incorrect references and ensure all specified entities or ranges are correctly defined.");
				}
			}
		}
	}