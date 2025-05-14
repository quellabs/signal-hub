<?php
	
	namespace Quellabs\Sculpt;
	
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	use Quellabs\Sculpt\Contracts\CommandInterface;
	
	/**
	 * This is the main entry point for the Sculpt CLI application, responsible for
	 * discovering and registering service providers from installed packages,
	 * registering and executing commands.
	 */
	class Application {
		
		/**
		 * @var string|null
		 */
		private ?string $basePath;
		
		/**
		 * Registered command instances indexed by their signature
		 * Commands are the primary way users interact with the application
		 */
		protected array $commands = [];
		
		/**
		 * Console input handler for reading user input
		 */
		protected ConsoleInput $input;
		
		/**
		 * Console output handler for displaying results to the user
		 */
		protected ConsoleOutput $output;
		
		/**
		 * Provider manager responsible for service provider discovery and management
		 */
		protected ServiceDiscoverer $serviceDiscoverer;
		
		/**
		 * Application constructor
		 * @param ConsoleInput $input Handler for reading from console
		 * @param ConsoleOutput $output Handler for writing to console
		 * @param string|null $basePath
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?string $basePath = null) {
			// Store input and output classes
			$this->input = $input;
			$this->output = $output;
			
			// Set base path (where src directory is located)
			if ($basePath === null) {
				// Default to 2 directories up from this file (assuming this file is in src/Application.php)
				$this->basePath = dirname(__DIR__);
			} else {
				$this->basePath = $basePath;
			}
			
			// Initialize the provider manager
			$this->serviceDiscoverer = new ServiceDiscoverer($this, $this->output, $this->basePath);
			
			// Register internal commands
			$this->discoverInternalCommands();
		}
		
		/**
		 * Returns the input class
		 * @return ConsoleInput
		 */
		public function getInput(): ConsoleInput {
			return $this->input;
		}
		
		/**
		 * Returns the output class
		 * @return ConsoleOutput
		 */
		public function getOutput(): ConsoleOutput {
			return $this->output;
		}
		
		/**
		 * Get the service discoverer instance
		 * @return ServiceDiscoverer
		 */
		public function getServiceDiscoverer(): ServiceDiscoverer {
			return $this->serviceDiscoverer;
		}

		/**
		 * Returns the base path of the application
		 * @return string
		 */
		public function getBasePath(): string {
			return $this->basePath;
		}
		
		/**
		 * Automatically finds and registers all command classes in the built-in
		 * Commands directory. This provides the core functionality of the framework.
		 * @return void
		 */
		public function discoverInternalCommands(): void {
			// Define the namespace prefix for internal commands
			$namespace = 'Quellabs\\Sculpt\\Commands\\';
			
			// Get the absolute path to the commands directory
			$commandsDir = $this->getBasePath() . '/src/Commands';
			
			// Handle gracefully if the directory doesn't exist
			// This prevents errors if the directory structure changes
			if (!is_dir($commandsDir)) {
				return;
			}
			
			// Iterate through files in the commands directory
			foreach (new \DirectoryIterator($commandsDir) as $file) {
				// Skip directory navigation entries (. and ..), subdirectories, and non-PHP files
				// This ensures we only process actual command class files
				if ($file->isDot() || $file->isDir() || $file->getExtension() !== 'php') {
					continue;
				}
				
				// Construct the fully qualified class name
				// Combines the namespace prefix with the filename (without extension)
				$className = $namespace . pathinfo($file->getFilename(), PATHINFO_FILENAME);
				
				// Instantiate and register command classes that implement CommandInterface
				// This validation ensures only proper command classes are registered
				if (class_exists($className) && is_subclass_of($className, CommandInterface::class)) {
					// Create a new instance of the command with required dependencies
					$command = new $className($this->input, $this->output, $this);
					
					// Register the command in the commands collection using its signature as the key
					// This allows commands to be looked up by their signature (e.g., "db:migrate")
					$this->commands[$command->getSignature()] = $command;
				}
			}
		}
		
		/**
		 * Discover and register service providers from installed packages
		 * @return void
		 */
		public function discoverProviders(): void {
			// Delegate to the service discoverer
			$this->getServiceDiscoverer()->discoverProviders();
		}
		
		/**
		 * Adds a command to the list of available commands, indexed by its signature.
		 * Commands are the primary way users interact with the application.
		 * @param CommandInterface $command The command instance to register
		 */
		public function registerCommand(CommandInterface $command): void {
			$this->commands[$command->getSignature()] = $command;
		}
		
		/**
		 * Verifies if a command with the given signature is registered in the application.
		 * @param string $name The command signature to check
		 * @return bool True if the command exists, false otherwise
		 */
		public function hasCommand(string $name): bool {
			return isset($this->commands[$name]);
		}
		
		/**
		 * Retrieves a registered command by its signature.
		 * @param string $name The command signature
		 * @return CommandInterface|null The command instance or null if not found
		 */
		public function getCommand(string $name): ?CommandInterface {
			return $this->commands[$name] ?? null;
		}
		
		/**
		 * Parses command-line arguments, finds the requested command,
		 * and executes it. If no command is specified or the command is not found,
		 * it displays the list of available commands.
		 * @param array $args Command-line arguments (typically from $argv)
		 * @return int Exit code (0 for success, non-zero for errors)
		 */
		public function run(array $args = []): int {
			// First argument after script name is the command
			$commandName = $args[1] ?? null;
			
			// If no command specified, show available commands
			if (!$commandName) {
				return $this->listCommands();
			}
			
			// Check if command exists
			if (!$this->hasCommand($commandName)) {
				$this->output->warning("Command '{$commandName}' not found.");
				return $this->listCommands();
			}
			
			// Get the command and execute it
			// Pass remaining arguments to the command
			$command = $this->commands[$commandName];
			return $command->execute(array_slice($args, 2));
		}
		
		/**
		 * Displays a formatted list of all registered commands, grouped by provider namespace
		 * @return int Exit code
		 */
		protected function listCommands(): int {
			// Group commands by provider namespace
			$providerGroups = [];
			
			// First, collect commands without a provider (internal commands)
			$internalCommands = [];
			
			foreach ($this->commands as $signature => $command) {
				$provider = $command->getProvider();
				
				if ($provider === null) {
					$internalCommands[$signature] = $command;
				} else {
					$namespace = $provider->getNamespace();
					$providerGroups[$namespace]['provider'] = $provider;
					$providerGroups[$namespace]['commands'][$signature] = $command;
				}
			}
			
			// Begin output with a header
			$this->output->writeLn("\n<bold><white>Sculpt CLI</white></bold> - Command Line Interface\n");
			
			// First display internal commands if there are any
			if (!empty($internalCommands)) {
				$this->output->writeLn("<bg_cyan><black>[core]</black></bg_cyan>");
				
				// Calculate padding for alignment
				$maxLength = max(array_map(fn($s) => strlen($s), array_keys($internalCommands)));
				
				// Display each command in the core group
				foreach ($internalCommands as $signature => $command) {
					$padding = str_repeat(' ', $maxLength - strlen($signature) + 4);
					$this->output->writeLn("  <green>{$signature}</green>{$padding}{$command->getDescription()}");
				}
				
				// Add blank line after the group
				$this->output->writeLn("");
			}
			
			// Sort provider groups alphabetically by namespace
			ksort($providerGroups);
			
			// Display commands for each provider group
			foreach ($providerGroups as $namespace => $group) {
				$provider = $group['provider'];
				$commands = $group['commands'];
				
				// Display the provider namespace and description
				$this->output->writeLn("<bg_cyan><black>[{$namespace}]</black></bg_cyan>");
				$this->output->writeLn("<dim>{$provider->getDescription()}</dim>");
				
				// Calculate padding for alignment
				$maxLength = max(array_map(fn($s) => strlen($s), array_keys($commands)));
				
				// Display each command in the current provider group
				foreach ($commands as $signature => $command) {
					$padding = str_repeat(' ', $maxLength - strlen($signature) + 4);
					$this->output->writeLn("  <green>{$signature}</green>{$padding}{$command->getDescription()}");
				}
				
				// Add blank line after each provider group
				$this->output->writeLn("");
			}
			
			// Display usage instructions at the end
			$this->output->writeLn("<bold>Usage:</bold>");
			$this->output->writeLn("  sculpt <green>command</green> [arguments]");
			$this->output->writeLn("  sculpt <green>help</green> command   <dim>(for detailed help)</dim>");
			
			// Return success code
			return 0;
		}
	}