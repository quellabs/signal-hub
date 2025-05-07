<?php
	
	namespace Quellabs\ObjectQuel\Annotations\Orm;
	
	use Quellabs\AnnotationReader\AnnotationInterface;
	
	/**
	 * Column annotation class for ORM mapping
	 * @package Quellabs\ObjectQuel\Annotations\Orm
	 */
	class Column implements AnnotationInterface {
		
		/**
		 * Array containing all column parameters
		 *
		 * @var array
		 */
		protected array $parameters;
		
		/**
		 * Column constructor
		 * @param array $parameters Associative array of column parameters
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Returns all parameters for this column annotation
		 * @return array The complete parameters array
		 */
		public function getParameters(): array {
			return $this->parameters;
		}
		
		/**
		 * Gets the column name
		 * @return string The name of the database column
		 */
		public function getName(): string {
			return $this->parameters["name"] ?? '';
		}
		
		/**
		 * Gets the column data type
		 * @return string The SQL data type of the column
		 */
		public function getType(): string {
			return $this->parameters["type"];
		}
		
		/**
		 * Gets the column length/size
		 * @return int|null The length/size of the column
		 */
		public function getLength(): ?int {
			if (empty($this->parameters["length"])) {
				return null;
			}
			
			if (!is_numeric($this->parameters["length"])) {
				return null;
			}
			
			return (int)$this->parameters["length"];
		}
		
		/**
		 * Checks if this column has a default value
		 * @return bool True if a default value is specified, false otherwise
		 */
		public function hasDefault(): bool {
			return array_key_exists("default", $this->parameters);
		}
		
		/**
		 * Gets the default value for this column
		 * @return mixed The default value for the column
		 */
		public function getDefault(): mixed {
			return $this->parameters["default"] ?? null;
		}
		
		/**
		 * Checks if this column is a primary key
		 * @return bool True if this column is a primary key, false otherwise
		 */
		public function isPrimaryKey(): bool {
			return $this->parameters["primary_key"] ?? false;
		}
		
		/**
		 * Checks if this column is unsigned (for numeric types)
		 * @return bool True if this column is unsigned, false otherwise
		 */
		public function isUnsigned(): bool {
			return $this->parameters["unsigned"] ?? false;
		}

		/**
		 * Checks if this column is auto-incrementing
		 * @return bool True if this column is auto-incrementing, false otherwise
		 */
		public function isAutoIncrement(): bool {
			return $this->parameters["auto_increment"] ?? false;
		}
		
		/**
		 * Checks if this column allows NULL values
		 * @return bool True if this column allows NULL values, false otherwise
		 */
		public function isNullable(): bool {
			return $this->parameters["nullable"] ?? false;
		}
	}