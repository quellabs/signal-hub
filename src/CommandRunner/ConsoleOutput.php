<?php
	
	
	namespace Services\CommandRunner;
	
	class ConsoleOutput {
		
		protected $output;
		
		/**
		 * ConsoleOutput Constructor
		 */
		public function __construct() {
			$this->output = STDOUT;
		}
		
		/**
		 * Print a table
		 * @param array $headers
		 * @param array $rows
		 * @return void
		 */
		public function table(array $headers, array $rows): void {
			// Calculate column widths
			$widths = array_map('strlen', $headers);
			
			foreach ($rows as $row) {
				foreach ($row as $key => $value) {
					$widths[$key] = max($widths[$key], strlen($value));
				}
			}
			
			// Print headers
			$this->printRow($headers, $widths);
			$this->printSeparator($widths);
			
			// Print rows
			foreach ($rows as $row) {
				$this->printRow($row, $widths);
			}
		}
		
		/**
		 * Print table row
		 * @param array $row
		 * @param array $widths
		 * @return void
		 */
		public function printRow(array $row, array $widths): void {
			$cells = array_map(function ($value, $width) {
				return str_pad($value, $width);
			}, $row, $widths);
			
			$this->write("| " . implode(" | ", $cells) . " |\n");
		}
		
		/**
		 * Print seperator
		 * @param array $widths
		 * @return void
		 */
		public function printSeparator(array $widths): void {
			$separator = array_map(function ($width) {
				return str_repeat('-', $width);
			}, $widths);
			
			$this->write("+-" . implode("-+-", $separator) . "-+\n");
		}
		
		/**
		 * Output text
		 * @param string $message
		 * @return void
		 */
		public function write(string $message): void {
			fwrite($this->output, $message);
		}

		/**
		 * Output text + newline
		 * @param string $message
		 * @return void
		 */
		public function writeLn(string $message): void {
			fwrite($this->output, $message . "\n");
		}
	}