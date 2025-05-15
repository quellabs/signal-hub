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
		 * Execute the database migration generation command
		 *
		 * @param ConfigurationManager $config Parameters passed to the command
		 * @return int Exit code (0 for success, 1 for failure)
		 */
		public function execute(ConfigurationManager $config): int {
			$this->output->writeLn("Generating database migrations based on entity changes...");
			
			// Step 1: Scan and validate entities
			$entityClasses = $this->scanEntityClasses();
			
			if (empty($entityClasses)) {
				$this->output->writeLn("No entity classes found.");
				return 1;
			}
			
			// Step 2: Analyze changes between entities and database
			$allChanges = $this->analyzeEntityChanges($entityClasses);
			
			// Step 3: Generate migration file based on changes
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
		 * Build Phinx column options based on property definition
		 * @param array $propertyDef Entity property definition
		 * @return array List of formatted Phinx column options
		 */
		private function buildColumnOptions(array $propertyDef): array {
			$options = [];
			
			// Handle size/length constraints
			$this->addLimitOption($options, $propertyDef);
			
			// Handle default value
			$this->addDefaultOption($options, $propertyDef);
			
			// Handle nullability
			$this->addNullabilityOption($options, $propertyDef);
			
			// Handle numeric precision and scale
			$this->addNumericPrecisionOptions($options, $propertyDef);
			
			// Handle unsigned flag for numeric types
			$this->addSignednessOption($options, $propertyDef);
			
			// Return result
			return $options;
		}
		
		/**
		 * Add column size/length constraint if specified
		 * @param array &$options Options array to modify
		 * @param array $propertyDef Property definition
		 */
		private function addLimitOption(array &$options, array $propertyDef): void {
			if (!empty($propertyDef['limit'])) {
				$options[] = "'limit' => " . $this->phinxTypeMapper->formatValue($propertyDef['limit']);
			}
		}
		
		/**
		 * Add default value if specified and not null
		 * @param array &$options Options array to modify
		 * @param array $propertyDef Property definition
		 */
		private function addDefaultOption(array &$options, array $propertyDef): void {
			if (!empty($propertyDef['default']) && $propertyDef['default'] !== null) {
				$options[] = "'default' => " . $this->phinxTypeMapper->formatValue($propertyDef['default']);
			}
		}
		
		/**
		 * Add nullability constraint (defaults to NOT NULL)
		 * @param array &$options Options array to modify
		 * @param array $propertyDef Property definition
		 */
		private function addNullabilityOption(array &$options, array $propertyDef): void {
			if (isset($propertyDef['nullable'])) {
				$options[] = "'null' => " . ($propertyDef['nullable'] ? 'true' : 'false');
			} else {
				// Default to NOT NULL if not explicitly set
				$options[] = "'null' => false";
			}
		}
		
		/**
		 * Add precision and scale for numeric types
		 * @param array &$options Options array to modify
		 * @param array $propertyDef Property definition
		 */
		private function addNumericPrecisionOptions(array &$options, array $propertyDef): void {
			// Set precision (total digits)
			if (!empty($propertyDef['precision'])) {
				$options[] = "'precision' => " . $propertyDef['precision'];
			}
			
			// Set scale (digits after decimal point)
			if (!empty($propertyDef['scale'])) {
				$options[] = "'scale' => " . $propertyDef['scale'];
			}
		}
		
		/**
		 * Add signed/unsigned flag for numeric types
		 * @param array &$options Options array to modify
		 * @param array $propertyDef Property definition
		 */
		private function addSignednessOption(array &$options, array $propertyDef): void {
			if (isset($propertyDef['unsigned'])) {
				// Note: Phinx uses 'signed' (opposite of 'unsigned')
				$options[] = "'signed' => " . ($propertyDef['unsigned'] ? 'false' : 'true');
			}
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
		
		/**
		 * Scan the entity path for entity classes
		 * @return array Mapping of class names to table names
		 */
		private function scanEntityClasses(): array {
			$entityScanner = new EntityScanner($this->entityPath, $this->getAnnotationReader());
			return $entityScanner->scanEntities();
		}
		
		/**
		 * Analyze changes between entity definitions and database schema
		 * @param array $entityClasses Mapping of entity class names to their corresponding table names
		 * @return array List of changes organized by table name
		 */
		private function analyzeEntityChanges(array $entityClasses): array {
			// Get all existing tables from the database connection
			$existingTables = $this->getConnection()->getTables();
			
			// Initialize the index comparator that will detect differences in indexes
			// between entity definitions and database schema
			$indexComparator = new IndexComparator($this->getConnection(), $this->getEntityStore());
			
			// This will hold all detected changes, organized by table name
			$allChanges = [];
			
			// Iterate through each entity class and analyze differences with database
			foreach ($entityClasses as $className => $tableName) {
				// Extract column definitions from the entity class annotations/metadata
				// This gives us the expected schema according to the entity definition
				$entityProperties = $this->getEntityStore()->extractEntityColumnDefinitions($className);
				
				// Special case: Table doesn't exist in database yet
				// No need for detailed comparison, just mark entire table as new
				if (!in_array($tableName, $existingTables)) {
					$allChanges[$tableName] = [
						// Flag indicating this is a completely new table
						'table_not_exists' => true,
						
						// All columns from entity will be added as new
						'added'            => $entityProperties,
						
						// Get all index definitions from the entity
						// These will all be new indexes since the table doesn't exist
						'indexes'          => $indexComparator->getEntityIndexes($className)
					];
					
					// Skip to next entity - no need to compare with existing schema
					continue;
				}
				
				// For existing tables, perform detailed comparison between
				// entity definition and database schema
				$allChanges[$tableName] = $this->compareExistingTable(
					$tableName,
					$className,
					$entityProperties,
					$indexComparator
				);
				
				// Optimization: Remove tables that have no actual changes
				// This keeps the change list clean and focused only on tables that need migration
				if (!$this->hasChanges($allChanges[$tableName])) {
					unset($allChanges[$tableName]);
				}
			}
			
			// Return the complete list of changes that need to be applied
			// This will be used to generate the migration file
			return $allChanges;
		}
		
		/**
		 * Compare an existing table with entity definition
		 * @param string $tableName Name of the database table
		 * @param string $className Entity class name
		 * @param array $entityProperties Properties extracted from entity
		 * @param IndexComparator $indexComparator The index comparator instance
		 * @return array Changes for this table
		 */
		private function compareExistingTable(string $tableName, string $className, array $entityProperties, IndexComparator $indexComparator): array {
			// Compare columns
			$schemaComparator = new SchemaComparator();
			$tableColumns = $this->getConnection()->getColumns($tableName);
			$changes = $schemaComparator->analyzeSchemaChanges($entityProperties, $tableColumns);
			
			// Compare indexes
			$changes['indexes'] = $indexComparator->compareIndexes($className);
			
			// Return the result
			return $changes;
		}
		
		/**
		 * Check if there are any changes for a table
		 * @param array $changes The changes array for a table
		 * @return bool True if there are no changes
		 */
		private function hasChanges(array $changes): bool {
			return
				!empty($changes['added']) ||
				!empty($changes['modified']) ||
				!empty($changes['deleted']) ||
				!empty($changes['indexes']['added']) ||
				!empty($changes['indexes']['modified']) ||
				!empty($changes['indexes']['deleted']);
		}
		
		/**
		 * Build code for creating a new database table
		 * @param string $tableName Table name
		 * @param array $entityColumns Column definitions
		 * @param array $indexes Index definitions from IndexComparator
		 * @return string Phinx code for creating the table
		 */
		private function buildCreateTableCode(string $tableName, array $entityColumns, array $indexes = []): string {
			// Step 1: Analyze columns to identify primary keys and auto-increment
			$columnInfo = $this->analyzeColumns($entityColumns);
			$primaryKeys = $columnInfo['primaryKeys'];
			$hasAutoIncrement = $columnInfo['hasAutoIncrement'];
			$autoIncrementColumn = $columnInfo['autoIncrementColumn'];
			
			// Step 2: Determine if we need an additional unique index for auto-increment column
			$needsUniqueIndex = $hasAutoIncrement && !in_array($autoIncrementColumn, $primaryKeys);
			
			// Step 3: Generate column definitions
			$columnDefs = $this->generateColumnDefinitions($entityColumns);
			
			// Step 4: Build the table creation code
			return $this->assembleTableCode(
				$tableName,
				$columnDefs,
				$primaryKeys,
				$needsUniqueIndex ? $autoIncrementColumn : null,
				$indexes
			);
		}
		
		/**
		 * Analyze column definitions to extract primary keys and auto-increment information
		 * @param array $entityColumns Column definitions
		 * @return array Information about primary keys and auto-increment
		 */
		private function analyzeColumns(array $entityColumns): array {
			$primaryKeys = [];
			$hasAutoIncrement = false;
			$autoIncrementColumn = null;
			
			foreach ($entityColumns as $columnName => $definition) {
				if (!empty($definition['primary_key'])) {
					$primaryKeys[] = $columnName;
				}
				
				if (!empty($definition['identity'])) {
					$hasAutoIncrement = true;
					$autoIncrementColumn = $columnName;
				}
			}
			
			return [
				'primaryKeys'         => $primaryKeys,
				'hasAutoIncrement'    => $hasAutoIncrement,
				'autoIncrementColumn' => $autoIncrementColumn
			];
		}
		
		/**
		 * Generate Phinx column definition code for each column
		 * @param array $entityColumns Column definitions
		 * @return array Array of column definition strings
		 */
		private function generateColumnDefinitions(array $entityColumns): array {
			$columnDefs = [];
			
			foreach ($entityColumns as $columnName => $definition) {
				$type = $definition['type'];
				$options = $this->buildColumnOptions($definition);
				
				if (!empty($definition['identity'])) {
					$options[] = "'identity' => true";
				}
				
				$optionsStr = empty($options) ? "" : ", [" . implode(", ", $options) . "]";
				$columnDefs[] = "            ->addColumn('$columnName', '$type'$optionsStr)";
			}
			
			return $columnDefs;
		}
		
		/**
		 * Assemble the complete table creation code
		 * @param string $tableName Table name
		 * @param array $columnDefs Column definition strings
		 * @param array $primaryKeys List of primary key columns
		 * @param string|null $autoIncrementColumn Auto-increment column that needs a unique index
		 * @param array $indexes Index definitions
		 * @return string Complete table creation code
		 */
		private function assembleTableCode(
			string  $tableName,
			array   $columnDefs,
			array   $primaryKeys,
			?string $autoIncrementColumn,
			array   $indexes
		): string {
			// Create primary key option string if needed
			$primaryKeyOption = "";
			
			if (!empty($primaryKeys)) {
				$primaryKeysList = "'" . implode("', '", $primaryKeys) . "'";
				$primaryKeyOption = ", 'primary_key' => [$primaryKeysList]";
			}
			
			// Start with table definition
			$tableStart = "        \$this->table('$tableName', ['id' => false$primaryKeyOption])";
			
			// Add columns
			$tableCode = $tableStart . "\n" . implode("\n", $columnDefs);
			
			// Add unique index for auto-increment column if needed
			if ($autoIncrementColumn) {
				$tableCode .= "\n            ->addIndex(['$autoIncrementColumn'], ['unique' => true, 'name' => 'uidx_{$tableName}_{$autoIncrementColumn}'])";
			}
			
			// Add user-defined indexes
			$tableCode .= $this->generateIndexDefinitions($indexes);
			
			// Close the table creation
			$tableCode .= "\n            ->create();";
			
			return $tableCode;
		}
		
		/**
		 * Generate index definitions for all indexes
		 * @param array $indexes Index definitions
		 * @return string Combined index definition code
		 */
		private function generateIndexDefinitions(array $indexes): string {
			// Early return if no indexes are defined
			if (empty($indexes)) {
				return "";
			}
			
			// Process each index defined for this table
			$indexCode = "";
			
			foreach ($indexes as $indexName => $indexConfig) {
				// Initialize an options array with the index name
				$indexOptions = [];
				$indexOptions[] = "'name' => '$indexName'";
				
				// Add unique constraint if specified
				if ($indexConfig['unique']) {
					$indexOptions[] = "'unique' => true";
				}
				
				// Convert options array to a string for Phinx method
				$indexOptionsStr = implode(", ", $indexOptions);
				
				// Format column list for Phinx (e.g. 'column1', 'column2')
				$columnsList = "'" . implode("', '", $indexConfig['columns']) . "'";
				
				// Build the addIndex() method call with proper indentation
				// Uses Phinx fluent interface for table creation
				$indexCode .= "\n            ->addIndex([$columnsList], [$indexOptionsStr])";
			}
			
			return $indexCode;
		}
	}