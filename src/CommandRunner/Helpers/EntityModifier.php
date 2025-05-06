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
		 * @param string $entityName Name of the entity
		 * @param array $properties List of properties to add
		 * @return bool True if successful
		 */
		public function createOrUpdateEntity(string $entityName, array $properties): bool {
			if ($this->entityExists($entityName . "Entity")) {
				return $this->updateEntity($entityName, $properties);
			} else {
				return $this->createNewEntity($entityName, $properties);
			}
		}
		
		/**
		 * Creates a new entity with properties and getters/setters
		 * @param string $entityName Name of the entity
		 * @param array $properties List of properties to add
		 * @return bool True if successful
		 */
		public function createNewEntity(string $entityName, array $properties): bool {
			// Create directory if it doesn't exist
			if (!is_dir($this->configuration->getEntityPath())) {
				mkdir($this->configuration->getEntityPath(), 0755, true);
			}
			
			$content = $this->generateEntityContent($entityName, $properties);
			
			return file_put_contents($this->getEntityPath($entityName . "Entity"), $content) !== false;
		}
		
		/**
		 * Updates an existing entity with new properties and getters/setters
		 * @param string $entityName Name of the entity
		 * @param array $properties List of properties to add
		 * @return bool True if successful
		 */
		public function updateEntity(string $entityName, array $properties): bool {
			$filePath = $this->getEntityPath($entityName . "Entity");
			$content = file_get_contents($filePath);
			
			if ($content === false) {
				return false;
			}
			
			// Find the position to insert new properties (after existing properties, before methods)
			$classContent = $this->parseClassContent($content);
			
			if (!$classContent) {
				return false;
			}
			
			// Add new properties and getters/setters
			$updatedContent = $this->insertProperties($classContent, $properties);
			$updatedContent = $this->insertGettersAndSetters($updatedContent, $properties, $entityName);
			
			return file_put_contents($filePath, $updatedContent) !== false;
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
			
			// Extract sections
			$classBody = substr($content, $classStartPos, $lastBracePos - $classStartPos);
			
			// Split into properties and methods sections
			$methodPattern = '/\s*(public|protected|private)\s+function\s+\w+/';
			$firstMethodPos = preg_match($methodPattern, $classBody, $methodMatch, PREG_OFFSET_CAPTURE) ? $methodMatch[0][1] : strlen($classBody);
			$propertiesSection = trim(substr($classBody, 0, $firstMethodPos));
			$methodsSection = trim(substr($classBody, $firstMethodPos));
			
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
				
				$propertyCode .= "\n\n\t" . $docComment . "\n\t" . $propertyDefinition;
			}
			
			return $classContent['header'] . $propertyCode . "\n\n\t" . $classContent['methods'] . $classContent['footer'];
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
		 * Attempts to get the singular form of a collection name
		 * @param string $pluralName The plural name to make singular
		 * @return string The singular form
		 */
		protected function getSingularName(string $pluralName): string {
			if (str_ends_with($pluralName, 'ies')) {
				return substr($pluralName, 0, -3) . 'y';
			} elseif (str_ends_with($pluralName, 's') && !str_ends_with($pluralName, 'ss')) {
				return substr($pluralName, 0, -1);
			} else {
				return $pluralName;
			}
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
			 * @Orm\Column(type=\"integer\" unsigned=true primary_key=true)
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
			
			$comment = "/**\n\t * @Orm\Column(name=\"{$snakeCaseName}\" type=\"{$type}\"";
			
			if (isset($property['length']) && is_numeric($property['length'])) {
				$comment .= " length={$property['length']}";
			}
			
			if (isset($property['unsigned'])) {
				$comment .= " unsigned=" . ($property['unsigned'] ? "true" : "false");
			}
			
			if ($nullable) {
				$comment .= " nullable=true";
			}
			
			$comment .= ")\n\t */";
			
			return $comment;
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
					return "\n\t/**\n\t * @return CollectionInterface<{$targetEntity}>\n\t */\n\tpublic function {$methodName}(): CollectionInterface {\n\t\treturn \$this->{$propertyName};\n\t}\n";
				}
				
				return "\n\t/**\n\t * Get {$propertyName}\n\t * @return {$nullableIndicator}{$type}\n\t */\n\tpublic function {$methodName}(): {$nullableIndicator}{$type} {\n\t\treturn \$this->{$propertyName};\n\t}\n";
			}
			
			// Handle regular property getter
			$nullable = $property['nullable'] ?? false;
			$type = $property['type'] ?? 'string';
			$phpType = $this->typeToPhpType($type);
			$nullableIndicator = $nullable ? '?' : '';
			
			return "\n\t/**\n\t * Get {$propertyName}\n\t * @return {$nullableIndicator}{$phpType}\n\t */\n\tpublic function {$methodName}(): {$nullableIndicator}{$phpType} {\n\t\treturn \$this->{$propertyName};\n\t}\n";
		}
		
		/**
		 * Generate a setter method for a property
		 * @param array $property Property information
		 * @return string Setter method code
		 */
		protected function generateSetter(array $property): string {
			$propertyName = $property['name'];
			$methodName = 'set' . ucfirst($propertyName);
			
			// Handle relationship setter
			if (isset($property['relationshipType'])) {
				$type = $property['type'];
				$nullable = $property['nullable'] ?? false;
				$nullableIndicator = $nullable ? '?' : '';
				
				return "\n\t/**\n\t * Set {$propertyName}\n\t * @param {$nullableIndicator}{$type} \${$propertyName}\n\t * @return \$this\n\t */\n\tpublic function {$methodName}({$nullableIndicator}{$type} \${$propertyName}): self {\n\t\t\$this->{$propertyName} = \${$propertyName};\n\t\treturn \$this;\n\t}\n";
			}
			
			// Handle regular property setter
			$nullable = $property['nullable'] ?? false;
			$type = $property['type'] ?? 'string';
			$phpType = $this->typeToPhpType($type);
			$nullableIndicator = $nullable ? '?' : '';
			
			return "\n\t/**\n\t * Set {$propertyName}\n\t * @param {$nullableIndicator}{$phpType} \${$propertyName}\n\t * @return \$this\n\t */\n\tpublic function {$methodName}({$nullableIndicator}{$phpType} \${$propertyName}): self {\n\t\t\$this->{$propertyName} = \${$propertyName};\n\t\treturn \$this;\n\t}\n";
		}
		
		/**
		 * Generate a method to add an item to a collection (for OneToMany)
		 * @param array $property Collection property information
		 * @param string $entityName Current entity name (without suffix)
		 * @return string Method code
		 */
		protected function generateCollectionAdder(array $property, string $entityName): string {
			$collectionName = $property['name'];
			$singularName = $this->getSingularName($collectionName);
			$methodName = 'add' . ucfirst($singularName);
			$targetEntity = $property['targetEntity'] . 'Entity';
			
			$inverseSetter = '';
			// If this is part of a bidirectional relationship
			if (!empty($property['mappedBy'])) {
				$mappedBy = $property['mappedBy'];
				
				// Use a setter method named after this entity
				$setterMethod = 'set' . ucfirst($entityName);
				
				$inverseSetter = "\n\t\t// Set the owning side of the relationship\n";
				$inverseSetter .= "\t\t\${$singularName}->{$setterMethod}(\$this);";
			}
			
			return "\n\t/**\n\t * Adds a relation between {$targetEntity} and " . substr($targetEntity, 0, -6) . "\n\t * @param {$targetEntity} \${$singularName}\n\t * @return \$this\n\t */\n\tpublic function {$methodName}({$targetEntity} \${$singularName}): self {\n\t\tif (!\$this->{$collectionName}->contains(\${$singularName})) {" .
				"\n\t\t\t\$this->{$collectionName}[] = \${$singularName};" .
				$inverseSetter .
				"\n\t\t}\n\t\treturn \$this;\n\t}\n";
		}
		
		/**
		 * Generate a method to remove an item from a collection (for OneToMany)
		 * @param array $property Collection property information
		 * @param string $entityName Current entity name (without suffix)
		 * @return string Method code
		 */
		protected function generateCollectionRemover(array $property, string $entityName): string {
			$collectionName = $property['name'];
			$singularName = $this->getSingularName($collectionName);
			$methodName = 'remove' . ucfirst($singularName);
			$targetEntity = $property['targetEntity'] . 'Entity';
			
			$inverseRemover = '';
			// If this is part of a bidirectional relationship
			if (!empty($property['mappedBy'])) {
				// Use a setter method named after this entity
				$setterMethod = 'set' . ucfirst($entityName);
				
				// Add remover code
				$inverseRemover = "\n\t\t// Unset the owning side of the relationship\n";
				$inverseRemover .= "\t\t\${$singularName}->{$setterMethod}(null);";
			}
			
			return "\n\t/**\n\t * Removes a relation between {$targetEntity} and " . substr($targetEntity, 0, -6) . "\n\t * @param {$targetEntity} \${$singularName}\n\t * @return \$this\n\t */\n\tpublic function {$methodName}({$targetEntity} \${$singularName}): self {\n\t\tif (\$this->{$collectionName}->remove(\${$singularName})) {" .
				$inverseRemover .
				"\n\t\t}\n\t\treturn \$this;\n\t}\n";
		}
	}