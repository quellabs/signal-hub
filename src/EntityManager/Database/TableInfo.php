<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Database;
	
	/**
	 * TableInfo class
	 *
	 * This class is responsible for extracting and processing database table information.
	 * It handles the mapping of database column types to PHP types and provides
	 * detailed information about table structure.
	 */
	class TableInfo {
		
		/**
		 * Database adapter instance used for database operations
		 * @var databaseAdapter
		 */
		protected databaseAdapter $db;
		
		/**
		 * TableInfo constructor.
		 * @param databaseAdapter $db Database adapter instance
		 */
		public function __construct(databaseAdapter $db) {
			$this->db = $db;
		}
		
		/**
		 * This function extracts the type and length from a type description.
		 * For example, from "VARCHAR(255)" it extracts the type "VARCHAR" and the length 255.
		 * @param string $typeDesc The type description, such as "VARCHAR(255)" or "INT".
		 * @return array An associative array with 'type' and 'length' as keys.
		 */
		private function extractTypeAndLength(string $typeDesc): array {
			// If there is no '(' in the description, return only the type.
			if (!str_contains($typeDesc, "(")) {
				return ['type' => $typeDesc, 'length' => null];
			}
			
			// Find the positions of the parentheses.
			$posOfParentheseOpen = strpos($typeDesc, "(");
			$posOfParentheseClose = strpos($typeDesc, ")");
			
			// Extract the type and length from the description.
			return [
				'type'   => substr($typeDesc, 0, $posOfParentheseOpen),
				'length' => (int)substr($typeDesc, $posOfParentheseOpen + 1, $posOfParentheseClose - $posOfParentheseOpen - 1)
			];
		}
		
		/**
		 * This function converts database column types to their corresponding PHP types.
		 * Special handling is provided for tinyint(1) which is mapped to boolean.
		 * @param string $type The SQL type (e.g., 'varchar', 'int')
		 * @param int|null $length The length parameter of the type, if available
		 * @return string The corresponding PHP type
		 */
		private function mapToPHPType(string $type, ?int $length): string {
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
			
			return $typeMap[$type] ?? '';  // Returns empty string if type is not found in the map
		}
		
		/**
		 * This function takes a column description and maps its properties to a more
		 * digestible array. The returned array includes important information about
		 * the column such as its type, PHP type mapping, length, and other attributes.
		 * @param array $desc The description of the column from the database
		 * @return array An array containing processed column information
		 */
		private function processColumn(array $desc): array {
			// Extract type and length from column description.
			$typeAndLength = $this->extractTypeAndLength($desc["Type"]);
			$type = $typeAndLength['type'];
			$length = $typeAndLength['length'];
			
			// Map SQL type to a PHP type.
			$phpType = $this->mapToPHPType($type, $length);
			
			// Return an array with all relevant information.
			return [
				'name'           => $desc["Field"],            // Column name
				'type'           => $type,                     // SQL type (without length)
				'length'         => $length,                   // Length parameter (if any)
				'php_type'       => $phpType,                  // Corresponding PHP type
				'primary_key'    => $desc["Key"] === "PRI",    // Whether column is primary key
				'key'            => !empty($desc["Key"]),      // Whether column is any kind of key
				'nullable'       => $desc["Null"] === "YES",   // Whether column allows NULL values
				'default'        => $desc["Default"],          // Default value (if any)
				'auto_increment' => str_contains($desc["Extra"], "auto_increment"), // Whether column auto-increments
			];
		}
		
		/**
		 * This function fetches the table description from the database
		 * and processes each column to collect relevant information.
		 * It serves as the main public API of this class.
		 * @param string $tableName The name of the table to extract information from
		 * @return array An array containing information about each column in the table
		 */
		public function extract(string $tableName): array {
			// Fetch table description from the database.
			$description = $this->db->getTableDescription($tableName);
			
			// If the table description is empty, return an empty array.
			// This could happen if the table doesn't exist or is inaccessible.
			if (empty($description)) {
				return [];
			}
			
			// Initialize the result array.
			$result = [];
			
			// Loop through each column description and process it.
			foreach ($description as $desc) {
				$result[] = $this->processColumn($desc);
			}
			
			// Return the array of processed columns.
			return $result;
		}
	}