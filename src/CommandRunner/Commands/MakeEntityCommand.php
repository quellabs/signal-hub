<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Commands;
	
	/**
	 * Import required classes for entity management and console interaction
	 */
	
	use Quellabs\ObjectQuel\CommandRunner\Command;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleInput;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleOutput;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\EntityModifier;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\EntityScanner;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\EntityManager\EntityStore;
	
	/**
	 * MakeEntityCommand - CLI command for creating or updating entity classes
	 *
	 * This command allows users to interactively create or update entity classes
	 * through a command-line interface, collecting properties with their types
	 * and constraints, including relationship definitions with primary key selection.
	 */
	class MakeEntityCommand extends Command {
		
		/**
		 * Entity modifier service for handling entity creation/modification operations
		 * @var EntityModifier
		 */
		private EntityModifier $entityModifier;
		
		/**
		 * Entity scanner for finding available entities and their properties
		 * @var EntityScanner
		 */
		private EntityScanner $entityScanner;
		
		/**
		 * Entity store for handling entity metadata
		 * @var EntityStore
		 */
		private EntityStore $entityStore;
		
		/**
		 * Constructor
		 * @param ConsoleInput $input Command line input interface
		 * @param ConsoleOutput $output Command line output interface
		 * @param Configuration $configuration Application configuration
		 * @param EntityStore $entityStore Entity store for metadata
		 */
		public function __construct(
			ConsoleInput  $input,
			ConsoleOutput $output,
			Configuration $configuration
		) {
			parent::__construct($input, $output, $configuration);
			$this->entityStore = new EntityStore($configuration);
			$this->entityModifier = new EntityModifier($configuration);
			$this->entityScanner = new EntityScanner($configuration, $this->entityStore);
		}
		
		/**
		 * Gets the target entity and reference field information for a relationship
		 *
		 * @param array $availableEntities List of available entities
		 * @return array Associative array with targetEntity, referencedField, and referencedColumnName
		 */
		private function getTargetEntityAndReferenceField(array $availableEntities): array {
			// Set default values
			$result = [
				'targetEntity'         => '',
				'referencedField'      => 'id',
				'referencedColumnName' => 'id'
			];
			
			// Get the target entity (either from selection or manual entry)
			$result['targetEntity'] = $this->selectTargetEntity($availableEntities);
			
			// If we have a valid existing entity, get its primary key
			if (in_array($result['targetEntity'], $availableEntities)) {
				$referenceInfo = $this->getTargetEntityReferenceField($result['targetEntity']);
				$result['referencedField'] = $referenceInfo['field'];
				$result['referencedColumnName'] = $referenceInfo['column'];
			}
			
			return $result;
		}
		
		/**
		 * Asks the user to select a target entity from available options or enter one manually
		 * @param array $availableEntities List of available entities
		 * @return string Selected or entered entity name
		 */
		private function selectTargetEntity(array $availableEntities): string {
			// If no entities available, ask for manual entry
			if (empty($availableEntities)) {
				return $this->input->ask("\nTarget entity name (without 'Entity' suffix)");
			}
			
			// Allow selecting from list or manual entry
			$targetEntityOptions = array_merge($availableEntities, ['[Enter manually]']);
			$targetEntityChoice = $this->input->choice("\nSelect target entity", $targetEntityOptions);
			
			if ($targetEntityChoice === '[Enter manually]') {
				return $this->input->ask("\nTarget entity name (without 'Entity' suffix)");
			}
			
			return $targetEntityChoice;
		}
		
		/**
		 * Gets the reference field and column information for a target entity
		 * @param string $targetEntity Name of the target entity
		 * @return array Associative array with field and column names
		 */
		private function getTargetEntityReferenceField(string $targetEntity): array {
			// Get primary keys from the target entity
			$primaryKeys = $this->entityScanner->getEntityPrimaryKeys($targetEntity);
			
			// Default values
			$result = [
				'field' => 'id',
				'column' => 'id'
			];
			
			if (empty($primaryKeys)) {
				$this->output->writeLn("\nNo primary keys found in target entity. Using 'id' as default.");
				return $result;
			}
			
			// If we have multiple primary keys, let the user choose
			if (count($primaryKeys) > 1) {
				$this->output->writeLn("\nMultiple primary keys found in target entity:");
				$result['field'] = $this->input->choice("\nSelect the primary key field to reference", $primaryKeys);
			} else {
				$result['field'] = $primaryKeys[0];
			}
			
			// Get the actual column name for the selected primary key
			$fullEntityName = $this->configuration->getEntityNameSpace() . '\\' . $targetEntity . 'Entity';
			$columnMap = $this->entityStore->getColumnMap($fullEntityName);
			$result['column'] = $columnMap[$result['field']] ?? $result['field'];
			
			$this->output->writeLn("\nUsing primary key: {$result['field']} (DB column: {$result['column']})");
			
			return $result;
		}
	
		/**
		 * Execute the command
		 * @param array $parameters Optional parameters passed to the command
		 * @return int Exit code (0 for success)
		 */
		public function execute(array $parameters = []): int {
			// Ask for entity name
			$entityName = $this->input->ask("Class name of the entity to create or update (e.g. AgreeableElephant)");
			
			// If none given, do nothing and exit gracefully
			if (empty($entityName)) {
				return 0;
			}
			
			// Show the appropriate message to user based on whether the entity exists
			$entityNamePlus = $entityName . "Entity";
			$entityPath = realpath($this->configuration->getEntityPath());

			if (!$this->entityModifier->entityExists($entityNamePlus)) {
				$this->output->writeLn("\nCreating new entity: {$entityPath}/{$entityNamePlus}.php\n");
			} else {
				$this->output->writeLn("\nUpdating existing entity: {$entityPath}/{$entityNamePlus}.php\n");
			}
			
			// Get list of available entities for relationships
			$availableEntities = [];
			$entityPath = $this->configuration->getEntityPath();
			
			if (is_dir($entityPath)) {
				$files = scandir($entityPath);
				
				foreach ($files as $file) {
					// Skip directories and non-php files
					if (is_dir($entityPath . '/' . $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
						continue;
					}
					
					// Extract entity name without "Entity" suffix and .php extension
					$entityFileName = pathinfo($file, PATHINFO_FILENAME);
					
					if (str_ends_with($entityFileName, 'Entity')) {
						$availableEntities[] = substr($entityFileName, 0, -6);
					}
				}
			}
			
			// Initialize an empty properties array
			$properties = [];
			
			// Loop to collect multiple property definitions
			while (true) {
				// Get property name or break the loop if empty
				$propertyName = $this->input->ask("New property name (press <return> to stop adding fields)");
				
				if (empty($propertyName)) {
					break;
				}
				
				// Prompt user to select property data type from available options
				$propertyType = $this->input->choice("\nField type", [
					'smallint', 'integer', 'float', 'string', 'text', 'guid', 'date', 'datetime', 'relationship'
				]);
				
				// If the type is relationship, collect relationship details
				if ($propertyType === 'relationship') {
					// Collect relationship information
					$relationshipType = $this->input->choice("\nRelationship type", [
						'OneToOne', 'OneToMany', 'ManyToOne'
					]);
					
					// Get target entity and reference field information
					$targetInfo = $this->getTargetEntityAndReferenceField($availableEntities);
					$targetEntity = $targetInfo['targetEntity'];
					$referencedField = $targetInfo['referencedField'];
					$referencedColumnName = $targetInfo['referencedColumnName'];
					
					// For OneToMany and ManyToOne, ask for mappedBy/inversedBy
					$mappedBy = null;
					$inversedBy = null;
					$joinColumnName = null;
					
					if ($relationshipType === 'ManyToOne' || $relationshipType === 'OneToOne') {
						// For ManyToOne and owning side of OneToOne, generate join column name
						$joinColumnName = lcfirst($targetEntity) . ucfirst($referencedField);
						$this->output->writeLn("\nJoin column name (for storage in database): " . $joinColumnName);
					}
					
					// Handle relationship type-specific configuration
					switch ($relationshipType) {
						case 'OneToMany':
							// For a OneToMany relationship, the mappedBy field should be the same as
							// the primary key field selected for the reference
							
							// We already asked the user to select a primary key field to reference
							// So we'll use the same field for mappedBy
							$mappedBy = $referencedField;
							
							$this->output->writeLn("\nUsing '{$mappedBy}' as the mappedBy field in the related entity");
							break;
							
						case 'ManyToOne':
							// Ask if this is a bidirectional relationship
							$bidirectional = $this->input->confirm("\nIs this a bidirectional relationship?", false);
							
							if ($bidirectional) {
								// Suggest a name for the collection in the target entity
								$suggestedCollectionName = lcfirst($entityName) . 's';
								$inversedBy = $this->input->ask("\nInversedBy field name in the related entity", $suggestedCollectionName);
							}
							
							break;
						
						case 'OneToOne':
							// For OneToOne, ask if this is the owning side
							$isOwningSide = $this->input->confirm("\nIs this the owning side of the relationship?", true);
							
							if (!$isOwningSide) {
								// If not the owning side, ask for mappedBy
								$suggestedFieldName = lcfirst($entityName);
								$mappedBy = $this->input->ask("\nMappedBy field name in the related entity", $suggestedFieldName);
							} else {
								// If owning side, ask if it's bidirectional
								$bidirectional = $this->input->confirm("\nIs this a bidirectional relationship?", false);
								
								if ($bidirectional) {
									// Suggest a name for the field in the target entity
									$suggestedFieldName = lcfirst($entityName);
									$inversedBy = $this->input->ask("\nInversedBy field name in the related entity", $suggestedFieldName);
								}
							}
							
							break;
					}
					
					// For OneToMany, the property will be a collection
					if ($relationshipType === 'OneToMany') {
						$propertyPhpType = "CollectionInterface";
					} else {
						$propertyPhpType = $targetEntity . "Entity";
					}
					
					// Add the relationship property
					$properties[] = [
						"name"                 => $propertyName,
						"type"                 => $propertyPhpType,
						"relationshipType"     => $relationshipType,
						"targetEntity"         => $targetEntity,
						"mappedBy"             => $mappedBy,
						"inversedBy"           => $inversedBy,
						"joinColumnName"       => $joinColumnName,
						"referencedColumnName" => $referencedColumnName,
						"nullable"             => $this->input->confirm("\nAllow this relationship to be null?", $relationshipType === 'ManyToOne'),
					];
					
					// Continue to next property
					continue;
				}
				
				// For string type, ask for length; otherwise set to null
				if ($propertyType == 'string') {
					$propertyLength = $this->input->ask("\nMaximum character length for this string field", "255");
				} else {
					$propertyLength = null;
				}
				
				// For integer types, ask if unsigned; otherwise set to null
				if (in_array($propertyType, ['integer', 'smallint'])) {
					$unsigned = $this->input->confirm("\nShould this number field store positive values only (unsigned)?", false);
				} else {
					$unsigned = null;
				}
				
				// Ask if property can be nullable in the database
				$propertyNullable = $this->input->confirm("\nAllow this field to be empty/null in the database?", false);
				
				// Add collected property info to the property array
				$properties[] = [
					"name"     => $propertyName,
					"type"     => $propertyType,
					"length"   => $propertyLength,
					'unsigned' => $unsigned,
					"nullable" => $propertyNullable,
				];
			}
			
			// If properties were defined, create or update the entity
			if (!empty($properties)) {
				$this->entityModifier->createOrUpdateEntity($entityName, $properties);
				$this->output->writeLn("Entity details written");
			}
			
			// Return success code
			return 0;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public static function getSignature(): string {
			return "make:entity";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public static function getDescription(): string {
			return "Create or update an entity class with properties and relationships";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public static function getHelp(): string {
			return "Creates or updates an entity class with standard properties and ORM relationship mappings.\n" .
				"Supported relationship types: OneToOne, OneToMany, ManyToOne.\n\n" .
				"Relationships can be established with specific primary key columns in the target entity.";
		}
	}