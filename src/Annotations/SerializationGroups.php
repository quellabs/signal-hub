<?php
    
    namespace Quellabs\ObjectQuel\Annotations;
    
    use Quellabs\AnnotationReader\AnnotationInterface;
    
    class SerializationGroups implements AnnotationInterface {
        
        protected array $parameters;
        
        /**
         * Table constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
	    
	    /**
	     * Returns the parameters for this annotation
	     * @return array
	     */
	    public function getParameters(): array {
		    return $this->parameters;
	    }
	    
	    /**
	     * Returns the serialize groups
	     * @return array
	     */
        public function getGroups(): array {
            return $this->parameters["groups"];
        }
	}