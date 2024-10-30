<?php
    
    namespace Services\EntityManager\Normalizer;
    
    class DatetimeNormalizer implements NormalizerInterface  {
    
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