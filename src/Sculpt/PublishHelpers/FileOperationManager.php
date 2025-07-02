<?php
	
	namespace Quellabs\Canvas\Sculpt\PublishHelpers;
	
	use Quellabs\Contracts\IO\ConsoleOutput;
	
	/**
	 * Manages file operations for the publish command with comprehensive rollback support
	 *
	 * This class handles all file system operations including copying, backup creation,
	 * directory management, and rollback functionality. It maintains detailed tracking
	 * of all operations to enable complete rollback in case of failures.
	 */
	class FileOperationManager {
		/**
		 * @var ConsoleOutput Console output for logging operations
		 */
		private ConsoleOutput $output;
		
		/**
		 * @var array Tracks all successfully copied files for rollback purposes
		 */
		private array $copiedFiles = [];
		
		/**
		 * @var array Maps original file paths to their backup file paths
		 */
		private array $backupFiles = [];
		
		/**
		 * @var array Tracks directories created during operations
		 */
		private array $createdDirectories = [];
		
		/**
		 * FileOperationManager constructor
		 *
		 * @param ConsoleOutput $output Console output interface for logging
		 */
		public function __construct(ConsoleOutput $output) {
			$this->output = $output;
		}
		
		/**
		 * Copy multiple files from source to target locations with backup support
		 * @param array $publishData Publishing configuration containing manifest and paths
		 * @param bool $overwrite Whether to overwrite existing files
		 * @return bool True if all files copied successfully, false otherwise
		 * @throws FileOperationException On any critical file operation failure
		 */
		public function copyFiles(array $publishData, bool $overwrite = false): bool {
			try {
				foreach ($publishData['manifest']['files'] as $file) {
					$sourcePath = $this->buildSourcePath($publishData['sourceDirectory'], $file['source']);
					$targetPath = $this->resolveTargetPath($file['target'], $publishData['projectRoot']);
					
					// copyFile now handles the overwrite logic internally
					$this->copyFile($sourcePath, $targetPath, $overwrite);
				}
				
				return true;
				
			} catch (\Exception $e) {
				// Re-throw as our custom exception for better error handling
				throw new FileOperationException(
					"File copy operation failed: " . $e->getMessage(),
					0,
					$e
				);
			}
		}
		
		/**
		 * Copy a single file with comprehensive backup and validation
		 * @param string $sourcePath Path to the source file
		 * @param string $targetPath Destination path for the file
		 * @param bool $overwrite Whether to overwrite existing files (default: false)
		 * @return bool True if the file was copied, false if skipped
		 * @throws FileOperationException On any file operation failure
		 */
		public function copyFile(string $sourcePath, string $targetPath, bool $overwrite = false): bool {
			// Step 1: Validate source file
			$this->validateSourceFile($sourcePath);
			
			// Step 2: Check if target exists and handle accordingly
			if (file_exists($targetPath)) {
				if (!$overwrite) {
					$this->output->writeLn("  • Skipped: {$targetPath} (already exists)");
					return false; // File was skipped
				}
				
				// Create backup before overwriting
				$this->createBackupFile($targetPath);
			}
			
			// Step 3: Ensure target directory exists
			$this->ensureTargetDirectory($targetPath);
			
			// Step 4: Perform the copy operation
			$this->performFileCopy($sourcePath, $targetPath);
			
			// Step 5: Track successful operation
			$this->copiedFiles[] = $targetPath;
			
			// Show message that we copied the file
			$this->output->writeLn("  ✓ Copied: {$sourcePath} → {$targetPath}");
			
			// The file was successfully copied
			return true;
		}
		
		/**
		 * Create a timestamped backup of an existing file
		 * @param string $targetPath Path to the file that needs backing up
		 * @return void
		 * @throws FileOperationException If backup creation fails
		 */
		public function createBackupFile(string $targetPath): void {
			$backupPath = $this->generateUniqueBackupPath($targetPath);
			
			if (!copy($targetPath, $backupPath)) {
				throw new FileOperationException("Failed to create backup: {$targetPath} → {$backupPath}");
			}
			
			// Track the backup for potential cleanup/rollback
			$this->backupFiles[$targetPath] = $backupPath;
			$this->output->writeLn("  📁 Backed up: {$targetPath} → {$backupPath}");
		}
		
		/**
		 * Ensure the target directory structure exists
		 * @param string $targetPath Full path to the target file
		 * @return void
		 * @throws FileOperationException If directory creation fails
		 */
		public function ensureTargetDirectory(string $targetPath): void {
			$targetDir = dirname($targetPath);
			
			// Skip if directory already exists
			if (is_dir($targetDir)) {
				return;
			}
			
			// Create directory structure with appropriate permissions
			if (!mkdir($targetDir, 0755, true)) {
				throw new FileOperationException("Failed to create target directory: {$targetDir}");
			}
			
			// Track created directory for potential rollback
			$this->createdDirectories[] = $targetDir;
			$this->output->writeLn("  📂 Created directory: {$targetDir}");
		}
		
		/**
		 * Perform the actual file copy operation with validation
		 * @param string $sourcePath Path to source file
		 * @param string $targetPath Path to target file
		 * @return void
		 * @throws FileOperationException If copy operation fails
		 */
		public function performFileCopy(string $sourcePath, string $targetPath): void {
			// Attempt the file copy
			if (!copy($sourcePath, $targetPath)) {
				$error = error_get_last();
				$errorMessage = $error ? $error['message'] : 'Unknown error';
				
				throw new FileOperationException(
					"Failed to copy file: {$sourcePath} → {$targetPath}. Error: {$errorMessage}"
				);
			}
			
			// Verify the copy was successful
			$this->verifyCopyOperation($sourcePath, $targetPath);
		}
		
		/**
		 * Perform complete rollback of all operations
		 * This method will:
		 * 1. Remove all successfully copied files
		 * 2. Restore all backup files to their original locations
		 * 3. Remove any directories that were created (if empty)
		 * @return array Array of any errors encountered during rollback
		 */
		public function performRollback(): array {
			$rollbackErrors = [];
			
			$this->output->writeLn("<comment>Performing rollback...</comment>");
			
			// Step 1: Remove files that were successfully copied
			$rollbackErrors = array_merge($rollbackErrors, $this->removeCopiedFiles());
			
			// Step 2: Restore backup files
			$rollbackErrors = array_merge($rollbackErrors, $this->restoreBackupFiles());
			
			// Step 3: Clean up created directories (if empty)
			$rollbackErrors = array_merge($rollbackErrors, $this->cleanupCreatedDirectories());
			
			if (empty($rollbackErrors)) {
				$this->output->writeLn("<info>Rollback completed successfully</info>");
			} else {
				$this->output->writeLn("<error>Rollback completed with errors:</error>");
				foreach ($rollbackErrors as $error) {
					$this->output->writeLn("  {$error}");
				}
			}
			
			return $rollbackErrors;
		}
		
		/**
		 * Clean up all backup files after successful operation
		 * @return void
		 */
		public function cleanupBackupFiles(): void {
			$cleanedCount = 0;
			
			foreach ($this->backupFiles as $backupPath) {
				if (file_exists($backupPath)) {
					if (unlink($backupPath)) {
						$cleanedCount++;
					} else {
						$this->output->writeLn("  Warning: Could not remove backup file: {$backupPath}");
					}
				}
			}
			
			if ($cleanedCount > 0) {
				$this->output->writeLn("  🧹 Cleaned up {$cleanedCount} backup file(s)");
			}
		}
		
		/**
		 * Get summary of operations performed
		 * @return array Summary including counts of various operations
		 */
		public function getOperationSummary(): array {
			return [
				'copied_files'        => count($this->copiedFiles),
				'backup_files'        => count($this->backupFiles),
				'created_directories' => count($this->createdDirectories),
			];
		}
		
		/**
		 * Reset all tracking arrays (useful for testing or reusing the instance)
		 * @return void
		 */
		public function reset(): void {
			$this->copiedFiles = [];
			$this->backupFiles = [];
			$this->createdDirectories = [];
		}
		
		/**
		 * Check if a path is absolute
		 * @param string $path Path to check
		 * @return bool True if path is absolute, false if relative
		 */
		public function isAbsolutePath(string $path): bool {
			return $path[0] === '/' || (strlen($path) > 1 && $path[1] === ':');
		}
		
		/**
		 * Resolve the target path, making it absolute if relative
		 * @param string $targetPath Target path (may be relative)
		 * @param string $projectRoot Project root directory
		 * @return string Absolute target path
		 */
		public function resolveTargetPath(string $targetPath, string $projectRoot): string {
			if ($this->isAbsolutePath($targetPath)) {
				return $targetPath;
			}
			
			return rtrim($projectRoot, '/') . '/' . ltrim($targetPath, '/');
		}
		
		/**
		 * Build the complete source path from directory and relative path
		 * @param string $sourceDirectory Base source directory
		 * @param string $relativePath Relative path to the file
		 * @return string Complete source path
		 */
		private function buildSourcePath(string $sourceDirectory, string $relativePath): string {
			return rtrim($sourceDirectory, '/') . '/' . ltrim($relativePath, '/');
		}
		
		/**
		 * Validate that source file exists and is readable
		 * @param string $sourcePath Path to validate
		 * @return void
		 * @throws FileOperationException If file doesn't exist or isn't readable
		 */
		private function validateSourceFile(string $sourcePath): void {
			if (!file_exists($sourcePath)) {
				throw new FileOperationException("Source file not found: {$sourcePath}");
			}
			
			if (!is_readable($sourcePath)) {
				throw new FileOperationException("Source file is not readable: {$sourcePath}");
			}
		}
		
		/**
		 * Generate a unique backup path with timestamp
		 * @param string $targetPath Original file path
		 * @return string Unique backup path
		 */
		private function generateUniqueBackupPath(string $targetPath): string {
			$timestamp = date('Y-m-d_H-i-s');
			$backupPath = $targetPath . '.backup.' . $timestamp;
			
			// Ensure uniqueness for rapid successive calls
			$counter = 1;
			
			while (file_exists($backupPath)) {
				$backupPath = $targetPath . '.backup.' . $timestamp . '_' . $counter;
				$counter++;
			}
			
			return $backupPath;
		}
		
		/**
		 * Verify that copy operation was successful
		 * @param string $sourcePath Source file path
		 * @param string $targetPath Target file path
		 * @return void
		 * @throws FileOperationException If verification fails
		 */
		private function verifyCopyOperation(string $sourcePath, string $targetPath): void {
			// Check that target file exists
			if (!file_exists($targetPath)) {
				throw new FileOperationException(
					"Copy operation appeared successful but target file was not created: {$targetPath}"
				);
			}
			
			// Verify file sizes match
			$sourceSize = filesize($sourcePath);
			$targetSize = filesize($targetPath);
			
			if ($sourceSize !== $targetSize) {
				throw new FileOperationException(
					"File copy verification failed: size mismatch. Source: {$sourceSize} bytes, Target: {$targetSize} bytes"
				);
			}
		}
		
		/**
		 * Remove all successfully copied files during rollback
		 * @return array Array of any errors encountered
		 */
		private function removeCopiedFiles(): array {
			$errors = [];
			
			foreach ($this->copiedFiles as $filePath) {
				if (file_exists($filePath)) {
					if (!unlink($filePath)) {
						$errors[] = "Failed to remove: {$filePath}";
					} else {
						$this->output->writeLn("  🗑️ Removed: {$filePath}");
					}
				}
			}
			
			return $errors;
		}
		
		/**
		 * Restore all backup files to their original locations
		 * @return array Array of any errors encountered
		 */
		private function restoreBackupFiles(): array {
			$errors = [];
			
			foreach ($this->backupFiles as $originalPath => $backupPath) {
				if (file_exists($backupPath)) {
					if (!copy($backupPath, $originalPath)) {
						$errors[] = "Failed to restore backup: {$backupPath} to {$originalPath}";
					} else {
						$this->output->writeLn("  ↩️ Restored: {$backupPath} → {$originalPath}");
						
						// Clean up the backup file after successful restore
						if (!unlink($backupPath)) {
							$errors[] = "Failed to clean up backup file: {$backupPath}";
						}
					}
				}
			}
			
			return $errors;
		}
		
		/**
		 * Clean up directories that were created (if they're empty)
		 * @return array Array of any errors encountered
		 */
		private function cleanupCreatedDirectories(): array {
			$errors = [];
			
			// Sort directories by depth (deepest first) for proper cleanup
			$sortedDirs = $this->createdDirectories;
			usort($sortedDirs, function ($a, $b) {
				return substr_count($b, '/') - substr_count($a, '/');
			});
			
			foreach ($sortedDirs as $dirPath) {
				if (is_dir($dirPath)) {
					// Only remove if directory is empty
					if ($this->isDirectoryEmpty($dirPath)) {
						if (!rmdir($dirPath)) {
							$errors[] = "Failed to remove created directory: {$dirPath}";
						} else {
							$this->output->writeLn("  📂 Removed empty directory: {$dirPath}");
						}
					}
				}
			}
			
			return $errors;
		}
		
		/**
		 * Check if a directory is empty
		 * @param string $dirPath Directory path to check
		 * @return bool True if directory is empty, false otherwise
		 */
		private function isDirectoryEmpty(string $dirPath): bool {
			$handle = opendir($dirPath);
	
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					closedir($handle);
					return false;
				}
			}
	
			closedir($handle);
			return true;
		}
	}