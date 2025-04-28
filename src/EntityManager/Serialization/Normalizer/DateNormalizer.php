<?php
    
    namespace Quellabs\ObjectQuel\EntityManager\Serialization\Normalizer;
    
    class DateNormalizer implements NormalizerInterface {
    
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