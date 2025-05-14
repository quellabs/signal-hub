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
		 * @return void
		 */
		public function discoverProviders(): void {
			// First discover and register project providers
			$this->discoverProjectProviders();
			
			// Then discover and register package providers
			$this->discoverPackageProviders();
			
			// Finally, boot all registered providers
			$this->bootServiceProviders();
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
			// Define the namespace prefix for internal commands
			$namespace = 'Quellabs\\Sculpt\\Commands\\';
			
			// Get the absolute path to the commands directory
			$commandsDir = $this->basePath . '/src/Commands';
			
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
		 * Displays a formatted list of all registered commands, grouped by their namespace.
		 * This is shown when no command is specified or when an invalid command is used.
		 * @return int Exit code (always 0 for this method)
		 */
		protected function listCommands(): int {
			// Group commands by namespace
			$groups = [];
			
			// Iterate through all registered commands to sort them by namespace
			foreach ($this->commands as $signature => $command) {
				// Split command signature into group and name (group:name format)
				// The + [1 => ''] provides a default value for $name if the command doesn't follow group:name format
				[$group, $name] = explode(':', $signature) + [1 => ''];
				
				// Store command information in the corresponding group
				$groups[$group][] = [
					'signature'   => $signature,        // Full command signature (e.g. "db:migrate")
					'description' => $command->getDescription()  // Command description from the command class
				];
			}
			
			// Begin output with a header
			$this->output->writeLn("\nAvailable Commands:\n");
			
			// Loop through each group to display its commands
			foreach ($groups as $group => $commands) {
				// Display the group/namespace name
				$this->output->writeLn("[{$group}]");
				
				// Calculate padding for alignment based on the longest command signature
				// This ensures that all command descriptions are aligned vertically
				$maxLength = max(array_map(fn($cmd) => strlen($cmd['signature']), $commands));
				
				// Display each command in the current group with proper formatting
				foreach ($commands as $command) {
					// Create spacing between command name and description for consistent alignment
					$padding = str_repeat(' ', $maxLength - strlen($command['signature']) + 4);
					$this->output->writeLn("  {$command['signature']}{$padding}{$command['description']}");
				}
				
				// Add blank line after each group for better readability
				$this->output->writeLn("");
			}
			
			// Display usage instructions at the end
			$this->output->writeLn("To run a command: sculpt command [arguments]");
			
			// Return success code
			return 0;
		}
		
		/**
		 * Determines the path to the project's composer.json file.
		 * @return string|null The full path to composer.json or null if not found
		 */
		protected function getProjectComposerPath(): ?string {
			/**
			 * This method handles two scenarios:
			 * 1. Sculpt installed as a dependency in vendor/quellabs/sculpt
			 * 2. Sculpt running directly in development mode
			 */
			
			// Determine the composer.json path based on installation context
			if (!str_contains($this->basePath, '/vendor/')) {
				// Running directly in development mode
				$composerPath = $this->basePath . '/composer.json';
			} else {
				// Running as a dependency - find project root
				$composerPath = $this->findComposerPathInDependencyMode();
			}
			
			// Verify that the composer.json file actually exists
			if (file_exists($composerPath)) {
				return $composerPath;
			}
			
			return null;
		}
		
		/**
		 * Finds the composer.json path when running as a package dependency.
		 * This traverses up from the current path to locate the project root
		 * where composer.json should be located.
		 * @return string The expected path to composer.json
		 */
		protected function findComposerPathInDependencyMode(): string {
			// Keep going up the directory structure until we find the vendor directory itself
			$path = $this->basePath;
			while ($path !== '/' && basename(dirname($path)) !== 'vendor') {
				$path = dirname($path);
			}
			
			// Once we've found the vendor directory, go up two levels to reach project root:
			// - One level up: from package directory to vendor directory
			// - Second level up: from vendor directory to project root
			$projectRoot = dirname($path, 2);
			
			// The composer.json should be located directly in the project root
			return $projectRoot . '/composer.json';
		}
		
		/**
		 * Get the path to the Composer's installed.json file
		 * Handles both direct usage and installation as a dependency
		 * @return string|null Path to the installed.json file or null if not found
		 */
		protected function getComposerInstalledPath(): ?string {
			// Possible locations of the installed.json file
			$possiblePaths = [
				// When running directly (development mode)
				$this->basePath . '/vendor/composer/installed.json',
				
				// When installed as a dependency (package inside vendor dir)
				dirname($this->basePath, 2) . '/composer/installed.json',
				
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
		
		/**
		 * This method reads the project's root composer.json file to find service providers
		 * that are defined directly in the application, rather than in third-party packages.
		 * Project-level providers are registered first to ensure they have priority over
		 * package providers if there are conflicts.
		 * @return void
		 */
		private function discoverProjectProviders(): void {
			// Get the path to the project's root composer.json file
			// This is typically in the root directory of the application
			$projectComposerPath = $this->getProjectComposerPath();
			
			// Exit early if the file doesn't exist or the path couldn't be determined
			// This prevents errors when trying to read a non-existent file
			if (!$projectComposerPath || !file_exists($projectComposerPath)) {
				return;
			}
			
			// Log that we're searching for providers in the project's composer.json
			// This is useful for debugging provider discovery issues
			$this->output->writeLn("Looking for providers in parent project: " . $projectComposerPath);
			
			// Read and parse the composer.json file into a PHP array
			$projectComposer = json_decode(file_get_contents($projectComposerPath), true);

			// Exit if the file couldn't be parsed (e.g., invalid JSON)
			if (!$projectComposer) {
				return;
			}
			
			// Extract provider class names from composer.json
			// This uses a dedicated method to handle different provider definition formats
			$projectProviders = $this->extractProviderClasses($projectComposer);
			
			// Register each discovered provider
			// We iterate through each provider class name and register them individually
			foreach ($projectProviders as $providerClass) {
				// The "project" source identifier helps with debugging and logging
				// It distinguishes project providers from package providers
				$this->registerProvider($providerClass, "project");
			}
		}
		
		/**
		 * Parses the composer.json configuration to extract service provider class names.
		 * Supports both single provider and multiple providers formats to maintain
		 * backward compatibility with different package structures.
		 * @param array $composerConfig The parsed composer.json configuration as an associative array
		 * @return array An array of fully qualified class names for service providers
		 */
		private function extractProviderClasses(array $composerConfig): array {
			// Initialize an empty array to store the provider class names
			$providers = [];
			
			// Check if the config has defined sculpt providers or sculpt provider
			if (isset($composerConfig['extra']['sculpt']['providers']) && is_array($composerConfig['extra']['sculpt']['providers'])) {
				// Plural format: multiple providers defined in an array under 'providers' key
				// This is the preferred format for packages that need to register multiple providers
				$providers = $composerConfig['extra']['sculpt']['providers'];
			} elseif (isset($composerConfig['extra']['sculpt']['provider'])) {
				// Singular format: single provider defined as a string under 'provider' key
				// This is for backward compatibility with older packages or simpler use cases
				// We wrap the single provider in an array to maintain consistent return type
				$providers = [$composerConfig['extra']['sculpt']['provider']];
			}
			
			// Return the array of provider class names (may be empty if no providers were defined)
			return $providers;
		}
		
		/**
		 * This method reads the Composer's installed.json file to find third-party packages
		 * that have registered Sculpt service providers, allowing external packages to
		 * extend the application's functionality.
		 * @return void
		 */
		private function discoverPackageProviders(): void {
			// Get the path to Composer's installed.json file
			// This file contains metadata about all installed dependencies
			$composerFile = $this->getComposerInstalledPath();
			
			// Check if the file exists and is readable
			if (!$composerFile || !file_exists($composerFile)) {
				// If no installed.json file is found and no providers have been registered yet,
				// log a warning to indicate that no providers were found anywhere
				if (empty($this->serviceProviders)) {
					$this->output->warning("No providers found in project or installed packages");
				}
			
				// Exit early since there are no package providers to discover
				return;
			}
			
			// Read the contents of the installed.json file
			$packagesJson = file_get_contents($composerFile);
			
			// Exit if the file couldn't be read
			if (!$packagesJson) {
				return;
			}
			
			// Parse the JSON content into a PHP array
			$packages = json_decode($packagesJson, true);
			
			// Exit if the JSON could not be parsed
			if (!$packages) {
				return;
			}
			
			// Handle both older and newer formats of installed.json
			// Newer Composer versions have packages nested under a 'packages' key
			// Older versions have packages directly at the root level
			$packagesList = $packages['packages'] ?? $packages;
			
			// Get list of already registered provider classes to avoid duplicates
			// This is important to prevent registering the same provider twice if it was
			// already registered from the project's composer.json
			$registeredProviders = array_map(
				fn($provider) => get_class($provider),
				$this->serviceProviders
			);
			
			// Iterate through each installed package to find and register providers
			foreach ($packagesList as $package) {
				// Check if the package has defined a Sculpt service provider
				// Packages without a provider entry are skipped
				if (!isset($package['extra']['sculpt']['provider'])) {
					continue;
				}
				
				// Get the fully qualified class name of the provider
				$providerClass = $package['extra']['sculpt']['provider'];
				
				// Skip this provider if it's already been registered
				// This prevents duplicates and potential conflicts
				if (in_array($providerClass, $registeredProviders)) {
					continue;
				}
				
				// Register this provider, marking it as coming from a package
				// This helps with debugging and error identification
				$this->registerProvider($providerClass, "package");
			}
		}
		
		/**
		 * Register a single service provider
		 * @param string $providerClass The fully qualified class name of the provider
		 * @param string $source The source of the provider (e.g., "project" or "package")
		 * @return void
		 */
		private function registerProvider(string $providerClass, string $source): void {
			// Log the discovery of this provider for debugging purposes
			$this->output->writeLn("Found {$source} provider: $providerClass");
			
			// Check if the provider class actually exists and can be instantiated
			if (class_exists($providerClass)) {
				// Instantiate the provider, passing the current application instance
				// This allows the provider to access application services
				$provider = new $providerClass($this);
				
				// Add the provider instance to the internal collection of registered providers
				// This collection will be used later during the boot phase
				$this->serviceProviders[] = $provider;
				
				// Call the register method on the provider
				// This allows the provider to register bindings in the service container
				// but should not perform any actions that require other services to be available
				$provider->register($this);
			} else {
				// If the class doesn't exist, log a warning but continue execution
				// This prevents one bad provider from breaking the entire application
				$this->output->warning("Provider class not found: $providerClass");
			}
		}
		
		/**
		 * Boot all registered service providers
		 * @return void
		 */
		private function bootServiceProviders(): void {
			// Iterate through all providers that were successfully registered
			foreach ($this->serviceProviders as $provider) {
				// Call the boot method on each provider
				// At this point, all services should be registered and available for use
				// Providers can now perform actions that depend on other services
				$provider->boot($this);
			}
		}
	}