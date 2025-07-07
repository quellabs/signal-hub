<?php
	
	namespace Quellabs\Canvas\TaskScheduler;
	
	/**
	 * Interface for scheduled tasks in the Canvas Task Scheduler
	 *
	 * This interface defines the contract that all scheduled tasks must implement
	 * to be managed by the task scheduler system. It provides methods for task
	 * execution, scheduling, configuration, and error handling.
	 */
	interface TaskInterface {
		
		/**
		 * Determine if the task is enabled
		 * @return bool True if the task should be executed, false otherwise
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
		 * Defines when the task should be executed using cron syntax or shortcuts.
		 * The scheduler will parse this expression to determine the next run time.
		 *
		 * Supports standard cron syntax:
		 * - '0 8 * * *' (8 AM daily)
		 * - '0 0 1 * *' (first day of month)
		 * - '0 9-17 * * 1-5' (hourly during business hours, weekdays only)
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
		 * @return int Maximum execution time in seconds, or 0 for no timeout
		 */
		public function getTimeout(): int;
		
		/**
		 * Handle task failure
		 * @param \Exception $exception The exception that was thrown during task execution
		 */
		public function onFailure(\Exception $exception): void;
		
		/**
		 * Handle task timeout.
		 * @param TaskTimeoutException $exception The timeout exception that was thrown
		 */
		public function onTimeout(TaskTimeoutException $exception): void;
	}