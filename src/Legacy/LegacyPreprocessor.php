<?php
// src/Legacy/LegacyPreprocessor.php
	
	namespace Quellabs\Canvas\Legacy;
	
	/**
	 * LegacyPreprocessor handles the transformation of legacy PHP code
	 * to make it compatible with the Canvas framework by replacing
	 * problematic function calls and adding helper functions.
	 */
	class LegacyPreprocessor {
		
		/**
		 * Main preprocessing method that transforms legacy PHP code
		 * @param string $filePath Path to the legacy PHP file to process
		 * @return string The processed PHP code as a string
		 */
		public function preprocess(string $filePath): string {
			// Read the original file contents
			$content = file_get_contents($filePath);
			
			// Apply transformations in sequence
			// Replace exit() calls with return statements for Canvas compatibility
			$content = $this->replaceExitCalls($content);
			
			// Replace header() calls with Canvas-compatible header collection
			$content = $this->replaceHeaderCalls($content);
			
			// Inject Canvas helper functions at the beginning of the file
			return $this->addCanvasHelper($content);
		}
		
		/**
		 * Replaces various forms of exit() calls with Canvas-compatible returns
		 * This prevents legacy code from terminating the entire application
		 * @param string $content The PHP code to process
		 * @return string The processed code with exit calls replaced
		 */
		private function replaceExitCalls(string $content): string {
			// Define patterns and their replacements for different exit() variations
			$patterns = [
				'/\bexit\s*\(\s*\)/'         => 'return',              // exit() -> return
				'/\bexit\s*\(\s*(\d+)\s*\)/' => 'return canvas_exit($1)', // exit(1) -> return canvas_exit(1)
				'/\bexit\s*;/'               => 'return;',             // exit; -> return;
				'/\bexit\b/'                 => 'return'               // exit -> return
			];
			
			return preg_replace(array_keys($patterns), array_values($patterns), $content);
		}
		
		/**
		 * Replaces header() function calls with Canvas header collection
		 * This allows headers to be captured and processed by Canvas instead
		 * of being sent immediately
		 *
		 * @param string $content The PHP code to process
		 * @return string The processed code with header calls replaced
		 */
		private function replaceHeaderCalls(string $content): string {
			// Pattern to match header('Content-Type: text/html') style calls
			$pattern = '/header\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';
			
			return preg_replace_callback($pattern, function ($matches) {
				$headerValue = $matches[1]; // Extract the header value
				return "canvas_header('{$headerValue}')"; // Replace with Canvas function
			}, $content);
		}
		
		/**
		 * Adds Canvas helper functions to the beginning of the processed file
		 * These functions provide Canvas-compatible alternatives to standard PHP functions
		 * @param string $content The original PHP code
		 * @return string The code with Canvas helpers prepended
		 */
		private function addCanvasHelper(string $content): string {
			// Define the Canvas helper functions
			$helper = '<?php
// Canvas Legacy Helper Functions
// These functions replace standard PHP functions to make legacy code Canvas-compatible

// Replacement for exit() that stores the exit code globally instead of terminating
if (!function_exists("canvas_exit")) {
    function canvas_exit($code = 0) {
        global $__canvas_exit_code;
        $__canvas_exit_code = $code;
        return; // Return instead of terminating the application
    }
}

// Replacement for header() that collects headers instead of sending them immediately
if (!function_exists("canvas_header")) {
    function canvas_header($header) {
        global $__canvas_headers;
        if (!isset($__canvas_headers)) {
            $__canvas_headers = [];
        }
        $__canvas_headers[] = $header; // Store header for later processing
    }
}

// Initialize global variables for Canvas helper functions
global $__canvas_exit_code, $__canvas_headers;
$__canvas_exit_code = 0;
$__canvas_headers = [];
?>';
			
			// Remove the existing <?php opening tag from the original content
			$content = preg_replace('/^\s*<\?php/', '', $content);
			
			// Prepend the helper functions to the original content
			return $helper . "\n" . $content;
		}
	}