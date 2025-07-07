<?php
	
	namespace Quellabs\Canvas\TaskScheduler;
	
	/**
	 * Task execution result
	 */
	class TaskResult {
		public readonly TaskInterface $task;
		public readonly bool $success;
		public readonly int $duration;
		public readonly ?\Exception $exception;
		
		public function __construct(
			TaskInterface $task,
			bool          $success,
			int           $duration = 0,
			?\Exception   $exception = null
		) {
			$this->duration = $duration;
			$this->exception = $exception;
			$this->success = $success;
			$this->task = $task;
		}
		
		public function getTaskName(): string {
			return $this->task->getName();
		}
		
		public function isSuccess(): bool {
			return $this->success;
		}
		
		public function getException(): ?\Exception {
			return $this->exception;
		}
		
		public function getDuration(): float {
			return $this->duration;
		}
	}