<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class AstIsNumeric
	 */
	class AstExists extends Ast {
		
		/**
		 * The value or string to check
		 * @var AstEntity
		 */
		protected AstEntity $entity;
		
		/**
		 * AstExists constructor.
		 * @param AstEntity $entity
		 */
		public function __construct(AstEntity $entity) {
			$this->entity = $entity;
		}
		
		/**
		 * Accept the visitor
		 * @param AstVisitorInterface $visitor
		 * @return void
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->entity->accept($visitor);
		}
		
		/**
		 * Retrieves the entity
		 * @return AstEntity
		 */
		public function getEntity(): AstEntity {
			return $this->entity;
		}
	}