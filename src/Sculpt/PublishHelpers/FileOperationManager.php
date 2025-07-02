<?php
	
	namespace Quellabs\Canvas\Sculpt\PublishHelpers;
	
	use Quellabs\Contracts\IO\ConsoleOutput;
	
	/**
	 * This class executes pre-planned FileTransaction objects with proper commit/rollback
	 * functionality. Transactions contain all planned operations and can be executed,
	 * committed, or rolled back as atomic units.
	 */
	class FileOperationManager {
		
		/**
		 * @var ConsoleOutput Console output for logging operations
		 */
		private ConsoleOutput $output;
		
		/**
		 * FileOperationManager constructor
		 * @param ConsoleOutput $output Console output interface for logging
		 */
		public function __construct(ConsoleOutput $output) {
			$this->output = $output;
		}
		
		/**
		 * Create a new transaction with planned operations
		 * @param array $publishData Publishing configuration containing manifest and paths
		 * @param bool $overwrite Whether to overwrite existing files
		 * @param string|null $description Optional description for the transaction
		 * @return FileTransaction New transaction object with planned operations
		 * @throws FileOperationException If planning fails
		 */
		public function createTransaction(array $publishData, bool $overwrite = false, ?string $description = null): FileTransaction {
			$transaction = new FileTransaction($publishData, $overwrite, $description);
			
			$plannedCount = count($transaction->getPlannedOperations());
			$desc = $description ? " ({$description})" : '';
			
			$this->output->writeLn("<info>Created transaction: {$transaction->getId()}{$desc} with {$plannedCount} planned operations</info>");
			
			return $transaction;
		}
		
		/**
		 * Show a preview of what the transaction will do
		 * @param FileTransaction $transaction Transaction to preview
		 * @return void
		 */
		public function previewTransaction(FileTransaction $transaction): void {
			$this->output->writeLn("<info>Transaction Preview: {$transaction->getId()}</info>");
			
			foreach ($transaction->getPlannedOperations() as $operation) {
				$icon = match ($operation->type) {
					PlannedOperation::TYPE_COPY => '✓',
					PlannedOperation::TYPE_OVERWRITE => '⚠',
					PlannedOperation::TYPE_SKIP => '•',
					default => '?'
				};
				
				$this->output->writeLn("  {$icon} {$operation->type}: {$operation->sourcePath} → {$operation->targetPath} ({$operation->reason})");
			}
			
			$summary = $transaction->getSummary();
		
			$this->output->writeLn("<comment>Summary: {$summary['planned']['copy']} copies, {$summary['planned']['overwrite']} overwrites, {$summary['planned']['skip']} skips</comment>");
		}
		
		/**
		 * Execute the planned operations in the transaction
		 * @param FileTransaction $transaction Transaction to execute
		 * @return bool True if all operations executed successfully
		 * @throws FileOperationException If transaction was already executed
		 */
		public function execute(FileTransaction $transaction): bool {
			if ($transaction->isExecuted()) {
				throw new FileOperationException("Transaction {$transaction->getId()} has already been executed");
			}
			
			$this->output->writeLn("<info>Executing transaction: {$transaction->getId()}</info>");
			
			foreach ($transaction->getPlannedOperations() as $operation) {
				try {
					$this->executeOperation($transaction, $operation);
				} catch (\Exception $e) {
					$this->output->writeLn("<e>Failed to execute operation: {$e->getMessage()}</e>");
					throw new FileOperationException("Transaction execution failed: " . $e->getMessage(), 0, $e);
				}
			}
			
			$transaction->markExecuted();
			$this->output->writeLn("<info>Transaction execution completed: {$transaction->getId()}</info>");
			return true;
		}
		
		/**
		 * Commit the specified transaction, making all operations permanent
		 * @param FileTransaction $transaction Transaction to commit
		 * @return bool True if commit was successful
		 * @throws FileOperationException If transaction wasn't executed
		 */
		public function commit(FileTransaction $transaction): bool {
			if (!$transaction->isExecuted()) {
				throw new FileOperationException("Cannot commit transaction {$transaction->getId()} - not executed yet");
			}
			
			try {
				// Clean up any backup files created during the transaction
				$this->cleanupTransactionBackups($transaction);
				
				$operationCount = count($transaction->getExecutedOperations());
				$this->output->writeLn("<info>Committed transaction: {$transaction->getId()} ({$operationCount} operations)</info>");
				
				return true;
				
			} catch (\Exception $e) {
				$this->output->writeLn("<e>Failed to commit transaction {$transaction->getId()}: {$e->getMessage()}</e>");
				return false;
			}
		}
		
		/**
		 * Rollback the specified transaction, undoing all operations
		 * @param FileTransaction $transaction Transaction to rollback
		 * @return array Array of any errors encountered during rollback
		 * @throws FileOperationException If transaction wasn't executed
		 */
		public function rollback(FileTransaction $transaction): array {
			if (!$transaction->isExecuted()) {
				throw new FileOperationException("Cannot rollback transaction {$transaction->getId()} - not executed yet");
			}
			
			$this->output->writeLn("<comment>Rolling back transaction: {$transaction->getId()}</comment>");
			
			$errors = [];
			
			$operations = array_reverse($transaction->getExecutedOperations()); // Reverse order for proper rollback
			
			foreach ($operations as $operation) {
				try {
					$this->rollbackOperation($operation);
				} catch (\Exception $e) {
					$errors[] = "Failed to rollback operation: {$e->getMessage()}";
				}
			}
			
			if (empty($errors)) {
				$this->output->writeLn("<info>Transaction rollback completed successfully</info>");
			} else {
				$this->output->writeLn("<e>Transaction rollback completed with errors</e>");
				foreach ($errors as $error) {
					$this->output->writeLn("  • {$error}");
				}
			}
			
			return $errors;
		}
		
		/**
		 * Execute a single planned operation
		 * @param FileTransaction $transaction Transaction context
		 * @param PlannedOperation $operation Operation to execute
		 * @return void
		 * @throws FileOperationException If operation fails
		 */
		private function executeOperation(FileTransaction $transaction, PlannedOperation $operation): void {
			switch ($operation->type) {
				case PlannedOperation::TYPE_SKIP:
					$this->output->writeLn("  • Skipped: {$operation->targetPath} (already exists)");
					break;
				
				case PlannedOperation::TYPE_COPY:
				case PlannedOperation::TYPE_OVERWRITE:
					$this->executeCopyOperation($transaction, $operation);
					break;
				
				default:
					throw new FileOperationException("Unknown planned operation type: {$operation->type}");
			}
		}
		
		/**
		 * Execute a copy or overwrite operation
		 * @param FileTransaction $transaction Transaction context
		 * @param PlannedOperation $operation Operation to execute
		 * @return void
		 * @throws FileOperationException If copy fails
		 */
		private function executeCopyOperation(FileTransaction $transaction, PlannedOperation $operation): void {
			// Create backup if overwriting
			if ($operation->type === PlannedOperation::TYPE_OVERWRITE) {
				$this->createBackupFile($transaction, $operation->targetPath);
			}
			
			// Ensure target directory exists
			$this->ensureTargetDirectory($transaction, $operation->targetPath);
			
			// Perform the copy operation
			$this->performFileCopy($operation->sourcePath, $operation->targetPath);
			
			// Log the executed operation
			$transaction->logExecutedOperation(FileTransaction::OP_FILE_COPY, [
				'source'        => $operation->sourcePath,
				'target'        => $operation->targetPath,
				'was_overwrite' => $operation->type === PlannedOperation::TYPE_OVERWRITE
			]);
			
			$action = $operation->type === PlannedOperation::TYPE_OVERWRITE ? 'Overwrote' : 'Copied';
			$this->output->writeLn("  ✓ {$action}: {$operation->sourcePath} → {$operation->targetPath}");
		}
		
		/**
		 * Create a timestamped backup of an existing file within the transaction
		 * @param FileTransaction $transaction Transaction context
		 * @param string $targetPath Path to the file that needs backing up
		 * @return void
		 * @throws FileOperationException If backup creation fails
		 */
		private function createBackupFile(FileTransaction $transaction, string $targetPath): void {
			$backupPath = $this->generateUniqueBackupPath($targetPath);
			
			if (!copy($targetPath, $backupPath)) {
				throw new FileOperationException("Failed to create backup: {$targetPath} → {$backupPath}");
			}
			
			$transaction->logExecutedOperation(FileTransaction::OP_BACKUP_CREATE, [
				'original' => $targetPath,
				'backup'   => $backupPath
			]);
			
			$this->output->writeLn("  📁 Backed up: {$targetPath} → {$backupPath}");
		}
		
		/**
		 * Ensure the target directory structure exists within the transaction
		 * @param FileTransaction $transaction Transaction context
		 * @param string $targetPath Full path to the target file
		 * @return void
		 * @throws FileOperationException If directory creation fails
		 */
		private function ensureTargetDirectory(FileTransaction $transaction, string $targetPath): void {
			$targetDir = dirname($targetPath);
			
			if (is_dir($targetDir)) {
				return;
			}
			
			if (!mkdir($targetDir, 0755, true)) {
				throw new FileOperationException("Failed to create target directory: {$targetDir}");
			}
			
			$transaction->logExecutedOperation(FileTransaction::OP_DIRECTORY_CREATE, [
				'directory' => $targetDir
			]);
			
			$this->output->writeLn("  📂 Created directory: {$targetDir}");
		}
		
		/**
		 * Perform the actual file copy operation with validation
		 * @param string $sourcePath Path to source file
		 * @param string $targetPath Path to target file
		 * @return void
		 * @throws FileOperationException If copy operation fails
		 */
		private function performFileCopy(string $sourcePath, string $targetPath): void {
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
		 * Rollback a single operation based on its type
		 * @param array $operation Operation to rollback containing type and data
		 * @return void
		 * @throws FileOperationException If rollback fails or operation type is unknown
		 */
		private function rollbackOperation(array $operation): void {
			switch ($operation['type']) {
				case FileTransaction::OP_FILE_COPY:
					$this->rollbackFileCopy($operation['data']);
					break;
				
				case FileTransaction::OP_BACKUP_CREATE:
					$this->rollbackBackupCreate($operation['data']);
					break;
				
				case FileTransaction::OP_DIRECTORY_CREATE:
					$this->rollbackDirectoryCreate($operation['data']);
					break;
				
				default:
					throw new FileOperationException("Unknown operation type: {$operation['type']}");
			}
		}
		
		/**
		 * Rollback a file copy operation by removing the copied file
		 * @param array $data Operation data containing target path
		 * @return void
		 * @throws FileOperationException If file removal fails
		 */
		private function rollbackFileCopy(array $data): void {
			$targetPath = $data['target'];
			
			if (file_exists($targetPath)) {
				if (!unlink($targetPath)) {
					throw new FileOperationException("Failed to remove copied file: {$targetPath}");
				}
				$this->output->writeLn("  🗑️ Removed: {$targetPath}");
			}
		}
		
		/**
		 * Rollback a backup creation by restoring the original file from backup
		 * @param array $data Operation data containing original and backup paths
		 * @return void
		 * @throws FileOperationException If backup restoration fails
		 */
		private function rollbackBackupCreate(array $data): void {
			$originalPath = $data['original'];
			$backupPath = $data['backup'];
			
			if (file_exists($backupPath)) {
				if (!copy($backupPath, $originalPath)) {
					throw new FileOperationException("Failed to restore backup: {$backupPath} → {$originalPath}");
				}
				
				// Clean up the backup file after successful restore
				if (!unlink($backupPath)) {
					throw new FileOperationException("Failed to cleanup backup file: {$backupPath}");
				}
				
				$this->output->writeLn("  ↩️ Restored: {$backupPath} → {$originalPath}");
			}
		}
		
		/**
		 * Rollback a directory creation by removing the directory if it's empty
		 * @param array $data Operation data containing directory path
		 * @return void
		 * @throws FileOperationException If directory removal fails
		 */
		private function rollbackDirectoryCreate(array $data): void {
			$dirPath = $data['directory'];
			
			// Only remove if directory exists and is empty to avoid removing directories
			// that may have been populated by other processes or operations
			if (is_dir($dirPath) && $this->isDirectoryEmpty($dirPath)) {
				if (!rmdir($dirPath)) {
					throw new FileOperationException("Failed to remove created directory: {$dirPath}");
				}
				$this->output->writeLn("  📂 Removed directory: {$dirPath}");
			}
		}
		
		/**
		 * Clean up backup files created during the specified transaction
		 * This is called during commit to remove temporary backup files that are no longer needed
		 * @param FileTransaction $transaction Transaction to clean up
		 * @return void
		 */
		private function cleanupTransactionBackups(FileTransaction $transaction): void {
			$cleanedCount = 0;
			
			foreach ($transaction->getExecutedOperations() as $operation) {
				if ($operation['type'] === FileTransaction::OP_BACKUP_CREATE) {
					$backupPath = $operation['data']['backup'];
					
					if (file_exists($backupPath)) {
						if (unlink($backupPath)) {
							$cleanedCount++;
						} else {
							$this->output->writeLn("  Warning: Could not remove backup file: {$backupPath}");
						}
					}
				}
			}
			
			if ($cleanedCount > 0) {
				$this->output->writeLn("  🧹 Cleaned up {$cleanedCount} backup file(s)");
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