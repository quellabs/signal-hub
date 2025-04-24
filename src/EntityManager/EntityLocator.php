<?php
	
	namespace Quellabs\ObjectQuel\EntityManager;
	
	use Quellabs\ObjectQuel\AnnotationsReader\AnnotationsReader;
	
	/**
	 * Responsible for locating and loading entity classes
	 */
	class EntityLocator {

		/**
		 * @var Configuration
		 */
		private Configuration $configuration;
		
		/**
		 * @var AnnotationsReader
		 */
		private AnnotationsReader $annotationReader;
		
		/**
		 * @var array Discovered entity classes
		 */
		private array $entityClasses = [];
		
		/**
		 * Constructor
		 * @param Configuration $configuration
		 * @param AnnotationsReader|null $annotationReader
		 */
		public function __construct(Configuration $configuration, ?AnnotationsReader $annotationReader = null) {
			$this->configuration = $configuration;
			$this->annotationReader = $annotationReader ?? new AnnotationsReader();
		}
		
		/**
		 * Discover all entity classes in configured paths
		 * @return array List of discovered entity class names
		 */
		public function discoverEntities(): array {
			if (!empty($this->entityClasses)) {
				return $this->entityClasses;
			}
			
			// Get the services path from configuration
			$entityDirectory = realpath($this->configuration->getEntityPath());
			
			// Validate the directory exists
			if (!is_dir($entityDirectory) || !is_readable($entityDirectory)) {
				throw new \RuntimeException("Entity directory does not exist or is not readable: " . $entityDirectory);
			}
			
			// Get all PHP files in the Entity directory
			$entityFiles = glob($entityDirectory . DIRECTORY_SEPARATOR . "*.php");
			
			// Process each entity file
			foreach ($entityFiles as $filePath) {
				// Get the fully qualified class name from the file
				$entityName = $this->extractEntityNameFromFile($filePath);
				
				// Skip if we couldn't determine the entity name
				if ($entityName === null) {
					continue;
				}
				
				// Check if it's a valid entity class
				if ($this->isEntity($entityName)) {
					$this->entityClasses[] = $entityName;
				}
			}
			
			return $this->entityClasses;
		}
		
		/**
		 * Extracts the fully qualified class name from a PHP file by reading its content
		 * @param string $filePath The full path to the PHP file
		 * @return string|null The fully qualified class name, or null if not found
		 */
		private function extractEntityNameFromFile(string $filePath): ?string {
			// Read the file contents
			$contents = file_get_contents($filePath);
			
			// If no content found, return null
			if ($contents === false) {
				return null;
			}
			
			// Extract the namespace
			if (preg_match('/namespace\s+([^;]+);/s', $contents, $namespaceMatches)) {
				$namespace = $namespaceMatches[1];
			} else {
				$namespace = $this->configuration->getEntityNamespace();
			}
			
			// Extract the class name
			if (preg_match('/class\s+(\w+)/s', $contents, $classMatches)) {
				$className = $classMatches[1];
				return $namespace . '\\' . $className;
			}
			
			// If no class found, use filename as class name (without .php extension)
			$fileName = basename($filePath);
			$className = substr($fileName, 0, strpos($fileName, '.php'));
			return $namespace . '\\' . $className;
		}
		
		/**
		 * Checks if the class is an ORM entity
		 * @param string $entityName
		 * @return bool
		 */
		private function isEntity(string $entityName): bool {
			$annotations = $this->annotationReader->getClassAnnotations($entityName);
			return array_key_exists("Orm\\Table", $annotations);
		}
	}