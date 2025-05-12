<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Commands;
	
	/**
	 * Import required classes for migration generation and entity analysis
	 */
	
	use Phinx\Db\Adapter\AdapterInterface;
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\ObjectQuel\Annotations\Orm\PrimaryKeyStrategy;
	use Quellabs\ObjectQuel\CommandRunner\Command;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleInput;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleOutput;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\EntityScanner;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\SchemaComparator;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\DatabaseAdapter\TableInfo;
	use Quellabs\ObjectQuel\Annotations\Orm\Column;
	use Quellabs\ObjectQuel\DatabaseAdapter\TypeMapper;
	
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
		private EntityScanner $entityScanner;
		private SchemaComparator $schemaComparator;
		private TypeMapper $phinxTypeMapper;
		
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
			$annotationReaderConfiguration->setUseAnnotationCache($configuration->useMetadataCache());
			$annotationReaderConfiguration->setAnnotationCachePath($configuration->getMetadataCachePath());
			
			$this->connection = new DatabaseAdapter($configuration);
			$this->tableInfo = new TableInfo($this->connection);
			$this->entityPath = $configuration->getEntityPath();
			$this->migrationsPath = $configuration->getMigrationsPath();
			$this->annotationReader = new AnnotationReader($annotationReaderConfiguration);
			$this->entityScanner = new EntityScanner($this->entityPath, $this->annotationReader);
			$this->schemaComparator = new SchemaComparator();
			$this->phinxTypeMapper = new TypeMapper();
		}
		
		/**
		 * Execute the command
		 * @param array $parameters Optional parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(array $parameters = []): int {
			$this->output->writeLn("Generating database migrations based on entity changes...");
			
			// Load all entity classes
			$entityClasses = $this->entityScanner->scanEntities();
			
			if (empty($entityClasses)) {
				$this->output->writeLn("No entity classes found.");
				return 1;
			}
			
			// Get existing tables from database
			$existingTables = $this->tableInfo->getTables();
			
			// Process each entity
			$allChanges = [];
			
			foreach ($entityClasses as $className => $tableName) {
				$entityProperties = $this->extractEntityColumnDefinitions($className);
				
				// Check if table exists
				if (!in_array($tableName, $existingTables)) {
					$allChanges[$tableName] = [
						'table_not_exists' => true,
						'added'            => $entityProperties
					];
					
					continue;
				}
				
				// Get table definition from database
				$phinxAdapter = $this->connection->getPhinxAdapter();
				$tableColumns = $this->getColumns($phinxAdapter, $tableName);
				
				// Compare entity properties with table columns
				$changes = $this->schemaComparator->analyzeSchemaChanges($entityProperties, $tableColumns);
				
				if (!empty($changes['added']) || !empty($changes['modified']) || !empty($changes['deleted'])) {
					$allChanges[$tableName] = $changes;
				}
			}
			
			// Generate migration file
			$success = $this->generateMigrationFile($allChanges);
			
			return $success ? 0 : 1;
		}
		
		/**
		 * Determines if a property represents an auto-increment column.
		 * A column is considered auto-increment if it has both:
		 * 1. A Column annotation marked as primary key
		 * 2. A PrimaryKeyStrategy annotation with value 'identity'
		 * @param array $propertyAnnotations The annotations attached to the property
		 * @return bool Returns true if the property is an auto-increment column, false otherwise
		 */
		private function isIdentityColumn(array $propertyAnnotations): bool {
			// First condition: verify the property has a Column annotation that is a primary key
			// If any Column annotation is not a primary key, return false immediately
			foreach ($propertyAnnotations as $annotation) {
				if ($annotation instanceof Column) {
					if (!$annotation->isPrimaryKey()) {
						// If the property has a Column annotation, but it's not a primary key,
						// then it cannot be an auto-increment column
						return false;
					}
				}
			}
			
			// Second condition: verify the property has a PrimaryKeyStrategy annotation with 'identity' value
			// If any PrimaryKeyStrategy annotation does not have 'identity' value, return false immediately
			foreach ($propertyAnnotations as $annotation) {
				if ($annotation instanceof PrimaryKeyStrategy) {
					if ($annotation->getValue() !== 'identity') {
						return false;
					}
				}
			}
			
			// If we reach this point, both conditions are satisfied (or no relevant annotations were found)
			// Note: This assumes that at least one Column and one PrimaryKeyStrategy annotation exist
			return true;
		}
		
		/**
		 * Get entity property definitions from annotations
		 * @param string $className Entity class name
		 * @return array Array of property definitions
		 */
		private function extractEntityColumnDefinitions(string $className): array {
			$result = [];
			
			try {
				$reflection = new \ReflectionClass($className);
				
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
						$result[$columnName] = [
							'property_name'  => $property->getName(),
							'type'           => $columnAnnotation->getType(),
							'limit'          => $columnAnnotation->getLimit(),
							'nullable'       => $columnAnnotation->isNullable(),
							'unsigned'       => $columnAnnotation->isUnsigned(),
							'default'        => $columnAnnotation->getDefault(),
							'primary_key'    => $columnAnnotation->isPrimaryKey(),
							'scale'          => $columnAnnotation->getScale(),
							'precision'      => $columnAnnotation->getPrecision(),
							'identity'       => $this->isIdentityColumn($propertyAnnotations),
						];
					}
				}
			} catch (\ReflectionException $e) {
			}
			
			return $result;
		}
		
		/**
		 * Generate Phinx migration file
		 * @param array $allChanges Changes for all tables
		 * @return bool Success status
		 */
		private function generateMigrationFile(array $allChanges): bool {
			// If no changes were detected, inform the user and exit early
			if (empty($allChanges)) {
				$this->output->writeLn("No changes detected. Migration file not created.");
				return false;
			}
			
			// Create timestamp and name components for the migration file
			$timestamp = time();
			$migrationName = 'EntitySchemaMigration' . date('YmdHis', $timestamp);
			$className = 'Migration' . date('YmdHis', $timestamp);
			
			// Construct the full filepath for the migration
			$filename = $this->migrationsPath . '/' . date('YmdHis', $timestamp) . '_' . $migrationName . '.php';
			
			// Generate the PHP code content for the migration file
			$migrationContent = $this->buildMigrationContent($className, $allChanges);
			
			// Create migrations directory if it doesn't exist
			if (!is_dir($this->migrationsPath)) {
				mkdir($this->migrationsPath, 0755, true);
			}
			
			// Write the migration file and provide feedback on success/failure
			if (file_put_contents($filename, $migrationContent)) {
				$this->output->writeLn("Migration file created: $filename");
				return true;
			}
			
			// If file writing failed, inform the user
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
    public function up(): void {
$upMethodContent
    }

    public function down(): void {
$downMethodContent
    }
}
PHP;
		}
		
		/**
		 * Build code for creating a new table
		 * @param string $tableName Table name
		 * @param array $entityColumns Column definitions
		 * @return string Code for creating table
		 */
		private function buildCreateTableCode(string $tableName, array $entityColumns): string {
			$columnDefs = [];
			
			foreach ($entityColumns as $columnName => $definition) {
				// Map the entity type to a valid Phinx type
				$type = $definition['type'];
				$options = $this->buildColumnOptions($definition);
				
				if (!empty($definition['primary_key'])) {
					$options[] = "'primary' => true";
				}
				
				if (!empty($definition['identity'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			// Add an index for auto-increment primary key columns
			foreach ($entityColumns as $columnName => $columnDef) {
				if ($columnDef['primary_key'] && $columnDef['identity']) {
					$columnDefs[] = "            ->addIndex(['$columnName'], ['unique' => true, 'name' => 'primary_$columnName'])";
				}
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->create();";
		}
		
		/**
		 * Build code for adding columns to a table
		 * @param string $tableName Table name
		 * @param array $entityColumns Column definitions
		 * @return string Code for adding columns
		 */
		private function buildAddColumnsCode(string $tableName, array $entityColumns): string {
			$result = [];
			
			foreach ($entityColumns as $columnName => $columnDef) {
				$type = $columnDef['type'];
				$options = $this->buildColumnOptions($columnDef);
				
				if (!empty($columnDef['primary_key'])) {
					$options[] = "'primary' => true";
				}
				
				if (!empty($columnDef['identity'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$result[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			// Add an index for auto-increment primary key columns
			foreach ($entityColumns as $columnName => $columnDef) {
				if ($columnDef['primary_key'] && $columnDef['identity']) {
					$result[] = "            ->addIndex(['$columnName'], ['unique' => true, 'name' => 'primary_$columnName'])";
				}
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $result) . "\n            ->update();";
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
		 * Generate column options array for Phinx migrations
		 *
		 * Takes a property definition array and extracts relevant options like:
		 * - limit (column size)
		 * - default value
		 * - nullability
		 * - precision/scale (for decimal types)
		 * - signed/unsigned status
		 *
		 * @param array $propertyDef The property definition containing column attributes
		 * @return array Formatted column options for Phinx migration
		 */
		private function buildColumnOptions(array $propertyDef): array {
			$result = [];
			
			// Set maximum column size (e.g., VARCHAR length)
			if (!empty($propertyDef['limit'])) {
				$result[] = "'limit' => " . $this->phinxTypeMapper->formatValue($propertyDef['limit']);
			}
			
			// Set default value if specified and not null
			if (!empty($propertyDef['default']) && $propertyDef['default'] !== null) {
				$result[] = "'default' => " . $this->phinxTypeMapper->formatValue($propertyDef['default']);
			}
			
			// Set whether column allows NULL values
			if (!empty($propertyDef['nullable'])) {
				$result[] = "'null' => " . ($propertyDef['nullable'] ? 'true' : 'false');
			}
			
			// Set precision for numeric types (total digits)
			if (!empty($propertyDef['precision'])) { // Note: Fixed variable name from $definition to $propertyDef
				$result[] = "'precision' => " . $propertyDef['precision']; // Fixed variable name
			}
			
			// Set scale for decimal types (digits after decimal point)
			if (!empty($propertyDef['scale'])) { // Note: Fixed variable name from $definition to $propertyDef
				$result[] = "'scale' => " . $propertyDef['scale']; // Fixed variable name
			}
			
			// Set whether column is signed or unsigned
			if (isset($propertyDef['unsigned'])) {
				$result[] = "'signed' => " . ($propertyDef['unsigned'] ? 'false' : 'true');
			}
			
			return $result;
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
				$type = $changes['to']['type'];
				$options = $this->buildColumnOptions($changes['to']); // Updated function name
				
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
				$type = $changes['from']['type'];
				$options = $this->buildColumnOptions($changes['from']); // Updated function name
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->changeColumn('$columnName', '$type'$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $columnDefs) . "\n            ->update();";
		}
		
		/**
		 * Retrieves and formats column definitions from the database table
		 * @param AdapterInterface $phinxAdapter The Phinx database adapter
		 * @param string $tableName Name of the table to analyze
		 * @return array Associative array of column definitions indexed by column name
		 */
		protected function getColumns(AdapterInterface $phinxAdapter, string $tableName): array {
			$result = [];
			
			// Get primary key columns first so we can mark them in column definitions
			$primaryKey = $phinxAdapter->getPrimaryKey($tableName);
			
			// Fetch and process each column in the table
			foreach ($phinxAdapter->getColumns($tableName) as $column) {
				$result[$column->getName()] = [
					// Basic column type (integer, string, decimal, etc.)
					'type'           => $column->getType(),
					
					// Maximum length for string types or display width for numeric types
					'limit'          => $column->getLimit(),
					
					// Default value for the column if not specified during insert
					'default'        => $column->getDefault(),
					
					// Whether NULL values are allowed in this column
					'nullable'       => $column->getNull(),
					
					// For numeric types: total number of digits (precision)
					'precision'      => $column->getPrecision(),
					
					// For decimal types: number of digits after decimal point
					'scale'          => $column->getScale(),
					
					// Whether column allows negative values (converted from signed to unsigned)
					'unsigned'       => !$column->getSigned(),
					
					// For generated columns (computed values based on expressions)
					'generated'      => $column->getGenerated(),
					
					// Whether column auto-increments (typically for primary keys)
					'identity'       => $column->getIdentity(),
					
					// Whether this column is part of the primary key
					'primary_key'    => in_array($column->getName(), $primaryKey),
				];
			}
			
			return $result;
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