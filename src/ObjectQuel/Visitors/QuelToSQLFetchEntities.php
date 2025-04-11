<?php
	
	// Namespace declaration voor gestructureerde code
	namespace Services\ObjectQuel\Visitors;
	
	// Importeer de vereiste klassen en interfaces
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class RetrieveEntities
	 * Implementeert AstVisitor om entiteiten uit een AST te verzamelen.
	 */
	class QuelToSQLFetchEntities implements AstVisitorInterface {
		
		// Array om verzamelde entiteiten op te slaan
		private array $entities;
		
		/**
		 * Constructor om de entities array te initialiseren.
		 */
		public function __construct() {
			$this->entities = [];
		}
		
		/**
		 * Voeg een entiteit toe aan de lijst als deze nog niet bestaat.
		 * @param AstIdentifier $entity De entiteit die mogelijk wordt toegevoegd.
		 * @return void
		 */
		protected function addEntityIfNotExists(AstIdentifier $entity): void {
			// Loop door alle bestaande entiteiten om te controleren op duplicaten
			foreach($this->entities as $e) {
				// Als een entiteit met dezelfde gegevens al bestaat, verlaat de functie vroegtijdig
				if (
					$e->getRange() instanceof AstRangeDatabase &&
					($e->getName() == $entity->getEntityName()) &&
					($e->getRange() == $entity->getRange())
				) {
					return;
				}
			}
			
			// Voeg de nieuwe entiteit toe aan de lijst
			$this->entities[] = $entity;
		}

		/**
		 * Bezoek een knooppunt in de AST.
		 * @param AstInterface $node Het te bezoeken knooppunt.
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			// Controleer of het knooppunt een entiteit is en voeg het toe aan de array
			if ($node instanceof AstIdentifier) {
				$this->addEntityIfNotExists($node);
			}
		}
		
		/**
		 * Verkrijg de verzamelde entiteiten.
		 * @return AstIdentifier[] De verzamelde entiteiten.
		 */
		public function getEntities(): array {
			return $this->entities;
		}
	}