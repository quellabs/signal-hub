<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstRetrieve;
	
	/**
	 * Represents a single execution stage within a decomposed query execution plan.
	 * Each stage represents a discrete query that must be executed to contribute to
	 * the final result set.
	 */
	class ExecutionStage {
		/**
		 * Unique name/identifier for this stage
		 */
		private string $name;
		
		/**
		 * The ObjectQuel query to execute for this stage
		 */
		private string $query;
		
		/**
		 * Parameter names that should be bound from previous stages' results
		 * Format: ['paramName' => ['sourceStage' => 'stageName', 'sourceField' => 'fieldName']]
		 */
		private array $dependentParams = [];
		
		/**
		 * Static parameters that are provided at the plan creation time
		 */
		private array $staticParams = [];
		
		/**
		 * Flag indicating if this stage's results should be included in the final output
		 */
		private bool $includeInOutput = false;
		
		/**
		 * The field to use from this stage's results when passing to later stages
		 */
		private ?string $outputField = null;
		
		/**
		 * Post-processing function to apply to results before passing to next stages
		 * @var callable|null
		 */
		private $resultProcessor = null;
		
		/**
		 * Create a new execution stage
		 * @param string $name Unique identifier for this stage
		 * @param string $query The ObjectQuel query for this stage
		 * @param array $staticParams Fixed parameters that don't depend on other stages
		 */
		public function __construct(string $name, string $query, array $staticParams = []) {
			$this->name = $name;
			$this->query = $query;
			$this->staticParams = $staticParams;
		}
		
		/**
		 * Returns the query to execute
		 * @return string
		 */
		public function getQuery(): string {
			return $this->query;
		}
		
		/**
		 * Add a parameter that depends on results from a previous stage
		 * @param string $paramName Name of the parameter in this stage's query
		 * @param string $sourceStage Name of the stage that produces the value
		 * @param string|null $sourceField Field from the source stage to use (or null for entire result)
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function addDependentParam(string $paramName, string $sourceStage, ?string $sourceField = null): self {
			$this->dependentParams[$paramName] = [
				'sourceStage' => $sourceStage,
				'sourceField' => $sourceField
			];
			
			return $this;
		}
		
		/**
		 * Mark this stage's results to be included in the final output
		 * @param bool $include Whether to include this stage's results
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function setIncludeInOutput(bool $include): self {
			$this->includeInOutput = $include;
			return $this;
		}
		
		/**
		 * Set the field to extract from results when passing to dependent stages
		 * @param string $field The field name to extract
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function setOutputField(string $field): self {
			$this->outputField = $field;
			return $this;
		}
		
		/**
		 * Returns true if the stage has a result processor
		 * @return bool
		 */
		public function hasResultProcessor(): bool {
			return $this->resultProcessor !== null;
		}
		
		/**
		 * Returns the result processor
		 * @return callable|null
		 */
		public function getResultProcessor(): ?callable {
			return $this->resultProcessor;
		}
		
		/**
		 * Set a processor function to transform results before passing to next stages
		 * @param callable|null $processor Function that takes results and returns processed results
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function setResultProcessor(?callable $processor): self {
			$this->resultProcessor = $processor;
			return $this;
		}
		
		/**
		 * Get the name of this stage
		 * @return string Stage name
		 */
		public function getName(): string {
			return $this->name;
		}

		/**
		 * Check if this stage contributes to the final output
		 * @return bool Whether this stage's results should be included
		 */
		public function isIncludedInOutput(): bool {
			return $this->includeInOutput;
		}
		
		public function getStaticParams(): array {
			return $this->staticParams;
		}
		
		public function getDependentParams(): array {
			return $this->dependentParams;
		}
	}