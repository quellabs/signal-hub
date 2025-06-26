<?php
	
	namespace Quellabs\Canvas\Validation;
	
	use Quellabs\Canvas\AOP\Contracts\BeforeAspect;
	use Quellabs\Canvas\AOP\MethodContext;
	use Symfony\Component\HttpFoundation\JsonResponse;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;
	
	/**
	 * Form validation aspect that intercepts method calls to validate request data
	 * before the method execution. Uses AOP (Aspect-Oriented Programming) pattern
	 * to separate validation concerns from business logic.
	 */
	class ValidateFormAspect implements BeforeAspect {
		
		/**
		 * The fully qualified class name of the validation rules class
		 * @var string
		 */
		private string $validationClass;
		
		/**
		 * ValidateFormAspect constructor
		 * @param string $validation The validation class name that contains the rules
		 */
		public function __construct(string $validation) {
			$this->validationClass = $validation;
		}
		
		/**
		 * Executes before the target method is called
		 * Validates the request data and either returns an error response (for API calls)
		 * or sets validation attributes on the request (for web requests)
		 * @param MethodContext $context The method execution context containing request data
		 * @return Response|null Returns JsonResponse for failed API validations, null otherwise
		 */
		public function before(MethodContext $context): ?Response {
			// Validate that the validationClass implements ValidationInterface
			if (!is_subclass_of($this->validationClass, ValidationInterface::class)) {
				throw new \InvalidArgumentException("Validation class must implement ValidationInterface");
			}
			
			// Instantiate the validation class to get the rules
			$validator = new $this->validationClass();
			
			// Extract the request from the method context
			$request = $context->getRequest();
			
			// Validate the request data against the defined rules
			$errors = $this->validateRequest($request, $validator->getRules());
			
			// Handle validation failures
			if (!empty($errors)) {
				// For API requests, return JSON error immediately
				if ($this->expectsJson($request)) {
					return new JsonResponse([
						'message' => 'Validation failed',
						'errors'  => $errors
					], 422); // HTTP 422 Unprocessable Entity
				}
				
				// For web requests, set validation flags and let controller handle the response
				$request->attributes->set('form_ok', false);
				$request->attributes->set('form_errors', $errors);
			} else {
				// Validation passed - set success flags
				$request->attributes->set('form_ok', true);
				$request->attributes->set('form_errors', []);
			}
			
			// Return null to continue execution to the target method
			return null;
		}
		
		/**
		 * Determines if the request expects a JSON response
		 * Checks Accept header, Content-Type header, and URL path patterns
		 * @param Request $request The HTTP request object
		 * @return bool True if JSON response is expected, false otherwise
		 */
		protected function expectsJson(Request $request): bool {
			return
				$request->headers->get('Accept') === 'application/json' ||
				$request->headers->get('Content-Type') === 'application/json' ||
				str_starts_with($request->getPathInfo(), '/api/');
		}
		
		/**
		 * Validates the input data against the given rules.
		 * Iterates through each field and applies all its validators,
		 * stopping at the first validation failure per field.
		 * @param Request $request The HTTP request containing form data
		 * @param array $rules The validation rules to apply (field => validator(s))
		 * @return array Array of validation errors grouped by field name
		 */
		protected function validateRequest(Request $request, array $rules): array {
			$errors = [];
			
			// Process each field and its validation rules
			foreach ($rules as $fieldName => $validators) {
				// Get field value from request (checks both POST and GET data)
				$fieldValue = $request->get($fieldName);
				
				// Normalize validators to array format for consistent processing
				$validators = is_array($validators) ? $validators : [$validators];
				
				// Apply each validator to the current field
				foreach ($validators as $validator) {
					// Run the validation check
					if (!$validator->validate($fieldValue, $request)) {
						// Validation failed - generate an error message with variable substitution
						$errors[$fieldName][] = $this->replaceVariablesInErrorString(
							$validator->getMessage() ?? "Validation failed for {$fieldName}",
							array_merge($validator->getConditions() ?? [], [
								'key'   => $fieldName,  // Field name for an error message
								'value' => $fieldValue, // Actual field value
							])
						);
						
						// Stop validating this field after first error (fail-fast approach)
						break;
					}
				}
			}
			
			return $errors;
		}
		
		/**
		 * Replaces template variables in error messages with actual values
		 * Uses {{variable_name}} syntax for variable placeholders
		 * @param string $string The error string containing variable placeholders
		 * @param array $variables Associative array of variable names and their values
		 * @return string The error string with variables replaced by actual values
		 */
		protected function replaceVariablesInErrorString(string $string, array $variables): string {
			// Use regex to find and replace {{variable}} patterns
			return preg_replace_callback('/{{\\s*([a-zA-Z_][a-zA-Z0-9_]*)\\s*}}/', function ($matches) use ($variables) {
				// Replace it with actual value if exists, otherwise keep the original placeholder
				return $variables[$matches[1]] ?? $matches[0];
			}, $string);
		}
	}