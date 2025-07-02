<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\Contracts\Discovery\ProviderInterface;
	use Quellabs\Contracts\IO\ConsoleInput;
	use Quellabs\Contracts\IO\ConsoleOutput;
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Contracts\CommandBase;
	use Quellabs\Discover\Scanner\ComposerScanner;
	use Quellabs\Contracts\Publishing\AssetPublisher;
	
	class PublishCommand extends CommandBase {
		
		/**
		 * @var Discover Discovery component
		 */
		private Discover $discover;
		
		/**
		 * PublishCommand constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param ProviderInterface|null $provider
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);
			$this->discover = new Discover();
		}
		
		/**
		 * Returns the signature of this command
		 * @return string
		 */
		public function getSignature(): string {
			return "canvas:publish";
		}
		
		/**
		 * Returns a brief description of what this command is for
		 * @return string
		 */
		public function getDescription(): string {
			return "Publishes assets from available publishers";
		}
		
		/**
		 * Execute the publish command
		 *
		 * This is the main entry point for the canvas:publish command. It handles four scenarios:
		 * 1. --list flag: Shows all available publishers
		 * 2. --help flag: Shows detailed help for a specific tag or general usage
		 * 4. No parameters: Shows usage help
		 *
		 * @param ConfigurationManager $config Configuration containing command flags and options
		 * @return int Exit code (0 = success, 1 = error)
		 */
		public function execute(ConfigurationManager $config): int {
			// Show title of command
			$this->output->writeLn("<info>Canvas Publish Command</info>");
			$this->output->writeLn("");
			
			// Initialize the discovery system to find all available asset publishers
			$discover = new Discover();
			$discover->addScanner(new ComposerScanner("publishers"));
			$discover->discover();
			
			// Retrieve all discovered publisher providers from the discovery system
			$providers = $discover->getProviders();
			
			// Handle the --list flag first (no tag validation needed)
			if ($config->hasFlag("list")) {
				return $this->listPublishers($providers);
			}
			
			// Get the tag parameter once
			$tag = $config->getPositional(0);
			
			// If no tag is provided, show usage help
			if (!$tag) {
				$this->showUsageHelp();
				return 0;
			}
			
			// Validate the tag exists before doing anything else
			if (!$this->findProviderByTag($providers, $tag)) {
				$this->output->error("Publisher with tag '{$tag}' not found.");
				return 1;
			}
			
			// Now that we know the tag is valid, handle help or publish
			if ($config->hasFlag("help")) {
				return $this->showHelp($providers, $tag);
			}
			
			// Proceed with publishing
			return $this->publishTag($providers, $tag, $config->hasFlag("force"));
		}
		
		/**
		 * Show help information for publishers
		 * @param array $providers Array of discovered publisher providers
		 * @param string|null $tag Optional tag to show specific help for
		 * @return int Exit code (0 = success, 1 = error)
		 */
		private function showHelp(array $providers, ?string $tag = null): int {
			// Show help for a specific publisher tag
			$provider = $this->findProviderByTag($providers, $tag);
			
			// Display detailed help for the specific publisher
			$this->output->writeLn("<info>Help for publisher: {$tag}</info>");
			$this->output->writeLn($provider::getDescription());
			$this->output->writeLn("Usage: php ./vendor/bin/sculpt canvas:publish {$tag}");
			$this->output->writeLn("");
			
			// Show extended help if the publisher supports it
			$helpText = $provider::getHelp();
			
			if (!empty($helpText)) {
				$this->output->writeLn($provider::getHelp());
				$this->output->writeLn("");
			}
			
			return 0;
		}
		
		/**
		 * Find a provider by its tag identifier
		 * @param array $providers Array of provider class names
		 * @param string $tag Tag to search for
		 * @return AssetPublisher|null Provider class if found, null otherwise
		 */
		private function findProviderByTag(array $providers, string $tag): ?AssetPublisher {
			foreach ($providers as $provider) {
				if ($provider::getTag() === $tag) {
					return $provider;
				}
			}
			
			return null;
		}
		
		/**
		 * List all available publishers
		 * @param array $providers
		 * @return int
		 */
		private function listPublishers(array $providers): int {
			$this->output->writeLn("<info>Available Publishers:</info>");
			$this->output->writeLn("");
			
			foreach ($providers as $provider) {
				$this->output->writeLn(sprintf(
					"  <comment>%s</comment> - %s",
					$provider->getTag(),
					$provider->getDescription()
				));
			}
			
			$this->output->writeLn("");
			return 0;
		}
		
		/**
		 * Publish assets for a specific tag with file copying and rollback functionality
		 * @param array $providers
		 * @param string $tag
		 * @param bool $force
		 * @return int
		 */
		private function publishTag(array $providers, string $tag, bool $force = false): int {
			$targetProvider = $this->findProviderByTag($providers, $tag);
			
			// Check if the assets can be published
			if (!$targetProvider->canPublish()) {
				$this->showCannotPublishError($targetProvider);
				return 1;
			}
			
			// Validate and prepare for publishing
			$publishData = $this->preparePublishing($targetProvider, $tag);
			
			if ($publishData === null) {
				return 1; // Error occurred during preparation
			}
			
			// Show preview and get confirmation
			if (!$this->showPublishPreview($publishData, $force)) {
				return 0; // User cancelled
			}
			
			// Execute the publishing process
			return $this->executePublishing($publishData, $targetProvider);
		}
		
		/**
		 * Prepare and validate everything needed for publishing
		 * @param AssetPublisher $targetProvider
		 * @param string $tag
		 * @return array|null Returns publish data array or null on error
		 */
		private function preparePublishing(AssetPublisher $targetProvider, string $tag): ?array {
			// Resolve the source directory
			$sourceDirectory = $this->discover->resolvePath($targetProvider->getSourcePath());
			
			// Show information about what we're publishing
			$this->output->writeLn("Publishing: " . $tag);
			$this->output->writeLn("Description: " . $targetProvider::getDescription());
			$this->output->writeLn("Source directory: " . $sourceDirectory);
			$this->output->writeLn("");
			
			// Get the manifest and validate it
			$manifest = $targetProvider->getManifest();
			
			if (!isset($manifest['files']) || !is_array($manifest['files'])) {
				$this->output->error("Invalid manifest: 'files' key not found or not an array");
				return null;
			}
			
			// Get project root and source directory
			$discover = new Discover();
			$projectRoot = $discover->getProjectRoot();
			
			// Make source path absolute if it's relative
			if (!$this->isAbsolutePath($sourceDirectory)) {
				$sourceDirectory = rtrim($projectRoot, '/') . '/' . ltrim($sourceDirectory, '/');
			}
			
			// Validate source directory exists
			if (!is_dir($sourceDirectory)) {
				$this->output->error("Source directory does not exist: {$sourceDirectory}");
				return null;
			}
			
			return [
				'manifest'        => $manifest,
				'tag'             => $tag,
				'projectRoot'     => $projectRoot,
				'sourceDirectory' => $this->discover->resolvePath($sourceDirectory)
			];
		}
		
		/**
		 * Show preview of files to be published and get user confirmation
		 * @param array $publishData
		 * @param bool $force
		 * @return bool True to proceed, false to cancel
		 */
		private function showPublishPreview(array $publishData, bool $force): bool {
			// Analyze all files in the manifest
			$analysisResult = $this->analyzeFilesForPublishing($publishData);
			
			// Handle any missing source files
			if (!$this->validateSourceFiles($analysisResult['missingSourceFiles'])) {
				return false;
			}
			
			// Display the publishing preview
			$this->displayPublishingPreview($analysisResult['filesToPublish']);
			
			// Show overwrite warnings if needed
			$this->showOverwriteWarnings($analysisResult['existingFiles']);
			
			// Get user confirmation
			return $this->getUserConfirmation($force);
		}
		
		/**
		 * Analyze all files in the manifest to determine their status
		 * @param array $publishData
		 * @return array Analysis results with filesToPublish, existingFiles, and missingSourceFiles
		 */
		private function analyzeFilesForPublishing(array $publishData): array {
			$filesToPublish = [];
			$existingFiles = [];
			$missingSourceFiles = [];
			
			foreach ($publishData['manifest']['files'] as $file) {
				$sourcePath = rtrim($publishData['sourceDirectory'], '/') . '/' . ltrim($file['source'], '/');
				$targetPath = $this->resolveTargetPath($file['target'], $publishData['projectRoot']);
				
				// Check if source file exists
				if (!file_exists($sourcePath)) {
					$missingSourceFiles[] = [
						'source'     => $file['source'],
						'target'     => $file['target'],
						'sourcePath' => $sourcePath
					];
					continue;
				}
				
				$fileInfo = [
					'source'     => $file['source'],
					'target'     => $file['target'],
					'sourcePath' => $sourcePath,
					'targetPath' => $targetPath,
					'exists'     => file_exists($targetPath)
				];
				
				$filesToPublish[] = $fileInfo;
				
				if ($fileInfo['exists']) {
					$existingFiles[] = $fileInfo;
				}
			}
			
			return [
				'filesToPublish'     => $filesToPublish,
				'existingFiles'      => $existingFiles,
				'missingSourceFiles' => $missingSourceFiles
			];
		}
		
		/**
		 * Validate that all source files exist
		 * @param array $missingSourceFiles
		 * @return bool True if all source files exist, false otherwise
		 */
		private function validateSourceFiles(array $missingSourceFiles): bool {
			if (empty($missingSourceFiles)) {
				return true;
			}
			
			$this->output->error("Source files not found:");
			foreach ($missingSourceFiles as $file) {
				$this->output->writeLn("  • " . $file['source'] . " (expected at: " . $file['sourcePath'] . ")");
			}
			
			return false;
		}
		
		/**
		 * Display the publishing preview showing what files will be published
		 * @param array $filesToPublish
		 * @return void
		 */
		private function displayPublishingPreview(array $filesToPublish): void {
			$this->output->writeLn("<info>Files to publish:</info>");
			
			foreach ($filesToPublish as $file) {
				$status = $file['exists'] ? "<comment>[OVERWRITE]</comment>" : "<info>[NEW]</info>";
				$this->output->writeLn("  • " . $file['source'] . " → " . $file['target'] . " " . $status);
			}
			
			$this->output->writeLn("");
		}
		
		/**
		 * Show warnings about files that will be overwritten
		 * @param array $existingFiles
		 * @return void
		 */
		private function showOverwriteWarnings(array $existingFiles): void {
			if (empty($existingFiles)) {
				return;
			}
			
			$this->output->writeLn("<comment>WARNING: The following files will be overwritten:</comment>");
			
			foreach ($existingFiles as $file) {
				$this->output->writeLn("  • " . $file['target']);
			}
			
			$this->output->writeLn("");
			$this->output->writeLn("Backup copies will be created with .backup.[timestamp] extension");
			$this->output->writeLn("");
		}
		
		/**
		 * Get user confirmation to proceed with publishing
		 * @param bool $force
		 * @return bool True to proceed, false to cancel
		 */
		private function getUserConfirmation(bool $force): bool {
			if ($force) {
				$this->output->writeLn("Force flag set, proceeding without confirmation...");
				return true;
			}
			
			return $this->askForConfirmation();
		}
		
		/**
		 * Execute the actual publishing process with rollback support
		 * @param array $publishData
		 * @param AssetPublisher $targetProvider
		 * @return int Exit code (0 = success, 1 = error)
		 */
		private function executePublishing(array $publishData, AssetPublisher $targetProvider): int {
			$copiedFiles = [];
			$backupFiles = [];
			
			try {
				// Copy all files with backup support
				$this->copyFiles($publishData, $copiedFiles, $backupFiles);
				
				// Clean up backup files and show the success message
				$this->handlePublishingSuccess($backupFiles, $targetProvider);
				return 0;
				
			} catch (\Exception $e) {
				// Publishing failed - perform rollback
				$this->handlePublishingFailure($e, $copiedFiles, $backupFiles);
				return 1;
			}
		}
		
		/**
		 * Copy files from source to target with backup support
		 * @param array $publishData
		 * @param array &$copiedFiles Reference to track copied files
		 * @param array &$backupFiles Reference to track backup files
		 * @throws \Exception On any file operation failure
		 */
		private function copyFiles(array $publishData, array &$copiedFiles, array &$backupFiles): void {
			// Fallback to original logic
			foreach ($publishData['manifest']['files'] as $file) {
				$sourcePath = rtrim($publishData['sourceDirectory'], '/') . '/' . ltrim($file['source'], '/');
				$targetPath = $this->resolveTargetPath($file['target'], $publishData['projectRoot']);
				
				$this->copyFile($sourcePath, $targetPath, $copiedFiles, $backupFiles);
			}
		}
		
		/**
		 * Copy a single file with backup support
		 * @param string $sourcePath
		 * @param string $targetPath
		 * @param array &$copiedFiles Reference to track copied files
		 * @param array &$backupFiles Reference to track backup files
		 * @throws \Exception On any file operation failure
		 */
		private function copyFile(string $sourcePath, string $targetPath, array &$copiedFiles, array &$backupFiles): void {
			// Validate source file exists
			if (!file_exists($sourcePath)) {
				throw new \Exception("Source file not found: {$sourcePath}");
			}
			
			// Create backup if the target file already exists
			if (file_exists($targetPath)) {
				$backupPath = $targetPath . '.backup.' . time();
				
				if (!copy($targetPath, $backupPath)) {
					throw new \Exception("Failed to create backup: {$backupPath}");
				}
				
				$backupFiles[$targetPath] = $backupPath;
				
				$this->output->writeLn("  Backed up: {$targetPath} → {$backupPath}");
			}
			
			// Create target directory if it doesn't exist
			$targetDir = dirname($targetPath);
			
			if (!is_dir($targetDir)) {
				if (!mkdir($targetDir, 0755, true)) {
					throw new \Exception("Failed to create directory: {$targetDir}");
				}
			}
			
			// Copy the file
			if (!copy($sourcePath, $targetPath)) {
				throw new \Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
			}
			
			$copiedFiles[] = $targetPath;
			
			$this->output->writeLn("  Copied: {$sourcePath} → {$targetPath}");
		}
		
		/**
		 * Handle successful publishing completion
		 * @param array $backupFiles
		 * @param AssetPublisher $targetProvider
		 */
		private function handlePublishingSuccess(array $backupFiles, AssetPublisher $targetProvider): void {
			$this->cleanupBackupFiles($backupFiles);
			
			$this->output->writeLn("");
			$this->output->writeLn("<info>Assets published successfully!</info>");
			$this->output->writeLn("");
			$this->output->writeLn($targetProvider->getPostPublishInstructions());
		}
		
		/**
		 * Handle publishing failure with rollback
		 * @param \Exception $e
		 * @param array $copiedFiles
		 * @param array $backupFiles
		 */
		private function handlePublishingFailure(\Exception $e, array $copiedFiles, array $backupFiles): void {
			$this->output->writeLn("");
			$this->output->error("Publishing failed: " . $e->getMessage());
			$this->output->writeLn("<comment>Performing rollback...</comment>");
			
			$this->performRollback($copiedFiles, $backupFiles);
		}
		
		/**
		 * Check if a path is absolute
		 * @param string $path
		 * @return bool
		 */
		private function isAbsolutePath(string $path): bool {
			return $path[0] === '/' || (strlen($path) > 1 && $path[1] === ':');
		}
		
		/**
		 * Resolve target path, making it absolute if relative
		 * @param string $targetPath
		 * @param string $projectRoot
		 * @return string
		 */
		private function resolveTargetPath(string $targetPath, string $projectRoot): string {
			if ($this->isAbsolutePath($targetPath)) {
				return $targetPath;
			}
			
			return rtrim($projectRoot, '/') . '/' . ltrim($targetPath, '/');
		}
		
		/**
		 * Clean up backup files after successful publishing
		 * @param array $backupFiles
		 * @return void
		 */
		private function cleanupBackupFiles(array $backupFiles): void {
			foreach ($backupFiles as $backupPath) {
				if (file_exists($backupPath)) {
					unlink($backupPath);
				}
			}
			
			if (!empty($backupFiles)) {
				$this->output->writeLn("  Cleaned up " . count($backupFiles) . " backup file(s)");
			}
		}
		
		/**
		 * Perform rollback by removing copied files and restoring backups
		 * @param array $copiedFiles
		 * @param array $backupFiles
		 * @return void
		 */
		private function performRollback(array $copiedFiles, array $backupFiles): void {
			$rollbackErrors = [];
			
			// Remove files that were successfully copied
			foreach ($copiedFiles as $filePath) {
				if (file_exists($filePath)) {
					if (!unlink($filePath)) {
						$rollbackErrors[] = "Failed to remove: {$filePath}";
					} else {
						$this->output->writeLn("  Removed: {$filePath}");
					}
				}
			}
			
			// Restore backup files
			foreach ($backupFiles as $originalPath => $backupPath) {
				if (file_exists($backupPath)) {
					if (!copy($backupPath, $originalPath)) {
						$rollbackErrors[] = "Failed to restore backup: {$backupPath} to {$originalPath}";
					} else {
						$this->output->writeLn("  Restored: {$backupPath} → {$originalPath}");
						unlink($backupPath);
					}
				}
			}
			
			if (empty($rollbackErrors)) {
				$this->output->writeLn("<info>Rollback completed successfully</info>");
			} else {
				$this->output->writeLn("<error>Rollback completed with errors:</error>");
				foreach ($rollbackErrors as $error) {
					$this->output->writeLn("  {$error}");
				}
			}
		}
		
		/**
		 * Display comprehensive usage help for the canvas:publish command
		 * @return void
		 */
		private function showUsageHelp(): void {
			$this->output->writeLn("<comment>Use publish to add assets using configured publishers</comment>");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>USAGE:</info>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish tag [options]");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>OPTIONS:</info>");
			$this->output->writeLn("  <comment>--list</comment>              List all available publishers (tags)");
			$this->output->writeLn("  <comment>--force</comment>              Skip all interactive prompts and confirmations");
			$this->output->writeLn("  <comment>--help</comment>               Display this help message");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>NOTE:</info>");
			$this->output->writeLn("  Publishers and tags refer to the same thing - configured publish targets.");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>EXAMPLES:</info>");
			$this->output->writeLn("  <comment># List all available publishers</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish --list");
			$this->output->writeLn("");
			$this->output->writeLn("  <comment># Publish using a specific publisher</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish production");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish staging");
			$this->output->writeLn("");
			$this->output->writeLn("  <comment># Skip interactive prompts (non-interactive mode)</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish -production --force");
			$this->output->writeLn("");
			$this->output->writeLn("  <comment># Show help information</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish -production --help");
		}
		
		/**
		 * Displays an error message when asset publishing fails
		 * @param AssetPublisher $targetProvider The asset publisher that failed to publish
		 * @return void
		 */
		private function showCannotPublishError(AssetPublisher $targetProvider): void {
			// Display the main error message to the user
			$this->output->error("Cannot publish assets");
			
			// Add a blank line for better readability
			$this->output->writeLn("");
			
			// Display the specific reason why publishing failed, obtained from the target provider
			$this->output->writeLn($targetProvider->getCannotPublishReason());
		}
		
		/**
		 * Prompts the user for confirmation before proceeding with an action.
		 * @return bool Returns true if user confirms with 'y', false otherwise.
		 */
		private function askForConfirmation(): bool {
			// Ask for confirmation
			$confirmation = $this->input->ask("Proceed? (y/N)");
			
			// Check if the user entered 'y' (case-insensitive)
			if ($confirmation && strtolower($confirmation) === 'y') {
				return true; // User confirmed - proceed with the action
			}
			
			// Any input other than 'y' is treated as cancellation
			$this->output->writeLn("Cancelled.");
			
			// User canceled or provided invalid input
			return false;
		}
	}