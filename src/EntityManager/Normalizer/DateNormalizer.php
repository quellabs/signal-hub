<?php
    
    namespace Services\EntityManager\Normalizer;
    
    class DateNormalizer implements NormalizerInterface {
    
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
         * Normalize converts a value in the database into something that can be inserted into the entity
         * @return \DateTime|null
		 */
		public function normalize(): ?\DateTime {
			if (is_null($this->value) || $this->value === "0000-00-00") {
				return null;
			}
			
			return \DateTime::createFromFormat("Y-m-d", $this->value);
		}

        /**
		 * Denormalize converts a value in an entity into something that can be inserted into the DB
         * @return string
         */
        public function denormalize(): string {
			return $this->value->format("Y-m-d");
        }
    }