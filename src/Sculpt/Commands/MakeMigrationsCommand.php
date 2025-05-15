<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Commands;
	
	/**
	 * Import required classes for migration generation and entity analysis
	 */
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\DatabaseAdapter\TypeMapper;
	use Quellabs\ObjectQuel\EntityStore;
	use Quellabs\ObjectQuel\OrmException;
	use Quellabs\ObjectQuel\Sculpt\Helpers\EntityScanner;
	use Quellabs\ObjectQuel\Sculpt\Helpers\IndexComparator;
	use Quellabs\ObjectQuel\Sculpt\Helpers\SchemaComparator;
	use Quellabs\Sculpt\CommandBase;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	use Quellabs\Sculpt\Contracts\ServiceProviderInterface;
	
	/**
	 * MakeMigration - CLI command for generating database migrations
	 *
	 * This command analyzes differences between entity definitions and database schema,
	 * then creates migration files to synchronize the database with entity changes.
	 * It tracks added, modified, or removed fields and relationships to generate
	 * the appropriate SQL commands for schema updates.
	 */
	class MakeMigrationsCommand extends CommandBase {
		private ?DatabaseAdapter $connection = null;
		private ?AnnotationReader $annotationReader = null;
		private ?EntityStore $entityStore = null;
		private string $entityPath;
		private string $migrationsPath;
		private TypeMapper $phinxTypeMapper;
		private Configuration $configuration;
		
		/**
		 * MakeEntityCommand constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param ServiceProviderInterface|null $provider
		 * @throws OrmException
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ServiceProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);
			$this->configuration = $provider->getConfiguration();
			
			$this->entityPath = $this->configuration->getEntityPath();
			$this->migrationsPath = $this->configuration->getMigrationsPath();
			$this->phinxTypeMapper = new TypeMapper();
		}
		
		/**
		 * Execute the command
		 * @param ConfigurationManager $config Parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(ConfigurationManager $config): int {
			$this->output->writeLn("Generating database migrations based on entity changes...");
			
			// Load all entity classes
			$entityScanner = new EntityScanner($this->entityPath, $this->getAnnotationReader());
			$entityClasses = $entityScanner->scanEntities();
			
			if (empty($entityClasses)) {
				$this->output->writeLn("No entity classes found.");
				return 1;
			}
			
			// Get existing tables from database
			$existingTables = $this->getConnection()->getTables();

			// Initialize IndexComparator
			$indexComparator = new IndexComparator($this->getConnection(), $this->getEntityStore());

			// Process each entity
			$allChanges = [];
			
			foreach ($entityClasses as $className => $tableName) {
				$entityProperties = $this->getEntityStore()->extractEntityColumnDefinitions($className);
				
				// Check if table exists
				if (!in_array($tableName, $existingTables)) {
					$allChanges[$tableName] = [
						'table_not_exists' => true,
						'added'            => $entityProperties,
						'indexes'          => $indexComparator->getEntityIndexes($className)
					];
					
					continue;
				}
				
				// Get table definition from database
				$tableColumns = $this->getConnection()->getColumns($tableName);
				
				// Compare entity properties with table columns
				$schemaComparator = new SchemaComparator();
				$changes = $schemaComparator->analyzeSchemaChanges($entityProperties, $tableColumns);

				// Compare indexes between entity and database
				$indexChanges = $indexComparator->compareIndexes($className);

				// First, always store the schema changes (even if empty)
				$allChanges[$tableName] = $changes;

				// Then add the index changes regardless of whether there were schema changes
				$allChanges[$tableName]['indexes'] = $indexChanges;

				// Finally, if there are no changes at all (neither schema nor indexes), remove this table from changes
				if (
					empty($changes['added']) && empty($changes['modified']) && empty($changes['deleted']) &&
					empty($indexChanges['added']) && empty($indexChanges['modified']) && empty($indexChanges['deleted'])
				) {
					
					unset($allChanges[$tableName]);
				}
			}
			
			// Generate migration file
			$success = $this->generateMigrationFile($allChanges);
			
			return $success ? 0 : 1;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public function getSignature(): string {
			return "make:migrations";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public function getDescription(): string {
			return "Generate database migrations based on entity changes";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public function getHelp(): string {
			return "Creates a new database migration file by comparing entity definitions with current database schema to synchronize changes.";
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
			$className = 'EntitySchemaMigration' . date('YmdHis', $timestamp);
			
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
					$upMethod[] = $this->buildCreateTableCode($tableName, $changes['added'], $changes['indexes']);
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
					$downMethod[] = $this->buildAddColumnsCode($tableName, $changes['deleted']);
				}
				
				// Handle index changes for existing tables
				if (!empty($changes['indexes'])) {
					// Add new indexes
					if (!empty($changes['indexes']['added'])) {
						$upMethod[] = $this->buildAddIndexesCode($tableName, $changes['indexes']['added']);
						$downMethod[] = $this->buildRemoveIndexesCode($tableName, $changes['indexes']['added']);
					}
					
					// Modify existing indexes
					if (!empty($changes['indexes']['modified'])) {
						$upMethod[] = $this->buildModifyIndexesCode($tableName, $changes['indexes']['modified']);
						
						$downIndexChanges = [];
						
						foreach ($changes['indexes']['modified'] as $name => $configs) {
							$downIndexChanges[$name] = [
								'database' => $configs['entity'],
								'entity'   => $configs['database']
							];
						}
						
						$downMethod[] = $this->buildModifyIndexesCode($tableName, $downIndexChanges);
					}
					
					// Remove deleted indexes
					if (!empty($changes['indexes']['deleted'])) {
						$upMethod[] = $this->buildRemoveIndexesCode($tableName, $changes['indexes']['deleted']);
						$downMethod[] = $this->buildAddIndexesCode($tableName, $changes['indexes']['deleted']);
					}
				}
			}
			
			$upMethodContent = implode("\n\n", $upMethod);
			$downMethodContent = implode("\n\n", $downMethod);
			
			return <<<PHP
<?php

use Phinx\Migration\AbstractMigration;

class $className extends AbstractMigration {
    /**
     * This migration was automatically generated by ObjectQuel
     *
     * More information on migrations is available on the Phinx website:
     * https://book.cakephp.org/phinx/0/en/migrations.html
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
		 * @param array $indexes Index definitions from IndexComparator
		 * @return string Code for creating table
		 */
		private function buildCreateTableCode(string $tableName, array $entityColumns, array $indexes = []): string {
			$columnDefs = [];
			$primaryKeys = [];
			$hasAutoIncrement = false;
			$autoIncrementColumn = null;
			
			// First pass - identify primary keys and auto-increment columns
			foreach ($entityColumns as $columnName => $definition) {
				if (!empty($definition['primary_key'])) {
					$primaryKeys[] = $columnName;
				}
				
				if (!empty($definition['identity'])) {
					$hasAutoIncrement = true;
					$autoIncrementColumn = $columnName;
				}
			}
			
			// MySQL can only have one auto-increment column, and it must be a key
			// If we have an auto-increment column but it's not in the primary key list,
			// we need to ensure it's part of a unique index
			$needsUniqueIndex = $hasAutoIncrement && !in_array($autoIncrementColumn, $primaryKeys);
			
			// Second pass - build column definitions
			foreach ($entityColumns as $columnName => $definition) {
				// Map the entity type to a valid Phinx type
				$type = $definition['type'];
				$options = $this->buildColumnOptions($definition);
				
				// Add identity option
				if (!empty($definition['identity'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			// For MySQL, specify the primary key in the table options
			$primaryKeyOption = "";
			if (!empty($primaryKeys)) {
				$primaryKeysList = "'" . implode("', '", $primaryKeys) . "'";
				$primaryKeyOption = ", 'primary_key' => [$primaryKeysList]";
			}
			
			// Start with a table that doesn't auto-create an ID column, but specifies primary keys
			$tableStart = "        \$this->table('$tableName', ['id' => false$primaryKeyOption])";
			
			// Add columns
			$tableCode = $tableStart . "\n" . implode("\n", $columnDefs);
			
			// If we have an auto-increment column that's not part of the primary key,
			// add a unique index for it
			if ($needsUniqueIndex) {
				$tableCode .= "\n            ->addIndex(['$autoIncrementColumn'], ['unique' => true, 'name' => 'uidx_{$tableName}_{$autoIncrementColumn}'])";
			}
			
			// Add user-defined indexes in the normalized format from IndexComparator
			if (!empty($indexes)) {
				// For a new table, we're only interested in the normalized entity indexes
				// from IndexComparator::getEntityIndexes(), not the comparison result
				foreach ($indexes as $indexName => $indexConfig) {
					// Format the columns for the Phinx code
					$columnsList = "'" . implode("', '", $indexConfig['columns']) . "'";
					
					// Build options array
					$indexOptions = ["'name' => '$indexName'"];
					
					if ($indexConfig['unique']) {
						$indexOptions[] = "'unique' => true";
					}
					
					// Add the index to the table creation code
					$indexOptionsStr = implode(", ", $indexOptions);
					$tableCode .= "\n            ->addIndex([$columnsList], [$indexOptionsStr])";
				}
			}
			
			// Close the table creation
			$tableCode .= "\n            ->create();";
			
			return $tableCode;
		}
		
		/**
		 * Build code for adding columns to a table
		 * @param string $tableName Table name
		 * @param array $entityColumns Column definitions
		 * @return string Code for adding columns
		 */
		private function buildAddColumnsCode(string $tableName, array $entityColumns): string {
			$result = [];
			$primaryKeys = [];
			$hasAutoIncrement = false;
			$autoIncrementColumn = null;
			
			// First pass - identify primary keys and auto-increment columns
			foreach ($entityColumns as $columnName => $columnDef) {
				if (!empty($columnDef['primary_key'])) {
					$primaryKeys[] = $columnName;
				}
				
				if (!empty($columnDef['identity'])) {
					$hasAutoIncrement = true;
					$autoIncrementColumn = $columnName;
				}
			}
			
			// MySQL can only have one auto-increment column, and it must be a key
			// If we have an auto-increment column but it's not in the primary key list,
			// we need to ensure it's part of a unique index
			$needsUniqueIndex = $hasAutoIncrement && !in_array($autoIncrementColumn, $primaryKeys);
			
			// Second pass - build column definitions
			foreach ($entityColumns as $columnName => $columnDef) {
				$type = $columnDef['type'];
				$options = $this->buildColumnOptions($columnDef);
				
				if (!empty($columnDef['identity'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$result[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			// Start with the table
			$tableCode = "        \$this->table('$tableName')";
			
			// Add columns
			$tableCode .= "\n" . implode("\n", $result);
			
			// Add primary key for existing tables - use Phinx's methods
			if (!empty($primaryKeys)) {
				// For Phinx, we add a unique index with a special name
				$primaryKeysStr = implode("', '", $primaryKeys);
				// Since we're modifying an existing table, we need to use resetIndexes
				// to remove existing indexes on these columns first
				$tableCode .= "\n            ->removeIndex(['$primaryKeysStr'])\n";
				$tableCode .= "            ->addIndex(['$primaryKeysStr'], ['unique' => true, 'name' => 'PRIMARY'])";
			}
			
			// If we have an auto-increment column that's not part of the primary key,
			// add a unique index for it
			if ($needsUniqueIndex) {
				$tableCode .= "\n            ->addIndex(['$autoIncrementColumn'], ['unique' => true, 'name' => 'uidx_{$tableName}_{$autoIncrementColumn}'])";
			}
			
			// Close the table update
			$tableCode .= "\n            ->update();";
			
			return $tableCode;
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
			
			// Set whether column allows NULL values - default to NOT NULL unless explicitly set nullable
			if (isset($propertyDef['nullable'])) {
				$result[] = "'null' => " . ($propertyDef['nullable'] ? 'true' : 'false');
			} else {
				$result[] = "'null' => false";  // Default to NOT NULL
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
		 * Returns the database connector
		 * @return DatabaseAdapter
		 */
		private function getConnection(): DatabaseAdapter {
			if ($this->connection === null) {
				$this->connection = new DatabaseAdapter($this->configuration);
			}
			
			return $this->connection;
		}
		
		/**
		 * Returns the AnnotationReader object
		 * @return AnnotationReader
		 */
		private function getAnnotationReader(): AnnotationReader {
			if ($this->annotationReader === null) {
				$annotationReaderConfiguration = new \Quellabs\AnnotationReader\Configuration();
				$annotationReaderConfiguration->setUseAnnotationCache($this->configuration->useMetadataCache());
				$annotationReaderConfiguration->setAnnotationCachePath($this->configuration->getMetadataCachePath());
				
				$this->annotationReader = new AnnotationReader($annotationReaderConfiguration);
			}
			
			return $this->annotationReader;
		}
		
		/**
		 * Returns the EntityStore object
		 * @return EntityStore
		 */
		private function getEntityStore(): EntityStore {
			if ($this->entityStore === null) {
				$this->entityStore = new EntityStore($this->configuration);
			}
			
			return $this->entityStore;
		}
		
		/**
		 * Build code for adding indexes to a table
		 * @param string $tableName Table name
		 * @param array $indexes Indexes to add
		 * @return string Code for adding indexes
		 */
		private function buildAddIndexesCode(string $tableName, array $indexes): string {
			$indexDefs = [];
			
			foreach ($indexes as $name => $indexConfig) {
				$columns = "'" . implode("', '", $indexConfig['columns']) . "'";
				$options = ["'name' => '$name'"];
				
				if ($indexConfig['unique']) {
					$options[] = "'unique' => true";
				}
				
				$optionsStr = ", [" . implode(", ", $options) . "]";
				$indexDefs[] = "            ->addIndex([$columns]$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $indexDefs) . "\n            ->update();";
		}
		
		/**
		 * Build code for modifying indexes in a table
		 * @param string $tableName Table name
		 * @param array $indexes Index modifications
		 * @return string Code for modifying indexes
		 */
		private function buildModifyIndexesCode(string $tableName, array $indexes): string {
			$indexDefs = [];
			
			foreach ($indexes as $name => $configs) {
				// We need to drop and recreate the index, as Phinx doesn't have a direct "modify index" function
				$indexDefs[] = "            ->removeIndex([], ['name' => '$name'])";
				
				$columns = "'" . implode("', '", $configs['entity']['columns']) . "'";
				$options = ["'name' => '$name'"];
				
				if ($configs['entity']['unique']) {
					$options[] = "'unique' => true";
				}
				
				$optionsStr = ", [" . implode(", ", $options) . "]";
				$indexDefs[] = "            ->addIndex([$columns]$optionsStr)";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $indexDefs) . "\n            ->update();";
		}
		
		/**
		 * Build code for removing indexes from a table
		 * @param string $tableName Table name
		 * @param array $indexes Indexes to remove
		 * @return string Code for removing indexes
		 */
		private function buildRemoveIndexesCode(string $tableName, array $indexes): string {
			$indexDefs = [];
			
			foreach ($indexes as $name => $indexConfig) {
				$indexDefs[] = "            ->removeIndex([], ['name' => '$name'])";
			}
			
			return "        \$this->table('$tableName')\n" . implode("\n", $indexDefs) . "\n            ->update();";
		}
	}