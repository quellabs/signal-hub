<?php
    
    namespace Quellabs\ObjectQuel\EntityManager\Serialization\Normalizer;
    
    class DecimalNormalizer implements NormalizerInterface  {
    
        protected $value;
        
	    /**
	     * Sets the value to normalize/denormalize
	     * @param $value
	     * @return void
	     */
	    public function setValue($value): void {
		    $this->value = $value;
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