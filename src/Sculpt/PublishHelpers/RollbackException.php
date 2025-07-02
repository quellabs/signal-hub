<?php
	
	namespace Quellabs\Canvas\Sculpt\PublishHelpers;
	
	/**
	 * Custom exception for file operation errors
	 *
	 * This exception is thrown when file operations fail, providing
	 * specific context about what went wrong during file system operations.
	 */
	class RollbackException extends \Exception {
		
		private array $errors;
		
		/**
		 * FileOperationException constructor
		 * @param string $message Error message describing what went wrong
		 * @param int $code Error code (default: 0)
		 * @param \Throwable|null $previous Previous exception for exception chaining
		 */
		public function __construct(array $errors, string $message = "", int $code = 0, \Throwable $previous = null) {
			$this->errors = $errors;
			parent::__construct($message, $code, $previous);
		}
		
		public function getErrors(): array {
			return $this->errors;
		}
	}