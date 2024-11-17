<?php
	
	namespace Services\CommandRunner\Commands;
	
	class MakeEntityCommand extends \Services\CommandRunner\Command {
		
		public function execute(array $parameters = []): int {
			// Ask for entity name
			$entityName = $this->input->ask("Class name of the entity to create or update (e.g. AgreeableElephant)");
			$this->output->writeLn("\ncreated: src/Entity/{$entityName}.php\n");
			
			// Ask for property names
			while(true) {
				$propertyName = $this->input->ask("New property name (press <return> to stop adding fields)");
				
				if (empty($propertyName)) {
					break;
				}
				
				$propertyName = $this->input->ask("\nField type", "string");
				$propertyLength = $this->input->ask("\nField length", "100");
				$propertyNullable = $this->input->confirm("\nCan this field be null in the database (nullable)", false);
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