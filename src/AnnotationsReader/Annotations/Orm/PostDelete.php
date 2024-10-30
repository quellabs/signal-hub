<?php
    
    namespace Services\AnnotationsReader\Annotations\Orm;
    
    class PostDelete {
        
        protected $parameters;
        
        /**
         * OneToMany constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
    }