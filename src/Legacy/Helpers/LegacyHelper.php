<?php
 
	// Canvas Legacy Helper Functions - Auto-generated
	if (!function_exists("canvas_header")) {
		/**
		 * Canvas-compatible header function that collects headers instead of sending them.
		 * @param string $header Header string to send
		 * @param bool $replace Whether to replace previous header (default: true)
		 * @return void
		 */
		function canvas_header(string $header, bool $replace = true): void {
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