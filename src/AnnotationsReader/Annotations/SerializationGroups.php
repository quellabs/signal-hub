<?php
    
    namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations;
    
    class SerializationGroups {
        
        protected array $parameters;
        
        /**
         * Table constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
        
        /**
         * Returns the serialize groups
         * @return string
         */
        public function getGroups(): string {
            return $this->parameters["groups"];
        }
    }