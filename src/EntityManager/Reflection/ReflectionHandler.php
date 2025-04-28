<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Reflection;
	
	use ReflectionClass;
	
	class ReflectionHandler {
		
		private array $reflection_classes;
		private array $file_cache;
		
		/**
		 * ReflectionHandler constructor
		 */
		public function __construct() {
			$this->reflection_classes = [];
			$this->file_cache = [];
		}
		
		/**
		 * Haalt de inhoud van een bestand op en slaat deze op in een cache.
		 * Als het bestand al in de cache staat, wordt de gecachte versie geretourneerd.
		 * @param string $filename De naam van het bestand dat moet worden ingelezen.
		 * @return array Een array van regels uit het bestand.
		 */
		protected function getCachedFile(string $filename): array {
			if (!isset($this->file_cache[$filename])) {
				$this->file_cache[$filename] = file($filename);
			}
			
			return $this->file_cache[$filename];
		}
		
		/**
		 * Retrieves a ReflectionClass instance for the specified class.
		 * @param mixed $class The object or the name of the class to reflect.
		 * @return ReflectionClass A ReflectionClass instance.
		 * @throws \ReflectionException
		 */
		protected function getReflectionClass(mixed $class): ReflectionClass {
			// Determine the class name from the object or directly use the provided class name
			$className = is_object($class) ? get_class($class) : $class;
			
			// Check if the ReflectionClass already exists in cache
			if (!array_key_exists($className, $this->reflection_classes)) {
				// Create a new ReflectionClass and cache it
				$this->reflection_classes[$className] = new ReflectionClass($className);
			}
			
			// Return the cached or newly created ReflectionClass
			return $this->reflection_classes[$className];
		}
		
		/**
		 * Haalt de naam van de parentklasse op voor een gegeven klasse.
		 * @param mixed $class De klassenaam of het object om te inspecteren.
		 * @return string|null De naam van de parentklasse als een string, of null als deze niet bestaat of er een fout optreedt.
		 */
		public function getParent(mixed $class): ?string {
			try {
				// Initialiseer ReflectionClass voor de opgegeven klassenaam of object.
				$reflectionClass = $this->getReflectionClass($class);
				
				// Haal de ReflectionClass van de parentklasse op.
				$parentClass = $reflectionClass->getParentClass();
				
				// Controleer of de parentklasse bestaat.
				if ($parentClass === false) {
					return null;
				}
				
				// Als de parentklasse bestaat, retourneer dan de naam.
				return $parentClass->getName();
			} catch (\ReflectionException $e) {
				// Retourneer null als er een fout optreedt, zoals wanneer de klasse niet gevonden kan worden.
				return null;
			}
		}
		
		/**
		 * Haalt het bestandspad op waar een bepaalde klasse is gedefinieerd.
		 * @param mixed $class De naam van de klasse waarvan we het bestandspad willen opzoeken.
		 * @return string|null Het volledige pad naar het bestand waar de klasse is gedefinieerd, of null als de klasse niet gevonden wordt.
		 */
		public function getFilename(mixed $class): ?string {
			try {
				// Initialiseer ReflectionClass voor de opgegeven klassenaam.
				$reflectionClass = $this->getReflectionClass($class);
				
				// Haal het bestandspad op waar de klasse is gedefinieerd.
				return $reflectionClass->getFileName();
			} catch (\ReflectionException $e) {
				// Retourneer null als er een fout optreedt (bijv. klasse niet gevonden).
				return null;
			}
		}
		
		/**
		 * Fetch the namespace of a given class.
		 * @param mixed $class The fully qualified class name.
		 * @return string|null The namespace name if it exists, otherwise null
		 */
		public function getNamespace(mixed $class): ?string {
			try {
				// Initialize ReflectionClass for the given class name.
				$reflectionClass = $this->getReflectionClass($class);
				
				// Check if the class is actually defined within a namespace.
				if (!$reflectionClass->inNamespace()) {
					return null;
				}
				
				// Return the namespace name.
				return $reflectionClass->getNamespaceName();
			} catch (\ReflectionException $e) {
				return null;
			}
		}
		
		/**
		 * Returns the DocComment for a given class if it exists.
		 * @param mixed $class The class name to fetch the DocComment for.
		 * @return string The DocComment as a string, or an empty string if not found or an error occurs.
		 */
		public function getDocComment(mixed $class): string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the DocComment from the ReflectionClass
				$docComment = $reflectionClass->getDocComment();
				
				// Return the DocComment if it exists, otherwise return an empty string
				return ($docComment !== false) ? $docComment : "";
			} catch (\ReflectionException $e) {
				return "";
			}
		}

		/**
		 * Returns an array containing the names of all interfaces implemented by a given class.
		 * @param mixed $class The class name to inspect.
		 * @return array An array containing the names of all implemented interfaces, or an empty array if not found or an error occurs.
		 */
		public function getInterfaces(mixed $class): array {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch and return the implemented interfaces using ReflectionClass
				return $reflectionClass->getInterfaceNames();
			} catch (\ReflectionException $e) {
				return [];
			}
		}
		
		/**
		 * Get the names of all properties of a given class.
		 * @param mixed $class The class name to inspect.
		 * @param bool $onlyCurrentClass Only list the properties in the current class, not of parents
		 * @return array An array containing the names of all properties.
		 */
		public function getProperties(mixed $class, bool $onlyCurrentClass=false): array {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Get all properties of the class
				$properties = $reflectionClass->getProperties();
				
				// Filter out the properties of parents
				if ($onlyCurrentClass) {
					// Store the reflection class name
					$reflectionClassName = $reflectionClass->getName();

					// Perform the filter action
					$properties = array_filter(
						$properties,
						function ($property) use ($reflectionClassName) {
							return $property->getDeclaringClass()->getName() === $reflectionClassName;
						}
					);
				}
				
				// Loop through each property and store its name in the result array
				$result = [];

				foreach ($properties as $property) {
					$result[] = $property->getName();
				}
				
				return $result;
			} catch (\ReflectionException $e) {
				// Return an empty array if a ReflectionException occurs
				return [];
			}
		}
		
		/**
		 * Returns the type of the specified property in a given class.
		 * @param mixed $class The class name to inspect.
		 * @param string $property The property name to fetch the type for.
		 * @return string|null The type of the property, or null if not found or an error occurs.
		 */
		public function getPropertyType(mixed $class, string $property): ?string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionProperty object for the given property name
				$property = $reflectionClass->getProperty($property);
				
				// Get the type of the property, if any
				$typeClass = $property->getType();
				
				// Check if the type is null before proceeding
				if ($typeClass === null) {
					return null;
				}
				
				// If the type is not null, get its name and return
				return $typeClass->getName();
			} catch (\ReflectionException $e) {
				return null;
			}
		}
		
		/**
		 * Returns the visibility (public, protected, or private) of a specified property in a given class.
		 * @param mixed $class The class name to inspect.
		 * @param string $property The property name to fetch the visibility for.
		 * @return string|null The visibility of the property ("public", "protected", or "private"), or null if not found or an error occurs.
		 */
		public function getPropertyVisibility(mixed $class, string $property): ?string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionProperty object for the given property name
				$property = $reflectionClass->getProperty($property);
				
				// Determine and return the visibility of the property
				if ($property->isPrivate()) {
					return "private";
				} elseif ($property->isProtected()) {
					return "protected";
				} else {
					return "public";
				}
			} catch (\ReflectionException $e) {
				return null;
			}
		}
		
		/**
		 * Returns the doc comment of the given property
		 * @param mixed $class The class name to inspect.
		 * @param string $property The property name to fetch the return type for.
		 * @return string The doc comments of the property, or an empty string if there's none
		 */
		public function getPropertyDocComment(mixed $class, string $property): string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$property = $reflectionClass->getProperty($property);
				
				// Get the doc comment
				$docComment = $property->getDocComment();
				
				// return the DocComment
				return ($docComment !== false) ? $docComment : '';
			} catch (\ReflectionException $e) {
				return '';
			}
		}
		
		/**
		 * Returns an array containing the names of all methods of a given class.
		 * @param mixed $class The class name to inspect.
		 * @param bool $onlyCurrentClass Only list the methods in the current class, not of parents
		 * @return array An array containing the names of all methods.
		 */
		public function getMethods(mixed $class, bool $onlyCurrentClass=false): array {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Store the reflection class name
				$reflectionClassName = $reflectionClass->getName();
				
				// Declare an array to store the method names
				$result = [];
				
				// Get all methods of the class
				$methods = $reflectionClass->getMethods();
				
				// Filter out the properties of parents
				if ($onlyCurrentClass) {
					$methods = array_filter(
						$methods,
						function ($property) use ($reflectionClassName) {
							return $property->getDeclaringClass()->getName() === $reflectionClassName;
						}
					);
				}
				
				// Loop through each method and store its name in the result array
				foreach ($methods as $method) {
					$result[] = $method->getName();
				}
				
				// Return the array of method names
				return $result;
			} catch (\ReflectionException $e) {
				return [];
			}
		}
		
		/**
		 * Returns the return type of the specified method in a given class.
		 * @param mixed $class The class name to inspect.
		 * @param string $method The method name to fetch the return type for.
		 * @return string The return type of the method
		 */
		public function getMethodReturnType(mixed $class, string $method): string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$method = $reflectionClass->getMethod($method);
				
				// Return null if the method does not have a return type
				if (!$method->hasReturnType()) {
					return "";
				}
				
				// Get and return the return type of the method
				$returnTypeClass = $method->getReturnType();
				
				if ($returnTypeClass instanceof \ReflectionUnionType) {
					return implode("|", array_map(function($type) { return $type->getName(); }, $returnTypeClass->getTypes()));
				}
				
				return $returnTypeClass->getName();
			} catch (\ReflectionException $e) {
				return "";
			}
		}
		
		/**
		 * Returns if the return method type is nullable
		 * @param mixed $class The class name to inspect.
		 * @param string $method The method name to fetch the return type for.
		 * @return bool True if the return type is nullable, false if not
		 */
		public function methodReturnTypeIsNullable(mixed $class, string $method): bool {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$method = $reflectionClass->getMethod($method);
				
				// Return null if the method does not have a return type
				if (!$method->hasReturnType()) {
					return false;
				}
				
				// Get and return the return type of the method
				return $method->getReturnType()->allowsNull();
			} catch (\ReflectionException $e) {
				return false;
			}
		}

		/**
		 * Returns the visibility (public, protected, or private) of a specified method in a given class.
		 * @param mixed $class The class name to inspect.
		 * @param string $method The method name to fetch the visibility for.
		 * @return string|null The visibility of the method ("public", "protected", or "private"), or null if not found or an error occurs.
		 */
		public function getMethodVisibility(mixed $class, string $method): ?string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$method = $reflectionClass->getMethod($method);
				
				// Determine and return the visibility of the method
				if ($method->isPrivate()) {
					return "private";
				} elseif ($method->isProtected()) {
					return "protected";
				} else {
					return "public";
				}
			} catch (\ReflectionException $e) {
				return null;
			}
		}
		
		/**
		 * Returns the doc comment of the given method
		 * @param mixed $class The class name to inspect.
		 * @param string $method The method name to fetch the return type for.
		 * @return string The doc comments of the methods, or an empty string if there are none
		 */
		public function getMethodDocComment(mixed $class, string $method): string {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$method = $reflectionClass->getMethod($method);
				
				// Get the doc comment
				$docComment = $method->getDocComment();
				
				// return the DocComment
				return ($docComment !== false) ? $docComment : '';
			} catch (\ReflectionException $e) {
				return '';
			}
		}
		
		/**
		 * Determines whether a specific method returns a reference.
		 * @param mixed $class The class name to inspect.
		 * @param string $method The method name to check.
		 * @return bool True if the method returns a reference, false otherwise.
		 */
		public function methodReturnsReference(mixed $class, string $method): bool {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$method = $reflectionClass->getMethod($method);
				
				// Use ReflectionMethod to check if the method returns a reference
				return $method->returnsReference();
			} catch (\ReflectionException $e) {
				// Return false if a ReflectionException occurs
				return false;
			}
		}

		/**
		 * Returns an array containing the parameters of a specified method in a given class.
		 * @param mixed $class The class name to inspect.
		 * @param string $method The method name to fetch parameters for.
		 * @return array An array containing details about each parameter (name, type, nullability, default value).
		 */
		public function getMethodParameters(mixed $class, string $method): array {
			try {
				// Initialize ReflectionClass for the given class name
				$reflectionClass = $this->getReflectionClass($class);
				
				// Fetch the ReflectionMethod object for the given method name
				$methodClass = $reflectionClass->getMethod($method);
				
				// Fetch the parameters of the method
				$parameterClass = $methodClass->getParameters();
				
				// Declare an array to store the parameters' details
				$result = [];
				
				// Loop through each parameter and store its details in the result array
				foreach ($parameterClass as $parameter) {
					$type = $parameter->getType();
					$typeName = $type !== null ? $type->getName() : "";
					$isDefaultValueAvailable = $parameter->isDefaultValueAvailable();
					
					$result[] = [
						'index'               => $parameter->getPosition(),
						'name'                => $parameter->getName(),
						'type'                => $typeName,
						'nullable'            => $parameter->allowsNull(),
						'has_default'         => $isDefaultValueAvailable,
						'default'             => $isDefaultValueAvailable ? $parameter->getDefaultValue() : null,
						'passed_by_reference' => $parameter->isPassedByReference(),
					];
				}
				
				// Return the array containing the parameters' details
				return $result;
			} catch (\ReflectionException $e) {
				return [];
			}
		}
		
		/**
		 * Haalt de body van een opgegeven methode op uit een gegeven klasse.
		 * Deze functie gebruikt de Reflection API om de bestandsnaam en de start- en eindregel van de methode te vinden.
		 * Vervolgens leest het de broncode van het bestand en extraheert het de methode-body.
		 * De functie houdt ook rekening met verschillende coderingsstijlen voor het plaatsen van accolades.
		 * @param mixed $class  De naam van de klasse of een instantie van de klasse.
		 * @param string $method  De naam van de methode waarvan de body moet worden opgehaald.
		 * @return string  De body van de opgegeven methode als een string.
		 */
		public function getMethodBody(mixed $class, string $method): string {
			try {
				// Maak een ReflectionClass object van de opgegeven klasse
				$reflectionClass = new \ReflectionClass($class);
				
				// Haal het ReflectionMethod object op voor de opgegeven methode
				$methodClass = $reflectionClass->getMethod($method);
				
				// Bepaal de bestandsnaam en start- en eindregels van de methode
				$fileName = $methodClass->getFileName();
				$startLine = $methodClass->getStartLine() - 1; // Neem ook de functie definitie mee
				$endLine = $methodClass->getEndLine();
				
				// Lees het bestand in een array van regels
				$lines = $this->getCachedFile($fileName);
				
				// Haal alleen de regels op die de methodebody vormen
				$bodyLines = array_slice($lines, $startLine, $endLine - $startLine);
				
				// Vind de eerste opening accolade in de eerste regel
				$firstLine = $bodyLines[0];
				$startPos = strpos($firstLine, '{');
				
				// Vind de laatste sluitende accolade in de laatste regel
				$lastLine = end($bodyLines);
				$endPos = strrpos($lastLine, '}');
				
				// Pas de eerste en laatste regel aan om respectievelijk de opening '{' en sluitende '}' te verwijderen
				$bodyLines[0] = substr($firstLine, $startPos + 1);
				$bodyLines[count($bodyLines) - 1] = substr($lastLine, 0, $endPos);
				
				// Voeg de aangepaste regels samen tot één string en retourneer dit als de body van de methode
				return implode("", $bodyLines);
			} catch (\ReflectionException $e) {
				// Retourneer een lege string als er een uitzondering optreedt
				return "";
			}
		}

		/**
		 * Verwijdert PHP-commentaar uit een gegeven string.
		 * Deze functie verwijdert alle typen PHP-commentaar uit de meegeleverde string.
		 * @param string $code De string waaruit het commentaar moet worden verwijderd.
		 * @return string De string zonder PHP-commentaar.
		 */
		public function removePHPComments(string $code): string {
			// Verwijder /** */ en /* */ blokcommentaar
			$code = preg_replace('!/\*.*?\*/!s', '', $code);
			
			// Verwijder // lijncommentaar
			return preg_replace('!//.*?$!m', '', $code);
		}
		
		/**
		 * Returns true if the class has a constructor, false if not.
		 * @param mixed $class
		 * @return bool
		 */
		public function hasConstructor(mixed $class): bool {
			return in_array("__construct", $this->getMethods($class));
		}
	}