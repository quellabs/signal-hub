<?php
    
    namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm;
    
    class Table {
        
        protected $parameters;
    
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
            return $this->parameters["name"];
        }
    }