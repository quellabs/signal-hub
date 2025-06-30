<?php
	
	namespace Quellabs\Contracts\IO;
	
	interface ConsoleOutput {
		
		/**
		 * Print a table
		 * @param array $headers
		 * @param array $rows
		 * @return void
		 */
		public function table(array $headers, array $rows): void;
		
		/**
		 * Print table row
		 * @param array $row
		 * @param array $widths
		 * @return void
		 */
		public function printRow(array $row, array $widths): void;
		
		/**
		 * Print separator
		 * @param array $widths
		 * @return void
		 */
		public function printSeparator(array $widths): void;
		
		/**
		 * Output text
		 * @param string $message
		 * @return void
		 */
		public function write(string $message): void;
		
		/**
		 * Output text + newline
		 * @param string $message
		 * @return void
		 */
		public function writeLn(string $message): void;
		
		/**
		 * Display a success message
		 * @param string $message
		 * @return void
		 */
		public function success(string $message): void;
		
		/**
		 * Display a warning message
		 * @param string $message
		 * @return void
		 */
		public function warning(string $message): void;
		
		/**
		 * Display an error message
		 * @param string $message
		 * @return void
		 */
		public function error(string $message): void;
	}