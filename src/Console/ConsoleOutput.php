<?php
	
	
	namespace Quellabs\Sculpt\Console;
	
	class ConsoleOutput implements \Quellabs\Contracts\IO\ConsoleOutput {
		
		/**
		 * @var false|resource The output stream (usually STDOUT)
		 */
		protected $output;
		
		/**
		 * ANSI color and style codes
		 */
		protected array $styles = [
			// Colors
			'black'      => "\033[30m",
			'red'        => "\033[31m",
			'green'      => "\033[32m",
			'yellow'     => "\033[33m",
			'blue'       => "\033[34m",
			'magenta'    => "\033[35m",
			'cyan'       => "\033[36m",
			'white'      => "\033[37m",
			
			// Background colors
			'bg_black'   => "\033[40m",
			'bg_red'     => "\033[41m",
			'bg_green'   => "\033[42m",
			'bg_yellow'  => "\033[43m",
			'bg_blue'    => "\033[44m",
			'bg_magenta' => "\033[45m",
			'bg_cyan'    => "\033[46m",
			'bg_white'   => "\033[47m",
			
			// Formatting
			'bold'       => "\033[1m",
			'dim'        => "\033[2m",
			'italic'     => "\033[3m",
			'underline'  => "\033[4m",
			'blink'      => "\033[5m",
			'reverse'    => "\033[7m",
			'hidden'     => "\033[8m",
			
			// Reset
			'reset'      => "\033[0m",
		];
		
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
			fwrite($this->output, $this->format($message));
		}
		
		/**
		 * Output text + newline
		 * @param string $message
		 * @return void
		 */
		public function writeLn(string $message): void {
			fwrite($this->output, $this->format($message) . "\n");
		}
		
		/**
		 * Display a success message
		 * @param string $message
		 * @return void
		 */
		public function success(string $message): void {
			$prefix = "<bg_green><white>✓ SUCCESS:</white></bg_green> ";  // White text on a green background with checkmark symbol
			$this->writeLn($prefix . "<green>{$message}</green>");
		}
		
		/**
		 * Display a warning message
		 * @param string $message
		 * @return void
		 */
		public function warning(string $message): void {
			$prefix = "<yellow>⚠ WARNING:</yellow>";  // Yellow color with warning symbol
			$this->writeLn($prefix . $message);
		}
		
		/**
		 * Display an error message
		 * @param string $message
		 * @return void
		 */
		public function error(string $message): void {
			$prefix = "<bg_red><white>✖ ERROR:</white></bg_red> ";  // White text on a red background with error symbol
			$this->writeLn($prefix . "<red>{$message}</red>");
		}
		
		/**
		 * Detect if the console supports colors
		 * @return bool
		 */
		protected function supportsColors(): bool {
			// Windows detection
			if (DIRECTORY_SEPARATOR === '\\') {
				return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI') || 'xterm' === getenv('TERM');
			}
			
			// Linux/macOS detection
			return function_exists('posix_isatty') && @posix_isatty(STDOUT);
		}
		
		/**
		 * Format a string by replacing style tags with ANSI codes
		 * @param string $text Text with style tags
		 * @return string Formatted text with ANSI codes
		 */
		protected function format(string $text): string {
			// Skip formatting if colors are not supported
			if (!$this->supportsColors()) {
				return preg_replace('/<[^>]+>/', '', $text);
			}
			
			// Replace opening style tags with ANSI codes
			foreach ($this->styles as $style => $code) {
				$text = str_replace("<{$style}>", $code, $text);
			}
			
			// Replace all closing tags with reset code
			return preg_replace('/<\/[^>]+>/', $this->styles['reset'], $text);
		}
	}