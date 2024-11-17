<?php
	
	
	namespace Services\CommandRunner;
	
	class ConsoleInput {
		protected $input;
		protected ConsoleOutput $output;
		
		/**
		 * ConsoleInput Constructor
		 * @param ConsoleOutput $output
		 */
		public function __construct(ConsoleOutput $output) {
			$this->input = STDIN;
			$this->output = $output;
		}
		
		/**
		 * Ask a question and return the answer
		 * @param string $question
		 * @param string|null $default
		 * @return string|null
		 */
		public function ask(string $question, ?string $default = null): ?string {
			$this->output->write($question);

			if ($default) {
				$this->output->write(" (default: $default): ");
			} else {
				$this->output->write(": ");
			}
			
			$answer = trim(fgets($this->input));
			return $answer ?: $default;
		}
		
		/**
		 * Ask for confirmation
		 * @param string $question
		 * @param bool $default
		 * @return bool
		 */
		public function confirm(string $question, bool $default = true): bool {
			$response = $this->ask($question . ' (y/n)', $default ? 'y' : 'n');
			return strtolower($response[0] ?? '') === 'y';
		}
		
		/**
		 * Multiple choice question
		 * @param string $question
		 * @param array $choices
		 * @param $default
		 * @return string
		 */
		public function choice(string $question, array $choices, $default = null): string {
			$this->output->writeLn($question);
			
			foreach ($choices as $key => $choice) {
				$this->output->writeLn(sprintf("  [%d] %s", $key + 1, $choice));
			}
			
			do {
				$answer = $this->ask('Enter your choice');
				$index = (int)$answer - 1;
			} while (!isset($choices[$index]));
			
			return $choices[$index];
		}
	}