<?php
	
	namespace Quellabs\Canvas\Legacy;
	
	use Quellabs\Canvas\Exceptions\RouteNotFoundException;
	use Quellabs\Canvas\Kernel;
	use Quellabs\Canvas\Legacy\Resolvers\DefaultFileResolver;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;
	
	/**
	 * Handles fallthrough routing for legacy PHP files when modern routes don't match.
	 * This allows gradual migration from legacy PHP files to modern framework routes.
	 */
	class LegacyHandler {
		
		/** @var FileResolverInterface[] Array of file resolvers to try in order */
		private array $resolvers = [];
		
		/** @var string Base path where legacy files are located */
		private string $legacyPath;
		
		/** @var bool True if we should preprocess the legacy php file */
		private bool $preprocessingEnabled;
		
		/** @var LegacyPreprocessor Optional preprocessor for handling exit() and headers */
		private LegacyPreprocessor $preprocessor;
		
		/**
		 * Cache directory for preprocessed legacy PHP files
		 * @var string
		 */
		private string $cacheDir;
		
		/**
		 * Initialize the handler with a base legacy path.
		 * @param Kernel $kernel
		 * @param string $legacyPath Base directory path for legacy files
		 * @param bool $preprocessingEnabled
		 */
		public function __construct(Kernel $kernel, string $legacyPath = 'legacy/', bool $preprocessingEnabled = true) {
			// Store the legacy path
			$this->legacyPath = $legacyPath;
			
			// Store preprocessing setting
			$this->preprocessingEnabled = $preprocessingEnabled;
			
			// Set cache directory using kernel's project root
			$this->cacheDir = $kernel->getDiscover()->getProjectRoot() . '/storage/cache/legacy';
			
			// Create cache directory if preprocessing is enabled
			if ($this->preprocessingEnabled && !is_dir($this->cacheDir)) {
				mkdir($this->cacheDir, 0755, true);
			}
			
			// Initialize the legacy preprocessor only if needed
			if ($this->preprocessingEnabled) {
				$this->preprocessor = new LegacyPreprocessor();
			}
			
			// Add default resolver for standard file resolution
			$this->addResolver(new DefaultFileResolver($this->legacyPath));
		}
		
		/**
		 * Add a file resolver to the chain of resolvers.
		 * Resolvers are tried in the order they were added.
		 * @param FileResolverInterface $resolver The resolver to add
		 * @return self Fluent interface
		 */
		public function addResolver(FileResolverInterface $resolver): self {
			$this->resolvers[] = $resolver;
			return $this;
		}
		
		/**
		 * Handle the request by attempting to find and execute a legacy file.
		 * Tries each resolver in order until one finds a matching file.
		 * @param Request $request The HTTP request to handle
		 * @return Response The response from executing the legacy file
		 * @throws RouteNotFoundException When no resolver can find a matching file
		 */
		public function handle(Request $request): Response {
			// Fetch the path to resolve
			$path = $request->getPathInfo();
			
			// Try each resolver until one finds a file
			foreach ($this->resolvers as $resolver) {
				$legacyFile = $resolver->resolve($path, $request);
				
				// If resolver found a file and it passes security checks
				if ($legacyFile && $this->isSafeFile($legacyFile)) {
					return $this->executeLegacyFile($legacyFile, $request);
				}
			}
			
			// No resolver could handle this path
			throw RouteNotFoundException::forLegacyFallthrough($request->getPathInfo());
		}
		
		/**
		 * Clear the legacy file preprocessing cache.
		 * @return int Number of files removed
		 */
		public function clearCache(): int {
			if (!$this->preprocessingEnabled || !is_dir($this->cacheDir)) {
				return 0;
			}
			
			$files = glob($this->cacheDir . '/*.php');
			$removed = 0;
			
			foreach ($files as $file) {
				if (unlink($file)) {
					++$removed;
				}
			}
			
			return $removed;
		}
		
		/**
		 * Check if preprocessing is enabled.
		 * @return bool True if preprocessing is enabled
		 */
		public function isPreprocessingEnabled(): bool {
			return $this->preprocessingEnabled;
		}
		
		/**
		 * Perform basic security checks on the resolved file.
		 * Ensures the file exists, is readable, and has a .php extension.
		 * @param string $file Path to the file to check
		 * @return bool True if the file is safe to execute
		 */
		private function isSafeFile(string $file): bool {
			// Basic security: must be .php and exist
			return
				file_exists($file) &&
				str_ends_with($file, '.php') &&
				is_readable($file);
		}
		
		/**
		 * Get the preprocessed version of a legacy file, creating it if necessary.
		 * @param string $originalFile Path to the original legacy file
		 * @return string Path to the preprocessed file
		 */
		private function getProcessedFile(string $originalFile): string {
			// Generate a cache key based on file path and modification time
			$cacheKey = md5($originalFile . filemtime($originalFile));
			$cachedFile = $this->cacheDir . '/' . $cacheKey . '.php';
			
			// Create a preprocessed file if it doesn't exist
			if (!file_exists($cachedFile)) {
				$processedContent = $this->preprocessor->preprocess($originalFile);
				file_put_contents($cachedFile, $processedContent);
			}
			
			return $cachedFile;
		}
		
		/**
		 * Execute the legacy PHP file and capture its output.
		 * Sets up the execution environment and returns the output as a Response.
		 * @param string $file Path to the legacy file to execute
		 * @param Request $request The original HTTP request
		 * @return Response The response containing the legacy file's output
		 */
		private function executeLegacyFile(string $file, Request $request): Response {
			// Set up basic environment variables for legacy compatibility
			$this->setupGlobals($request);
			
			// Determine which file to execute (original or preprocessed)
			if ($this->preprocessingEnabled) {
				$fileToExecute = $this->getProcessedFile($file);
			} else {
				$fileToExecute = $file;
			}
			
			// Initialize Canvas helper globals if preprocessing is enabled
			if ($this->preprocessingEnabled) {
				$this->initializeCanvasGlobals();
			}
			
			// Capture output buffer to get the legacy file's output
			ob_start();
			include $fileToExecute;
			$content = ob_get_clean();
			
			// Create response with proper status and headers if preprocessing enabled
			return $this->preprocessingEnabled ? $this->createResponseWithHeaders($content) : new Response($content);
		}
		
		/**
		 * Initialize global variables used by Canvas helper functions.
		 * @return void
		 */
		private function initializeCanvasGlobals(): void {
			global $__canvas_exit_code, $__canvas_headers;
			$__canvas_exit_code = 0;
			$__canvas_headers = [];
		}
		
		/**
		 * Set up global variables that legacy PHP files expect.
		 * Populates $_GET, $_POST, $_REQUEST and starts session if needed.
		 * @param Request $request The HTTP request to extract data from
		 * @return void
		 */
		private function setupGlobals(Request $request): void {
			// Basic $_GET, $_POST setup for legacy compatibility
			$_GET = $request->query->all();
			$_POST = $request->request->all();
			$_REQUEST = array_merge($_GET, $_POST);
			
			// Start session if needed (legacy files often expect sessions)
			if (session_status() === PHP_SESSION_NONE) {
				session_start();
			}
		}
		
		/**
		 * Determine HTTP status code from headers and exit code.
		 * @param array $headers Array of header strings
		 * @param int $exitCode Exit code from the legacy script
		 * @return int HTTP status code
		 */
		private function determineStatusCode(array $headers, int $exitCode): int {
			// Check headers for HTTP status
			foreach ($headers as $header) {
				if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
					return (int)$matches[1];
				}
			}
			
			// Using ternary operators for status code determination
			return $exitCode === 0 ? 200 : ($exitCode === 1 ? 500 : $exitCode);
		}
		
		/**
		 * Parse raw header strings into an associative array.
		 * @param array $rawHeaders Array of raw header strings
		 * @return array Associative array of headers
		 */
		private function parseHeaders(array $rawHeaders): array {
			$headers = [];
			
			foreach ($rawHeaders as $header) {
				// Skip HTTP status lines
				if (preg_match('/^HTTP\/\d\.\d/', $header)) {
					continue;
				}
				
				// Parse header: value format
				if (preg_match('/^([^:]+):\s*(.+)$/', $header, $matches)) {
					$headers[trim($matches[1])] = trim($matches[2]);
				}
			}
			
			return $headers;
		}
		
		/**
		 * Handle 404 responses by loading custom error page if available.
		 * @param string $content Current content (may be empty)
		 * @return Response 404 response
		 */
		private function handle404(string $content): Response {
			// Try to load custom 404 page if content is empty
			if (empty(trim($content))) {
				$custom404 = $this->legacyPath . '/404.php';
				
				if (file_exists($custom404)) {
					if ($this->preprocessingEnabled) {
						$fileToExecute = $this->getProcessedFile($custom404);
					} else {
						$fileToExecute = $custom404;
					}
					
					ob_start();
					include $fileToExecute;
					$content = ob_get_clean();
				}
			}
			
			return new Response($content, 404);
		}
		
		/**
		 * Create a Response object with proper status code and headers from Canvas globals.
		 * @param string $content The output content from the legacy file
		 * @return Response The response with proper status and headers
		 */
		private function createResponseWithHeaders(string $content): Response {
			global $__canvas_exit_code, $__canvas_headers;
			
			// Using ternary operators for variable assignment
			$exitCode = isset($__canvas_exit_code) ? $__canvas_exit_code : 0;
			$headers = isset($__canvas_headers) ? $__canvas_headers : [];
			
			$statusCode = $this->determineStatusCode($headers, $exitCode);
			$parsedHeaders = $this->parseHeaders($headers);
			
			// Using ternary for return statement
			return $statusCode === 404 ? $this->handle404($content) : new Response($content, $statusCode, $parsedHeaders);
		}
	}