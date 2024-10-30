<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\Visitors\RangePresent;
	
	/**
	 * Class AstEntity
	 * Represents an entity within the AST.
	 */
	class AstEntity extends Ast {
		
		/**
		 * The name of the entity.
		 * @var string
		 */
		private string $name;
		
		/**
		 * The range (AST) of the entity.
		 * @var AstRange|null
		 */
		private ?AstRange $range;
		
		/**
		 * True if an implicit entity, false if not
		 * @var bool
		 */
		private bool $implicit;
		
		/**
		 * AstEntity constructor.
		 * @param string $entityName The name of the entity.
		 * @param AstRange|null $range The range the entity belongs to
		 */
		public function __construct(string $entityName, ?AstRange $range=null) {
			$this->name = $entityName;
			$this->range = $range;
			$this->implicit = false;
		}
		
		/**
		 * Returns true if a range is set, false if not
		 * @return bool
		 */
		public function hasRange(): bool {
			return $this->range != "";
		}
		
		/**
		 * Returns the range (alias)
		 * @return AstRange|null
		 */
		public function getRange(): ?AstRange {
			return $this->range;
		}
		
		/**
		 * Sets the range (alias)
		 * @param AstRange $range
		 * @return void
		 */
		public function setRange(AstRange $range) {
			$this->range = $range;
		}
		
		/**
		 * Sets the entity
		 * @param string $name
		 * @return void
		 */
		public function setName(string $name) {
			$this->name = $name;
		}
		
		/**
		 * Get the name of the entity.
		 * @return string The name of the entity.
		 */
		public function getName(): string {
			return $this->name;
		}
		
		/**
		 * Marks the entity as 'implicit'. E.g. it's used to create
		 * the query, but it's not part of the result set.
		 * @param bool $implicit
		 * @return void
		 */
		public function setImplicit(bool $implicit=true): void {
			$this->implicit = $implicit;
		}
		
		/**
		 * Returns true if the entity is implicit, false if not.
		 * An implicit entity is used to make the query, but is not
		 * part of the result set
		 * @return bool
		 */
		public function isImplicit(): bool {
			return $this->implicit;
		}
	}