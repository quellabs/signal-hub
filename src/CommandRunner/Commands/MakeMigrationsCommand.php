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
		 * Convert a string to camelcase
		 * @param string $input
		 * @param string $separator
		 * @return string
		 */
		private function camelCase(string $input, string $separator = '_'): string {
			$array = explode($separator, $input);
			$parts = array_map('ucfirst', $array);
			return implode('', $parts);
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
			$reflection = new ReflectionClass($className);
			$properties = [];
			
			foreach ($reflection->getProperties() as $property) {
				$propertyAnnotations = $this->annotationReader->getPropertyAnnotations($className, $property->getName());
				$columnAnnotation = null;
				
				// Look for Column annotation
				foreach ($propertyAnnotations as $annotation) {
					if ($annotation instanceof Column) {
						$columnAnnotation = $annotation;
						break;
					}
				}
				
				if ($columnAnnotation) {
					// Extract property type from PHP property type hint if available
					$phpTypeHint = null;
					if ($property->hasType()) {
						$phpType = $property->getType();
						if (!$phpType->isBuiltin()) {
							// For non-builtin types (like DateTime), use the class name
							$phpTypeHint = $phpType->getName();
							$parts = explode('\\', $phpTypeHint);
							$phpTypeHint = end($parts);
						} else {
							$phpTypeHint = $phpType->getName();
						}
					}
					
					// IMPORTANT: Use the column name from the annotation, not the property name
					$columnName = $columnAnnotation->getName();
					
					if (empty($columnName)) {
						// Fallback only if name is explicitly empty
						$columnName = $this->snakeCase($property->getName());
					}
					
					$properties[$columnName] = [
						'property_name'  => $property->getName(),
						'name'           => $columnName,
						'type'           => $columnAnnotation->getType(),
						'length'         => $columnAnnotation->getLength() ?? null,
						'nullable'       => $columnAnnotation->isNullable() ?? false,
						'default'        => $columnAnnotation->getDefault() ?? null,
						'primary_key'    => $columnAnnotation->isPrimaryKey() ?? false,
						'auto_increment' => $columnAnnotation->isAutoIncrement() ?? false,
						'php_type'       => $phpTypeHint,
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
		 * Compare entity properties with database columns
		 * @param array $entityProperties Entity property definitions
		 * @param array $tableColumns Database table column definitions
		 * @return array Changes needed (added, modified, deleted columns)
		 */
		private function compareColumns(array $entityProperties, array $tableColumns): array {
			$changes = [
				'added' => [],
				'modified' => [],
				'deleted' => []
			];
			
			// Check for added or modified columns
			foreach ($entityProperties as $columnName => $propertyDef) {
				// Use the actual column name from the annotation
				if (!isset($tableColumns[$columnName])) {
					$changes['added'][$columnName] = $propertyDef;
				} else {
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
			
			// Check for deleted columns
			foreach ($tableColumns as $columnName => $columnDef) {
				if (!isset($entityProperties[$columnName])) {
					$changes['deleted'][$columnName] = $columnDef;
				}
			}
			
			return $changes;
		}
		
		/**
		 * Compare entity property definition with database column definition
		 * @param array $propertyDef Entity property definition
		 * @param array $columnDef Database column definition
		 * @return array Array of differences
		 */
		private function compareColumnDefinitions(array $propertyDef, array $columnDef): array {
			$differences = [];
			
			// Skip comparison if the column exists in a schema level but not an entity level
			if (!isset($propertyDef['type']) || !isset($columnDef['type'])) {
				return $differences;
			}
			
			// Compare type - both values must be string
			$propertyType = is_string($propertyDef['type']) ? strtolower(trim($propertyDef['type'])) : '';
			$columnType = is_string($columnDef['type']) ? strtolower(trim($columnDef['type'])) : '';
			
			// Normalize types for proper comparison
			$propertyType = $this->normalizeColumnType($propertyType);
			$columnType = $this->normalizeColumnType($columnType);
			
			if ($propertyType !== $columnType) {
				// Special case for tinyint(1) which is often used as boolean
				if ($columnType === 'tinyint' && $columnDef['size'] === '1' && $propertyType === 'boolean') {
					// This is fine, don't mark as different
				} else {
					$differences['type'] = [
						'from' => $columnType,
						'to' => $propertyType
					];
				}
			}
			
			// Compare length - only if types are compatible
			if (empty($differences['type']) && $propertyDef['length'] !== null && $columnDef['size'] !== null) {
				// Handle special case for decimal/numeric types with precision and scale
				if (in_array($propertyType, ['decimal', 'numeric']) && strpos($propertyDef['length'], ',') !== false && strpos($columnDef['size'], ',') !== false) {
					list($propertyPrecision, $propertyScale) = explode(',', $propertyDef['length']);
					list($columnPrecision, $columnScale) = explode(',', $columnDef['size']);
					
					if (trim($propertyPrecision) != trim($columnPrecision) || trim($propertyScale) != trim($columnScale)) {
						$differences['length'] = [
							'from' => $columnDef['size'],
							'to' => $propertyDef['length']
						];
					}
				}
				// For standard length types
				elseif ($propertyDef['length'] != $columnDef['size']) {
					// Exclude unimportant differences (like int(11) vs int(10))
					$lengthMatters = in_array($propertyType, ['varchar', 'char', 'binary', 'varbinary']);
					if ($lengthMatters) {
						$differences['length'] = [
							'from' => $columnDef['size'],
							'to' => $propertyDef['length']
						];
					}
				}
			}
			
			// Compare nullability - only if we have values to compare
			if (isset($propertyDef['nullable']) && isset($columnDef['nullable']) &&
				$propertyDef['nullable'] !== $columnDef['nullable']) {
				$differences['nullable'] = [
					'from' => $columnDef['nullable'],
					'to' => $propertyDef['nullable']
				];
			}
			
			// Compare default value
			$propDefault = isset($propertyDef['default']) ? $this->normalizeDefaultValue($propertyDef['default']) : null;
			$colDefault = isset($columnDef['default']) ? $this->normalizeDefaultValue($columnDef['default']) : null;
			
			if ($propDefault !== $colDefault) {
				// Handle special cases for default values
				// For datetime/timestamp fields, CURRENT_TIMESTAMP is equivalent to NULL in many cases
				if (in_array($propertyType, ['datetime', 'timestamp'])) {
					$isCurrentTs = ($propDefault === 'CURRENT_TIMESTAMP' || $colDefault === 'CURRENT_TIMESTAMP');
					$isNull = ($propDefault === null || $colDefault === null);
					
					if (!($isCurrentTs && $isNull)) {
						$differences['default'] = [
							'from' => $columnDef['default'],
							'to' => $propertyDef['default']
						];
					}
				} else {
					$differences['default'] = [
						'from' => $columnDef['default'],
						'to' => $propertyDef['default']
					];
				}
			}
			
			return $differences;
		}
		
		/**
		 * Normalize column type for consistent comparison
		 * @param string $type Column type
		 * @return string Normalized type
		 */
		private function normalizeColumnType(string $type): string {
			// Map of equivalent types
			$typeMap = [
				'int' => 'int',
				'integer' => 'int',
				'smallint' => 'smallint',
				'tinyint' => 'tinyint',
				'mediumint' => 'mediumint',
				'bigint' => 'bigint',
				'decimal' => 'decimal',
				'numeric' => 'decimal',
				'float' => 'float',
				'double' => 'double',
				'char' => 'char',
				'varchar' => 'varchar',
				'text' => 'text',
				'mediumtext' => 'mediumtext',
				'longtext' => 'longtext',
				'date' => 'date',
				'datetime' => 'datetime',
				'timestamp' => 'timestamp'
			];
			
			return $typeMap[trim($type)] ?? trim($type);
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
		 * Debug function to print an entity property for inspection
		 * @param array $property The property details to print
		 * @return void
		 */
		private function debugProperty(array $property): void {
			$this->output->writeLn("Property Debug:");
			$this->output->writeLn("- Name: " . $property['name']);
			$this->output->writeLn("- Type: " . $property['type']);
			$this->output->writeLn("- Property Name: " . $property['property_name']);
		}
		
		/**
		 * Execute the command
		 * @param array $parameters Optional parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(array $parameters = []): int {
			$this->output->writeLn("Generating database migrations based on entity changes...");
			
			// Debug mode flag - enable to see verbose output
			$debug = false;
			if (isset($parameters['debug']) && $parameters['debug']) {
				$debug = true;
			}
			
			// Load all entity classes
			$this->entityClasses = $this->loadEntityClasses();
			
			if (empty($this->entityClasses)) {
				$this->output->writeLn("No entity classes found. Migration not created.");
				return 1;
			}
			
			// Get existing tables from database
			$existingTables = $this->tableInfo->getTables();
			$allChanges = [];
			
			// Process each entity
			foreach ($this->entityClasses as $className => $tableName) {
				$this->output->writeLn("Processing entity: $className -> $tableName");
				
				$entityProperties = $this->getEntityProperties($className);
				
				if ($debug) {
					$this->output->writeLn("Entity properties for $className:");
					foreach ($entityProperties as $columnName => $property) {
						$this->debugProperty($property);
					}
				}
				
				// Check if table exists
				if (!in_array($tableName, $existingTables)) {
					$this->output->writeLn("Table '$tableName' does not exist. Will be created.");
					$allChanges[$tableName] = [
						'table_not_exists' => true,
						'added' => $entityProperties
					];
					continue;
				}
				
				// Get table definition from database
				$tableColumns = $this->getTableDefinition($tableName);
				
				if ($debug) {
					$this->output->writeLn("Database columns for $tableName:");
					foreach ($tableColumns as $columnName => $column) {
						$this->output->writeLn("- Column: $columnName, Type: {$column['type']}");
					}
				}
				
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