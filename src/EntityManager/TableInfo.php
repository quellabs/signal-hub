<?php
	
	namespace Services\EntityManager;
	
	class TableInfo {
		
		protected \Services\EntityManager\databaseAdapter $db;
		
		/**
		 * TableInfo constructor.
		 * @param \Services\EntityManager\databaseAdapter $db
		 */
		public function __construct(\Services\EntityManager\databaseAdapter $db) {
			$this->db = $db;
		}
		
		/**
		 * Deze functie haalt het type en de lengte uit een typebeschrijving.
		 * Bijvoorbeeld, van "VARCHAR(255)" wordt het type "VARCHAR" en de lengte 255.
		 * @param string $typeDesc De typebeschrijving, zoals "VARCHAR(255)" of "INT".
		 * @return array Een associatieve array met 'type' en 'length' als sleutels.
		 */
		private function extractTypeAndLength(string $typeDesc): array {
			// Als er geen '(' in de beschrijving zit, retourneer dan alleen het type.
			if (!str_contains($typeDesc, "(")) {
				return ['type' => $typeDesc, 'length' => null];
			}
			
			// Vind de posities van de haakjes.
			$posOfParentheseOpen = strpos($typeDesc, "(");
			$posOfParentheseClose = strpos($typeDesc, ")");
			
			// Extraheer het type en de lengte uit de beschrijving.
			return [
				'type'   => substr($typeDesc, 0, $posOfParentheseOpen),
				'length' => (int)substr($typeDesc, $posOfParentheseOpen + 1, $posOfParentheseClose - $posOfParentheseOpen - 1)
			];
		}
		
		/**
		 * Map SQL types to PHP types
		 * @param string $type
		 * @param int|null $length
		 * @return string
		 */
		private function mapToPHPType(string $type, ?int $length): string {
			$typeMap = [
				'tinyint'  => ($length === 1) ? 'bool' : 'int',
				'datetime' => '\DateTime',
				'date'     => '\DateTime',
				'int'      => 'int',
				'bigint'   => 'int',
				'char'     => 'string',
				'varchar'  => 'string',
				'text'     => 'string',
				'tinytext' => 'string',
				'longtext' => 'string',
				'decimal'  => 'float',
			];
			
			return $typeMap[$type] ?? '';
		}
		
		/**
		 * Processes a single column description.
		 * This function takes a column description and maps its properties to a more
		 * digestible array. The returned array includes important information about
		 * the column such as its type, PHP type mapping, length, and other attributes.
		 * @param array $desc The description of the column.
		 * @return array An array containing processed column information.
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
				'name'           => $desc["Field"],
				'type'           => $type,
				'length'         => $length,
				'php_type'       => $phpType,
				'primary_key'    => $desc["Key"] === "PRI",
				'key'            => !empty($desc["Key"]),
				'nullable'       => $desc["Null"] === "YES",
				'default'        => $desc["Default"],
				'auto_increment' => str_contains($desc["Extra"], "auto_increment"),
			];
		}
		
		/**
		 * Extracts table information based on the given table name.
		 * This function fetches the table description from the database
		 * and processes each column to collect relevant information.
		 * @param string $tableName The name of the table to extract information from.
		 * @return array An array containing information about each column in the table.
		 */
		public function extract(string $tableName): array {
			// Fetch table description from the database.
			$description = $this->db->getTableDescription($tableName);
			
			// If the table description is empty, return an empty array.
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