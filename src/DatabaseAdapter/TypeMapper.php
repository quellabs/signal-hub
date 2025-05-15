<?php
	
	namespace Quellabs\ObjectQuel\DatabaseAdapter;
	
	/**
	 * TypeMapper class
	 */
	class TypeMapper {
		
		/**
		 * Convert a Phinx column type to a corresponding PHP type
		 * @param string $phinxType The Phinx column type
		 * @return string The corresponding PHP type
		 */
		public function phinxTypeToPhpType(string $phinxType): string {
			$typeMap = [
				// Integer types
				'tinyinteger'  => 'int',
				'smallinteger' => 'int',
				'integer'      => 'int',
				'biginteger'   => 'int', // Could be 'string' for very large numbers
				
				// String types
				'string'       => 'string',
				'char'         => 'string',
				'text'         => 'string',
				
				// Float/decimal types
				'float'        => 'float',
				'decimal'      => 'float', // Could also be string for precision
				
				// Boolean type
				'boolean'      => 'bool',
				
				// Date and time types
				'date'         => '\DateTime',
				'datetime'     => '\DateTime',
				'time'         => '\DateTime',
				'timestamp'    => '\DateTime',
				
				// Binary type
				'binary'       => 'string',
				'blob'         => 'string',
				
				// JSON type
				'json'         => 'array',  // Assuming JSON is decoded to array
				'jsonb'        => 'array',  // PostgreSQL JSON type
				
				// Other types
				'enum'         => 'string',
				'set'          => 'array',
				'uuid'         => 'string',
				'year'         => 'int'
			];
			
			return $typeMap[$phinxType] ?? 'mixed';
		}
		
		/**
		 * Get relevant properties for column comparison based on type
		 * @param string $type
		 * @return array
		 */
		public function getRelevantProperties(string $type): array {
			// Base properties all columns have
			$baseProperties = ['type', 'null', 'default'];
			
			// Type-specific properties
			$typeProperties = [
				// Integer types (universally supported)
				'tinyinteger'  => ['limit', 'unsigned', 'identity'],
				'smallinteger' => ['limit', 'unsigned', 'identity'],
				'integer'      => ['limit', 'unsigned', 'identity'],
				'biginteger'   => ['limit', 'unsigned', 'identity'],
				
				// String types (universally supported)
				'string'       => ['limit'],
				'char'         => ['limit'],
				'text'         => [],
				
				// Float/decimal types (universally supported)
				'float'        => ['precision', 'unsigned'],
				'decimal'      => ['precision', 'scale', 'unsigned'],
				
				// Boolean type (universally supported)
				'boolean'      => [],
				
				// Date and time types (universally supported)
				'date'         => [],
				'datetime'     => ['precision'],
				'time'         => ['precision'],
				'timestamp'    => ['precision', 'update'],
				
				// Binary type (universally supported)
				'binary'       => ['limit'],
				'blob'         => [],
				
				// Common extension types
				'json'         => [],
				'enum'         => ['values'],
			];
			
			return array_merge($baseProperties, $typeProperties[$type] ?? []);
		}
		
		/**
		 * Format a value for inclusion in PHP code
		 * @param mixed $value The value to format
		 * @return string Formatted value
		 */
		public function formatValue(mixed $value): string {
			if (is_null($value)) {
				return 'null';
			}
			
			if (is_bool($value)) {
				return $value ? 'true' : 'false';
			}
			
			if (is_int($value) || is_float($value)) {
				return (string)$value;
			}

			return "'" . addslashes($value) . "'";
		}
	}