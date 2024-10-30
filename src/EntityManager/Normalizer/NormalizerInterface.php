<?php
    
    namespace Services\EntityManager\Normalizer;
    
    interface NormalizerInterface {
        /**
         * NormalizerInterface constructor.
         * @param $value
         * @param $annotation
         */
        public function __construct($value, $annotation);
    
        /**
         * The normalize function converts a value residing in an entity into a value
         * that can be inserted into an entity
         */
        public function normalize();

        /**
         * The denormalize function converts a value residing in the database into a value
         * that can be implanted in the DB
         */
        public function denormalize();
    }