<?php
    
    namespace Quellabs\ObjectQuel\Annotations;
    
    use Quellabs\AnnotationReader\AnnotationInterface;
    
    class AfterFilter implements AnnotationInterface {
        
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
         * Returns the table name
         * @return string
         */
        public function getName(): string {
            return $this->parameters["value"];
        }
    }