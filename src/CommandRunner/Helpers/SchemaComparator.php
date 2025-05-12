<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	use Quellabs\ObjectQuel\DatabaseAdapter\TypeMapper;
	
	/**
	 * Class SchemaComparator
	 * Compares entity schema (object properties) with database schema (table columns)
	 * to identify changes such as added, modified, or deleted columns.
	 */
	class SchemaComparator {
		
		/**
		 * @var TypeMapper Class responsible for mapping phinx types to php and vice versa
		 */
		private TypeMapper $typeMapper;
		
		/**
		 * SchemaComparator constructor
		 */
		public function __construct() {
			$this->typeMapper = new TypeMapper();
		}
		
		/**
		 * Main public method to compare entity properties with table columns
		 * Identifies added, modified, and deleted columns
		 * @param array $entityColumns Map of property names to definitions from entity model
		 * @param array $tableColumns Map of column names to definitions from database
		 * @return array Structured array of all detected changes
		 */
		public function analyzeSchemaChanges(array $entityColumns, array $tableColumns): array {
			return [
				'added'    => array_diff_key($entityColumns, $tableColumns),
				'modified' => $this->detectColumnChanges($entityColumns, $tableColumns),
				'deleted'  => array_diff_key($tableColumns, $entityColumns)
			];
		}
		
		/**
		 * Identifies columns that exist in both entity and table but have differences
		 * @param array $entityColumns Definition of properties from the entity model
		 * @param array $tableColumns Definition of columns from the database table
		 * @return array Columns that need to be modified in the table
		 */
		private function detectColumnChanges(array $entityColumns, array $tableColumns): array {
			$result = [];
			
			foreach (array_intersect_key($entityColumns, $tableColumns) as $name => $entityColumn) {
				// Fetch column information
				$column = $tableColumns[$name];
				
				// Compare only the relevant properties for the column type
				$relevantProperties = $this->typeMapper->getRelevantProperties($entityColumn['type']);
				$entityCompare = array_intersect_key($entityColumn, array_flip($relevantProperties));
				$dbCompare = array_intersect_key($column, array_flip($relevantProperties));
				
				if ($entityCompare != $dbCompare) {
					$result[$name] = [
						'from'       => $column,
						'to'         => $entityColumn,
					];
				}
			}
			
			return $result;
		}
		
	}