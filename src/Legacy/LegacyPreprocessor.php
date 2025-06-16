<?php
	
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
		 * Replaces various forms of exit() calls with exception throws
		 * This preserves the termination behavior while allowing Canvas to catch and handle it
		 * @param string $content The PHP code to process
		 * @return string The processed code with exit calls replaced
		 */
		private function replaceExitCalls(string $content): string {
			// Define patterns and their replacements for different exit() variations
			$patterns = [
				// exit() with no arguments -> throw exception with code 0
				'/\bexit\s*\(\s*\)/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(0)',
				
				// exit(code) with numeric argument -> throw exception with that code
				'/\bexit\s*\(\s*(\d+)\s*\)/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException($1)',
				
				// exit(message) with string argument -> throw exception with code 1 and message
				'/\bexit\s*\(\s*([\'"][^\'"]*[\'"])\s*\)/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(1, $1)',
				
				// exit; (statement without parentheses) -> throw exception with code 0
				'/\bexit\s*;/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(0);',
				
				// exit (without parentheses or semicolon) -> throw exception with code 0
				'/\bexit\b(?!\s*[\(;])/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(0)',
				
				// die() calls (alias for exit) - same patterns
				'/\bdie\s*\(\s*\)/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(0)',
				'/\bdie\s*\(\s*(\d+)\s*\)/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException($1)',
				'/\bdie\s*\(\s*([\'"][^\'"]*[\'"])\s*\)/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(1, $1)',
				'/\bdie\s*;/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(0);',
				'/\bdie\b(?!\s*[\(;])/' => 'throw new \Quellabs\Canvas\Legacy\LegacyExitException(0)'
			];
			
			return preg_replace(array_keys($patterns), array_values($patterns), $content);
		}
		
		/**
		 * Replace header() calls with Canvas header collection function.
		 * @param string $content The PHP content to process
		 * @return string Content with header() calls replaced
		 */
		private function replaceHeaderCalls(string $content): string {
			// Match header() calls with string literals
			$pattern = '/header\s*\(\s*([\'"])([^\1]*?)\1\s*(?:,\s*(true|false))?\s*\)/';
			
			return preg_replace_callback($pattern, function ($matches) {
				$headerValue = $matches[2];
				$replace = isset($matches[3]) && $matches[3] === 'false' ? 'true' : 'false';
				
				// Escape single quotes in the header value
				$headerValue = str_replace("'", "\\'", $headerValue);
				
				return "canvas_header('{$headerValue}', {$replace})";
			}, $content);
		}
		
		/**
		 * Add Canvas helper functions to the beginning of the PHP file.
		 * @param string $content The original PHP content
		 * @return string Content with Canvas helpers prepended
		 */
		private function addCanvasHelper(string $content): string {
			$helper = '<?php
// Canvas Legacy Helper Functions - Auto-generated

// Import the LegacyExitException class
use Quellabs\Canvas\Legacy\LegacyExitException;

if (!function_exists("canvas_header")) {
    /**
     * Canvas-compatible header function that collects headers instead of sending them.
     * @param string $header Header string to send
     * @param bool $replace Whether to replace previous header (default: true)
     */
    function canvas_header($header, $replace = true) {
        global $__canvas_headers;
        
        if (!isset($__canvas_headers)) {
            $__canvas_headers = [];
        }
        
        // If not replacing, just add to the array
        if (!$replace) {
            $__canvas_headers[] = $header;
            return;
        }
        
        // If replacing, remove any existing headers with the same name
        $headerName = strtolower(explode(":", $header, 2)[0] ?? "");
        
        if ($headerName) {
            $__canvas_headers = array_filter($__canvas_headers, function($existingHeader) use ($headerName) {
                $existingName = strtolower(explode(":", $existingHeader, 2)[0] ?? "");
                return $existingName !== $headerName;
            });
        }
        
        $__canvas_headers[] = $header;
    }
}

';
			
			// Remove existing <?php opening tag if present and add our helper
			$content = preg_replace('/^\s*<\?php\s*/', '', $content, 1);
			return $helper . "\n" . $content;
		}
	}