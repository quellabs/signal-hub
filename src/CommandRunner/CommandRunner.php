<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner;
	
	use Quellabs\ObjectQuel\Kernel\Kernel;
	use Quellabs\ObjectQuel\Kernel\ServiceInterface;
	
	class CommandRunner implements ServiceInterface {

		protected array $commands;
		protected Kernel $kernel;
		protected ConsoleOutput $consoleOutput;
		protected ConsoleInput $consoleInput;
		
		/**
		 * List of supported classes
		 * @var array<class-string>
		 */
		private const array SUPPORTED_CLASSES = [
			ConsoleOutput::class,
			ConsoleInput::class
		];
		
		/**
		 * CommandRunner constructor
		 * @param Kernel $kernel
		 */
		public function __construct(Kernel $kernel) {
			$this->commands = [];
			$this->kernel = $kernel;
			$this->consoleOutput = new ConsoleOutput();
			$this->consoleInput = new ConsoleInput($this->consoleOutput);
			
			// Scan the list of commands
			$this->scanCommands(dirname(__FILE__) . '/Commands');
		}
		
		/**
		 * Scan the commands
		 * @param string $directory
		 * @return void
		 */
		private function scanCommands(string $directory): void {
			$currentDirname = dirname(__FILE__);
			$currentDirnameLength = strlen($currentDirname) + 1;
			
			foreach (glob($directory . '/*') as $item) {
				// Recursief scannen voor subdirectories
				if (is_dir($item)) {
					$this->scanCommands($item);
					continue;
				}
				
				// Command registreren
				if (str_ends_with($item, 'Command.php')) {
					$itemWithoutDirectory = substr($item, $currentDirnameLength);
					$itemWithoutPhp = substr($itemWithoutDirectory, 0, -4);
					$itemFixedSlashes = str_replace("/", "\\", $itemWithoutPhp);
					$itemAddedNamespace = "Services\\CommandRunner\\{$itemFixedSlashes}";
					
					$this->commands[$itemAddedNamespace::getSignature()] = $itemAddedNamespace;
				}
			}
		}
		
		/**
		 * Voer het commando uit
		 * @param array $args
		 * @return void
		 */
		public function run(array $args=[]): void {
			// The first argument is the command we want to execute
			$commandName = $args[1] ?? '';
			
			// Check if the command exists. If so, execute it.
			if (isset($this->commands[$commandName])) {
				$className = $this->commands[$commandName];
				$object = new $className(... $this->kernel->autowireClass($className));
				$object->execute($args);
				return;
			}

			// Otherwise show a list of possible commands
			$this->listCommands();
		}
		
		/**
		 * Lists all available commands grouped by their prefix
		 * Displays commands in a formatted console output with descriptions
		 *
		 * Format example:
		 * [group1]
		 *   group1:command1    Description of command1
		 *   group1:command2    Description of command2
		 *
		 * [group2]
		 *   group2:command1    Description of command1
		 *
		 * @return void
		 */
		public function listCommands(): void {
			// Initialize an array to store commands grouped by prefix
			$groups = [];
			
			// Loop through all registered commands and organize them into groups
			foreach ($this->commands as $signature => $class) {
				// Split command signature into group and command name
				// If no group specified, empty string is used as default
				[$group, $command] = explode(':', $signature) + [1 => ''];
				
				// Store command info under its group
				$groups[$group][] = [
					'signature'   => $signature,
					'description' => $class::getDescription() // Get command description from command class
				];
			}
			
			// Get console output interface for writing
			$output = $this->consoleOutput;
			
			// Print header
			$output->writeLn("\nAvailable Commands:\n");
			
			// Iterate through each group of commands
			foreach ($groups as $group => $commands) {
				// Print group header
				$output->writeLn("[" . $group . "]");
				
				// Calculate padding needed for aligned output
				// Find the longest command signature in current group
				$maxLength = max(array_map(fn($cmd) => strlen($cmd['signature']), $commands));
				
				// Print each command in the group
				foreach ($commands as $command) {
					// Calculate padding between command signature and description
					// Add 4 extra spaces for consistent spacing
					$padding = str_repeat(' ', $maxLength - strlen($command['signature']) + 4);
					
					// Output formatted command line:
					// - 2 spaces indentation
					// - command signature
					// - calculated padding
					// - command description
					$output->writeLn("  " . $command['signature'] . $padding . $command['description']);
				}
				
				// Add blank line between groups
				$output->writeLn("");
			}
			
			// Print usage instructions
			$output->writeLn("To run a command: php bin/sculpt command [arguments]");
			$output->writeLn("To get help: php bin/sculpt command --help");
		}
		
		/**
		 * Checks if the Service supports the given class
		 * @param class-string $class
		 * @return bool
		 */
		public function supports(string $class): bool {
			return in_array($class, self::SUPPORTED_CLASSES, true);
		}
		
		/**
		 * Returns an instance of the requested class
		 * @param class-string $class
		 * @param array<string, mixed> $parameters Currently unused, but kept for interface compatibility
		 * @return object|null The requested instance or null if class is not supported
		 */
		public function getInstance(string $class, array $parameters = []): ?object {
			if (php_sapi_name() !== 'cli') {
				return null;
			}
			
			return match ($class) {
				ConsoleOutput::class => $this->consoleOutput,
				ConsoleInput::class => $this->consoleInput,
				default => null
			};
		}
	}