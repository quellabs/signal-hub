<?php
    
    namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm;
    
    class PostPersist {
        
        protected $parameters;
        
        /**
         * OneToMany constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
    }