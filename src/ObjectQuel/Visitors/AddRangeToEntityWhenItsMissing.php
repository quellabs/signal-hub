<?php
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	class AddRangeToEntityWhenItsMissing implements AstVisitorInterface {
		
		private array $ranges;
		private array $range_names;
		private array $created_ranges;
		private array $counters;
		
		/**
		 * AddRangeToEntityWhenItsMissing constructor
		 */
		public function __construct() {
			$this->ranges = [];
			$this->range_names = [];
			$this->counters = [];
			$this->created_ranges = [];
		}
		
		/**
		 * Voegt een unieke range toe aan de entity als deze ontbreekt
		 * @param AstInterface $node
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			// Deze visitor behandeld alleen AstEntity
			if (!$node instanceof AstIdentifier) {
				return;
			}
			
			// Sla deze node over als hij al een alias heeft
			if ($node->hasRange()) {
				return;
			}
			
			// Sla deze node over als het onderdeel is van een keten
			if ($node->getParent() instanceof AstIdentifier) {
				return;
			}
			
			// Als we al een alias hebben gemaakt voor deze entity, gebruik deze dan
			if (isset($this->created_ranges[$node->getName()])) {
				$node->setRange($this->created_ranges[$node->getName()]);
				return;
			}
			
			// Haal de eerste letter van de entiteitsnaam op
			$firstLetter = substr($node->getName(), 0, 1);
			
			// Initialiseer of verhoog het volgnummer voor deze entiteit
			if (!isset($this->counters[$firstLetter])) {
				$this->counters[$firstLetter] = 0;
			}
			
			do {
				// Verhoog het volgnummer
				$this->counters[$firstLetter]++;
				
				// Maak de nieuwe alias
				$newAlias = sprintf('%s%03d', $firstLetter, $this->counters[$firstLetter]);
			} while (in_array($newAlias, $this->range_names));
			
			// Make een nieuwe range
			$newRange = new AstRangeDatabase($newAlias, $node->getEntityName(), null);
			
			// Voeg de nieuwe, unieke alias toe aan de lijst met bestaande aliassen
			$this->created_ranges[$node->getName()] = $newRange;
			$this->range_names[] = $newAlias;
			
			// Voeg de nieuwe AstRange toe
			$this->ranges[] = $newRange;
			
			// Zet de nieuwe alias voor het AstEntity
			$node->setRange($newRange);
		}
		
		/**
		 * Returns the ranges this visitor created
		 * @return array
		 */
		public function getRanges(): array {
			return $this->ranges;
		}
	}