<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstRetrieve;
	
	/**
	 * Represents a single execution stage within a decomposed query execution plan.
	 * Each stage represents a discrete query that must be executed to contribute to
	 * the final result set.
	 *
	 * Stages can depend on results from other stages, allowing for complex query
	 * composition where the output of one query becomes the input for another.
	 */
	class ExecutionStage {
		/**
		 * Unique name/identifier for this stage
		 * Used to reference this stage from other stages and from the execution plan
		 * @var string
		 */
		private string $name;
		
		/**
		 * The ObjectQuel query to execute for this stage
		 * This contains the actual query string that will be parsed and executed
		 * @var string
		 */
		private string $query;
		
		/**
		 * Parameter names that should be bound from previous stages' results
		 * Format: ['paramName' => ['sourceStage' => 'stageName', 'sourceField' => 'fieldName']]
		 * These parameters establish the dependency graph between stages
		 * @var array
		 */
		private array $dependentParams = [];
		
		/**
		 * Static parameters that are provided at the plan creation time
		 * These are fixed values that don't depend on the execution of other stages
		 * @var array
		 */
		private array $staticParams = [];
		
		/**
		 * Flag indicating if this stage's results should be included in the final output
		 * When true, this stage's results will be part of the final result set
		 * @var bool
		 */
		private bool $includeInOutput = false;
		
		/**
		 * The field to use from this stage's results when passing to later stages
		 * If null, the entire result is used
		 * @var string|null
		 */
		private ?string $outputField = null;
		
		/**
		 * Post-processing function to apply to results before passing to next stages
		 * Allows for transformation or filtering of results before they're used by dependent stages
		 * @var callable|null
		 */
		private $resultProcessor = null;
		
		/**
		 * Create a new execution stage
		 *
		 * Initializes a stage with the required parameters. Additional configuration like
		 * dependencies and output settings can be added through method chaining.
		 *
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
		 * @return string The ObjectQuel query associated with this stage
		 */
		public function getQuery(): string {
			return $this->query;
		}
		
		/**
		 * Add a parameter that depends on results from a previous stage
		 *
		 * This establishes a dependency relationship where this stage requires
		 * data from another stage to execute properly. The dependency will be used
		 * to determine execution order and to pass values at runtime.
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
		 *
		 * When a stage is included in output, its results become part of the
		 * final result set returned by the execution plan.
		 * @param bool $include Whether to include this stage's results
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function setIncludeInOutput(bool $include): self {
			$this->includeInOutput = $include;
			return $this;
		}
		
		/**
		 * Set the field to extract from results when passing to dependent stages
		 *
		 * When other stages depend on this stage, this field determines what
		 * specific data from the results is passed along. If not set, the entire
		 * result set is passed.
		 * @param string $field The field name to extract
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function setOutputField(string $field): self {
			$this->outputField = $field;
			return $this;
		}
		
		/**
		 * Returns true if the stage has a result processor
		 *
		 * Used to determine if results need to be transformed before being used
		 * by dependent stages.
		 * @return bool Whether a result processor has been configured
		 */
		public function hasResultProcessor(): bool {
			return $this->resultProcessor !== null;
		}
		
		/**
		 * Returns the result processor
		 *
		 * The result processor function transforms stage results before they're
		 * passed to dependent stages or included in final output.
		 * @return callable|null The processor function or null if none is set
		 */
		public function getResultProcessor(): ?callable {
			return $this->resultProcessor;
		}
		
		/**
		 * Set a processor function to transform results before passing to next stages
		 *
		 * The processor allows for custom manipulation of result data, enabling
		 * filtering, mapping, or other transformations before the data is used
		 * by dependent stages.
		 *
		 * @param callable|null $processor Function that takes results and returns processed results
		 * @return ExecutionStage This stage instance for method chaining
		 */
		public function setResultProcessor(?callable $processor): self {
			$this->resultProcessor = $processor;
			return $this;
		}
		
		/**
		 * Get the name of this stage
		 *
		 * The name serves as the unique identifier for referencing this stage
		 * within the execution plan.
		 *
		 * @return string Stage name
		 */
		public function getName(): string {
			return $this->name;
		}
		
		/**
		 * Check if this stage contributes to the final output
		 * When true, this stage's results (possibly after processing) will be
		 * included in the final result set of the execution plan.
		 * @return bool Whether this stage's results should be included
		 */
		public function isIncludedInOutput(): bool {
			return $this->includeInOutput;
		}
		
		/**
		 * Get the static parameters configured for this stage
		 * Static parameters are fixed values provided at stage creation time
		 * that don't depend on other stages' execution.
		 * @return array The static parameters for this stage
		 */
		public function getStaticParams(): array {
			return $this->staticParams;
		}
		
		/**
		 * Get the dependent parameters configured for this stage
		 * These parameters establish the dependencies between stages and define
		 * how data flows from one stage to another during execution.
		 * @return array The dependent parameters and their source information
		 */
		public function getDependentParams(): array {
			return $this->dependentParams;
		}
	}