<?php
	
	namespace Quellabs\Canvas\TaskScheduler;
	
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	/**
	 * Abstract base class for scheduled tasks
	 */
	abstract class AbstractTask implements TaskInterface, ProviderInterface {
		
		/**
		 * Task name defaults to class name in kebab-case
		 * Converts the class name from CamelCase to kebab-case format
		 * (e.g., SendEmailTask becomes "send-email-task")
		 * @return string The kebab-case task name
		 */
		public function getName(): string {
			// Fetch class name using reflection to get the short name (without namespace)
			$className = (new \ReflectionClass($this))->getShortName();
			
			// Convert CamelCase to kebab-case using regex
			// Matches lowercase letter followed by uppercase letter and inserts hyphen
			return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $className));
		}
		
		/**
		 * Default: always run when scheduled
		 * @return bool True if the task should execute, false otherwise
		 */
		public function enabled(): bool {
			return true;
		}
		
		/**
		 * Default: no description. Override to provide a human-readable description
		 * of what the task does. This is useful for logging, monitoring, and
		 * administrative interfaces
		 *
		 * @return string Task description
		 */
		public function getDescription(): string {
			return '';
		}
		
		/**
		 * Default: 5-minute timeout. Override to set a custom timeout based
		 * on the task's expected execution time. Value is in seconds (300 = 5 minutes)
		 * @return int Timeout in seconds
		 */
		public function getTimeout(): int {
			return 300;
		}
		
		/**
		 * Default: no special failure handling
		 *
		 * Override to implement custom error handling logic such as:
		 * - Logging specific error details
		 * - Sending notifications
		 * - Performing cleanup operations
		 * - Retrying with different parameters
		 *
		 * @param \Exception $exception The exception that caused the task to fail
		 */
		public function onFailure(\Exception $exception): void {
			// Override for custom error handling
		}
		
		/**
		 * Default: no special timeout handling
		 *
		 * Override to implement custom timeout handling logic such as:
		 * - Logging timeout details
		 * - Sending alerts
		 * - Performing partial cleanup
		 * - Adjusting timeout for future runs
		 *
		 * @param TaskException $exception The timeout exception that was thrown
		 */
		public function onTimeout(TaskException $exception): void {
			// Override for custom timeout handling
		}
		
		public static function getMetadata(): array {
			return [];
		}
		
		public static function getDefaults(): array {
			return [];
		}
		
		public function getConfig(): array {
			return [];
		}
		
		public function setConfig(array $config): void {
		}
		
		/**
		 * Main task execution logic
		 * @throws \Exception When task execution fails
		 */
		abstract public function handle(): void;
		
		/**
		 * Cron expression defining when the task should run
        * @return string Cron expression for task scheduling
        */
       abstract public function getSchedule(): string;
    }