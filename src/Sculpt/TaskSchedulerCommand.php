<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\Canvas\TaskScheduler\Storage\FileTaskStorage;
	use Quellabs\Canvas\TaskScheduler\TaskScheduler;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	
	class TaskSchedulerCommand extends RoutesBase {
		
		/**
		 * Returns the signature of this command
		 * @return string
		 */
		public function getSignature(): string {
			return "schedule:run";
		}
		
		/**
		 * Returns a brief description of what this command is for
		 * @return string
		 */
		public function getDescription(): string {
			return "Runs all discovered tasks";
		}
		
		/**
		 * List the routes
		 * @param ConfigurationManager $config
		 * @return int
		 */
		public function execute(ConfigurationManager $config): int {
			$discover = new Discover();
			
			$scheduler = new TaskScheduler(
				new FileTaskStorage($discover->getProjectRoot() . "/storage/task-scheduler")
			);
			
			$scheduler->run();
			
			// Return success status
			return 0;
		}
	}