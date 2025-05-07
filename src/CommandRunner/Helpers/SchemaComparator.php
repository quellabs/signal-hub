<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	/**
	 * Class SchemaComparator
	 * Compares entity schema (object properties) with database schema (table columns)
	 * to identify changes such as added, modified, or deleted columns.
	 */
	class SchemaComparator {
		
		/**
		 * Main public method to compare entity properties with table columns
		 * Identifies added, modified, and deleted columns
		 * @param array $entityProperties Map of property names to definitions from entity model
		 * @param array $tableColumns Map of column names to definitions from database
		 * @return array Structured array of all detected changes
		 */
		public function analyzeSchemaChanges(array $entityProperties, array $tableColumns): array {
			return [
				'added'    => $this->detectColumnAdditions($entityProperties, $tableColumns),
				'modified' => $this->detectColumnChanges($entityProperties, $tableColumns),
				'deleted'  => $this->detectColumnRemovals($entityProperties, $tableColumns),
			];
		}
		
		/**
		 * Identifies columns that exist in the entity but not in the table
		 * @param array $entityProperties Definition of properties from the entity model
		 * @param array $tableColumns Definition of columns from the database table
		 * @return array Columns that need to be added to the table
		 */
		private function detectColumnAdditions(array $entityProperties, array $tableColumns): array {
			return array_filter($entityProperties, function ($columnName) use ($tableColumns) {
				return !isset($tableColumns[$columnName]);
			}, ARRAY_FILTER_USE_KEY);
		}
		
		/**
		 * Identifies columns that exist in both entity and table but have differences
		 * @param array $entityProperties Definition of properties from the entity model
		 * @param array $tableColumns Definition of columns from the database table
		 * @return array Columns that need to be modified in the table
		 */
		private function detectColumnChanges(array $entityProperties, array $tableColumns): array {
			$result = [];
			
			foreach ($entityProperties as $columnName => $propertyDef) {
				// Skip if the column doesn't exist in the table (it's an added column)
				if (!isset($tableColumns[$columnName])) {
					continue;
				}
				
				// Compare property definition with column definition to find modifications
				$modifications = $this->getColumnDifferences($propertyDef, $tableColumns[$columnName]);
				
				// If modifications found, store the details
				if (!empty($modifications)) {
					$result[$columnName] = [
						'property'      => $propertyDef,
						'column'        => $tableColumns[$columnName],
						'modifications' => $modifications
					];
				}
			}
			
			return $result;
		}
		
		/**
		 * Identifies columns that exist in the table but not in the entity
		 * @param array $entityProperties Definition of properties from the entity model
		 * @param array $tableColumns Definition of columns from the database table
		 * @return array Columns that need to be removed from the table
		 */
		private function detectColumnRemovals(array $entityProperties, array $tableColumns): array {
			return array_filter($tableColumns, function ($columnName) use ($entityProperties) {
				return !isset($entityProperties[$columnName]);
			}, ARRAY_FILTER_USE_KEY);
		}
		
		/**
		 * Compares individual column definitions and identifies specific differences
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @return array Array of differences found (type, length, nullable, default)
		 */
		private function getColumnDifferences(array $propertyDef, array $columnDef): array {
			// Skip comparison if either definition is missing type information
			if (!$this->bothDefinitionsHaveTypes($propertyDef, $columnDef)) {
				return [];
			}
			
			// Compare different aspects of the column definitions
			$differences = [];
			$this->findTypeDifferences($propertyDef, $columnDef, $differences);
			$this->findLengthDifferences($propertyDef, $columnDef, $differences);
			$this->findNullabilityDifferences($propertyDef, $columnDef, $differences);
			$this->findDefaultValueDifferences($propertyDef, $columnDef, $differences);
			return $differences;
		}
		
		/**
		 * Validates that both property and column definitions have type information
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @return bool True if both definitions have type information
		 */
		private function bothDefinitionsHaveTypes(array $propertyDef, array $columnDef): bool {
			return isset($propertyDef['type']) && isset($columnDef['type']);
		}
		
		/**
		 * Compares data types between property and column
		 * Standardizes types to handle aliases (e.g., 'integer' vs 'int')
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @param array &$differences Reference to array for storing detected differences
		 * @return void
		 */
		private function findTypeDifferences(array $propertyDef, array $columnDef, array &$differences): void {
			// Sanitize and standardize type names
			$propertyType = is_string($propertyDef['type']) ? strtolower(trim($propertyDef['type'])) : '';
			$columnType = is_string($columnDef['type']) ? strtolower(trim($columnDef['type'])) : '';
			$propertyType = $this->standardizeDataType($propertyType);
			$columnType = $this->standardizeDataType($columnType);
			
			// Record difference if types don't match and aren't equivalent (e.g., tinyint(1) vs boolean)
			if ($propertyType !== $columnType && !$this->isTypeEquivalent($propertyType, $columnType, $columnDef)) {
				$differences['type'] = [
					'from' => $columnType,
					'to'   => $propertyType
				];
			}
		}
		
		/**
		 * Compares length/size constraints between property and column
		 * Handles special cases for different data types
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @param array &$differences Reference to array for storing detected differences
		 * @return void
		 */
		private function findLengthDifferences(array $propertyDef, array $columnDef, array &$differences): void {
			// Skip comparison if type already differs or length information is missing
			if (!empty($differences['type']) ||
				$propertyDef['length'] === null ||
				$columnDef['size'] === null) {
				return;
			}
			
			// Handle decimal types separately (precision and scale)
			$propertyType = $this->standardizeDataType($propertyDef['type']);
			
			if ($this->isDecimalType($propertyType)) {
				$this->findPrecisionDifferences($propertyDef, $columnDef, $differences);
				return;
			}
			
			// Compare length for types where length matters (e.g., varchar, char)
			if ($this->isLengthSensitiveType($propertyType) && $propertyDef['length'] != $columnDef['size']) {
				$differences['length'] = [
					'from' => $columnDef['size'],
					'to'   => $propertyDef['length']
				];
			}
		}
		
		/**
		 * Special comparison for decimal types that have precision and scale
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @param array &$differences Reference to array for storing detected differences
		 * @return void
		 */
		private function findPrecisionDifferences(array $propertyDef, array $columnDef, array &$differences): void {
			// Skip if either definition doesn't use the precision,scale format
			if (!str_contains($propertyDef['length'], ',') || !str_contains($columnDef['size'], ',')) {
				return;
			}
			
			// Extract and compare precision and scale values
			list($propertyPrecision, $propertyScale) = explode(',', $propertyDef['length']);
			list($columnPrecision, $columnScale) = explode(',', $columnDef['size']);
			
			if (trim($propertyPrecision) != trim($columnPrecision) || trim($propertyScale) != trim($columnScale)) {
				$differences['length'] = [
					'from' => $columnDef['size'],
					'to'   => $propertyDef['length']
				];
			}
		}
		
		/**
		 * Compares nullability constraints between property and column
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @param array &$differences Reference to array for storing detected differences
		 * @return void
		 */
		private function findNullabilityDifferences(array $propertyDef, array $columnDef, array &$differences): void {
			if (isset($propertyDef['nullable']) && isset($columnDef['nullable']) && $propertyDef['nullable'] !== $columnDef['nullable']) {
				$differences['nullable'] = [
					'from' => $columnDef['nullable'],
					'to'   => $propertyDef['nullable']
				];
			}
		}
		
		/**
		 * Compares default values between property and column
		 * Handles special cases like empty strings and timestamp defaults
		 * @param array $propertyDef Definition of property from the entity model
		 * @param array $columnDef Definition of column from the database table
		 * @param array &$differences Reference to array for storing detected differences
		 * @return void
		 */
		private function findDefaultValueDifferences(array $propertyDef, array $columnDef, array &$differences): void {
			// Normalize default values for consistent comparison
			$propDefault = isset($propertyDef['default']) ? $this->normalizeDefaultValue($propertyDef['default']) : null;
			$colDefault = isset($columnDef['default']) ? $this->normalizeDefaultValue($columnDef['default']) : null;
			
			// Skip if defaults are the same after normalization
			if ($propDefault === $colDefault) {
				return;
			}
			
			// Handle special case for timestamp defaults (NULL vs CURRENT_TIMESTAMP)
			$propertyType = $this->standardizeDataType($propertyDef['type'] ?? '');
			
			if ($this->isTimestampType($propertyType) && $this->areTimestampDefaultsEquivalent($propDefault, $colDefault)) {
				return;
			}
			
			$differences['default'] = [
				'from' => $columnDef['default'],
				'to'   => $propertyDef['default']
			];
		}
		
		/**
		 * Normalizes default value representations for consistent comparison
		 * Handles NULL, empty strings, and timestamp defaults
		 * @param mixed $value The default value to normalize
		 * @return string|null Normalized default value
		 */
		private function normalizeDefaultValue($value): ?string {
			if ($value === null) {
				return null;
			}
			
			if ($value === '') {
				return "''";
			}
			
			// Standardize timestamp default expressions
			if (is_string($value) && preg_match('/current_timestamp/i', $value)) {
				return 'CURRENT_TIMESTAMP';
			}
			
			return (string)$value;
		}
		
		/**
		 * Standardizes data type names to handle variations and aliases
		 * Maps database-specific types to standard types
		 * @param string $type The data type to standardize
		 * @return string Standardized data type
		 */
		private function standardizeDataType(string $type): string {
			$type = strtolower(trim($type));
			
			// Map variant type names to standardized types
			$typeMap = [
				'int'        => 'int',
				'integer'    => 'int',
				'smallint'   => 'smallint',
				'tinyint'    => 'tinyint',
				'mediumint'  => 'mediumint',
				'bigint'     => 'bigint',
				'decimal'    => 'decimal',
				'numeric'    => 'decimal',
				'float'      => 'float',
				'double'     => 'double',
				'char'       => 'char',
				'varchar'    => 'varchar',
				'string'     => 'varchar',
				'text'       => 'text',
				'mediumtext' => 'mediumtext',
				'longtext'   => 'longtext',
				'date'       => 'date',
				'datetime'   => 'datetime',
				'timestamp'  => 'timestamp',
				'boolean'    => 'boolean'
			];
			
			// Default to varchar if type not found in map
			return $typeMap[$type] ?? 'varchar';
		}
		
		/**
		 * Checks if two different type names are functionally equivalent
		 * Handles special cases like boolean vs tinyint(1)
		 * @param string $propertyType Standardized property type
		 * @param string $columnType Standardized column type
		 * @param array $columnDef Full column definition
		 * @return bool True if types are functionally equivalent
		 */
		private function isTypeEquivalent(string $propertyType, string $columnType, array $columnDef): bool {
			// Special case: tinyint(1) is often used to represent boolean
			return $columnType === 'tinyint' && $columnDef['size'] === '1' && $propertyType === 'boolean';
		}
		
		/**
		 * Checks if a type is a decimal or numeric type
		 * @param string $type The data type to check
		 * @return bool True if type is decimal or numeric
		 */
		private function isDecimalType(string $type): bool {
			return in_array($type, ['decimal', 'numeric']);
		}
		
		/**
		 * Checks if a type's length constraint is significant
		 * Some types (e.g., text) don't need explicit length comparison
		 * @param string $type The data type to check
		 * @return bool True if type's length is significant for comparison
		 */
		private function isLengthSensitiveType(string $type): bool {
			return in_array($type, ['varchar', 'char', 'binary', 'varbinary']);
		}
		
		/**
		 * Checks if a type is a timestamp-like type
		 * @param string $type The data type to check
		 * @return bool True if type is datetime or timestamp
		 */
		private function isTimestampType(string $type): bool {
			return in_array($type, ['datetime', 'timestamp']);
		}
		
		/**
		 * Special comparison for timestamp default values
		 * In some cases, NULL and CURRENT_TIMESTAMP are equivalent defaults
		 * @param mixed $propDefault Property default value
		 * @param mixed $colDefault Column default value
		 * @return bool True if defaults are functionally equivalent
		 */
		private function areTimestampDefaultsEquivalent(mixed $propDefault, mixed $colDefault): bool {
			$isCurrentTs = ($propDefault === 'CURRENT_TIMESTAMP' || $colDefault === 'CURRENT_TIMESTAMP');
			$isNull = ($propDefault === null || $colDefault === null);
			return $isCurrentTs && $isNull;
		}

	}