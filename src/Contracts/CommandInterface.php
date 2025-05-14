<?php
	
	namespace Quellabs\Sculpt\Contracts;
	
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	
	/**
	 * CommandInterface
	 *
	 * Defines the contract that all CLI commands must implement in the Sculpt framework.
	 * This interface ensures a consistent structure for all commands, allowing the framework
	 * to discover, register, and execute them in a standardized way.
	 *
	 * Commands are the primary way users interact with the application through the CLI.
	 * Each command is responsible for a specific task and is identified by a unique signature
	 * in the format "namespace:name" (e.g., "make:entity", "db:migrate").
	 */
	interface CommandInterface {
		
		/**
		 * Command constructor
		 *
		 * Initializes a new command with input and output handlers.
		 * These handlers allow the command to interact with the console by
		 * reading user input and displaying output.
		 *
		 * @param ConsoleInput $input Provides methods to read user input from the console
		 * @param ConsoleOutput $output Provides methods to write output to the console
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output);
		
		/**
		 * Execute the command
		 *
		 * This is the main entry point for running the command's logic.
		 * Implementations should contain the core functionality of the command.
		 * The method should return an exit code, where 0 typically indicates success
		 * and any non-zero value indicates an error condition.
		 *
		 * @param array $parameters Command-line arguments passed to this command
		 * @return int Exit code (0 for success, non-zero for errors)
		 */
		public function execute(array $parameters = []): int;
		
		/**
		 * Get signature of this command
		 *
		 * Returns the unique identifier for this command in the format "namespace:name".
		 * The signature is used to register and look up the command in the application.
		 * For example: "make:entity", "db:migrate", "cache:clear".
		 *
		 * The namespace (part before the colon) is used to group related commands
		 * when displaying the command list to users.
		 *
		 * @return string The command signature in "namespace:name" format
		 */
		public function getSignature(): string;
		
		/**
		 * Get the description
		 *
		 * Returns a short, single-line description of what the command does.
		 * This is displayed in the command list when users run the application
		 * without specifying a command.
		 *
		 * The description should be concise but informative enough to help users
		 * understand the command's purpose at a glance.
		 *
		 * @return string A brief description of the command's purpose
		 */
		public function getDescription(): string;
		
		/**
		 * Get the help text
		 *
		 * Returns detailed usage instructions for the command.
		 * This typically includes:
		 * - A more thorough explanation of what the command does
		 * - Available arguments and options with their descriptions
		 * - Usage examples
		 * - Any relevant notes or warnings
		 *
		 * The help text is displayed when users explicitly request help for a
		 * specific command (e.g., by using "--help" flag or a dedicated help command).
		 *
		 * @return string Detailed usage instructions and information
		 */
		public function getHelp(): string;
	}