<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Canvas\TaskScheduler\TaskScheduler;
	use Quellabs\Canvas\TaskScheduler\Storage\FileTaskStorage;
	
	/**
	 * Command class for listing scheduled tasks
	 *
	 * This command discovers and displays all scheduled tasks in the system.
	 * It uses a file-based storage system to retrieve task scheduling information.
	 */
	class SchedulerListCommand extends RoutesBase {
		
		/**
		 * Returns the signature of this command
		 * @return string The command signature "schedule:list"
		 */
		public function getSignature(): string {
			return "schedule:list";
		}
		
		/**
		 * Returns a brief description of what this command is for
		 * @return string A human-readable description of the command
		 */
		public function getDescription(): string {
			return "Lists all scheduled tasks with their schedules";
		}
		
		/**
		 * Executes the scheduled task lister
		 *
		 * This method performs the main functionality of the command:
		 * 1. Initializes the project discovery service
		 * 2. Creates a task scheduler with file-based storage
		 * 3. Retrieves and displays all scheduled tasks in a sorted table
		 *
		 * @param ConfigurationManager $config The application configuration manager
		 * @return int Exit code (0 for success, non-zero for failure)
		 */
		public function execute(ConfigurationManager $config): int {
			// Initialize the project discovery service to locate the project root
			$discover = new Discover();
			
			// Create a task scheduler instance with file-based storage
			// The storage directory is set to {project_root}/storage/task-scheduler
			$scheduler = new TaskScheduler(
				new FileTaskStorage($discover->getProjectRoot() . "/storage/task-scheduler")
			);
			
			// Build the task list with sorting data
			$tasks = [];
			
			foreach ($scheduler->getTaskList() as $task) {
				$tasks[] = [
					'name'          => $task->getName(),
					'description'   => $task->getDescription(),
					'schedule'      => $task->getSchedule(),
					'sort_priority' => $this->getSchedulePriority($task->getSchedule())
				];
			}
			
			// Sort by schedule frequency (most frequent first), then by name
			usort($tasks, function ($a, $b) {
				if ($a['sort_priority'] === $b['sort_priority']) {
					return strcmp($a['name'], $b['name']);
				}
				
				return $a['sort_priority'] <=> $b['sort_priority'];
			});
			
			// Prepare output data (remove sort_priority from display)
			$result = [];
			
			foreach ($tasks as $task) {
				$result[] = [
					$task['name'],
					$task['description'],
					$task['schedule']
				];
			}
			
			// Show the sorted list
			$this->output->table(['Name', 'Description', 'Schedule'], $result);
			
			// Return success status code
			return 0;
		}
		
		/**
		 * Determines the sorting priority for a cron schedule
		 * Lower numbers = higher priority (more frequent tasks first)
		 * This helps users see the most active tasks at the top of the list.
		 * @param string $schedule The cron schedule string
		 * @return int Priority value for sorting
		 */
		private function getSchedulePriority(string $schedule): int {
			// Handle common cron patterns
			if (str_contains($schedule, '* * * * *')) {
				return 1;
			}
			
			// Every minute
			if (preg_match('/^\d+\/\d+ \* \* \* \*$/', $schedule)) {
				return 2;
			}
			
			// Every X minutes
			if (preg_match('/^\d+ \* \* \* \*$/', $schedule)) {
				return 3;
			}
			
			// Hourly
			if (preg_match('/^\d+ \d+\/\d+ \* \* \*$/', $schedule)) {
				return 4;
			}
			
			// Every X hours
			if (preg_match('/^\d+ \d+ \* \* \*$/', $schedule)) {
				return 5;
			}
			
			// Daily
			if (preg_match('/^\d+ \d+ \* \* \d+$/', $schedule)) {
				return 6;
			}
			
			// Weekly
			if (preg_match('/^\d+ \d+ \d+ \* \*$/', $schedule)) {
				return 7;
			}
			
			// Monthly
			if (preg_match('/^\d+ \d+ \d+ \d+ \*$/', $schedule)) {
				return 8;
			}
			
			// Yearly
			// Default for unrecognized patterns
			return 9;
		}
	}