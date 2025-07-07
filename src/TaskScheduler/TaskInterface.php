<?php
	
	namespace Quellabs\Canvas\TaskScheduler;
	
	use Psr\Log\LoggerInterface;
	
	interface TaskInterface {
		
		/**
		 * Determine if the task is enabled
		 * @return bool
		 */
		public function enabled(): bool;
		
		/**
		 * Execute the scheduled task
		 * @throws \Exception When task execution fails
		 */
		public function handle(): void;
		
		/**
		 * Get the cron schedule expression for this task
		 *
		 * Supports standard cron syntax:
		 * - '0 8 * * *' (8 AM daily)
		 * - '0 0 1 * *' (first day of month)
		 *
		 * Also supports shortcuts:
		 * - 'daily', 'weekly', 'monthly', 'yearly', 'hourly'
		 *
		 * @return string Cron expression or shortcut
		 */
		public function getSchedule(): string;
		
		/**
		 * Get a unique name for this task
		 * @return string Unique task identifier
		 */
		public function getName(): string;
		
		/**
		 * Get task description for CLI and logging (optional)
		 * @return string Human-readable description
		 */
		public function getDescription(): string;
		
		/**
		 * Get maximum execution time in seconds (optional)
		 * Return 0 for no timeout.
		 * @return int|null Maximum execution time in seconds
		 */
		public function getTimeout(): ?int;
		
		/**
		 * Handle task failure
		 * @param \Exception $exception The exception that was thrown
		 */
		public function onFailure(\Exception $exception): void;
		
		/**
		 * Handle task timeout
		 * @param TaskException $exception The exception that was thrown
		 */
		public function onTimeout(TaskException $exception): void;
	}