<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	use Quellabs\ObjectQuel\Configuration;
	
	class EntityModifier {
		
		private Configuration $configuration;
		
		/**
		 * Constructor for EntityModifier
		 * @param Configuration $configuration
		 */
		public function __construct(Configuration $configuration) {
			$this->configuration = $configuration;
		}
		
		/**
		 * Checks if an entity exists
		 * @param string $entityName Name of the entity
		 * @return bool True if entity exists
		 */
		public function entityExists(string $entityName): bool {
			return file_exists($this->getEntityPath($entityName));
		}
		
		/**
		 * Gets the file path for an entity
		 * @param string $entityName Name of the entity
		 * @return string Path to the entity file
		 */
		public function getEntityPath(string $entityName): string {
			return $this->configuration->getEntityPath() . '/' . $entityName . '.php';
		}
		
		/**
		 * Creates a new entity or updates an existing one
		 * This function serves as the main entry point for entity management, determining
		 * whether to create a new entity class or update an existing one based on file existence
		 * @param string $entityName Name of the entity (without "Entity" suffix)
		 * @param array $properties List of properties to add - detailed metadata for each property
		 * @return bool True if successful - indicates whether the operation completed correctly
		 */
		public function createOrUpdateEntity(string $entityName, array $properties): bool {
			// Check if the entity already exists by looking for its file
			// The entityExists method likely checks for the presence of the file at the expected path
			// We append "Entity" to match the standard naming convention for entity classes
			if ($this->entityExists($entityName . "Entity")) {
				// If the entity exists, delegate to the updateEntity method
				// This method handles the complex task of modifying an existing class
				// without disrupting existing code or functionality
				// It will add the new properties and methods while preserving existing ones
				return $this->updateEntity($entityName, $properties);
			}
			
			// If the entity doesn't exist, delegate to the createNewEntity method
			// This method generates a complete class file from scratch with all required
			// properties, methods, annotations, and proper initialization
			return $this->createNewEntity($entityName, $properties);
		}
		
		/**
		 * Creates a new entity with properties and getters/setters
		 * This function generates a complete entity class file from scratch, including
		 * all necessary properties, constructors, and accessor methods
		 * @param string $entityName Name of the entity (without "Entity" suffix)
		 * @param array $properties List of properties to add - detailed metadata for each property
		 * @return bool True if successful - indicates whether the file was created correctly
		 */
		public function createNewEntity(string $entityName, array $properties): bool {
			// Ensure the entity directory exists before attempting to create files
			// This is important for first-time setup or when deploying to new environments
			if (!is_dir($this->configuration->getEntityPath())) {
				// Create the directory structure recursively with standard permissions
				// 0755 allows the owner to read/write/execute and others to read/execute
				// The 'true' parameter creates parent directories as needed
				mkdir($this->configuration->getEntityPath(), 0755, true);
			}
			
			// Generate the complete entity class content as a string
			// This includes:
			// - Namespace declaration
			// - Use statements for imports
			// - Class declaration with proper inheritance
			// - Property declarations with proper annotations
			// - Constructor for collection initialization
			// - Getter/setter methods for all properties
			// - Adder/remover methods for collections
			$content = $this->generateEntityContent($entityName, $properties);
			
			// Write the generated content to a new file
			// The file path is constructed using the entity name plus "Entity" suffix
			// Returns boolean success/failure of the write operation
			return file_put_contents($this->getEntityPath($entityName . "Entity"), $content) !== false;
		}
		
		/**
		 * Updates an existing entity with new properties and getters/setters
		 * This function modifies entity classes by adding new properties, updating constructors
		 * for collection initialization, and generating accessor methods
		 * @param string $entityName Name of the entity (without "Entity" suffix)
		 * @param array $properties List of properties to add - detailed metadata for each property
		 * @return bool True if successful - indicates whether the file was updated correctly
		 */
		public function updateEntity(string $entityName, array $properties): bool {
			// Construct the full file path to the entity class using the entity name
			// The getEntityPath method likely adds necessary directory prefixes and file extension
			$filePath = $this->getEntityPath($entityName . "Entity");
			
			// Read the current content of the entity file
			// This gives us the starting point for our modifications
			$content = file_get_contents($filePath);
			
			// Verify that the file exists and was read successfully
			// Return false immediately if file cannot be read
			if ($content === false) {
				return false;
			}
			
			// Parse the class structure to identify where to insert new code
			// This helper method likely extracts information about class boundaries,
			// existing properties, methods, etc.
			$classContent = $this->parseClassContent($content);
			
			// Safety check - if parsing failed, abort the operation
			// This prevents corrupting the file with improperly placed code
			if (!$classContent) {
				return false;
			}
			
			// Analyze the properties to determine if we need to handle OneToMany relationships
			// OneToMany relationships require special handling for collection initialization
			$hasNewOneToMany = false;
			$oneToManyProperties = [];
			
			// Iterate through each property to identify OneToMany relationships
			foreach ($properties as $property) {
				if (isset($property['relationshipType']) && $property['relationshipType'] === 'OneToMany') {
					// Flag that we need constructor updates
					$hasNewOneToMany = true;
					// Track the OneToMany properties for constructor initialization
					$oneToManyProperties[] = $property;
				}
			}
			
			// Update or create a constructor if we have OneToMany collections that need initialization
			// Collection properties must be initialized to prevent null reference errors
			if ($hasNewOneToMany) {
				// The updateConstructor method handles both creating new constructors
				// and modifying existing ones as needed
				$updatedContent = $this->updateConstructor($content, $oneToManyProperties);
			} else {
				// If no OneToMany properties, we don't need to modify the constructor
				$updatedContent = $content;
			}
			
			// Add the new properties to the class
			// This inserts the property declarations in the appropriate location
			// Re-parse the class content after constructor updates to ensure correct insertion points
			$updatedContent = $this->insertProperties($this->parseClassContent($updatedContent), $properties);
			
			// Generate and add the getter and setter methods for the new properties
			// For OneToMany relationships, this also adds the collection adder/remover methods
			$updatedContent = $this->insertGettersAndSetters($updatedContent, $properties, $entityName);
			
			// Write the updated content back to the file
			// Return success/failure based on the write operation
			// The !== false check handles cases where zero bytes might be written (unlikely but possible)
			return file_put_contents($filePath, $updatedContent) !== false;
		}
		
		/**
		 * Parses the class content to identify sections
		 * @param string $content Entity file content
		 * @return array|false Class content sections or false on error
		 */
		protected function parseClassContent(string $content): false|array {
			// Find the class definition
			if (!preg_match('/class\s+(\w+)(?:\s+extends\s+\w+)?(?:\s+implements\s+[\w\s,]+)?\s*\{/s', $content, $classMatch, PREG_OFFSET_CAPTURE)) {
				return false;
			}
			
			// Find the last closing brace
			$classStartPos = (int)$classMatch[0][1] + strlen($classMatch[0][0]);
			$lastBracePos = strrpos($content, '}');
			
			if ($lastBracePos === false) {
				return false;
			}
			
			// Extract class body
			$classBody = substr($content, $classStartPos, $lastBracePos - $classStartPos);
			
			// Identify property section (ends at the first method declaration)
			// Methods start with: any visibility, then "function", then a name
			$methodPattern = '/\s*(public|protected|private)?\s+function\s+\w+/';
			
			// Split into properties and methods sections
			if (preg_match($methodPattern, $classBody, $methodMatch, PREG_OFFSET_CAPTURE)) {
				$firstMethodPos = $methodMatch[0][1];
				// Find the beginning of the method or its docblock
				$potentialDocBlockStart = strrpos(substr($classBody, 0, $firstMethodPos), '/**');
				
				if ($potentialDocBlockStart !== false && ($firstMethodPos - $potentialDocBlockStart) < 100) {
					// There's a docblock before this method, adjust firstMethodPos
					$firstMethodPos = $potentialDocBlockStart;
				}
				
				$propertiesSection = trim(substr($classBody, 0, $firstMethodPos));
				$methodsSection = trim(substr($classBody, $firstMethodPos));
			} else {
				// No methods found
				$propertiesSection = trim($classBody);
				$methodsSection = '';
			}
			
			return [
				'header'     => substr($content, 0, $classStartPos),
				'properties' => $propertiesSection,
				'methods'    => $methodsSection,
				'footer'     => substr($content, $lastBracePos)
			];
		}
		
		/**
		 * Insert properties into the class content
		 * @param array $classContent Parsed class content
		 * @param array $properties List of properties to add
		 * @return string Updated class content
		 */
		protected function insertProperties(array $classContent, array $properties): string {
			$propertyCode = $classContent['properties'];
			
			// Add each new property
			$newProperties = '';
			foreach ($properties as $property) {
				// Skip if property already exists
				$propertyName = $property['name'];
				if (preg_match('/\s*(protected|private|public)\s+.*\$' . $propertyName . '\s*;/i', $propertyCode)) {
					continue;
				}
				
				$docComment = isset($property['relationshipType'])
					? $this->generateRelationshipDocComment($property)
					: $this->generatePropertyDocComment($property);
				
				$propertyDefinition = $this->generatePropertyDefinition($property);
				
				$newProperties .= "\n\n\t" . $docComment . "\n\t" . $propertyDefinition;
			}
			
			// Add the new properties at the end of the properties section
			$updatedPropertyCode = $propertyCode . $newProperties;
			
			return $classContent['header'] . $updatedPropertyCode . "\n\n\t" . $classContent['methods'] . $classContent['footer'];
		}
		
		/**
		 * Insert getters and setters into the class content
		 * @param string $content Class content
		 * @param array $properties List of properties to add
		 * @param string $entityName Name of the entity
		 * @return string Updated class content
		 */
		protected function insertGettersAndSetters(string $content, array $properties, string $entityName): string {
			// Find the position of the last closing brace
			$lastBracePos = strrpos($content, '}');
			if ($lastBracePos === false) {
				return $content;
			}
			
			$methodsToAdd = '';
			
			// Generate getter and setter for each property
			foreach ($properties as $property) {
				// Skip if getter/setter already exists
				$getterName = 'get' . ucfirst($property['name']);
				$setterName = 'set' . ucfirst($property['name']);
				
				if (!preg_match('/function\s+' . $getterName . '\s*\(/i', $content)) {
					$methodsToAdd .= $this->generateGetter($property);
				}
				
				if (!preg_match('/function\s+' . $setterName . '\s*\(/i', $content)) {
					$methodsToAdd .= $this->generateSetter($property);
				}
				
				// For OneToMany relationships, add additional methods for collection management
				if (isset($property['relationshipType']) && $property['relationshipType'] === 'OneToMany') {
					$singularName = $this->getSingularName($property['name']);
					$addMethodName = 'add' . ucfirst($singularName);
					$removeMethodName = 'remove' . ucfirst($singularName);
					
					if (!preg_match('/function\s+' . $addMethodName . '\s*\(/i', $content)) {
						$methodsToAdd .= $this->generateCollectionAdder($property, $entityName);
					}
					
					if (!preg_match('/function\s+' . $removeMethodName . '\s*\(/i', $content)) {
						$methodsToAdd .= $this->generateCollectionRemover($property, $entityName);
					}
				}
			}
			
			// Insert methods before the last brace
			return substr($content, 0, $lastBracePos) . $methodsToAdd . "\n}" . substr($content, $lastBracePos + 1);
		}
		
		/**
		 * Generate the content for a new entity
		 * @param string $entityName Name of the entity
		 * @param array $properties List of properties for the entity
		 * @return string Entity file content
		 */
		protected function generateEntityContent(string $entityName, array $properties): string {
			// Namespace
			$namespace = $this->configuration->getEntityNameSpace();
			$content = "<?php\n\nnamespace $namespace;\n";
			
			// Use statements
			$content .= "\n";
			$content .= "use Quellabs\\ObjectQuel\\Annotations\Orm\Table;\n";
			$content .= "use Quellabs\\ObjectQuel\\Annotations\Orm\Column;\n";
			$content .= "use Quellabs\\ObjectQuel\\Annotations\Orm\PrimaryKeyStrategy;\n";
			$content .= "use Quellabs\\ObjectQuel\\Annotations\Orm\OneToOne;\n";
			$content .= "use Quellabs\\ObjectQuel\\Annotations\Orm\OneToMany;\n";
			$content .= "use Quellabs\\ObjectQuel\\Annotations\Orm\ManyToOne;\n";
			$content .= "use Quellabs\\ObjectQuel\\Collections\\Collection;\n";
			$content .= "use Quellabs\\ObjectQuel\\Collections\\CollectionInterface;\n";
			
			// Class definitions
			$tableName = $this->snakeCase($entityName);
			$content .= "/**\n * @Orm\Table(name=\"{$tableName}\")\n */\n";
			$content .= "class {$entityName}Entity {\n";
			
			// Add primary key property
			$content .= "
			/**
			 * @Orm\Column(name=\"id\", type=\"integer\", unsigned=true, primary_key=true)
			 * @Orm\PrimaryKeyStrategy(strategy=\"auto_increment\")
			 */
			protected int \$id;
		";
			
			// Add constructor for OneToMany relationships initialization
			$hasOneToMany = false;
			foreach ($properties as $property) {
				if (isset($property['relationshipType']) && $property['relationshipType'] === 'OneToMany') {
					$hasOneToMany = true;
					break;
				}
			}
			
			// If we have OneToMany relationships, add a constructor to initialize collections
			if ($hasOneToMany) {
				$content .= "\n\t/**\n\t * Constructor to initialize collections\n\t */\n";
				$content .= "\tpublic function __construct() {\n";
				
				foreach ($properties as $property) {
					if (isset($property['relationshipType']) && $property['relationshipType'] === 'OneToMany') {
						$content .= "\t\t\$this->{$property['name']} = new Collection();\n";
					}
				}
				
				$content .= "\t}\n";
			}
			
			// Add properties
			foreach ($properties as $property) {
				if (isset($property['relationshipType'])) {
					$docComment = $this->generateRelationshipDocComment($property);
				} else {
					$docComment = $this->generatePropertyDocComment($property);
				}
				
				$propertyDefinition = $this->generatePropertyDefinition($property);
				
				$content .= "\n\t" . $docComment . "\n\t" . $propertyDefinition . "\n";
			}
			
			// Add getter for primary id
			$content .= $this->generateGetter(['name' => 'id', 'type' => 'int']);
			
			// Add getters and setters
			foreach ($properties as $property) {
				$content .= $this->generateGetter($property);
				$content .= $this->generateSetter($property);
				
				// For OneToMany relationships, add additional methods
				if (isset($property['relationshipType']) && $property['relationshipType'] === 'OneToMany') {
					$content .= $this->generateCollectionAdder($property, $entityName);
					$content .= $this->generateCollectionRemover($property, $entityName);
				}
			}
			
			$content .= "}\n";
			
			return $content;
		}
		
		/**
		 * Generate a property's PHPDoc comment
		 * @param array $property Property information
		 * @return string PHPDoc comment
		 */
		protected function generatePropertyDocComment(array $property): string {
			$nullable = $property['nullable'] ?? false;
			$type = $property['type'] ?? 'string';
			$snakeCaseName = $this->snakeCase($property['name']);
			
			$properties = [];
			
			// Add the name and type properties
			$properties[] = "name=\"{$snakeCaseName}\"";
			$properties[] = "type=\"{$type}\"";
			
			// Add optional properties if they exist
			if (isset($property['length']) && is_numeric($property['length'])) {
				$properties[] = "length={$property['length']}";
			}
			
			if (isset($property['unsigned'])) {
				$properties[] = "unsigned=" . ($property['unsigned'] ? "true" : "false");
			}
			
			if ($nullable) {
				$properties[] = "nullable=true";
			}
			
			$propertiesString = implode(", ", $properties);
			return "/**\n\t * @Orm\Column({$propertiesString})\n\t */";
		}
		
		/**
		 * Generate a relationship PHPDoc comment
		 * @param array $property Relationship property information
		 * @return string PHPDoc comment
		 */
		protected function generateRelationshipDocComment(array $property): string {
			$relationshipType = $property['relationshipType'];
			$targetEntity = $property['targetEntity'];
			$nullable = $property['nullable'] ?? false;
			
			$comment = "/**\n\t * @Orm\\{$relationshipType}(targetEntity=\"{$targetEntity}Entity\"";
			
			// Add mappedBy attribute for OneToMany or bidirectional OneToOne
			if (!empty($property['mappedBy'])) {
				$comment .= " mappedBy=\"{$property['mappedBy']}\"";
			}
			
			// Add inversedBy attribute for ManyToOne
			if (!empty($property['inversedBy'])) {
				$comment .= " inversedBy=\"{$property['inversedBy']}\"";
			}
			
			// Add fetch="LAZY" for OneToMany relationships
			if ($relationshipType === 'OneToMany') {
				$comment .= " fetch=\"LAZY\"";
			}
			
			// Add nullable attribute if specified
			if ($nullable) {
				$comment .= " nullable=true";
			}
			
			// If we have a referenced column name that's not the default 'id',
			// we can store it as a custom attribute in the annotation
			if (isset($property['referencedColumnName']) && $property['referencedColumnName'] !== 'id') {
				$comment .= " referencedColumnName=\"{$property['referencedColumnName']}\"";
			}
			
			// Add a join column name if provided
			if (!empty($property['joinColumnName'])) {
				$comment .= " joinColumnName=\"{$property['joinColumnName']}\"";
			}
			
			$comment .= ")";
			
			// Add PHPDoc var type for collections
			if ($relationshipType === 'OneToMany') {
				$comment .= "\n\t * @var \$" . $property['name'] . " CollectionInterface<" . $targetEntity . "Entity>";
			}
			
			$comment .= "\n\t */";
			
			return $comment;
		}
		
		/**
		 * Generate a property definition
		 * @param array $property Property information
		 * @return string Property definition
		 */
		protected function generatePropertyDefinition(array $property): string {
			$nullable = $property['nullable'] ?? false;
			
			// Handle relationship types
			if (isset($property['relationshipType'])) {
				$type = $property['type'];
				$nullableIndicator = $nullable ? '?' : '';
				
				return "protected {$nullableIndicator}{$type} \${$property['name']};";
			}
			
			// Handle regular properties
			$type = $property['type'] ?? 'string';
			$phpType = $this->typeToPhpType($type);
			$nullableIndicator = $nullable ? '?' : '';
			
			return "protected {$nullableIndicator}{$phpType} \${$property['name']};";
		}
		
		/**
		 * Transforms an entered type in a php type
		 * @param string $type
		 * @return string
		 */
		protected function typeToPhpType(string $type): string {
			return match ($type) {
				'integer', 'smallint' => 'int',
				'date' => '\\DateTime',
				'datetime' => '\\DateTime',
				'float' => 'float',
				default => 'string',
			};
		}
		
		/**
		 * Generate a getter method for a property
		 * @param array $property Property information
		 * @return string Getter method code
		 */
		protected function generateGetter(array $property): string {
			$propertyName = $property['name'];
			$methodName = 'get' . ucfirst($propertyName);
			
			// Handle relationship getter
			if (isset($property['relationshipType'])) {
				$type = $property['type'];
				$nullable = $property['nullable'] ?? false;
				$nullableIndicator = $nullable ? '?' : '';

				// Specially handle OneToMany collection getters
				if ($property['relationshipType'] === 'OneToMany') {
					$targetEntity = $property['targetEntity'] . 'Entity';

					return <<<EOT

						/**
						 * @return CollectionInterface<{$targetEntity}>
						 */
						public function {$methodName}(): CollectionInterface {
							return \$this->{$propertyName};
						}
					EOT;
				}
				
				return <<<EOT

					/**
					 * Get {$propertyName}
					 * @return {$nullableIndicator}{$type}
					 */
					public function {$methodName}(): {$nullableIndicator}{$type} {
						return \$this->{$propertyName};
					}
				EOT;
			}
			
			// Handle regular property getter
			$nullable = $property['nullable'] ?? false;
			$type = $property['type'] ?? 'string';
			$phpType = $this->typeToPhpType($type);
			$nullableIndicator = $nullable ? '?' : '';
			
			return <<<EOT
				/**
				 * Get {$propertyName}
				 * @return {$nullableIndicator}{$phpType}
				 */
				public function {$methodName}(): {$nullableIndicator}{$phpType} {
					return \$this->{$propertyName};
				}
			EOT;
		}
		
		/**
		 * Generate a setter method for a property
		 * This function creates properly typed setter methods for entity properties,
		 * handling both regular properties and relationship properties with appropriate type hints
		 * @param array $property Property information - metadata about the property
		 * @return string Setter method code - the complete PHP method as a formatted string
		 */
		protected function generateSetter(array $property): string {
			// Extract the property name from the property information array
			$propertyName = $property['name'];
			
			// Create the method name by prefixing "set" to the capitalized property name
			// E.g., "firstName" becomes "setFirstName" - follows standard setter naming conventions
			$methodName = 'set' . ucfirst($propertyName);
			
			// Handle relationship setter (ManyToOne, OneToOne relationships)
			// These require special handling since they directly reference other entities
			if (isset($property['relationshipType'])) {
				// For relationships, use the type directly (typically an entity class name)
				$type = $property['type'];
				
				// Check if the relationship is nullable (optional)
				// Default to false (required) if not specified
				$nullable = $property['nullable'] ?? false;
				
				// Create PHP 7.1+ nullable type indicator (? prefix) if property is nullable
				// This enables strict type checking while allowing null values
				$nullableIndicator = $nullable ? '?' : '';
				
				// Generate the complete setter method for relationship properties
				return <<<EOT
				
					/**
					 * Set {$propertyName}
					 * @param {$nullableIndicator}{$type} \${$propertyName}
					 * @return \$this
					 */
					public function {$methodName}({$nullableIndicator}{$type} \${$propertyName}): self {
						\$this->{$propertyName} = \${$propertyName};
						return \$this;
					}
				EOT;
			}
			
			// Handle regular property setter (non-relationship properties)
			// These use PHP primitive types or custom types
			
			// Check if the property is nullable
			// Default to false (required) if not specified
			$nullable = $property['nullable'] ?? false;
			
			// Get the property's data type, default to 'string' if not specified
			$type = $property['type'] ?? 'string';
			
			// Convert database/schema type to PHP type
			// This handles mapping of types like 'varchar' to 'string', 'integer' to 'int', etc.
			$phpType = $this->typeToPhpType($type);
			
			// Create PHP 7.1+ nullable type indicator (? prefix) if property is nullable
			$nullableIndicator = $nullable ? '?' : '';
			
			// Generate the complete setter method for regular properties
			return <<<EOT
			
				/**
				 * Set {$propertyName}
				 * @param {$nullableIndicator}{$phpType} \${$propertyName}
				 * @return \$this
				 */
				public function {$methodName}({$nullableIndicator}{$phpType} \${$propertyName}): self {
					\$this->{$propertyName} = \${$propertyName};
					return \$this;
				}
			EOT;
		}
		
		/**
		 * Generate a method to add an item to a collection (for OneToMany)
		 * This function creates an adder method for OneToMany relationships that
		 * safely adds entities to collections while maintaining bidirectional integrity
		 * @param array $property Collection property information - contains relationship metadata
		 * @param string $entityName Current entity name (without suffix) - the parent entity class name
		 * @return string Method code - the complete PHP method as a formatted string
		 */
		protected function generateCollectionAdder(array $property, string $entityName): string {
			// Extract the collection property name from the property information
			// This is the name of the property that holds the Collection object
			$collectionName = $property['name'];
			
			// Convert the collection name to its singular form for method naming and parameter naming
			// E.g., "orderItems" would become "orderItem" - helps create intuitive method signatures
			$singularName = $this->getSingularName($collectionName);
			
			// Create the method name by prefixing "add" to the capitalized singular name
			// E.g., "orderItem" becomes "addOrderItem" - follows standard naming conventions
			$methodName = 'add' . ucfirst($singularName);
			
			// Get the full class name of the target entity by appending "Entity" suffix
			// This will be used in the method signature for type hinting
			$targetEntity = $property['targetEntity'] . 'Entity';
			
			// Initialize empty string for bidirectional relationship handling code
			$inverseSetter = '';
			
			// Check if this is part of a bidirectional relationship by looking for mappedBy property
			// mappedBy indicates this is the inverse side of a bidirectional relationship
			if (!empty($property['mappedBy'])) {
				// Determine the setter method name on the target entity that references this entity
				// E.g., if entityName is "Order", this creates "setOrder"
				$setterMethod = 'set' . ucfirst($entityName);
				
				// Build the code to update the inverse side of the relationship
				// This maintains referential integrity by setting the back-reference
				$inverseSetter = "\n\t\t// Set the owning side of the relationship\n";
				$inverseSetter .= "\t\t\${$singularName}->{$setterMethod}(\$this);";
			}
			
			// Construct the complete method code with proper formatting, PHPDoc, and logic
			return <<<EOT
				/**
				 * Adds a relation between {$targetEntity} and {$targetEntity}
				 * @param {$targetEntity} \${$singularName}
				 * @return \$this
				 */
				public function {$methodName}({$targetEntity} \${$singularName}): self {
					if (!\$this->{$collectionName}->contains(\${$singularName})) {
						\$this->{$collectionName}[] = \${$singularName};{$inverseSetter}
					}
					return \$this;
				}
			EOT;
		}
		
		/**
		 * Generate a method to remove an item from a collection (for OneToMany)
		 * This function creates a removal method for OneToMany relationships that
		 * safely removes entities from collections while maintaining bidirectional integrity
		 * @param array $property Collection property information - contains relationship metadata
		 * @param string $entityName Current entity name (without suffix) - the parent entity class name
		 * @return string Method code - the complete PHP method as a formatted string
		 */
		protected function generateCollectionRemover(array $property, string $entityName): string {
			// Extract the collection property name from the property information
			$collectionName = $property['name'];
			
			// Convert the collection name to its singular form for method naming and parameter naming
			// E.g., "orderItems" would become "orderItem"
			$singularName = $this->getSingularName($collectionName);
			
			// Create the method name by prefixing "remove" to the capitalized singular name
			// E.g., "orderItem" becomes "removeOrderItem"
			$methodName = 'remove' . ucfirst($singularName);
			
			// Get the full class name of the target entity by appending "Entity" suffix
			// This will be used in the method signature for type hinting
			$targetEntity = $property['targetEntity'] . 'Entity';
			
			// Check if this is part of a bidirectional relationship by looking for mappedBy property
			// In a bidirectional relationship, we need to update both sides when removing
			$inverseRemover = '';

			if (!empty($property['mappedBy'])) {
				// Determine the setter method name on the target entity that references this entity
				// E.g., if entityName is "Order", this creates "setOrder"
				$setterMethod = 'set' . ucfirst($entityName);
				
				// Build the code to update the inverse side of the relationship
				// This maintains referential integrity by setting the reference to null
				$inverseRemover = "\n\t\t// Unset the owning side of the relationship\n";
				$inverseRemover .= "\t\t\${$singularName}->{$setterMethod}(null);";
			}

			// Construct the complete method code with proper formatting, PHPDoc, and logic
			$targetEntityBase = substr($targetEntity, 0, -6);
			
			return <<<EOT
				/**
				 * Removes a relation between {$targetEntity} and {$targetEntityBase}
				 * @param {$targetEntity} \${$singularName}
				 * @return \$this
				 */
				public function {$methodName}({$targetEntity} \${$singularName}): self {
					if (\$this->{$collectionName}->remove(\${$singularName})) {
						$inverseRemover
					}
					
					return \$this;
				}
			EOT;
		}
		
		/**
		 * Updates constructor to initialize new OneToMany collections
		 * This function serves as the main entry point for modifying entity classes
		 * to ensure proper initialization of OneToMany relationship collections
		 * @param string $content Entity file content - the full PHP class definition as a string
		 * @param array $oneToManyProperties OneToMany properties to initialize - array of property details
		 * @return string Updated content - the modified class content with proper collection initialization
		 */
		protected function updateConstructor(string $content, array $oneToManyProperties): string {
			// First, determine if a constructor already exists in the class
			// This check uses a regex pattern that matches constructors with any visibility
			// (public, private, protected) or with no explicit visibility modifier
			if ($this->constructorExists($content)) {
				// If a constructor exists, we need to modify it to include our collection initializations
				// without disrupting any existing code in the constructor
				// This approach preserves existing constructor logic while adding new initializations
				return $this->updateExistingConstructorWithReflection($content, $oneToManyProperties);
			} else {
				// If no constructor exists, we need to create a new one from scratch
				// The new constructor will contain only the collection initializations
				// It will be placed in the appropriate position within the class structure
				return $this->addNewConstructor($content, $oneToManyProperties);
			}
			
			// Note: Both paths ensure that all OneToMany properties are properly initialized
			// to prevent "Attempting to call methods on a non-object" errors when the
			// entity is instantiated and collections are accessed before items are added
		}
		
		/**
		 * Checks if a constructor exists in the class
		 * This function determines whether an entity class already has a constructor method
		 * defined, to decide whether to add a new constructor or update an existing one
		 * @param string $content Entity file content - the full PHP class definition as a string
		 * @return bool True if constructor exists - indicates whether __construct method is present
		 */
		protected function constructorExists(string $content): bool {
			// Use regular expression to search for constructor method signature
			// Pattern matches constructors with any visibility modifier (public, private, protected)
			// or with no visibility modifier at all
			return preg_match('/\s+(?:public|private|protected)?\s*function\s+__construct\s*\(\s*\)\s*\{/i', $content) === 1;
		}
		
		/**
		 * Updates an existing constructor to initialize OneToMany collections
		 * This function uses Reflection to modify a class constructor
		 * by adding collection initializations for OneToMany relationships
		 * @param string $className Fully qualified class name of the entity
		 * @param array $oneToManyProperties OneToMany properties to initialize
		 * @return string Updated PHP code with constructor updated
		 */
		protected function updateExistingConstructorWithReflection(string $className, array $oneToManyProperties): string {
			// Load the class using reflection
			$reflectionClass = new \ReflectionClass($className);
			
			// Get the current constructor or null if it doesn't exist
			$constructor = $reflectionClass->getConstructor();
			
			// Get the source file
			$fileName = $reflectionClass->getFileName();
			$content = file_get_contents($fileName);
			
			if ($constructor) {
				// Get constructor start and end line
				$startLine = $constructor->getStartLine();
				$endLine = $constructor->getEndLine();
				
				// Get constructor body
				$fileLines = file($fileName);
				$constructorLines = array_slice($fileLines, $startLine - 1, $endLine - $startLine + 1);
				$constructorBody = implode('', $constructorLines);
				
				// Find the position to insert collection initializations (before the closing brace)
				$lastBracePos = strrpos($constructorBody, '}');
				
				// Create the initialization code for collections
				$collectionInit = $this->generateCollectionInitCode($oneToManyProperties);
				
				// Insert the initialization code before the last brace
				$updatedConstructor = substr_replace(
					$constructorBody,
					"\n        " . $collectionInit . "\n    ",
					$lastBracePos - 1,
					0
				);
				
				// Replace the old constructor with the updated one
				return str_replace($constructorBody, $updatedConstructor, $content);
			}
			
			// If no constructor exists, we'd need different logic to add one
			return $content;
		}
		
		/**
		 * Generates the PHP code for initializing collections
		 * @param array $oneToManyProperties
		 * @return string
		 */
		private function generateCollectionInitCode(array $oneToManyProperties): string {
			$code = '';
			
			foreach ($oneToManyProperties as $property) {
				$code .= "\$this->{$property['name']} = new ArrayCollection();\n        ";
			}
			
			return rtrim($code);
		}
		
		/**
		 * Adds a new constructor to initialize OneToMany collections
		 * This function generates a constructor method that initializes Collection objects
		 * for OneToMany relationship properties in an entity class
		 * @param string $content Entity file content - the full PHP class definition as a string
		 * @param array $oneToManyProperties OneToMany properties to initialize - array of property details
		 * @return string Updated content - the modified class content with constructor added
		 */
		protected function addNewConstructor(string $content, array $oneToManyProperties): string {
			// Create constructor method with proper PHPDoc comment block
			$constructorCode = "\n\t/**\n\t * Constructor to initialize collections\n\t */\n\tpublic function __construct() {";
			
			// Iterate through each OneToMany property and add initialization code
			foreach ($oneToManyProperties as $property) {
				$propertyName = $property['name'];
				// Initialize each collection property with a new Collection instance
				// This prevents "null" errors when adding items to the collection later
				$constructorCode .= "\n\t\t\$this->{$propertyName} = new Collection();";
			}
			
			// Close the constructor method
			$constructorCode .= "\n\t}\n";
			
			// Determine where to place the constructor in the class definition
			// Typically after properties but before other methods
			$insertPosition = $this->findConstructorInsertPosition($content);
			
			// Only insert if a valid position was found
			if ($insertPosition !== null) {
				// Splice the constructor into the content at the appropriate position
				// Add a newline before the constructor for clean formatting
				return substr($content, 0, $insertPosition) . "\n" . $constructorCode . substr($content, $insertPosition);
			}
			
			// Return original content if no suitable insertion position found
			return $content;
		}
		
		/**
		 * Finds the position to insert a new constructor in an entity file
		 * This function analyzes PHP class content to determine the optimal position
		 * for inserting a constructor method, following standard code organization practices
		 * @param string $content The complete PHP file content containing the entity class
		 * @return int|null Position (character index) to insert constructor or null if suitable position not found
		 */
		protected function findConstructorInsertPosition(string $content): ?int {
			// Search for the class declaration using a regex pattern.
			if (!preg_match('/class\s+[^{]+\{/i', $content, $classMatch, PREG_OFFSET_CAPTURE)) {
				return null;
			}
			
			// Find the position of the opening brace '{' of the class
			$classOpenBracePos = strpos($content, '{', $classMatch[0][1]);
			
			if ($classOpenBracePos === false) {
				return null;
			}
			
			// Search for all property declarations in the class
			// This regex matches:
			// 1. Properties with visibility modifiers (protected, private, public)
			// 2. Properties without visibility modifiers (directly starting with $)
			// The regex uses a non-capturing group (?:) with alternation to handle both cases
			preg_match_all('/(?:(protected|private|public)\s+|^\s*)\$[^;]+;/im', $content, $propertyMatches, PREG_OFFSET_CAPTURE);
			
			if (!empty($propertyMatches[0])) {
				// If properties are found, we'll insert the constructor after the last property
				$lastPropertyPos = $propertyMatches[0][count($propertyMatches[0]) - 1][1];
				return strpos($content, ';', $lastPropertyPos) + 1;
			}
			
			// If no property declarations were found, insert after class opening brace
			return $classOpenBracePos + 1;
		}
		
		/**
		 * Convert a string to snake case
		 * @url https://stackoverflow.com/questions/40514051/using-preg-replace-to-convert-camelcase-to-snake-case
		 * @param string $string
		 * @return string
		 */
		protected function snakeCase(string $string): string {
			return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
		}
		
		/**
		 * Attempts to get the singular form of a collection name using basic English pluralization rules
		 * This function applies simple rules to convert plural nouns to singular form
		 * @param string $pluralName The plural name to convert to singular form
		 * @return string The singular form of the name
		 */
		protected function getSingularName(string $pluralName): string {
			// Rule 1: Words ending in 'ies' typically come from singular words ending in 'y'
			// Examples: entities -> entity, categories -> category, properties -> property
			if (str_ends_with($pluralName, 'ies')) {
				// Remove 'ies' suffix and add 'y'
				return substr($pluralName, 0, -3) . 'y';
			}
			
			// Rule 2: Words ending in 's' but not 'ss' are typically regular plurals
			// Examples: cars -> car, books -> book
			// The 'ss' check avoids incorrectly transforming words like 'address' to 'addres'
			if (str_ends_with($pluralName, 's') && !str_ends_with($pluralName, 'ss')) {
				// Remove the trailing 's'
				return substr($pluralName, 0, -1);
			}
			
			// Rule 3: If no rules match, return the original name unchanged
			// This handles:
			// - Words that are already singular
			// - Irregular plurals not covered by these simple rules
			// - Uncountable nouns that don't have distinct singular/plural forms
			return $pluralName;
		}
	}