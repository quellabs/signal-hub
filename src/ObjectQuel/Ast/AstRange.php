<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	use Services\ObjectQuel\Visitors\FindIdentifier;
	
	/**
	 * Class AstRange
	 * AstRange klasse is verantwoordelijk voor het definiëren van een bereik in de AST (Abstract Syntax Tree).
	 */
	class AstRange extends Ast {
		
		// Alias voor het bereik
		private string $name;
		
		/**
		 * AstRange constructor.
		 * @param string $name De naam voor dit bereik.
		 */
		public function __construct(string $name) {
			$this->name = $name;
		}
		
		/**
		 * Haal de alias voor dit bereik op.
		 * @return string De alias van dit bereik.
		 */
		public function getName(): string {
			return $this->name;
		}
	}