<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Database;
	
	/**
	 * TypeMapper class
	 */
	class TypeMapper {
		
		/**
		 * This function converts database column types to their corresponding PHP types.
		 * Special handling is provided for tinyint(1) which is mapped to boolean.
		 * @param string $type The SQL type (e.g., 'varchar', 'int')
		 * @param int|null $length The length parameter of the type, if available
		 * @return string The corresponding PHP type
		 */
		public function mapToPHPType(string $type, ?int $length): string {
			$typeMap = [
				'tinyint'  => ($length === 1) ? 'bool' : 'int',  // tinyint(1) is typically used for boolean values
				'datetime' => '\DateTime',  // Maps to PHP's DateTime class
				'date'     => '\DateTime',  // Also maps to DateTime
				'int'      => 'int',
				'bigint'   => 'int',        // Even though bigint could exceed PHP int size on 32-bit systems
				'char'     => 'string',
				'varchar'  => 'string',
				'text'     => 'string',
				'tinytext' => 'string',
				'longtext' => 'string',
				'decimal'  => 'float',      // Decimal could also be mapped to string for precision-sensitive applications
			];
			
			return $typeMap[$type] ?? 'string';  // Returns string if type is not found in the map
		}
	}