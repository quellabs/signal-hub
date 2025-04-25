<?php
    
    namespace Quellabs\ObjectQuel\Annotations\Orm;
    
    class EntityBridge {
        
        protected $parameters;
    
        /**
         * EntityBridge constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
    }