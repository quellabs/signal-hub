<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	/**
	 * Compares entity schema with database schema to identify changes
	 */
	class SchemaComparator {
		
		/**
		 * Identify columns that need to be added or modified
		 *
		 * @param array $entityProperties Properties defined in the entity model
		 * @param array $tableColumns Columns that exist in the database table
		 * @param array &$changes Reference to the changes array to be updated
		 */
		private function findAddedOrModifiedColumns(array $entityProperties, array $tableColumns, array &$changes): void {
			foreach ($entityProperties as $columnName => $propertyDef) {
				// Add new property if it doesn't exist in the database
				if (!isset($tableColumns[$columnName])) {
					$changes['added'][$columnName] = $propertyDef;
					continue;
				}
				
				// Check for modifications to existing properties
				$columnDef = $tableColumns[$columnName];
				$modifications = $this->compareColumnDefinitions($propertyDef, $columnDef);
				
				if (!empty($modifications)) {
					$changes['modified'][$columnName] = [
						'property'      => $propertyDef,
						'column'        => $columnDef,
						'modifications' => $modifications
					];
				}
			}
		}
		
		/**
		 * Identify columns that have been deleted from the entity
		 *
		 * @param array $entityProperties Properties defined in the entity model
		 * @param array $tableColumns Columns that exist in the database table
		 * @param array &$changes Reference to the changes array to be updated
		 */
		private function findDeletedColumns(array $entityProperties, array $tableColumns, array &$changes): void {
			foreach ($tableColumns as $columnName => $columnDef) {
				if (!isset($entityProperties[$columnName])) {
					$changes['deleted'][$columnName] = $columnDef;
				}
			}
		}
		
		/**
		 * Compare entity property definition with database column definition
		 *
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @return array Array of differences
		 */
		private function compareColumnDefinitions(array $propertyDef, array $columnDef): array {
			$differences = [];
			
			// Skip comparison if either definition is missing type information
			if (!$this->hasValidTypeDefinitions($propertyDef, $columnDef)) {
				return $differences;
			}
			
			// Compare and collect differences
			$this->compareTypes($propertyDef, $columnDef, $differences);
			$this->compareLengths($propertyDef, $columnDef, $differences);
			$this->compareNullability($propertyDef, $columnDef, $differences);
			$this->compareDefaultValues($propertyDef, $columnDef, $differences);
			
			return $differences;
		}
		
		/**
		 * Check if both definitions have valid type information
		 *
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @return bool True if both have valid type information
		 */
		private function hasValidTypeDefinitions(array $propertyDef, array $columnDef): bool {
			return isset($propertyDef['type']) && isset($columnDef['type']);
		}
		
		/**
		 * Compare and normalize column types
		 *
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @param array &$differences Reference to differences array
		 */
		private function compareTypes(array $propertyDef, array $columnDef, array &$differences): void {
			$propertyType = is_string($propertyDef['type']) ? strtolower(trim($propertyDef['type'])) : '';
			$columnType = is_string($columnDef['type']) ? strtolower(trim($columnDef['type'])) : '';
			
			// Normalize types for proper comparison
			$propertyType = $this->standardizeDataType($propertyType);
			$columnType = $this->standardizeDataType($columnType);
			
			if ($propertyType !== $columnType && !$this->isTypeEquivalent($propertyType, $columnType, $columnDef)) {
				$differences['type'] = [
					'from' => $columnType,
					'to'   => $propertyType
				];
			}
		}
		
		/**
		 * Compare column lengths for compatible types
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @param array &$differences Reference to differences array
		 */
		private function compareLengths(array $propertyDef, array $columnDef, array &$differences): void {
			// Skip comparison if types don't match or if either definition lacks length information
			if (!empty($differences['type']) ||
				$propertyDef['length'] === null ||
				$columnDef['size'] === null) {
				return;
			}
			
			// Get normalized column type for comparison
			$propertyType = $this->standardizeDataType($propertyDef['type']);
			
			// Handle decimal/numeric types separately with precision and scale comparison
			if ($this->isDecimalType($propertyType)) {
				$this->compareDecimalPrecision($propertyDef, $columnDef, $differences);
				return;
			}
			
			// For types where length matters (like varchar, char), check for differences
			// Note: For some types like integer, small differences in length are ignored
			// as they don't affect database behavior (e.g., int(10) vs int(11))
			if ($this->isLengthSensitiveType($propertyType) && $propertyDef['length'] != $columnDef['size']) {
				$differences['length'] = [
					'from' => $columnDef['size'],
					'to'   => $propertyDef['length']
				];
			}
		}
		
		/**
		 * Check if column type is decimal or numeric
		 * @param string $type Normalized column type
		 * @return bool True if decimal type
		 */
		private function isDecimalType(string $type): bool {
			return in_array($type, ['decimal', 'numeric']);
		}
		
		/**
		 * Check if column type's length is significant for comparison
		 * @param string $type Normalized column type
		 * @return bool True if length matters for this type
		 */
		private function isLengthSensitiveType(string $type): bool {
			return in_array($type, ['varchar', 'char', 'binary', 'varbinary']);
		}
		
		/**
		 * Compare precision and scale for decimal types
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @param array &$differences Reference to differences array
		 */
		private function compareDecimalPrecision(array $propertyDef, array $columnDef, array &$differences): void {
			if (!str_contains($propertyDef['length'], ',') || !str_contains($columnDef['size'], ',')) {
				return;
			}
			
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
		 * Compare nullability settings
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @param array &$differences Reference to differences array
		 */
		private function compareNullability(array $propertyDef, array $columnDef, array &$differences): void {
			if (isset($propertyDef['nullable']) && isset($columnDef['nullable']) && $propertyDef['nullable'] !== $columnDef['nullable']) {
				$differences['nullable'] = [
					'from' => $columnDef['nullable'],
					'to'   => $propertyDef['nullable']
				];
			}
		}
		
		/**
		 * Compare default values with special handling for timestamps
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @param array &$differences Reference to differences array
		 */
		private function compareDefaultValues(array $propertyDef, array $columnDef, array &$differences): void {
			// Normalize default values for consistent comparison
			// This handles cases like quoted strings, special literals, etc.
			$propDefault = isset($propertyDef['default']) ? $this->normalizeDefaultValue($propertyDef['default']) : null;
			$colDefault = isset($columnDef['default']) ? $this->normalizeDefaultValue($columnDef['default']) : null;
			
			// If defaults are identical after normalization, no difference exists
			if ($propDefault === $colDefault) {
				return;
			}
			
			// Get the property type for special case handling
			// Use null coalescing to handle potentially undefined 'type' key
			$propertyType = $this->standardizeDataType($propertyDef['type'] ?? '');
			
			// Special case: For datetime/timestamp fields, CURRENT_TIMESTAMP and NULL
			// are often treated equivalently by database systems, so we don't report
			// these as differences to avoid unnecessary ALTER TABLE statements
			if ($this->isTimestampType($propertyType) &&
				$this->isEquivalentTimestampDefault($propDefault, $colDefault)) {
				return;
			}
			
			// Record the default value difference for schema migration
			// Note: We store the original values (not normalized) for proper SQL generation
			$differences['default'] = [
				'from' => $columnDef['default'],
				'to'   => $propertyDef['default']
			];
		}
		
		/**
		 * Check if column type is datetime or timestamp
		 * @param string $type Normalized column type
		 * @return bool True if timestamp type
		 */
		private function isTimestampType(string $type): bool {
			return in_array($type, ['datetime', 'timestamp']);
		}
		
		/**
		 * Check if timestamp defaults are equivalent
		 * (CURRENT_TIMESTAMP is often equivalent to NULL)
		 * @param mixed $propDefault Property default value
		 * @param mixed $colDefault Column default value
		 * @return bool True if defaults are equivalent
		 */
		private function isEquivalentTimestampDefault(mixed $propDefault, mixed $colDefault): bool {
			$isCurrentTs = ($propDefault === 'CURRENT_TIMESTAMP' || $colDefault === 'CURRENT_TIMESTAMP');
			$isNull = ($propDefault === null || $colDefault === null);
			return $isCurrentTs && $isNull;
		}
		
		/**
		 * Normalize default values for consistent comparison
		 * @param mixed $value Default value
		 * @return string|null Normalized value
		 */
		private function normalizeDefaultValue($value): ?string {
			if ($value === null) {
				return null;
			}
			
			// Convert empty strings to explicit string representation for comparison
			if ($value === '') {
				return "''";
			}
			
			// Normalize CURRENT_TIMESTAMP and similar
			if (is_string($value) && preg_match('/current_timestamp/i', $value)) {
				return 'CURRENT_TIMESTAMP';
			}
			
			return (string)$value;
		}
		
		
		
		// Include all the other comparison methods here (compareLengths, compareNullability, etc.)
		// ...
		
		/**
		 * Normalize column type for consistent comparison
		 *
		 * @param string $type Column or PHP type
		 * @return string Normalized type
		 */
		private function standardizeDataType(string $type): string {
			// Convert to lowercase and trim
			$type = strtolower(trim($type));
			
			// Map of equivalent types
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
			
			return $typeMap[$type] ?? 'varchar';
		}
		
		/**
		 * Check if types are equivalent (handles special cases like boolean/tinyint)
		 *
		 * @param string $propertyType Normalized property type
		 * @param string $columnType Normalized column type
		 * @param array $columnDef Column definition
		 * @return bool True if types are equivalent
		 */
		private function isTypeEquivalent(string $propertyType, string $columnType, array $columnDef): bool {
			// Special case for tinyint(1) which is often used as boolean
			return $columnType === 'tinyint' && $columnDef['size'] === '1' && $propertyType === 'boolean';
		}
		
		/**
		 * Compare entity properties with existing table columns to identify changes
		 * @param array $entityProperties Properties defined in the entity model
		 * @param array $tableColumns Columns that exist in the database table
		 * @return array Changes categorized as added, modified, or deleted
		 */
		public function compareColumns(array $entityProperties, array $tableColumns): array {
			$changes = [
				'added'    => [],
				'modified' => [],
				'deleted'  => []
			];
			
			$this->findAddedOrModifiedColumns($entityProperties, $tableColumns, $changes);
			$this->findDeletedColumns($entityProperties, $tableColumns, $changes);
			
			return $changes;
		}
	}