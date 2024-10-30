<?php
    
    namespace Services\AnnotationsReader;
    
    class AnnotationsReader {
    
        protected string|false $current_dir;
        protected array $cached_annotations;
        protected array $cached_annotations_filemtime;
        protected array $cached_annotations_filemtime_checked;
        
        /**
         * AnnotationReader constructor
         */
        public function __construct() {
            $this->current_dir = realpath(dirname(__FILE__));

            // read cached data
            $this->cached_annotations = [];
            $this->cached_annotations_filemtime = [];
            $this->cached_annotations_filemtime_checked = [];
            $files = scandir("{$this->current_dir}/Cache");
    
            foreach($files as $file) {
                if (str_ends_with($file, '.cache')) {
                    $this->cached_annotations[$file] = unserialize(file_get_contents("{$this->current_dir}/Cache/{$file}"));
                    $this->cached_annotations_filemtime[$file] = filemtime("{$this->current_dir}/Cache/{$file}");
                    $this->cached_annotations_filemtime_checked[$file] = false;
                }
            }
        }
		
		/**
		 * Transforms a className to a filename
		 * @param string $className
		 * @return string
		 */
		protected function generateCacheFilename(string $className): string {
			return str_replace("\\", "#", $className) . ".cache";
		}
		
		/**
		 * Returns true if the cache should be updated, false if not
		 * @param string $cacheFilename
		 * @param \ReflectionClass $reflection
		 * @return bool
		 */
		protected function shouldUpdateCache(string $cacheFilename, \ReflectionClass $reflection): bool {
			return (
				!isset($this->cached_annotations[$cacheFilename]) ||
				!is_array($this->cached_annotations[$cacheFilename]) ||
				(
					!$this->cached_annotations_filemtime_checked[$cacheFilename] &&
					(filemtime($reflection->getFileName()) >= $this->cached_annotations_filemtime[$cacheFilename])
				)
			);
		}
		
		/**
		 * Updates the cache
		 * @param string $cacheFilename
		 * @param array $annotations
		 * @return void
		 */
		protected function updateCache(string $cacheFilename, array $annotations): void {
			$cachePath = "{$this->current_dir}/Cache/{$cacheFilename}";
			file_put_contents($cachePath, serialize($annotations));
			$this->cached_annotations[$cacheFilename] = $annotations;
			$this->cached_annotations_filemtime[$cacheFilename] = filemtime($cachePath);
		}
		
		/**
		 * Parse annotations for properties or methods and update the result array.
		 * @param array $items An array of ReflectionProperty or ReflectionMethod objects.
		 * @param array $result The result array to be updated with parsed annotations.
		 */
		protected function parseAnnotations(array $items, array &$result): void {
			// Loop through each Reflection item (either property or method)
			foreach ($items as $item) {
				// Get the doc comment for the current item
				$docComment = $item->getDocComment();
				
				// Skip if there is no doc comment
				if (empty($docComment)) {
					continue;
				}
				
				// Retrieve annotations from the doc comment
				$annotations = $this->getAnnotations($docComment);
				
				// Skip if there are no annotations
				if (empty($annotations)) {
					continue;
				}
				
				// Add the annotations to the result array, indexed by the item's name
				$result[$item->getName()] = $annotations;
			}
		}

		/**
         * Fetch all object annotations
         * @param mixed $class
         * @return array
         */
		protected function readAllObjectAnnotations($class): array {
			$result = [
				'class'      => [],
				'properties' => [],
				'methods'    => []
			];
			
			try {
				$reflection = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				return $result;
			}
			
			$result['class'] = $this->getAnnotations($reflection->getDocComment());
			$this->parseAnnotations($reflection->getProperties(), $result['properties']);
			$this->parseAnnotations($reflection->getMethods(), $result['methods']);
			return $result;
		}
		
		/**
		 * Retrieve all annotations for a given class, caching the results for performance.
		 * @param mixed $class The fully qualified class name to get annotations for.
		 * @return array An array containing all annotations for the class, its properties, and its methods.
		 */
		protected function getAllObjectAnnotations(mixed $class): array {
			try {
				// Create a ReflectionClass object for the given class
				$reflection = new \ReflectionClass($class);
				
				// Generate a cache filename based on the class name
				$cacheFilename = $this->generateCacheFilename($reflection->getName());
				
				// Check if the cache should be updated
				if ($this->shouldUpdateCache($cacheFilename, $reflection)) {
					// Read all annotations for the class
					$annotations = $this->readAllObjectAnnotations($class);
					
					// Update the cache with the new annotations
					$this->updateCache($cacheFilename, $annotations);
				}
				
				// Mark this cache file as checked for file modification time
				$this->cached_annotations_filemtime_checked[$cacheFilename] = true;
				
				// Return the cached annotations
				return $this->cached_annotations[$cacheFilename];
			} catch (\ReflectionException $e) {
				// Return an empty array if a ReflectionException occurs
				return [];
			}
		}

        /**
         * Takes a class's docComment and parses it
         * @param $class
         * @return array
         */
        public function getClassAnnotations($class): array {
            $annotations = $this->getAllObjectAnnotations($class);
            return $annotations["class"] ?? [];
        }
        
        /**
         * Takes a method's docComment and parses it
         * @param $class
         * @param $method
         * @return array
         */
        public function getMethodAnnotations($class, $method): array {
            $annotations = $this->getAllObjectAnnotations($class);
            return $annotations["methods"][$method] ?? [];
        }

        /**
         * Takes a property's docComment and parses it
         * @param $class
         * @param $property
         * @return array
         */
        public function getPropertyAnnotations($class, $property): array {
            $annotations = $this->getAllObjectAnnotations($class);
            return $annotations["properties"][$property] ?? [];
        }
     
        /**
         * Parses a string and returns the found annotations
         * @param $string
         * @return array
         */
        public function getAnnotations($string, ?string &$errorMessage=null): array {
            try {
                $lexer = new Lexer($string);
                $parser = new Parser($lexer);
                return $parser->parse();
            } catch (LexerException | ParserException $e) {
				if ($errorMessage !== null) {
					$errorMessage = $e->getMessage();
				}
				
                return [];
            }
        }
    }