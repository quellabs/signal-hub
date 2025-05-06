<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\PrimaryKeys\Generators;
	
	use Quellabs\ObjectQuel\EntityManager;
	use Quellabs\ObjectQuel\EntityManager\PrimaryKeys\PrimaryKeyGeneratorInterface;
	
	/**
	 * AutoIncrementGenerator class for handling auto-incrementing primary keys
	 */
	class AutoIncrementGenerator implements PrimaryKeyGeneratorInterface {
		
		/**
		 * Generate a new primary key for the given entity
		 * @param EntityManager $em The EntityManager instance
		 * @param object $entity The entity object for which to generate a primary key
		 * @return null Always returns null for auto-incrementing keys
		 */
		public function generate(EntityManager $em, object $entity): null {
			// For auto-incrementing primary keys, we return null
			// This allows the database to automatically assign the next available ID
			return null;
		}
	}