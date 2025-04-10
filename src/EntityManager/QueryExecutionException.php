<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\QuelException;
	
	class QueryExecutionException extends \Exception {
		
		/**
		 * @param string $string
		 * @param int $int
		 * @param QuelException|null $e
		 */
		public function __construct(string $string, int $int, ?QuelException $e=null) {
			parent::__construct($string, $int, $e);
		}
	}