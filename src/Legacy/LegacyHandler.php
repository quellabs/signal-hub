<?php
	
	namespace Quellabs\Canvas\Legacy;
	
	use Quellabs\Canvas\Exceptions\RouteNotFoundException;
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
		
		/**
		 * Initialize the handler with a base legacy path.
		 * @param string $legacyPath Base directory path for legacy files
		 */
		public function __construct(string $legacyPath = 'legacy/') {
			// Store the legacy path
			$this->legacyPath = $legacyPath;
			
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
		 * Execute the legacy PHP file and capture its output.
		 * Sets up the execution environment and returns the output as a Response.
		 * @param string $file Path to the legacy file to execute
		 * @param Request $request The original HTTP request
		 * @return Response The response containing the legacy file's output
		 */
		private function executeLegacyFile(string $file, Request $request): Response {
			// Set up basic environment variables for legacy compatibility
			$this->setupGlobals($request);
			
			// Capture output buffer to get the legacy file's output
			ob_start();
			include $file;
			$content = ob_get_clean();
			
			// Return the captured output as an HTTP response
			return new Response($content);
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
	}