<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Commands;
	
	/**
	 * Import required classes for entity management and console interaction
	 */
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\OrmException;
	use Quellabs\Sculpt\CommandBase;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	use Quellabs\Sculpt\Contracts\ServiceProviderInterface;
	
	/**
	 * MakeEntityFromTableCommand - CLI command for creating or updating entity classes
	 *
	 * This command allows users to interactively create or update entity classes
	 * through a command-line interface, collecting properties with their types
	 * and constraints, including relationship definitions with primary key selection.
	 */
	class MakeEntityFromTableCommand extends CommandBase {
		private Configuration $configuration;
		private ?DatabaseAdapter $connection = null;
		
		/**
		 * MakeEntityFromTableCommand constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param ServiceProviderInterface|null $provider
		 * @throws OrmException
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ServiceProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);
			$this->configuration = $provider->getConfiguration();
		}
		
		/**
		 * Execute the command
		 * @param ConfigurationManager $config Parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(ConfigurationManager $config): int {
			// Prompt the user to select which database table they would like to create a new entity for
			$table = $this->promptForTable();
			
			if (empty($table)) {
				return 0;
			}
			
			// Extract all necessary data from the table
			$tableCamelCase = $this->camelCase($table);
			$tableDescription = $this->getConnection()->getColumns($table);
			
			if (empty($tableDescription)) {
				$this->output->writeLn("Could not extract table description for {$table}.");
				return 0;
			}
			
			// Generate namespace and imports
			$entityCode = "<?php\n";
			$entityCode .= $this->generateNamespace();
			$entityCode .= $this->generateImports();
			$entityCode .= $this->generateClassDocBlock($table, $tableCamelCase);
			
			// Generate entity code
			$entityCode .= "    class {$tableCamelCase}Entity {\n";
			$entityCode .= $this->generateMemberVariables($tableDescription);
			$entityCode .= $this->generateConstructor($tableDescription);
			$entityCode .= $this->generateGettersAndSetters($tableDescription, $tableCamelCase);
			$entityCode .= "    }\n"; // Class closing brace
			
			// Store the file
			$this->saveEntityFile($tableCamelCase, $entityCode);
			
			// Output message
			$this->output->writeLn("Entity class {$tableCamelCase}Entity successfully created.");
			return 0;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public function getSignature(): string {
			return "make:entity-from-table";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public function getDescription(): string {
			return "Generate entity classes from existing database table structures";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public function getHelp(): string {
			return "Generates entity classes by mapping database tables to object-oriented entities.";
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
		 * Generate the namespace section of the entity class
		 * @return string
		 */
		private function generateNamespace(): string {
			return "    namespace {$this->configuration->getEntityNameSpace()};\n\n";
		}
		
		/**
		 * Generate the imports section of the entity class
		 * @return string The imports code
		 */
		private function generateImports(): string {
			$output = "";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\Table;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\Column;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\PrimaryKeyStrategy;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\OneToOne;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\OneToMany;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\ManyToOne;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\Index;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Annotations\Orm\UniqueIndex;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Collections\\Collection;\n";
			$output .= "    use Quellabs\\ObjectQuel\\Collections\\CollectionInterface;\n";
			$output .= "\n";
			
			return $output;
		}
		
		/**
		 * Generate the class docblock with ORM annotations
		 * @param string $tableName The table name
		 * @param string $tableCamelCase The camelCase version of the table name
		 * @return string The class docblock
		 */
		private function generateClassDocBlock(string $tableName, string $tableCamelCase): string {
			$output = "";
			$output .= "    /**\n";
			$output .= "     * Class {$tableCamelCase}Entity\n";
			$output .= "     * @package {$this->configuration->getEntityNameSpace()}\n";
			$output .= "     * @Orm\Table(name=\"{$tableName}\")\n";
			
			foreach ($this->getTableIndexes($tableName) as $name => $indexConfig) {
				$columns = "'" . implode("', '", $indexConfig['columns']) . "'";
				$annotationType = $indexConfig['unique'] ? "UniqueIndex" : "Index";
				
				$output .= "     * @Orm\\{$annotationType}(name=\"{$name}\", columns={{$columns}})\n";
			}
			
			$output .= "     */\n";
			return $output;
		}
		
		/**
		 * Prompt the user to select a database table
		 * @return string The selected table name
		 */
		private function promptForTable(): string {
			return $this->input->choice(
				"Select a database table to generate an entity class from:",
				$this->getConnection()->getTables()
			);
		}
		
		/**
		 * This function analyzes table column definitions and creates PHP code statements
		 * that will properly initialize entity properties with their database default values.
		 * These statements are intended to be included in the entity class constructor.
		 * @param array $tableDescription An associative array containing column definitions from the database schema
		 * @return array List of PHP code statements for initializing properties with default values
		 */
		private function buildPropertyDefaultInitializers(array $tableDescription): array {
			$result = [];
			
			// Iterate through each column in the table description
			foreach ($tableDescription as $columnName => $column) {
				// Skip columns that don't have specified default values
				if (!$this->hasColumnDefaultValue($column)) {
					continue;
				}
				
				// Convert the database column name to camelCase for PHP property naming convention
				$columnCamelCase = lcfirst($this->camelCase($columnName));
				
				// Get the PHP-compatible default value representation for this column
				$defaultValue = $this->getColumnDefaultValue($column);
				
				// Generate the property initialization statement
				$result[] = "\$this->{$columnCamelCase} = {$defaultValue}";
			}
			
			return $result;
		}
		
		/**
		 * This function creates a complete constructor method implementation that initializes
		 * entity properties with their default values from the database schema. If no properties
		 * require initialization, an empty string is returned.
		 * @param array $tableDescription An associative array containing column definitions from the database schema
		 * @return string The complete constructor method code, or an empty string if no initializations are needed
		 */
		private function generateConstructor(array $tableDescription): string {
			// Get the property initialization statements based on column default values
			$initializers = $this->buildPropertyDefaultInitializers($tableDescription);
			
			// If there are no default values to initialize, don't generate a constructor
			if (empty($initializers)) {
				return "";
			}
			
			// Join all initialization statements with line breaks for readability
			$initializersImpl = implode(";\n            ", $initializers);
			
			// Build and return the complete constructor method with proper indentation
			return "
       /**
		* Constructor - automatically initializes properties with database default values
		*/
		public function __construct() {
			{$initializersImpl};
		}
			   
		";
		}
		
		/**
		 * Generate the member variables for the entity class
		 * @param array $tableDescription The table description with column details
		 * @return string The generated member variables code with proper ORM annotations
		 */
		private function generateMemberVariables(array $tableDescription): string {
			// Initialize empty output string to store the generated code
			$output = "";
			
			// Iterate through each column in the table description
			foreach ($tableDescription as $columnName => $column) {
				// Convert the database column name to camelCase for PHP property naming convention
				$columnCamelCase = lcfirst($this->camelCase($columnName));
				
				// Get the PHP type for this column (string, int, \DateTime, etc.)
				$acceptType = $this->getColumnType($column);
				
				// Begin generating the PHPDoc comment block with ORM annotations
				$output .= "        /**\n";
				
				// Add the Column annotation with name and type
				$output .= "         * @Orm\Column(name=\"{$columnName}\", type=\"{$column["type"]}\"";
				$output .= $this->getColumnAnnotationDetails($column);
				$output .= ")\n";
				
				// If this is an auto-incrementing primary key, add the PrimaryKeyStrategy annotation
				if ($column["primary_key"] && $column["identity"]) {
					$output .= "         * @Orm\PrimaryKeyStrategy(strategy=\"identity\")\n";
				}
				
				// Close the PHPDoc comment block
				$output .= "         */\n";
				
				// Begin the property declaration with its type
				$output .= "        protected {$acceptType} \${$columnCamelCase};";
				
				// Add a blank line for readability
				$output .= "\n\n";
			}
			
			return $output;
		}
		
		/**
		 * Get the column type for PHP
		 * @param array $column The column description
		 * @return string The PHP type for the column
		 */
		private function getColumnType(array $column): string {
			if ($column["nullable"] && $column["php_type"] !== 'mixed') {
				return "?{$column["php_type"]}";
			} else {
				return $column["php_type"];
			}
		}
		
		/**
		 * Get the column annotation details
		 * @param array $column The column description
		 * @return string The column annotation details
		 */
		private function getColumnAnnotationDetails(array $column): string {
			// Initialize an empty array to collect annotation details
			$details = [];
			
			// Add limit annotation if specified
			if (!empty($column["limit"])) {
				if (is_numeric($column["limit"])) {
					// For numeric limits, don't use quotes
					$details[] = "limit={$column["limit"]}";
				} else {
					// For non-numeric limits, use quotes
					$details[] = "limit=\"{$column["limit"]}\"";
				}
			}
			
			// Add nullable annotation if the column is nullable
			if ($column["nullable"]) {
				$details[] = "nullable=true";
			}
			
			// Add primary key annotation if the column is a primary key
			if ($column["primary_key"]) {
				$details[] = "primary_key=true";
			}
			
			// Add default value annotation if specified
			if (!empty($column["default"])) {
				$details[] = "default=\"{$column["default"]}\"";
			}
			
			// Add precision annotation for decimal/numeric columns if specified
			if (!empty($column["precision"])) {
				$details[] = "precision={$column["precision"]}";
			}
			
			// Add scale annotation for decimal/numeric columns if specified
			if (!empty($column["scale"])) {
				$details[] = "scale={$column["scale"]}";
			}
			
			// Implode the array with comma separator and prepend a comma if details exist
			// If no details found, return an empty string
			return !empty($details) ? ", " . implode(", ", $details) : "";
		}
		
		/**
		 * Returns true if the column has a default value
		 * @param array $column The column description
		 * @return bool
		 */
		private function hasColumnDefaultValue(array $column): bool {
			return $column["default"] !== null && $column["default"] !== '';
		}
			
		/**
		 * Get the default value for a column
		 * @param array $column The column description
		 * @return string The default value expression
		 */
		private function getColumnDefaultValue(array $column): string {
			// Store the default value and type for easier reference
			$defaultValue = $column["default"];
			$columnType = $column['type'];
			
			// For datetime properties with a default string value
			// Convert the string to a DateTime object initialization
			if ($columnType === 'datetime' && is_string($defaultValue)) {
				return "new \DateTime('{$defaultValue}');";
			}
			
			// For date properties with a default string value
			// Convert the string to a DateTime object initialization
			if ($columnType === 'date' && is_string($defaultValue)) {
				return "new \DateTime('{$defaultValue} 00:00:00');";
			}
			
			// For numeric values (integers, floats), return as-is without quotes
			if (is_numeric($defaultValue)) {
				return $defaultValue;
			}
			
			// For all other values (strings, etc.), wrap in double quotes
			return "\"{$defaultValue}\"";
		}
		
		/**
		 * Generate the getters and setters for the entity class
		 * @param array $tableDescription The table description
		 * @param string $tableCamelCase The camelCase version of the table name
		 * @return string The generated getters and setters code
		 */
		private function generateGettersAndSetters(array $tableDescription, string $tableCamelCase): string {
			$output = "";
			
			foreach ($tableDescription as $columnName => $column) {
				$fieldCamelCase = $this->camelCase($columnName);
				$variableCamelCase = lcfirst($fieldCamelCase);
				$acceptType = $this->getColumnType($column);
				
				$output .= $this->generateGetter($fieldCamelCase, $variableCamelCase, $acceptType);
				$output .= $this->generateSetter($column, $fieldCamelCase, $variableCamelCase, $acceptType, $tableCamelCase);
			}
			
			return $output;
		}
		
		/**
		 * Generate a getter method for a column
		 * @param string $fieldCamelCase The camelCase field name
		 * @param string $variableCamelCase The camelCase variable name
		 * @param string $acceptType The PHP type for the column
		 * @return string The generated getter method
		 */
		private function generateGetter(string $fieldCamelCase, string $variableCamelCase, string $acceptType): string {
			$output = "\n";
			
			if ($acceptType !== '') {
				$output .= "        public function get{$fieldCamelCase}() : {$acceptType} {\n";
			} else {
				$output .= "        public function get{$fieldCamelCase}() {\n";
			}
			
			$output .= "            return \$this->{$variableCamelCase};\n";
			$output .= "        }\n";
			
			return $output;
		}
		
		/**
		 * Generate a setter method for a column
		 * @param array $column The column description
		 * @param string $fieldCamelCase The camelCase field name
		 * @param string $variableCamelCase The camelCase variable name
		 * @param string $acceptType The PHP type for the column
		 * @param string $tableCamelCase The camelCase table name
		 * @return string The generated setter method
		 */
		private function generateSetter(array $column, string $fieldCamelCase, string $variableCamelCase, string $acceptType, string $tableCamelCase): string {
			$output = "\n";
			
			// Only generate setters for non-autoincrement primary keys
			if (!$column["primary_key"] || !$column["identity"]) {
				$output .= "        public function set{$fieldCamelCase}({$acceptType} \$value): {$tableCamelCase}Entity {\n";
				$output .= "            \$this->{$variableCamelCase} = \$value;\n";
				$output .= "            return \$this;\n";
				$output .= "        }\n";
			}
			
			return $output;
		}
		
		/**
		 * Save the entity file to disk
		 * @param string $tableCamelCase The camelCase version of the table name
		 * @param string $entityCode The generated entity class code
		 * @return void
		 */
		private function saveEntityFile(string $tableCamelCase, string $entityCode): void {
			$path = $this->configuration->getEntityPath();
			$filename = "{$path}/{$tableCamelCase}Entity.php";
			file_put_contents($filename, $entityCode);
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
		 * Retrieves all database indexes defined for a specific table
		 * @param string $tableName The name of the database table to get indexes for
		 * @return array Formatted array of database indexes with their configurations
		 */
		private function getTableIndexes(string $tableName): array {
			return array_map(function ($index) {
				return [
					'columns' => $index['columns'],   // Array of column names included in this index
					'type'    => $index['type'],      // Original index type from database
					'unique'  => strtoupper($index['type']) === 'UNIQUE'  // Convert type to boolean flag for uniqueness
				];
			}, $this->getConnection()->getIndexes($tableName));
		}
	}
