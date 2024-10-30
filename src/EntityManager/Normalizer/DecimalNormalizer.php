<?php
    
    namespace Services\EntityManager\Normalizer;
    
    class DecimalNormalizer implements NormalizerInterface  {
    
        protected $value;
        protected $annotation;
        
        /**
         * IntNormalizer constructor.
         * @param $value
         * @param $annotation
         */
        public function __construct($value, $annotation) {
            $this->value = $value;
            $this->annotation = $annotation;
        }
    
        /**
         * @return mixed
         */
        public function normalize(): mixed {
            return $this->value;
        }

        /**
         * @return mixed
         */
        public function denormalize(): mixed {
            return $this->value;
        }
    }