<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
	use Services\AnnotationsReader\Annotations\Orm\OneToMany;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstBinaryOperator;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class TransformRelationInViaToPropertyLookup
	 * Validates the existence of entities within an AST.
	 */
	class TransformRelationInViaToPropertyLookup implements AstVisitorInterface {
		
		private EntityStore $entityStore;
		private AstRangeDatabase $range;
		
		/**
		 * TransformRelationInViaToPropertyLookup constructor.
		 * @param EntityStore $entityStore
		 * @param AstRangeDatabase $range
		 */
		public function __construct(EntityStore $entityStore, AstRangeDatabase $range) {
			$this->entityStore = $entityStore;
			$this->range = $range;
		}
		
		/**
		 * Returns true if the given identifier targets a relation, false if not
		 * @param AstIdentifier $node
		 * @return bool
		 */
		public function isRelationProperty(AstIdentifier $node): bool {
			$entityName = $node->getEntityName();
			$propertyName = $node->getName();
			
			return array_key_exists($propertyName, array_merge(
				$this->entityStore->getOneToOneDependencies($entityName),
				$this->entityStore->getManyToOneDependencies($entityName),
				$this->entityStore->getOneToManyDependencies($entityName)
			));
		}
		
		/**
		 * Returns a new expression
		 * @param string $propertyA
		 * @param AstRangeDatabase $rangeB
		 * @param string $propertyB
		 * @return AstExpression
		 */
		public function createPropertyLookupAst(string $propertyA, AstRange $rangeB, string $propertyB): AstInterface {
			$identifierA = new AstIdentifier(clone $this->range->getEntity(), $propertyA);
			$identifierB = new AstIdentifier(clone $rangeB->getEntity(), $propertyB);
			return new AstExpression($identifierA, $identifierB, '=');
		}
		
		/**
		 * Creëert een Property Lookup AST (Abstract Syntax Tree) gebruikmakend van een relatie.
		 * @param AstIdentifier $joinProperty Het join property waarmee de relatie wordt gemaakt.
		 * @param mixed $relation De relatie die gebruikt wordt om de property lookup te creëren.
		 * @return AstInterface Het gegenereerde AST-object.
		 */
		public function createPropertyLookupAstUsingRelation(AstIdentifier $joinProperty, mixed $relation): AstInterface {
			// Haal de entiteit en range op van de join property
			$entity = $joinProperty->getParentIdentifier();
			$range = $entity->getRange();
			$relationColumn = $relation->getRelationColumn();
			
			// Als de relatiekolom null is, gebruik de eerste identifier key van de entiteit
			if ($relationColumn === null) {
				$identifierKeys = $this->entityStore->getIdentifierKeys($entity->getName());
				$relationColumn = $identifierKeys[0];
			}
			
			// Voor ManyToOne relaties, gebruik de 'inversedBy' waarde
			if ($relation instanceof ManyToOne) {
				return $this->createPropertyLookupAst($relationColumn, $range, $relation->getInversedBy());
			}
			
			// Voor OneToMany relaties, gebruik de 'mappedBy' waarde
			if ($relation instanceof OneToMany) {
				return $this->createPropertyLookupAst($relationColumn, $range, $relation->getMappedBy());
			}
			
			// Voor relaties met een 'inversedBy' waarde, gebruik deze
			if (!empty($relation->getInversedBy())) {
				return $this->createPropertyLookupAst($relationColumn, $range, $relation->getInversedBy());
			}
			
			// Voor alle andere gevallen, gebruik de 'mappedBy' waarde
			return $this->createPropertyLookupAst($relationColumn, $range, $relation->getMappedBy());
		}
		
		/**
		 * Verwerkt een enkele zijde (links of rechts) van de node en past deze aan indien nodig.
		 * Als de zijde een AstIdentifier is en overeenkomt met een relatie-eigenschap, wordt deze
		 * vervangen door een nieuwe node die de relatie vertegenwoordigt. Anders wordt de originele
		 * zijde ongewijzigd geretourneerd.
		 * @param AstInterface $side De linker of rechterzijde van de node die verwerkt moet worden.
		 * @return AstInterface De oorspronkelijke of aangepaste zijde van de node.
		 */
		public function processNodeSide(AstInterface $side): AstInterface {
			// Controleer of de zijde een AstIdentifier is en een relatie-eigenschap vertegenwoordigt.
			if (!($side instanceof AstIdentifier) || !$this->isRelationProperty($side)) {
				return $side; // Geen aanpassingen nodig als het geen AstIdentifier of geen relatie-eigenschap is.
			}
			
			// Haal de entiteitsnaam en eigenschapsnaam op van de zijde.
			$entityName = $side->getEntityName();
			$propertyName = $side->getName();
			
			// Combineer alle relatie-afhankelijkheden voor de gegeven entiteitsnaam.
			$relations = array_merge(
				$this->entityStore->getOneToOneDependencies($entityName),
				$this->entityStore->getManyToOneDependencies($entityName),
				$this->entityStore->getOneToManyDependencies($entityName)
			);
			
			// Controleer of de eigenschapsnaam bestaat in de relaties voordat we deze gebruiken.
			if (!isset($relations[$propertyName])) {
				return $side; // Geen aanpassingen nodig als de eigenschap niet bestaat in de relaties.
			}
			
			// Vervang de zijde door een nieuwe node die de relatie vertegenwoordigt.
			return $this->createPropertyLookupAstUsingRelation($side, $relations[$propertyName]);
		}
		
		/**
		 * Visit a node in the AST (Abstract Syntax Tree).
		 * This function is responsible for visiting a node in the AST and validating it. The type of node
		 * determines what kind of validation is performed.
		 * @param AstInterface $node The node to visit.
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			// Als de node niet van het type AstBinaryOperator is, doen we niets.
			if (!$node instanceof AstBinaryOperator) {
				return;
			}
			
			// Verwerk en update indien nodig de linkerzijde van de node
			$node->setLeft($this->processNodeSide($node->getLeft()));
			
			// Verwerk en update indien nodig de rechterzijde van de node
			$node->setRight($this->processNodeSide($node->getRight()));
		}
	}