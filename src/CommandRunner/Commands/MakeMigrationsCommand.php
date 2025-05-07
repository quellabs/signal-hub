<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Commands;
	
	/**
	 * Import required classes for migration generation and entity analysis
	 */
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\ObjectQuel\CommandRunner\Command;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleInput;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleOutput;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\DatabaseAdapter\TableInfo;
	use Quellabs\ObjectQuel\Annotations\Orm\Column;
	use Quellabs\ObjectQuel\Annotations\Orm\Table;
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;
	use ReflectionClass;
	use ReflectionProperty;
	use DateTime;
	
	/**
	 * MakeMigration - CLI command for generating database migrations
	 *
	 * This command analyzes differences between entity definitions and database schema,
	 * then creates migration files to synchronize the database with entity changes.
	 * It tracks added, modified, or removed fields and relationships to generate
	 * the appropriate SQL commands for schema updates.
	 */
	class MakeMigrationsCommand extends Command {
		private DatabaseAdapter $connection;
		private TableInfo $tableInfo;
		private string $entityPath;
		private AnnotationReader $annotationReader;
		private string $migrationsPath;
		private array $entityClasses = [];
		private array $tableDefinitions = [];
		
		/**
		 * Constructor
		 * @param ConsoleInput $input Command line input interface
		 * @param ConsoleOutput $output Command line output interface
		 * @param Configuration $configuration Application configuration
		 */
		public function __construct(
			ConsoleInput  $input,
			ConsoleOutput $output,
			Configuration $configuration
		) {
			parent::__construct($input, $output, $configuration);
			
			$annotationReaderConfiguration = new \Quellabs\AnnotationReader\Configuration();
			$annotationReaderConfiguration->setUseAnnotationCache($configuration->useAnnotationCache());
			$annotationReaderConfiguration->setAnnotationCachePath($configuration->getAnnotationCachePath());
			
			$this->connection = new DatabaseAdapter($configuration);
			$this->tableInfo = new TableInfo($this->connection);
			$this->entityPath = $configuration->getEntityPath();
			$this->migrationsPath = $configuration->getMigrationsPath();
			$this->annotationReader = new AnnotationReader($annotationReaderConfiguration);
		}
		
		/**
		 * Convert a string to snake case
		 * @url https://stackoverflow.com/questions/40514051/using-preg-replace-to-convert-camelcase-to-snake-case
		 * @param string $string
		 * @return string
		 */
		private function snakeCase(string $string): string {
			return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
		}
		
		/**
		 * Normalize column type for consistent comparison
		 * Can handle both SQL and PHP types
		 * @param string $type Column or PHP type
		 * @return string Normalized type
		 */
		private function normalizeColumnType(string $type): string {
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
		 * Convert a PHP type to an SQL type
		 * @param string $phpType PHP type
		 * @return string SQL type
		 */
		private function phpTypeToSqlType(string $phpType): string {
			$map = [
				'int'      => 'integer',
				'integer'  => 'integer',
				'float'    => 'float',
				'double'   => 'double',
				'bool'     => 'boolean',
				'boolean'  => 'boolean',
				'string'   => 'varchar',
				'array'    => 'text',
				'DateTime' => 'datetime'
			];
			
			return $map[strtolower($phpType)] ?? 'varchar';
		}
		
		/**
		 * Finds and loads all entity classes from the entity path
		 * @return array Array of entity class names with their table names
		 */
		private function loadEntityClasses(): array {
			$entityClasses = [];
			$directory = new RecursiveDirectoryIterator($this->entityPath);
			$iterator = new RecursiveIteratorIterator($directory);
			
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$className = $this->getClassNameFromFile($file->getPathname());
					
					if ($className && class_exists($className)) {
						$reflection = new ReflectionClass($className);
						
						// Skip abstract classes and interfaces
						if ($reflection->isAbstract() || $reflection->isInterface()) {
							continue;
						}
						
						// Check if class has Table annotation
						$classAnnotations = $this->annotationReader->getClassAnnotations($className);
						$tableName = null;
						
						// Look for Table annotation
						foreach ($classAnnotations as $annotation) {
							if ($annotation instanceof Table) {
								$tableName = $annotation->getName();
								break;
							}
						}
						
						// Only consider classes with a Table annotation as entities
						if ($tableName) {
							$entityClasses[$className] = $tableName;
						}
					}
				}
			}
			
			return $entityClasses;
		}
		
		/**
		 * Extract class name from file path
		 * @param string $filePath Path to the PHP file
		 * @return string|null The fully qualified class name if found, null otherwise
		 */
		private function getClassNameFromFile(string $filePath): ?string {
			$content = file_get_contents($filePath);
			
			// Extract namespace
			preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
			$namespace = $namespaceMatches[1] ?? '';
			
			// Extract class name
			preg_match('/class\s+(\w+)(?:\s+extends|\s+implements|\s*{)/', $content, $classMatches);
			$className = $classMatches[1] ?? null;
			
			if ($namespace && $className) {
				return $namespace . '\\' . $className;
			}
			
			return null;
		}
		
		/**
		 * Get entity property definitions from annotations
		 * @param string $className Entity class name
		 * @return array Array of property definitions
		 */
		private function getEntityProperties(string $className): array {
			$reflection = new \ReflectionClass($className);
			$properties = [];
			
			foreach ($reflection->getProperties() as $property) {
				$propertyAnnotations = $this->annotationReader->getPropertyAnnotations($className, $property->getName());
				
				// Look for Column annotation
				$columnAnnotation = null;
				
				foreach ($propertyAnnotations as $annotation) {
					if ($annotation instanceof Column) {
						$columnAnnotation = $annotation;
						break;
					}
				}
				
				if ($columnAnnotation) {
					// Use the column name from the annotation, not the property name
					$columnName = $columnAnnotation->getName();
					
					// If no column name found, skip this property
					if (empty($columnName)) {
						continue;
					}
					
					// Gather property info
					$properties[$columnName] = [
						'property_name'  => $property->getName(),
						'name'           => $columnAnnotation->getName(),
						'type'           => $columnAnnotation->getType(),
						'length'         => $columnAnnotation->getLength(),
						'nullable'       => $columnAnnotation->isNullable(),
						'default'        => $columnAnnotation->getDefault(),
						'primary_key'    => $columnAnnotation->isPrimaryKey(),
						'auto_increment' => $columnAnnotation->isAutoIncrement(),
					];
				}
			}
			
			return $properties;
		}
		
		/**
		 * Get table definitions from database
		 * @param string $tableName Table name
		 * @return array Array of column definitions
		 */
		private function getTableDefinition(string $tableName): array {
			if (!isset($this->tableDefinitions[$tableName])) {
				$columns = $this->connection->getColumnsEx($tableName);
				
				if (empty($columns)) {
					return [];
				}
				
				$this->tableDefinitions[$tableName] = $columns;
			}
			
			return $this->tableDefinitions[$tableName];
		}
		
		/**
		 * Compare entity properties with existing table columns to identify changes.
		 * @param array $entityProperties Properties defined in the entity model
		 * @param array $tableColumns Columns that exist in the database table
		 * @return array Changes categorized as added, modified, or deleted
		 */
		private function compareColumns(array $entityProperties, array $tableColumns): array {
			$changes = [
				'added'    => [],
				'modified' => [],
				'deleted'  => []
			];
			
			$this->findAddedOrModifiedColumns($entityProperties, $tableColumns, $changes);
			$this->findDeletedColumns($entityProperties, $tableColumns, $changes);
			return $changes;
		}
		
		/**
		 * Identify columns that need to be added or modified.
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
		 * Identify columns that have been deleted from the entity.
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
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @return bool True if both have valid type information
		 */
		private function hasValidTypeDefinitions(array $propertyDef, array $columnDef): bool {
			return isset($propertyDef['type']) && isset($columnDef['type']);
		}
		
		/**
		 * Compare and normalize column types
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @param array &$differences Reference to differences array
		 */
		private function compareTypes(array $propertyDef, array $columnDef, array &$differences): void {
			$propertyType = is_string($propertyDef['type']) ? strtolower(trim($propertyDef['type'])) : '';
			$columnType = is_string($columnDef['type']) ? strtolower(trim($columnDef['type'])) : '';
			
			// Normalize types for proper comparison
			$propertyType = $this->normalizeColumnType($propertyType);
			$columnType = $this->normalizeColumnType($columnType);
			
			if ($propertyType !== $columnType && !$this->isTypeEquivalent($propertyType, $columnType, $columnDef)) {
				$differences['type'] = [
					'from' => $columnType,
					'to'   => $propertyType
				];
			}
		}
		
		/**
		 * Check if types are equivalent (handles special cases like boolean/tinyint)
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
			$propertyType = $this->normalizeColumnType($propertyDef['type']);
			
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
		 *
		 * @param string $type Normalized column type
		 * @return bool True if decimal type
		 */
		private function isDecimalType(string $type): bool {
			return in_array($type, ['decimal', 'numeric']);
		}
		
		/**
		 * Check if column type's length is significant for comparison
		 *
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
			$propertyType = $this->normalizeColumnType($propertyDef['type'] ?? '');
			
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
		
		/**
		 * Generate Phinx migration file
		 * @param array $allChanges Changes for all tables
		 * @return bool Success status
		 */
		private function generateMigrationFile(array $allChanges): bool {
			if (empty($allChanges)) {
				$this->output->writeLn("No changes detected. Migration file not created.");
				return false;
			}
			
			$timestamp = time();
			$migrationName = 'EntitySchemaMigration' . date('YmdHis', $timestamp);
			$className = 'Migration' . date('YmdHis', $timestamp);
			$filename = $this->migrationsPath . '/' . date('YmdHis', $timestamp) . '_' . $migrationName . '.php';
			
			$migrationContent = $this->buildMigrationContent($className, $allChanges);
			
			if (!is_dir($this->migrationsPath)) {
				mkdir($this->migrationsPath, 0755, true);
			}
			
			if (file_put_contents($filename, $migrationContent)) {
				$this->output->writeLn("Migration file created: $filename");
				return true;
			}
			
			$this->output->writeLn("Failed to create migration file.");
			return false;
		}
		
		/**
		 * Build the content of the migration file
		 * @param string $className Migration class name
		 * @param array $allChanges Changes for all tables
		 * @return string Migration file content
		 */
		private function buildMigrationContent(string $className, array $allChanges): string {
			$upMethod = [];
			$downMethod = [];
			
			foreach ($allChanges as $tableName => $changes) {
				// Add table if it doesn't exist
				if (!empty($changes['table_not_exists'])) {
					$upMethod[] = $this->buildCreateTableCode($tableName, $changes['added']);
					$downMethod[] = "        \$this->table('$tableName')->drop()->save();";
					continue;
				}
				
				// Add columns
				if (!empty($changes['added'])) {
					$upMethod[] = $this->buildAddColumnsCode($tableName, $changes['added']);
					$downMethod[] = $this->buildRemoveColumnsCode($tableName, $changes['added']);
				}
				
				// Modify columns
				if (!empty($changes['modified'])) {
					$upMethod[] = $this->buildModifyColumnsCode($tableName, $changes['modified']);
					$downMethod[] = $this->buildReverseModifyColumnsCode($tableName, $changes['modified']);
				}
				
				// Remove columns
				if (!empty($changes['deleted'])) {
					$upMethod[] = $this->buildRemoveColumnsCode($tableName, $changes['deleted']);
					$downMethod[] = $this->buildAddColumnsCode($tableName, $changes['deleted'], true);
				}
			}
			
			$upMethodContent = implode("\n\n", $upMethod);
			$downMethodContent = implode("\n\n", $downMethod);
			
			return <<<PHP
<?php

use Phinx\Migration\AbstractMigration;

class $className extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up()
    {
$upMethodContent
    }

    public function down()
    {
$downMethodContent
    }
}
PHP;
		}
		
		/**
		 * Build code for creating a new table
		 * @param string $tableName Table name
		 * @param array $columns Column definitions
		 * @return string Code for creating table
		 */
		private function buildCreateTableCode(string $tableName, array $columns): string {
			$columnDefs = [];
			
			foreach ($columns as $columnName => $columnDef) {
				// Map the entity type to a valid Phinx type
				$type = $this->mapToPhinxType($columnDef['type']);
				$options = [];
				
				if (!empty($columnDef['length'])) {
					$options[] = "'limit' => " . $this->formatValue($columnDef['length']);
				}
				
				if (isset($columnDef['nullable'])) {
					$options[] = "'null' => " . ($columnDef['nullable'] ? 'true' : 'false');
				}
				
				if (isset($columnDef['default']) && $columnDef['default'] !== null) {
					$options[] = "'default' => " . $this->formatValue($columnDef['default']);
				}
				
				if (!empty($columnDef['primary_key'])) {
					$options[] = "'primary' => true";
				}
				
				if (!empty($columnDef['auto_increment'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->create();";
		}
		
		/**
		 * Build code for adding columns to a table
		 * @param string $tableName Table name
		 * @param array $columns Column definitions
		 * @param bool $isRollback Whether this is for rollback code
		 * @return string Code for adding columns
		 */
		private function buildAddColumnsCode(string $tableName, array $columns, bool $isRollback = false): string {
			$columnDefs = [];
			
			foreach ($columns as $columnName => $columnDef) {
				$type = $this->mapToPhinxType($columnDef['type']);
				$options = [];
				
				if (!empty($columnDef['length'])) {
					$options[] = "'limit' => " . $this->formatValue($columnDef['length']);
				}
				
				if (isset($columnDef['nullable'])) {
					$options[] = "'null' => " . ($columnDef['nullable'] ? 'true' : 'false');
				}
				
				if (isset($columnDef['default']) && $columnDef['default'] !== null) {
					$options[] = "'default' => " . $this->formatValue($columnDef['default']);
				}
				
				if (!empty($columnDef['primary_key'])) {
					$options[] = "'primary' => true";
				}
				
				if (!empty($columnDef['auto_increment'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->update();";
		}
		
		/**
		 * Build code for removing columns from a table
		 * @param string $tableName Table name
		 * @param array $columns Column definitions
		 * @return string Code for removing columns
		 */
		private function buildRemoveColumnsCode(string $tableName, array $columns): string {
			$columnDefs = [];
			
			foreach ($columns as $columnName => $columnDef) {
				$columnDefs[] = "            ->removeColumn('$columnName')";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->update();";
		}
		
		/**
		 * Build code for modifying columns in a table
		 * @param string $tableName Table name
		 * @param array $modifiedColumns Modified column definitions
		 * @return string Code for modifying columns
		 */
		private function buildModifyColumnsCode(string $tableName, array $modifiedColumns): string {
			$columnDefs = [];
			
			foreach ($modifiedColumns as $columnName => $changes) {
				$propertyDef = $changes['property'];
				$type = $this->mapToPhinxType($propertyDef['type']);
				$options = [];
				
				if (!empty($propertyDef['length'])) {
					$options[] = "'limit' => " . $this->formatValue($propertyDef['length']);
				}
				
				if (isset($propertyDef['nullable'])) {
					$options[] = "'null' => " . ($propertyDef['nullable'] ? 'true' : 'false');
				}
				
				if (isset($propertyDef['default']) && $propertyDef['default'] !== null) {
					$options[] = "'default' => " . $this->formatValue($propertyDef['default']);
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->changeColumn('$columnName', '$type'$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->update();";
		}
		
		/**
		 * Build code for reversing column modifications
		 * @param string $tableName Table name
		 * @param array $modifiedColumns Modified column definitions
		 * @return string Code for reversing column modifications
		 */
		private function buildReverseModifyColumnsCode(string $tableName, array $modifiedColumns): string {
			$columnDefs = [];
			
			foreach ($modifiedColumns as $columnName => $changes) {
				$columnDef = $changes['column'];
				$type = $this->mapToPhinxType($columnDef['type']);
				$options = [];
				
				if (!empty($columnDef['size'])) {
					$options[] = "'limit' => " . $this->formatValue($columnDef['size']);
				}
				
				if (isset($columnDef['nullable'])) {
					$options[] = "'null' => " . ($columnDef['nullable'] ? 'true' : 'false');
				}
				
				if (isset($columnDef['default']) && $columnDef['default'] !== null) {
					$options[] = "'default' => " . $this->formatValue($columnDef['default']);
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->changeColumn('$columnName', '$type'$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->update();";
		}
		
		/**
		 * Format a value for inclusion in PHP code
		 * @param mixed $value The value to format
		 * @return string Formatted value
		 */
		private function formatValue($value): string {
			if (is_null($value)) {
				return 'null';
			} elseif (is_bool($value)) {
				return $value ? 'true' : 'false';
			} elseif (is_int($value) || is_float($value)) {
				return (string)$value;
			} else {
				return "'" . addslashes($value) . "'";
			}
		}
		
		/**
		 * Map entity data type to Phinx data type
		 * @param string $type Entity data type
		 * @return string Phinx data type
		 */
		private function mapToPhinxType(string $type): string {
			$map = [
				'int'        => 'integer',
				'integer'    => 'integer',
				'tinyint'    => 'boolean',
				'smallint'   => 'integer',
				'mediumint'  => 'integer',
				'bigint'     => 'biginteger',
				'float'      => 'float',
				'double'     => 'double',
				'decimal'    => 'decimal',
				'char'       => 'char',
				'varchar'    => 'string',
				'text'       => 'text',
				'mediumtext' => 'text',
				'longtext'   => 'text',
				'date'       => 'date',
				'datetime'   => 'datetime',
				'timestamp'  => 'timestamp',
				'time'       => 'time',
				'enum'       => 'enum'
			];
			
			return $map[strtolower($type)] ?? 'string';
		}
		
		/**
		 * Execute the command
		 * @param array $parameters Optional parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(array $parameters = []): int {
			$this->output->writeLn("Generating database migrations based on entity changes...");
			
			// Load all entity classes
			$this->entityClasses = $this->loadEntityClasses();
			
			if (empty($this->entityClasses)) {
				$this->output->writeLn("No entity classes found.");
				return 1;
			}
			
			// Get existing tables from database
			$existingTables = $this->tableInfo->getTables();
			$allChanges = [];
			
			// Process each entity
			foreach ($this->entityClasses as $className => $tableName) {
				$entityProperties = $this->getEntityProperties($className);
				
				// Check if table exists
				if (!in_array($tableName, $existingTables)) {
					$this->output->writeLn("Table '$tableName' does not exist. Will be created.");
					
					$allChanges[$tableName] = [
						'table_not_exists' => true,
						'added'            => $entityProperties
					];
					
					continue;
				}
				
				// Get table definition from database
				$tableColumns = $this->getTableDefinition($tableName);
				
				// Compare entity properties with table columns
				$changes = $this->compareColumns($entityProperties, $tableColumns);
				
				if (!empty($changes['added']) || !empty($changes['modified']) || !empty($changes['deleted'])) {
					$allChanges[$tableName] = $changes;
					
					// Log the changes
					if (!empty($changes['added'])) {
						$this->output->writeLn("  Added columns: " . implode(', ', array_keys($changes['added'])));
					}
					
					if (!empty($changes['modified'])) {
						$this->output->writeLn("  Modified columns: " . implode(', ', array_keys($changes['modified'])));
					}
					
					if (!empty($changes['deleted'])) {
						$this->output->writeLn("  Deleted columns: " . implode(', ', array_keys($changes['deleted'])));
					}
				} else {
					$this->output->writeLn("  No changes detected for table: $tableName");
				}
			}
			
			// Only generate migration file if there are changes
			if (empty($allChanges)) {
				$this->output->writeLn("No changes detected in any entities. Migration file not created.");
				return 0;
			}
			
			// Generate migration file
			$success = $this->generateMigrationFile($allChanges);
			
			return $success ? 0 : 1;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public static function getSignature(): string {
			return "make:migrations";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public static function getDescription(): string {
			return "Generate database migrations based on entity changes";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public static function getHelp(): string {
			return "Creates a new database migration file by comparing entity definitions with current database schema to synchronize changes.";
		}
	}