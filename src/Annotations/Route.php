<?php
    
    namespace Quellabs\ObjectQuel\Annotations;
    
    use Quellabs\AnnotationReader\AnnotationInterface;
    
    /**
     * @Annotation
     */
    final class Route implements AnnotationInterface {
        private mixed $route;
        private array $methods;
        
        /**
         * Route constructor.
         * @param array $values
         */
        public function __construct(array $values) {
            $this->route = $values["value"];
            
            if (empty($values["methods"])) {
                $this->methods = ["GET", "POST", "PATCH", "DELETE"];
            } elseif (is_array($values["methods"])) {
                $this->methods = $values["methods"];
            } else {
                $this->methods = [$values["methods"]];
            }
        }
	    
	    /**
	     * Returns the parameters for this annotation
	     * @return array
	     */
	    public function getParameters(): array {
		    return [];
	    }
	    
	    /**
         * Fetches the rout
         * @return string
         */
        public function getRoute(): string {
            return $this->route;
        }
        
        public function getMethods(): array {
            return $this->methods;
        }
    }