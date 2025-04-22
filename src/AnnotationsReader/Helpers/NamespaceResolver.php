<?php
	
	namespace Quellabs\ObjectQuel\AnnotationsReader\Helpers;
	
	/**
	 * Helper class to resolve class names relative to a namespace
	 */
	class NamespaceResolver {
		
		/**
		 * @var string The current namespace
		 */
		private string $namespace;
		
		/**
		 * @var array<string, string> Map of aliases to fully qualified class names
		 */
		private array $imports;
		
		/**
		 * Constructor
		 *
		 * @param string $namespace The current namespace
		 * @param array<string, string> $imports Map of aliases to fully qualified class names
		 */
		public function __construct(string $namespace, array $imports = []) {
			$this->namespace = $namespace;
			$this->imports = $imports;
		}
		
		/**
		 * Resolve a class name to its fully qualified name
		 * @param string $className Class name to resolve
		 * @return string Fully qualified class name
		 */
		public function resolve(string $className): string {
			// Already fully qualified
			if (str_starts_with($className, '\\')) {
				return $className;
			}
			
			// Check if it's an imported alias
			if (isset($this->imports[$className])) {
				return $this->imports[$className];
			}
			
			// Check if it contains a namespace separator
			if (str_contains($className, '\\')) {
				// Extract the first part as a potential alias
				$parts = explode('\\', $className, 2);
				$alias = $parts[0];
				$rest = $parts[1];
				
				// Check if the first part is an imported alias
				if (isset($this->imports[$alias])) {
					return $this->imports[$alias] . '\\' . $rest;
				}
			}
			
			// Assume it's relative to the current namespace
			if (!empty($this->namespace)) {
				return '\\' . $this->namespace . '\\' . $className;
			}
			
			// No namespace, return as is with leading separator
			return '\\' . $className;
		}
		
		/**
		 * Add an import mapping
		 * @param string $alias The alias
		 * @param string $className The fully qualified class name
		 * @return self
		 */
		public function addImport(string $alias, string $className): self {
			$this->imports[$alias] = $className;
			return $this;
		}
		
		/**
		 * Set the current namespace
		 * @param string $namespace The namespace
		 * @return self
		 */
		public function setNamespace(string $namespace): self {
			$this->namespace = $namespace;
			return $this;
		}
	}