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
		 * @var mixed|string
		 */
		private ?string $basePath;
		
		/**
		 * Array of registered service provider instances
		 * These providers extend the application functionality
		 */
		protected array $serviceProviders = [];
		
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
		 * Discover and register service providers from installed packages
		 *
		 * Reads the Composer's installed.json file to find packages that have
		 * registered a Sculpt service provider. This allows third-party packages
		 * to extend the application's functionality.
		 *
		 * The two-phase registration (register, then boot) ensures all providers
		 * are registered before any of them are booted, allowing dependencies
		 * between providers.
		 */
		public function discoverProviders(): void {
			// First try to find providers in the parent project's composer.json
			$projectComposerPath = $this->getProjectComposerPath();
			$projectProviders = [];
			
			if ($projectComposerPath && file_exists($projectComposerPath)) {
				$projectComposer = json_decode(file_get_contents($projectComposerPath), true);
				
				// Debug
				$this->output->writeLn("Looking for providers in parent project: " . $projectComposerPath);
				
				// Check if the project has defined sculpt providers or sculpt provider
				if (isset($projectComposer['extra']['sculpt']['providers']) && is_array($projectComposer['extra']['sculpt']['providers'])) {
					$projectProviders = $projectComposer['extra']['sculpt']['providers'];
				} elseif (isset($projectComposer['extra']['sculpt']['provider'])) {
					$projectProviders = [$projectComposer['extra']['sculpt']['provider']];
				}
				
				// Register providers from parent project
				foreach ($projectProviders as $providerClass) {
					$this->output->writeLn("Found project provider: $providerClass");
					if (class_exists($providerClass)) {
						$provider = new $providerClass($this);
						$this->serviceProviders[] = $provider;
						$provider->register($this);
					} else {
						$this->output->warning("Provider class not found: $providerClass");
					}
				}
			}
			
			// Then look for providers in installed packages (original method)
			$composerFile = $this->getComposerInstalledPath();
			
			if (!$composerFile || !file_exists($composerFile)) {
				// If no installed.json and no project providers, we're done
				if (empty($projectProviders)) {
					$this->output->warning("No providers found in project or installed packages");
				}
				return;
			}
			
			$packagesJson = file_get_contents($composerFile);
			
			if (!$packagesJson) {
				return;
			}
			
			$packages = json_decode($packagesJson, true);
			
			if (!$packages) {
				return;
			}
			
			$packagesList = $packages['packages'] ?? $packages;
			
			// Register providers from installed packages
			foreach ($packagesList as $package) {
				if (!isset($package['extra']['sculpt']['provider'])) {
					continue;
				}
				
				$providerClass = $package['extra']['sculpt']['provider'];
				
				// Skip if already registered from project
				if (in_array($providerClass, $projectProviders)) {
					continue;
				}
				
				if (class_exists($providerClass)) {
					$provider = new $providerClass($this);
					$this->serviceProviders[] = $provider;
					$provider->register($this);
				}
			}
			
			// Boot all registered providers
			foreach ($this->serviceProviders as $provider) {
				$provider->boot($this);
			}
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
		 * Automatically finds and registers all command classes in the built-in
		 * Commands directory. This provides the core functionality of the framework.
		 * @return void
		 */
		protected function discoverInternalCommands(): void {
			$namespace = 'Quellabs\\Sculpt\\Commands\\';
			$commandsDir = $this->basePath . '/src/Commands';
			
			// Handle gracefully if the directory doesn't exist
			if (!is_dir($commandsDir)) {
				return;
			}
			
			// Iterate through files in the commands directory
			foreach (new \DirectoryIterator($commandsDir) as $file) {
				if ($file->isDot() || $file->isDir() || $file->getExtension() !== 'php') {
					continue;
				}
				
				$className = $namespace . pathinfo($file->getFilename(), PATHINFO_FILENAME);
				
				// Instantiate and register command classes that implement CommandInterface
				if (class_exists($className) && is_subclass_of($className, CommandInterface::class)) {
					$command = new $className($this->input, $this->output, $this);
					$this->commands[$command->getSignature()] = $command;
				}
			}
		}
		
		/**
		 * Displays a formatted list of all registered commands, grouped by their namespace.
		 * This is shown when no command is specified or when an invalid command is used.
		 * @return int Exit code (always 0 for this method)
		 */
		protected function listCommands(): int {
			// Group commands by namespace
			$groups = [];
			
			foreach ($this->commands as $signature => $command) {
				// Split command signature into group and name (group:name format)
				[$group, $name] = explode(':', $signature) + [1 => ''];
				
				$groups[$group][] = [
					'signature'   => $signature,
					'description' => $command->getDescription()
				];
			}
			
			// Display grouped commands
			$this->output->writeLn("\nAvailable Commands:\n");
			
			foreach ($groups as $group => $commands) {
				$this->output->writeLn("[{$group}]");
				
				// Calculate padding for alignment based on the longest command signature
				$maxLength = max(array_map(fn($cmd) => strlen($cmd['signature']), $commands));
				
				foreach ($commands as $command) {
					$padding = str_repeat(' ', $maxLength - strlen($command['signature']) + 4);
					$this->output->writeLn("  {$command['signature']}{$padding}{$command['description']}");
				}
				
				$this->output->writeLn("");
			}
			
			$this->output->writeLn("To run a command: sculpt command [arguments]");
			
			return 0;
		}
		
		protected function getProjectComposerPath(): ?string {
			// When sculpt is installed as a dependency, find the parent project's composer.json
			$vendorDir = dirname($this->basePath, 3);
			
			$possiblePaths = [
				// Standard location for project's composer.json
				$vendorDir . '/composer.json',
				
				// Go one level up if we're in vendor/bin
				dirname($vendorDir) . '/composer.json'
			];
			
			foreach ($possiblePaths as $path) {
				if (file_exists($path)) {
					return $path;
				}
			}
			
			return null;
		}
		
		/**
		 * Get the path to the Composer's installed.json file
		 * Handles both direct usage and installation as a dependency
		 * @return string|null Path to the installed.json file or null if not found
		 */
		protected function getComposerInstalledPath(): ?string {
			// Possible locations of the installed.json file
			$possiblePaths = [
				// When installed as a dependency (package inside vendor dir)
				dirname($this->basePath, 2) . '/composer/installed.json',
				
				// When running directly (development mode)
				$this->basePath . '/vendor/composer/installed.json',
				
				// When running in a project that uses the package
				dirname($this->basePath, 3) . '/composer/installed.json'
			];
			
			// Return the first path that exists
			foreach ($possiblePaths as $path) {
				if (file_exists($path)) {
					return $path;
				}
			}
			
			return null;
		}
	}