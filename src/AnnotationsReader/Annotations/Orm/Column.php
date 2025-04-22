<?php
    
    namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm;
    
    class Column {
        
        protected $parameters;
    
        /**
         * Table constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
        
        public function getName() {
            return $this->parameters["name"];
        }

        public function getType() {
            return $this->parameters["type"];
        }

        public function getLength() {
            return $this->parameters["length"];
        }

        public function hasDefault(): bool {
            return array_key_exists("default", $this->parameters);
        }
        
        public function getDefault() {
            return $this->parameters["default"];
        }
        
        public function isPrimaryKey(): bool {
            return $this->parameters["primary_key"] ?? false;
        }

        public function isAutoIncrement(): bool {
            return $this->parameters["auto_increment"] ?? false;
        }

        public function isNullable(): bool {
            return $this->parameters["nullable"] ?? false;
        }
    }