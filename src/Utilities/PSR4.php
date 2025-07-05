<?php
	
	namespace Quellabs\Discover\Utilities;
	
	use Composer\Autoload\ClassLoader;
	use RuntimeException;
	
	class PSR4 {
		
		/**
		 * @var string|null Cached project root
		 */
		protected ?string $projectRootPathCache = null;
		
		/**
		 * Cache of parsed composer.json files
		 * @var array<string, array|null>
		 */
		private array $composerJsonCache = [];
		
		/**
		 * Cache for path resolving paths
		 * @var array
		 */
		private array $resolvedPaths = [];
		
		/**
		 * Gets the Composer autoloader instance
		 * @return ClassLoader
		 * @throws RuntimeException If autoloader can't be found
		 */
		public function getComposerAutoloader(): ClassLoader {
			// Try to find the Composer autoloader
			foreach (spl_autoload_functions() as $autoloader) {
				if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
					return $autoloader[0];
				}
			}
			
			// Look for the autoloader in common locations
			$autoloaderPaths = [
				// From the current working directory
				getcwd() . '/vendor/autoload.php',
				
				// From this file's directory, going up to find vendor
				dirname(__DIR__, 3) . '/vendor/autoload.php',
				dirname(__DIR__, 4) . '/vendor/autoload.php',
			];
			
			foreach ($autoloaderPaths as $path) {
				if (file_exists($path)) {
					return require $path;
				}
			}
			
			throw new RuntimeException('Could not find Composer autoloader');
		}
		
		/**
		 * Find directory containing composer.json by traversing up from the given directory
		 * Uses multiple detection strategies optimized for different hosting environments
		 * @param string|null $directory Directory to start searching from (defaults to current directory)
		 * @return string|null Directory containing composer.json if found, null otherwise
		 */
		public function getProjectRoot(?string $directory = null): ?string {
			// Return the cached result if available to avoid repeated filesystem operations
			if ($this->projectRootPathCache !== null) {
				return $this->projectRootPathCache;
			}
			
			// Try multiple detection strategies in order of efficiency
			$strategies = [
				// Strategy 1: Check for common shared hosting directory patterns
				// This is faster than traversing and works well for cPanel/Plesk environments
				fn() => $this->getSharedHostingRoot($directory),
				
				// Strategy 2: Traditional traversal method - walk up directory tree looking for composer.json
				// This handles custom setups and non-standard hosting environments
				fn() => $this->getProjectRootFromComposerJson($directory),
			];
			
			foreach ($strategies as $strategy) {
				$projectRoot = $strategy();
				
				if ($projectRoot !== null) {
					return $this->projectRootPathCache = $projectRoot;
				}
			}
			
			// No project root found using any strategy
			return null;
		}
		
		/**
		 * Find the path to the local composer.json file
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return string|null Path to composer.json if found, null otherwise
		 */
		public function getComposerJsonFilePath(?string $startDirectory = null): ?string {
			// Find the directory containing composer.json, starting from provided directory or current directory
			$projectRoot = $this->getProjectRoot($startDirectory);
			
			// If we couldn't find the project root, we can't locate installed.json
			if ($projectRoot === null) {
				return null;
			}
			
			// Return the full path to the composer.json file
			return $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
		}
		
		/**
		 * Find the path to Composer's installed.json file
		 * This file contains information about all installed packages
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return string|null Path to installed.json if found, null otherwise
		 */
		public function getComposerInstalledFilePath(?string $startDirectory = null): ?string {
			// First find the project root, as we'll need to navigate to vendor/composer from there
			$projectRoot = $this->getProjectRoot($startDirectory);
			
			// If we couldn't find the project root, we can't locate installed.json
			if ($projectRoot === null) {
				return null;
			}
			
			// The installed.json file is typically located in vendor/composer directory
			return $projectRoot . DIRECTORY_SEPARATOR . 'vendor' .
				DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
		}
		
		/**
		 * Maps a directory path to a namespace based on PSR-4 rules.
		 * This method attempts to determine the correct namespace for a directory by:
		 * 1. First checking against registered autoloader PSR-4 mappings (for dependencies)
		 * 2. Then checking against the main project's composer.json PSR-4 mappings if necessary
		 * @param string $directory Directory path to map to a namespace
		 * @return string|null The corresponding namespace if found, null otherwise
		 */
		public function resolveNamespaceFromPath(string $directory): ?string {
			// Convert to the absolute real path to ensure consistent path comparison
			$directory = realpath($directory);
			
			// Early return if the directory doesn't exist or isn't readable
			if (!$directory) {
				return null;
			}
			
			// First approach: Use the already registered Composer autoloader
			// This works well for packages/dependencies that have been autoloaded
			$composerNamespace = $this->resolveNamespaceFromAutoloader($directory);
			
			// If we found a matching namespace through the autoloader, return it immediately
			if ($composerNamespace !== null) {
				return $composerNamespace;
			}
			
			// Second approach: Parse the main project's composer.json file directly
			// This is necessary when dealing with the current project's namespaces
			// which might not be fully registered in the autoloader yet
			return $this->resolveNamespaceFromComposerJson($directory);
		}
		
		/**
		 * Recursively scans a directory and maps files to namespaced classes based on PSR-4 rules
		 * @param string $directory Directory to scan
		 * @param callable|null $filter Optional callback function to filter classes (receives className as parameter)
		 * @return array<string> Array of fully qualified class names
		 */
		public function findClassesInDirectory(string $directory, ?callable $filter = null): array {
			// Early return if directory doesn't exist or is not readable
			$absoluteDir = realpath($directory);
			
			if (!$absoluteDir) {
				return [];
			}
			
			// Get the namespace for this directory using our preferred method
			$namespaceForDir = $this->resolveNamespaceFromPath($absoluteDir);
			
			// If no namespace was found for the directory, we can return early
			// This is an optimization as we avoid scanning directories that aren't part of a PSR-4 namespace
			if (!$namespaceForDir) {
				return [];
			}
			
			// Get directory entries or return an empty array if scandir fails
			$classNames = [];
			$entries = scandir($absoluteDir) ?: [];
			
			foreach ($entries as $entry) {
				// Skip current directory, parent directory, and hidden files
				if ($this->shouldSkipEntry($entry)) {
					continue;
				}
				
				// Fetch the full path
				$fullPath = $absoluteDir . DIRECTORY_SEPARATOR . $entry;
				
				// Recursively scan subdirectories and merge results
				if (is_dir($fullPath)) {
					$subDirClasses = $this->findClassesInDirectory($fullPath, $filter);
					$classNames = array_merge($classNames, $subDirClasses);
					continue; // Early continue to next iteration
				}
				
				// Skip if not a PHP file
				if (!$this->isPhpFile($entry)) {
					continue;
				}
				
				// Fetch class name from the file
				$className = $this->extractClassNameFromFile($entry);
				
				// Build the fully qualified class name
				$fullyQualifiedClassName = $namespaceForDir . '\\' . $className;
				
				// Apply the filter if provided
				if ($filter !== null && !$filter($fullyQualifiedClassName)) {
					continue;
				}
				
				// Add the complete namespace to the list
				$classNames[] = $fullyQualifiedClassName;
			}
			
			return $classNames;
		}

		/**
		 * Resolves relative path components without checking file existence
		 * @param string $path The path to resolve (e.g., "hallo/../test")
		 * @return string The resolved path (e.g., "test")
		 */
		public function resolvePath(string $path): string {
			// Check if this path has already been resolved and cached
			if (isset($this->resolvedPaths[$path])) {
				return $this->resolvedPaths[$path];
			}
			
			// Perform the actual path resolution logic
			$resolved = $this->doResolvePath($path);
			
			// Cache the resolved path for future lookups to improve performance
			$this->resolvedPaths[$path] = $resolved;
			
			// Return the resolved path
			return $resolved;
		}
		
		/**
		 * Attempts to find namespace from the registered Composer autoloader
		 * @param string $directory Resolved realpath to directory
		 * @return string|null Namespace if found
		 */
		private function resolveNamespaceFromAutoloader(string $directory): ?string {
			try {
				// Get the Composer autoloader
				$composerAutoloader = $this->getComposerAutoloader();
				
				// Get PSR-4 prefixes from the autoloader
				$prefixesPsr4 = $composerAutoloader->getPrefixesPsr4();
				
				// Find the longest matching namespace prefix
				return $this->findMostSpecificNamespace($directory, $prefixesPsr4);
			} catch (\Exception $e) {
				return null;
			}
		}
		
		/**
		 * Attempts to find a namespace for a directory by directly parsing the main project's composer.json file.
		 * This approach is used when the autoloader-based approach fails, typically for the current project's
		 * files that might not be fully registered in the autoloader during development.
		 * @param string $directory Resolved realpath to the directory we need to find a namespace for
		 * @return string|null The namespace corresponding to the directory, or null if not found
		 */
		private function resolveNamespaceFromComposerJson(string $directory): ?string {
			// First, locate the project's composer.json file by traversing upwards from the current directory
			$composerJsonPath = $this->getComposerJsonFilePath();
			
			// If we can't find composer.json, we can't determine the namespace
			if (!$composerJsonPath) {
				return null;
			}
			
			// Parse the composer.json file with caching to avoid repeated parsing
			$composerJson = $this->parseComposerJson($composerJsonPath);
			
			// Verify the composer.json contains PSR-4 autoloading configuration
			// This is necessary because not all projects use PSR-4 autoloading
			if (!$composerJson || !isset($composerJson['autoload']['psr-4'])) {
				return null;
			}
			
			// Convert the composer.json PSR-4 configuration to the same format used by the autoloader
			// Format needed: ['Namespace\\' => ['/absolute/path1', '/absolute/path2']]
			$prefixesPsr4 = [];
			
			// Get the project root directory (the directory containing composer.json)
			$projectDir = dirname($composerJsonPath);
			
			// Process each PSR-4 namespace defined in composer.json
			foreach ($composerJson['autoload']['psr-4'] as $namespace => $paths) {
				// Normalize paths to an array (composer.json allows both string and array formats)
				// Example: "src/" or ["src/", "lib/"]
				$paths = is_array($paths) ? $paths : [$paths];
				
				// Convert relative paths to absolute paths, as required for path comparison
				// Example: "src/" becomes "/var/www/project/src/"
				$absolutePaths = array_map(function ($path) use ($projectDir) {
					return realpath($projectDir . DIRECTORY_SEPARATOR . $path) ?: '';
				}, $paths);
				
				// Remove any paths that don't exist or aren't directories
				// This prevents issues with misconfigured or outdated composer.json files
				$absolutePaths = array_filter($absolutePaths, 'is_dir');
				
				// Only add this namespace if at least one valid directory exists for it
				if (!empty($absolutePaths)) {
					$prefixesPsr4[$namespace] = $absolutePaths;
				}
			}
			
			// Use the same logic as the autoloader-based approach to find the best namespace match
			// This ensures consistent namespace resolution regardless of which method finds it
			return $this->findMostSpecificNamespace($directory, $prefixesPsr4);
		}
		
		/**
		 * Finds the longest matching namespace for a directory based on PSR-4 prefixes.
		 * When multiple PSR-4 prefixes could match a directory, we select the one with the
		 * longest matching path, which is typically the most specific match.
		 * @param string $directory Absolute directory path to find namespace for
		 * @param array $prefixesPsr4 PSR-4 namespace prefixes and their directories
		 *                           Format: ['Namespace\\' => ['/path/to/dir', '/another/path']]
		 * @return string|null The complete namespace for the directory, or null if no match found
		 */
		private function findMostSpecificNamespace(string $directory, array $prefixesPsr4): ?string {
			// Track best match found so far
			$matchedNamespace = null;
			$longestMatch = 0;
			
			// Iterate through all registered PSR-4 namespace prefixes
			foreach ($prefixesPsr4 as $prefix => $dirs) {
				// A single namespace prefix may map to multiple directories
				foreach ($dirs as $psr4Dir) {
					// Skip empty or invalid directories
					if (empty($psr4Dir)) {
						continue;
					}
					
					// Check if our target directory starts with this PSR-4 path
					// If it does, it means our directory is either the same as or within this PSR-4 root
					if (str_starts_with($directory, $psr4Dir)) {
						// Calculate how much of the path matches to determine specificity
						$matchLength = strlen($psr4Dir);
						
						// If this match is more specific (longer) than previous matches, use it
						if ($matchLength > $longestMatch) {
							$longestMatch = $matchLength;
							
							// Calculate the relative path from the PSR-4 root to our directory
							// This will be converted to the namespace suffix
							if (strlen($directory) > strlen($psr4Dir)) {
								// Add 1 to skip the directory separator
								$relativePath = substr($directory, strlen($psr4Dir) + 1);
							} else {
								$relativePath = '';
							}
							
							// Convert the filesystem path format to namespace format
							// Example: "Controller/User" becomes "Controller\User"
							$namespaceSuffix = str_replace(
								DIRECTORY_SEPARATOR,
								'\\',
								$relativePath
							);
							
							// Build the complete namespace by combining:
							// 1. The PSR-4 namespace prefix (e.g., "App\")
							// 2. The namespace suffix derived from the relative path
							$matchedNamespace =
								rtrim($prefix, '\\') .
								(empty($namespaceSuffix) ? '' : '\\' . $namespaceSuffix);
						}
					}
				}
			}
			
			// Return the most specific namespace match, or null if none found
			return $matchedNamespace;
		}
		
		/**
		 * Parses a composer.json file with caching
		 * @param string $path Path to composer.json
		 * @return array|null Parsed composer.json as array or null on failure
		 */
		private function parseComposerJson(string $path): ?array {
			// Return cached result if available
			if (isset($this->composerJsonCache[$path])) {
				return $this->composerJsonCache[$path];
			}
			
			// Parse the file
			$result = $this->parseComposerJsonWithoutCache($path);
			
			// Cache the result
			$this->composerJsonCache[$path] = $result;
			
			// Return the result
			return $result;
		}
		
		/**
		 * Parses a composer.json file without caching
		 * @param string $path Path to composer.json
		 * @return array|null Parsed composer.json as array or null on failure
		 */
		private function parseComposerJsonWithoutCache(string $path): ?array {
			// Attempt to read the file at the given path
			// Note: file_get_contents returns string on success, false on failure
			$content = file_get_contents($path);
			
			// Check if file reading was successful
			// If the file doesn't exist or is not readable, return null early
			if ($content === false) {
				return null;
			}
			
			// Decode JSON string into associative array
			// Second parameter 'true' ensures the result is an array instead of an object
			$data = json_decode($content, true);
			
			// Verify JSON decoding was successful
			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}
			
			// Return the parsed composer.json as an associative array
			return $data;
		}
		
		/**
		 * Checks if an entry should be skipped during directory scanning
		 * @param string $entry Directory entry name
		 * @return bool True if entry should be skipped
		 */
		private function shouldSkipEntry(string $entry): bool {
			return in_array($entry, ['.', '..', '.htaccess'], true);
		}
		
		/**
		 * Checks if a file is a PHP file
		 * @param string $filename Filename to check
		 * @return bool True if the file is a PHP file
		 */
		private function isPhpFile(string $filename): bool {
			return str_ends_with($filename, '.php');
		}
		
		/**
		 * Gets class name from a file path
		 * @param string $filename File name
		 * @return string Class name
		 */
		private function extractClassNameFromFile(string $filename): string {
			return pathinfo($filename, PATHINFO_FILENAME);
		}
		
		/**
		 * Find directory containing composer.json by traversing up from the given directory
		 * @param string|null $directory Directory to start searching from (defaults to current directory)
		 * @return string|null Directory containing composer.json if found, null otherwise
		 */
		private function getProjectRootFromComposerJson(?string $directory = null): ?string {
			// If no directory provided, use current directory
			// Otherwise, convert the given path to an absolute path if it's not already
			$directory = $directory !== null ? realpath($directory) : getcwd();
			
			// Ensure we have a valid directory
			if (!$directory || !is_dir($directory)) {
				return null;
			}
			
			// Start with the provided/default directory
			$currentDir = $directory;
			
			// Continue searching until we reach filesystem root or find composer.json
			while ($currentDir) {
				// Construct the potential path to composer.json in the current directory
				$composerPath = $currentDir . DIRECTORY_SEPARATOR . 'composer.json';
				
				// Check if composer.json exists in the current directory
				if (file_exists($composerPath)) {
					// Return the directory containing composer.json
					return $currentDir;
				}
				
				// Get parent directory to continue search upward in filesystem hierarchy
				$parentDir = dirname($currentDir);
				
				// Stop if we've reached the filesystem root (dirname returns the same path)
				if ($parentDir === $currentDir) {
					break;
				}
				
				// Move up to parent directory for next iteration
				$currentDir = $parentDir;
			}
			
			// If we get here, composer.json wasn't found in this path or any parent directories
			return null;
		}
		
		/**
		 * Detect project root based on common shared hosting directory patterns
		 * @param string|null $directory Directory to start searching from (defaults to current directory)
		 * @return string|null Project root directory if found, null otherwise
		 */
		private function getSharedHostingRoot(?string $directory = null): ?string {
			// Start from provided directory or current working directory
			$directory = $directory !== null ? realpath($directory) : getcwd();
			
			// Validate that directory exists and is actually a directory
			if (!$directory || !is_dir($directory)) {
				return null;
			}
			
			// Define regex patterns for common shared hosting directory structures
			// These patterns help identify the project root by matching typical hosting layouts
			$patterns = [
				// cPanel/Plesk pattern: /var/www/vhosts/domain.com/httpdocs -> /var/www/vhosts/domain.com
				// Captures the domain directory as the project root, not the web-accessible folder
				'#^(/var/www/vhosts/[^/]+)/(httpdocs|public_html|public)(/.*)?$#',
				
				// Home directory pattern: /home/username/public_html -> /home/username
				// Common in shared hosting where users have home directories
				'#^(/home/[^/]+)/(public_html|www)(/.*)?$#',
			];
			
			// Test each pattern against the current directory path
			foreach ($patterns as $pattern) {
				// Check if the current directory matches any known shared hosting pattern
				if (preg_match($pattern, $directory, $matches)) {
					// Extract the project root from the regex match (first capture group)
					$projectRoot = $matches[1]; // The domain/username directory, not the web root
					
					// Verify the extracted project root actually exists
					// Then return the project root directory (should contain composer.json, etc.)
					if (is_dir($projectRoot)) {
						return $projectRoot;
					}
				}
			}
			
			// No matching shared hosting pattern found
			return null;
		}
		
		/**
		 * Resolves relative path components without checking file existence
		 *
		 * This function normalizes paths by resolving '..' (parent directory) and '.' (current directory)
		 * references while maintaining the original path type (absolute vs relative).
		 *
		 * Examples:
		 * - "hallo/../test" → "test"
		 * - "/var/www/../html" → "/html"
		 * - "C:\Windows\..\System32" → "C:\System32"
		 * - "../../folder" → "../../folder" (keeps relative .. when can't go up further)
		 *
		 * @param string $path The path to resolve (can be Unix or Windows format)
		 * @return string The resolved path using system directory separators
		 */
		private function doResolvePath(string $path): string {
			// Handle empty paths early - nothing to resolve
			if ($path === '') {
				return '';
			}
			
			// Step 1: Normalize all separators to forward slashes for consistent processing
			$normalizedPath = strtr($path, '\\', '/');
			
			// Step 2: Determine if the path is absolute and extract prefix
			$isAbsolute = false;
			$prefix = '';
			
			if (strlen($normalizedPath) >= 2 && $normalizedPath[1] === ':' && ctype_alpha($normalizedPath[0])) {
				// Windows drive letter format (C:, D:, etc.)
				$isAbsolute = true;
				$prefix = substr($normalizedPath, 0, 2) . DIRECTORY_SEPARATOR;
				$pathWithoutPrefix = ltrim(substr($normalizedPath, 2), '/');
			} elseif ($normalizedPath[0] === '/') {
				// Unix root path format
				$isAbsolute = true;
				$prefix = DIRECTORY_SEPARATOR;
				$pathWithoutPrefix = substr($normalizedPath, 1);
			} else {
				// Relative path - no prefix
				$pathWithoutPrefix = $normalizedPath;
			}
			
			// Step 3: Split into components and filter out empty parts
			$components = array_filter(explode('/', $pathWithoutPrefix), function($part) {
				return $part !== '';
			});
			
			// Step 4: Resolve components by handling . and .. references
			$resolved = [];
			
			foreach ($components as $component) {
				// Skip current directory references - they don't change the path
				if ($component === '.') {
					continue;
				}
				
				// Regular directory or file name - add to the resolved path
				if ($component !== '..') {
					$resolved[] = $component;
					continue;
				}
				
				// Parent directory reference
				if (!empty($resolved) && end($resolved) !== '..') {
					// We can go up one level - remove the last component
					array_pop($resolved);
					continue;
				}
				
				// For relative paths, keep .. when we can't go up further
				// This preserves paths like "../../folder" when starting from a relative location
				if (!$isAbsolute) {
					$resolved[] = '..';
				}
				
				// For absolute paths, ignore .. that would go above filesystem root
				// "/var/.." becomes "/" not "/.."
			}
			
			// Step 5: Build the final resolved path
			$result = $prefix . implode(DIRECTORY_SEPARATOR, $resolved);
			
			// Return '.' for empty relative paths to maintain their relative nature
			return $result === '' && !$isAbsolute ? '.' : $result;
		}
	}