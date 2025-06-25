<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Helpers;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\AnnotationReader\Exception\ParserException;
	use Quellabs\ObjectQuel\Annotations\Orm\Table;
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;
	use ReflectionClass;
	
	/**
	 * EntityScanner - Identifies entity classes with Table annotations in a specified directory
	 *
	 * This class scans PHP files in a given directory path to find classes that are
	 * marked as database entities through the Table annotation. It uses PHP's
	 * reflection capabilities to analyze class properties.
	 */
	class EntityScanner {
		
		/** @var string The directory path where entity classes are located */
		private string $entityPath;
		
		/** @var AnnotationReader Service to read annotation metadata from classes */
		private AnnotationReader $annotationReader;
		
		/**
		 * Constructor initializes the scanner with the path to entity classes
		 * and the annotation reader service
		 * @param string $entityPath The directory path to scan for entity classes
		 * @param AnnotationReader $annotationReader Service to read annotations
		 */
		public function __construct(string $entityPath, AnnotationReader $annotationReader) {
			$this->entityPath = $entityPath;
			$this->annotationReader = $annotationReader;
		}
		
		/**
		 * Finds all PHP files in the entity directory
		 * @return array<int, string> List of PHP file paths
		 */
		private function findPhpFiles(): array {
			$phpFiles = [];
			$directory = new RecursiveDirectoryIterator($this->entityPath);
			$iterator = new RecursiveIteratorIterator($directory);
			
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$phpFiles[] = $file->getPathname();
				}
			}
			
			return $phpFiles;
		}
		
		/**
		 * Determines if a class is a valid entity class
		 *
		 * A valid entity class must:
		 * - Exist
		 * - Not be abstract
		 * - Not be an interface
		 *
		 * @param string|null $className Fully qualified class name
		 * @return bool Whether the class is a valid entity candidate
		 */
		private function isValidEntityClass(?string $className): bool {
			if (!$className || !class_exists($className)) {
				return false;
			}
			
			$reflection = new ReflectionClass($className);
			return !($reflection->isAbstract() || $reflection->isInterface());
		}
		
		/**
		 * Extracts the table name from a class using its Table annotation
		 * @param string $className Fully qualified class name
		 * @return string|null The table name, or null if no Table annotation exists
		 */
		private function extractTableName(string $className): ?string {
			try {
				// Use the annotation reader to retrieve all Table annotations from the specified class
				// This will return a collection of Table annotation instances found on the class
				$classAnnotations = $this->annotationReader->getClassAnnotations($className, Table::class);
				
				// Check if no Table annotations were found on the class
				// If the collection is empty, this class doesn't have a Table annotation
				if ($classAnnotations->isEmpty()) {
					return null;
				}
				
				// Extract the table name from the first (and typically only) Table annotation
				// We use index [0] since there should only be one Table annotation per class
				// Call getName() method on the Table annotation to get the configured table name
				return $classAnnotations[0]->getName();
			} catch (ParserException $e) {
				// Handle parsing errors that may occur during annotation reading
				// This could happen if the annotation syntax is invalid or malformed
				// Return null to gracefully handle the error and indicate no table name found
				return null;
			}
		}
		
		/**
		 * Extracts the fully qualified class name from a PHP file
		 * Parses the file content to find the namespace and class name,
		 * then combines them to form the fully qualified class name.
		 * @param string $filePath Path to the PHP file
		 * @return string|null The fully qualified class name, or null if not found
		 */
		private function getClassNameFromFile(string $filePath): ?string {
			// Read the file contents
			$content = file_get_contents($filePath);
			
			if ($content === false) {
				return null;
			}
			
			// Extract namespace using regex
			preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
			$namespace = $namespaceMatches[1] ?? '';
			
			// Extract class name using regex
			// This pattern matches "class" followed by a name and either "extends", "implements", or "{"
			preg_match('/class\s+(\w+)(?:\s+extends|\s+implements|\s*{)/', $content, $classMatches);
			$className = $classMatches[1] ?? null;
			
			// Combine namespace and class name if both are found
			if ($namespace && $className) {
				return $namespace . '\\' . $className;
			}
			
			return null;
		}
		
		/**
		 * Scans the directory for PHP files containing entity classes
		 * An entity class is identified by having a Table annotation.
		 * @return array<string, string> Map of class names to their table names
		 */
		public function scanEntities(): array {
			$entityClasses = [];
			
			foreach ($this->findPhpFiles() as $filePath) {
				$className = $this->getClassNameFromFile($filePath);
				
				if ($this->isValidEntityClass($className)) {
					$tableName = $this->extractTableName($className);
					
					if ($tableName) {
						$entityClasses[$className] = $tableName;
					}
				}
			}
			
			return $entityClasses;
		}
	}