<?php
	
	namespace Quellabs\ObjectQuel\Annotations\Orm;
	
	/**
	 * Definieert de ManyToOne klasse die de relatie tussen entiteiten beschrijft
	 */
	class RequiredRelation {
		
		// Bevat parameters die extra informatie over de relatie geven
		protected $parameters;
		
		/**
		 * Constructor om de parameters te initialiseren.
		 * @param array $parameters Array met parameters die de relatie beschrijven.
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
	}