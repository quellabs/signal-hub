<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Commands;
	
	/**
	 * Import required classes for entity management and console interaction
	 */
	use Quellabs\ObjectQuel\CommandRunner\Command;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleInput;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleOutput;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\EntityModifier;
	use Quellabs\ObjectQuel\EntityManager\Configuration;
	
	/**
	 * MakeEntityCommand - CLI command for creating or updating entity classes
	 *
	 * This command allows users to interactively create or update entity classes
	 * through a command-line interface, collecting properties with their types
	 * and constraints.
	 */
	class MakeEntityCommand extends Command {
		
		/**
		 * Entity modifier service for handling entity creation/modification operations
		 * @var EntityModifier
		 */
		private EntityModifier $entityModifier;
		
		/**
		 * Constructor
		 * @param ConsoleInput $input Command line input interface
		 * @param ConsoleOutput $output Command line output interface
		 * @param Configuration $configuration Application configuration
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, Configuration $configuration) {
			parent::__construct($input, $output, $configuration);
			$this->entityModifier = new EntityModifier($configuration);
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
			
			if (!$this->entityModifier->entityExists($entityNamePlus)) {
				$entityPath = realpath($this->configuration->getEntityPath());
				$this->output->writeLn("\nCreating new entity: {$entityPath}/{$entityNamePlus}.php\n");
			} else {
				$entityPath = realpath($this->configuration->getEntityPath());
				$this->output->writeLn("\nUpdating existing entity: {$entityPath}/{$entityNamePlus}.php\n");
			}
			
			// Initialize an empty properties array
			$properties = [];
			
			// Loop to collect multiple property definitions
			while(true) {
				// Get property name or break the loop if empty
				$propertyName = $this->input->ask("New property name (press <return> to stop adding fields)");
				
				if (empty($propertyName)) {
					break;
				}
				
				// Prompt user to select property data type from available options
				$propertyType = $this->input->choice("\nField type", [
					'smallint', 'integer', 'float', 'string', 'text', 'guid', 'date', 'datetime'
				]);
				
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
			return "test description";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public static function getHelp(): string {
			return "test help";
		}
	}