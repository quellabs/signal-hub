<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner;
	
	use Quellabs\ObjectQuel\EntityManager\Configuration;
	
	abstract class Command {
		
		protected ConsoleInput $input;
		protected ConsoleOutput $output;
		protected Configuration $configuration;
		
		/**
		 * Command constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, Configuration $configuration) {
			$this->configuration = $configuration;
			$this->input = $input;
			$this->output = $output;
		}
		
		/**
		 * Execute the command
		 * @param array $parameters
		 * @return int
		 */
		abstract public function execute(array $parameters=[]): int;
		
		/**
		 * Haal signature op van dit commando (e.g. make:entity)
		 * @return string
		 */
		abstract public static function getSignature(): string;
		
		/**
		 * Haal de beschrijving op
		 * @return string
		 */
		abstract public static function getDescription(): string;

		/**
		 * Haal de hulp tekst op
		 * @return string
		 */
		abstract public static function getHelp(): string;
	}