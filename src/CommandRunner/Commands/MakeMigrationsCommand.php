<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Commands;
	
	/**
	 * Import required classes for migration generation and entity analysis
	 */
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\ObjectQuel\CommandRunner\Command;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleInput;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleOutput;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\DatabaseSchemaLoader;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\EntityScanner;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\SchemaComparator;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\DatabaseAdapter\TableInfo;
	use Quellabs\ObjectQuel\Annotations\Orm\Column;
	
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
		private DatabaseSchemaLoader $databaseSchemaLoader;
		private SchemaComparator $schemaComparator;
		
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
			$this->entityScanner = new EntityScanner($this->entityPath, $this->annotationReader);
			$this->databaseSchemaLoader = new DatabaseSchemaLoader($this->connection);
			$this->schemaComparator = new SchemaComparator();
		}
		
		/**
		 * Get entity property definitions from annotations
		 * @param string $className Entity class name
		 * @return array Array of property definitions
		 */
		private function extractEntityColumnDefinitions(string $className): array {
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
			$entityClasses = $this->entityScanner->scanEntities();
			
			if (empty($entityClasses)) {
				$this->output->writeLn("No entity classes found.");
				return 1;
			}
			
			// Get existing tables from database
			$existingTables = $this->tableInfo->getTables();
			$allChanges = [];
			
			// Process each entity
			foreach ($entityClasses as $className => $tableName) {
				$entityProperties = $this->extractEntityColumnDefinitions($className);
				
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
				$tableColumns = $this->databaseSchemaLoader->fetchDatabaseTableSchema($tableName);
				
				// Compare entity properties with table columns
				$changes = $this->schemaComparator->compareColumns($entityProperties, $tableColumns);
				
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