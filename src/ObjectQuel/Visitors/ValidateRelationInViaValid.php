<?php
	
	
	namespace Quellabs\ObjectQuel\ObjectQuel\Visitors;
	
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Class EntityExistenceValidator
	 * Validates the existence of entities within an AST.
	 */
	class ValidateRelationInViaValid implements AstVisitorInterface {
		
		private EntityStore $entityStore;
		private string $entityName;
		private string $rangeName;
		
		/**
		 * ValidateRelationInViaValid constructor.
		 * @param EntityStore $entityStore
		 * @param string $entityName
		 * @param string $rangeName
		 */
		public function __construct(EntityStore $entityStore, string $entityName, string $rangeName) {
			$this->entityStore = $entityStore;
			$this->entityName = $entityName;
			$this->rangeName = $rangeName;
		}
		
		/**
		 * Visit a node in the AST (Abstract Syntax Tree).
		 * This function is responsible for visiting a node in the AST and validating it. The type of node
		 * determines what kind of validation is performed.
		 * @param AstInterface $node The node to visit.
		 * @return void
		 * @throws QuelException
		 */
		public function visitNode(AstInterface $node): void {
			// Eerst controleren we of de node van het type AstIdentifier is.
			if (!$node instanceof AstIdentifier) {
				return;
			}
			
			// Doe niets als de identifier onderdeel is van een ketting
			if (!$node->isRoot()) {
				return;
			}
			
			// Doe niets als de identifier geen properties heeft
			if (!$node->hasNext()) {
				return;
			}
			
			// Haal alle afhankelijkheden op.
			$entityName = $node->getEntityName();
			$rangeName = $node->getRange()->getName();
			$propertyName = $node->getNext()->getName();
			
			$dependencies = [
				'oneToOne'  => $this->entityStore->getOneToOneDependencies($entityName),
				'manyToOne' => $this->entityStore->getManyToOneDependencies($entityName),
				'oneToMany' => $this->entityStore->getOneToManyDependencies($entityName),
			];
			
			// Doorloop alle afhankelijkheidstypes.
			foreach ($dependencies as $dependencyType => $dependency) {
				if (isset($dependency[$propertyName])) {
					$relation = $dependency[$propertyName];
					$targetEntity = $relation->getTargetEntity();
					
					if ($targetEntity !== $this->entityName) {
						throw new QuelException("Failed to join {$targetEntity} via {$rangeName}.{$propertyName} from {$this->entityName}. This is not a valid relationship path.");
					}
				}
			}
		}
	}