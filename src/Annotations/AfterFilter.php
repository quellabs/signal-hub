<?php
    
    namespace Quellabs\ObjectQuel\Annotations;
    
    class AfterFilter {
        
        protected array $parameters;
        
        /**
         * Table constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
        
        /**
         * Returns the table name
         * @return string
         */
        public function getName(): string {
            return $this->parameters["value"];
        }
    }