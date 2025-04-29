<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Commands;
	
	use Quellabs\ObjectQuel\CommandRunner\Command;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleInput;
	use Quellabs\ObjectQuel\CommandRunner\ConsoleOutput;
	use Quellabs\ObjectQuel\CommandRunner\Helpers\EntityModifier;
	use Quellabs\ObjectQuel\EntityManager\Configuration;
	
	class MakeEntityCommand extends Command {
		
		private EntityModifier $entityModifier;
		
		/**
		 * Constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param Configuration $configuration
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, Configuration $configuration) {
			parent::__construct($input, $output, $configuration);
			$this->entityModifier = new EntityModifier($configuration);
		}
		
		public function execute(array $parameters = []): int {
			// Ask for entity name
			$entityName = $this->input->ask("Class name of the entity to create or update (e.g. AgreeableElephant)");
			
			// If none given, do nothing
			if (empty($entityName)) {
				return 0;
			}
			
			// Show message to user
			$entityNamePlus = $entityName . "Entity";

			if (!$this->entityModifier->entityExists($entityNamePlus)) {
				$entityPath = realpath($this->configuration->getEntityPath());
				$this->output->writeLn("\nCreating new entity: {$entityPath}/{$entityNamePlus}.php\n");
			} else {
				$entityPath = realpath($this->configuration->getEntityPath());
				$this->output->writeLn("\nUpdating existing entity: {$entityPath}/{$entityNamePlus}.php\n");
			}
			
			// Ask for property names
			$properties = [];
			
			while(true) {
				$propertyName = $this->input->ask("New property name (press <return> to stop adding fields)");
				
				if (empty($propertyName)) {
					break;
				}
				
				$propertyType = $this->input->ask("\nField type", "string");
				$propertyLength = $this->input->ask("\nField length", "255");
				$propertyNullable = $this->input->confirm("\nCan this field be null in the database (nullable)?", false);
				
				$properties[] = [
					"name"     => $propertyName,
					"type"     => $propertyType,
					"length"   => $propertyLength,
					"nullable" => $propertyNullable,
				];
			}
			
			if (!empty($properties)) {
				$this->entityModifier->createOrUpdateEntity($entityName, $properties);
				$this->output->writeLn("Entity details written");
			}
			
			return 0;
		}
		
		public static function getSignature(): string {
			return "make:entity";
		}
		
		public static function getDescription(): string {
			return "test description";
		}
		
		public static function getHelp(): string {
			return "test help";
		}
	}