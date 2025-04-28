<?php
    
    namespace Quellabs\ObjectQuel\EntityManager\Serialization\Normalizer;
    
    class DatetimeNormalizer implements NormalizerInterface  {
    
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
         * @return \DateTime|null
         */
        public function normalize(): ?\DateTime {
            if (is_null($this->value) || $this->value == "0000-00-00 00:00:00") {
				return null;
			}
			
			return \DateTime::createFromFormat("Y-m-d H:i:s", $this->value);
        }
    
        /**
         * @return string
         */
        public function denormalize(): ?string {
			if ($this->value === null) {
				return null;
			}
			
        	return $this->value->format("Y-m-d H:i:s");
        }
    }