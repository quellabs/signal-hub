<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner;
	
	use Quellabs\ObjectQuel\Configuration;
	
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
		 * Get signature of this command (e.g. make:entity)
		 * @return string
		 */
		abstract public static function getSignature(): string;
		
		/**
		 * Get the description
		 * @return string
		 */
		abstract public static function getDescription(): string;
		
		/**
		 * Get the help text
		 * @return string
		 */
		abstract public static function getHelp(): string;
	}