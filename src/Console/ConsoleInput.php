<?php
	
	namespace Quellabs\Sculpt\Console;
	
	class ConsoleInput implements \Quellabs\Contracts\IO\ConsoleInput {
		
		/**
		 * @var false|resource The input stream (usually STDIN)
		 */
		protected $input;
		
		/**
		 * @var ConsoleOutput Output object
		 */
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
				$this->output->write(" (default: $default):\n> ");
			} else {
				$this->output->write(":\n> ");
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
			// Display the main question to the user
			$this->output->writeLn($question);
			
			// Loop through all available choices and display them with numbered options
			foreach ($choices as $key => $choice) {
				// Format each choice with a number (starting from 1) for user selection
				$this->output->writeLn(sprintf("  [%d] %s", $key + 1, $choice));
			}
			
			do {
				// Prompt user to enter their choice
				$answer = $this->ask('Enter your choice');
				
				// Convert user input to zero-based array index (subtract 1 since display starts at 1)
				$index = (int)$answer - 1;
				
				// Continue looping while the selected index doesn't exist in the choices array
			} while (!isset($choices[$index]));
			
			// Return the selected choice text from the choices array
			return $choices[$index];
		}
	}