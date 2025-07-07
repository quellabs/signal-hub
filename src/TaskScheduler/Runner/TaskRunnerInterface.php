<?php
	
	namespace Quellabs\Canvas\TaskScheduler\Runner;
	
	use Quellabs\Canvas\TaskScheduler\TaskException;
	use Quellabs\Canvas\TaskScheduler\TaskInterface;
	use Quellabs\Canvas\TaskScheduler\TaskTimeoutException;
	
	/**
	 * Interface for timeout strategy implementations.
	 */
	interface TaskRunnerInterface {
		
		/**
		 * Executes a task with a specified timeout limit.
		 * @param TaskInterface $task The task to execute
		 * @throws TaskTimeoutException If the task exceeds the specified timeout
		 * @throws TaskException If the task fails to execute or encounters an error
		 * @return void
		 */
		public function run(TaskInterface $task): void;
	}