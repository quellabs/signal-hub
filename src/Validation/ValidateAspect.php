<?php
	
	namespace Quellabs\Canvas\Validation;
	
	use Quellabs\Canvas\AOP\Contracts\BeforeAspect;
	use Quellabs\Canvas\AOP\MethodContext;
	use Quellabs\DependencyInjection\Container;
	use Symfony\Component\HttpFoundation\JsonResponse;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;
	
	/**
	 * Form validation aspect that intercepts method calls to validate request data
	 * before the method execution. Uses AOP (Aspect-Oriented Programming) pattern
	 * to separate validation concerns from business logic.
	 */
	class ValidateAspect implements BeforeAspect {
		
		/**
		 * @var Container The Dependency Injector object
		 */
		protected Container $di;
		
		/**
		 * @var string The fully qualified class name of the validation rules class
		 */
		protected string $validationClass;
		
		/**
		 * @var bool In the case of JSON, send an auto response. By default, this is disabled.
		 */
		protected bool $autoRespond;
		
		/**
		 * @var string|null The ID of the form being checked
		 */
		protected ?string $formId;
		
		/**
		 * ValidateFormAspect constructor
		 * @param Container $di The Dependency Injector object
		 * @param string $validate The validation class name that contains the rules
		 * @param bool $autoRespond In the case of JSON, send an auto response
		 * @throws \InvalidArgumentException If validation class doesn't exist or implement interface
		 */
		public function __construct(Container $di, string $validate, bool $autoRespond = false, ?string $formId = null) {
			$this->validateValidationClass($validate);
			$this->di = $di;
			$this->validationClass = $validate;
			$this->autoRespond = $autoRespond;
			$this->formId = $formId;
		}
		
		/**
		 * Executes before the target method is called
		 * Validates the request data and either returns an error response (for API calls)
		 * or sets validation attributes on the request (for web requests)
		 * @param MethodContext $context The method execution context containing request data
		 * @return Response|null Returns JsonResponse for failed API validations, null otherwise
		 */
		public function before(MethodContext $context): ?Response {
			// Extract the request from the method context
			// The context object wraps the incoming HTTP request and other execution data
			$request = $context->getRequest();

			// Skip validation if no form data is present
			if ($this->shouldSkipValidation($request)) {
				return null;
			}
			
			try {
				// Instantiate the validation class to get the rules
				// This creates a new instance of the validation class specified in $this->validationClass
				$validator = $this->di->make($this->validationClass);
			} catch (\Throwable $e) {
				// If validation class instantiation fails, throw a more descriptive runtime exception
				// This could happen if the class doesn't exist or has constructor issues
				throw new \RuntimeException("Failed to instantiate validation class '{$this->validationClass}': " . $e->getMessage(), 0, $e);
			}
			
			// Validate the request data against the defined rules
			// This calls a helper method that applies validation rules to the request data
			$errors = $this->validateRequest($request, $validator);
			
			// Prefix distinguishes validation results when multiple forms are present
			$prefix = $this->formId ? "{$this->formId}_" : '';
			
			// Handle validation failures
			if (!empty($errors)) {
				// For API requests, return JSON error immediately
				// Check if the request expects a JSON response (usually via Accept header or AJAX)
				if ($this->autoRespond && $this->expectsJson($request)) {
					// Return a structured JSON error response with HTTP 422 status
					// This immediately terminates the request lifecycle
					return $this->createValidationErrorResponse($errors);
				}
				
				// For web requests, set validation flags and let controller handle the response
				// Instead of returning immediately, we store the validation state in request attributes
				// This allows the controller to access validation results and render appropriate views
				$request->attributes->set("{$prefix}validation_passed", false);
				$request->attributes->set("{$prefix}validation_errors", $errors);
			} else {
				// Validation passed - set success flags
				// Mark the data as valid so the controller knows validation succeeded
				$request->attributes->set("{$prefix}validation_passed", true);
				$request->attributes->set("{$prefix}validation_errors", []); // Empty array for consistency
			}
			
			// Return null to continue execution to the target method
			// Returning null signals that the interceptor should continue to the actual controller method
			// Only non-null Response objects will terminate the request early
			return null;
		}
		
		/**
		 * Creates the JSON error response for validation failures
		 * Override this method to customize the error response format
		 */
		protected function createValidationErrorResponse(array $errors): JsonResponse {
			return new JsonResponse([
				'message' => 'Validation failed',
				'errors'  => $errors
			], 422);
		}
		
		/**
		 * Determines whether request validation should be bypassed based on request method and content.
		 * @param Request $request The HTTP request object to evaluate
		 * @return bool True if validation should be skipped, false if validation should proceed
		 */
		private function shouldSkipValidation(Request $request): bool {
			// Skip validation for GET requests that have no query parameters
			// Rationale: Empty GET requests typically fetch default/index pages and contain no user input
			if ($request->isMethod('GET') && empty($request->query->all())) {
				return true;
			}
			
			// Skip validation for data-modifying requests (POST/PUT/PATCH/DELETE) that contain no actual data
			// This handles cases where:
			// - Forms are submitted empty (accidental submissions)
			// - API endpoints are called without payloads
			// - File upload forms are submitted without files or form data
			if (
				in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE']) &&
				empty($request->request->all()) &&  // No form/JSON data in request body
				empty($request->files->all()) // No uploaded files
			) {
				return true;
			}
			
			// Default behavior: proceed with validation for all other cases
			// This includes:
			// - GET requests with query parameters
			// - POST/PUT/PATCH/DELETE requests with form data or files
			// - Any other HTTP methods not explicitly handled above
			return false;
		}
		
		/**
		 * Validates that the validation class exists and implements the required interface
		 * @param string $validationClass The validation class to validate
		 * @throws \InvalidArgumentException If class is invalid
		 */
		private function validateValidationClass(string $validationClass): void {
			// Check if the specified class exists in the current namespace/autoloader
			// This prevents runtime errors when trying to instantiate non-existent classes
			if (!class_exists($validationClass)) {
				throw new \InvalidArgumentException("Validation class '{$validationClass}' does not exist");
			}
			
			// Verify that the class implements the ValidationInterface
			// This ensures the class has all required methods defined by the interface contract
			// Using is_subclass_of() to check interface implementation (works for both classes and interfaces)
			if (!is_subclass_of($validationClass, ValidationInterface::class)) {
				throw new \InvalidArgumentException("Validation class '{$validationClass}' must implement ValidationInterface");
			}
		}
		
		/**
		 * Determines if the request expects a JSON response
		 * Checks Accept header, Content-Type header, URL path patterns, and request format
		 * @param Request $request The HTTP request object
		 * @return bool True if JSON response is expected, false otherwise
		 */
		private function expectsJson(Request $request): bool {
			// Check if the request format is explicitly set to JSON
			if ($request->getRequestFormat() === 'json') {
				return true;
			}
			
			// Check Accept header for JSON content types
			$acceptHeader = $request->headers->get('Accept', '');
			
			if ($this->acceptsJsonContentType($acceptHeader)) {
				return true;
			}
			
			// Check Content-Type header for JSON content types
			$contentType = $request->headers->get('Content-Type', '');
			
			if ($this->isJsonContentType($contentType)) {
				return true;
			}
			
			// Check URL patterns that typically indicate API endpoints
			$pathInfo = $request->getPathInfo();
			
			if ($this->isApiPath($pathInfo)) {
				return true;
			}
			
			// Check for AJAX requests that might expect JSON
			if ($request->isXmlHttpRequest()) {
				// Additional check: if it's AJAX and Accept header prefers JSON to HTML
				if (str_contains($acceptHeader, 'application/json') &&
					strpos($acceptHeader, 'application/json') < strpos($acceptHeader, 'text/html')) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Checks if the Accept header indicates JSON is acceptable
		 * @param string $acceptHeader The Accept header value
		 * @return bool True if JSON content types are accepted
		 */
		private function acceptsJsonContentType(string $acceptHeader): bool {
			$jsonTypes = [
				'application/json',
				'application/vnd.api+json',
				'application/hal+json',
				'application/ld+json',
				'application/problem+json',
				'text/json'
			];
			
			foreach ($jsonTypes as $type) {
				if (str_contains($acceptHeader, $type)) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Checks if the Content-Type header indicates JSON content
		 * @param string $contentType The Content-Type header value
		 * @return bool True if it's a JSON content type
		 */
		private function isJsonContentType(string $contentType): bool {
			// Remove charset and other parameters
			$contentType = strtok($contentType, ';');
			
			$jsonTypes = [
				'application/json',
				'application/vnd.api+json',
				'application/hal+json',
				'application/ld+json',
				'application/problem+json',
				'text/json'
			];
			
			return in_array(trim($contentType), $jsonTypes, true);
		}
		
		/**
		 * Checks if the URL path indicates an API endpoint
		 * @param string $pathInfo The request path
		 * @return bool True if it looks like an API path
		 */
		private function isApiPath(string $pathInfo): bool {
			$apiPatterns = [
				'/^\/api\//',           // /api/...
				'/^\/v\d+\//',          // /v1/, /v2/, etc.
				'/^\/api\/v\d+\//',     // /api/v1/, /api/v2/, etc.
				'/\.json$/',            // ends with .json
				'/^\/graphql/',         // GraphQL endpoints
				'/^\/webhook/',         // Webhook endpoints
			];
			
			foreach ($apiPatterns as $pattern) {
				if (preg_match($pattern, $pathInfo)) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Validates the input data against the given rules.
		 * Iterates through each field and applies all its validators,
		 * stopping at the first validation failure per field.
		 * @param Request $request The HTTP request containing form data
		 * @param ValidationInterface $rules The validation class containing the rules
		 * @return array Array of validation errors grouped by field name
		 */
		private function validateRequest(Request $request, ValidationInterface $rules): array {
			$errors = [];
			
			// Process each field and its validation rules
			foreach ($rules->getRules() as $fieldName => $validators) {
				// Get field value from request (checks both POST and GET data)
				$fieldValue = $request->get($fieldName);
				
				// Normalize validators to array format for consistent processing
				$validators = is_array($validators) ? $validators : [$validators];
				
				// Apply each validator to the current field
				foreach ($validators as $validator) {
					try {
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
					} catch (\Throwable $e) {
						// Handle validator execution errors
						$errors[$fieldName][] = "Validation error occurred for {$fieldName}";
						
						// Log the actual error for debugging (assuming you have a logger)
						// $this->logger?->error("Validator error for field '{$fieldName}': " . $e->getMessage());
						
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
		private function replaceVariablesInErrorString(string $string, array $variables): string {
			// Use regex to find and replace {{variable}} patterns
			return preg_replace_callback('/{{\\s*([a-zA-Z_][a-zA-Z0-9_]*)\\s*}}/', function ($matches) use ($variables) {
				// Replace it with actual value if exists, otherwise keep the original placeholder
				return $variables[$matches[1]] ?? $matches[0];
			}, $string);
		}
	}