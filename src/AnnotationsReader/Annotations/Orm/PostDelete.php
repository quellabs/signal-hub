<?php
    
    namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm;
    
    class PostDelete {
        
        protected array $parameters;
        
        /**
         * PostDelete constructor.
         * @param array $parameters
         */
        public function __construct(array $parameters) {
            $this->parameters = $parameters;
        }
    }