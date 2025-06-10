<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\Canvas\Kernel;
	use Quellabs\Canvas\Routing\AnnotationResolver;
	use Quellabs\Sculpt\ConfigurationManager;
	use Symfony\Component\HttpFoundation\Request;
	
	class MatchRoutesCommand extends RoutesBase {
		
		/**
		 * Returns the signature of this command
		 * @return string
		 */
		public function getSignature(): string {
			return "route:match";
		}
		
		/**
		 * Returns a brief description of what this command is for
		 * @return string
		 */
		public function getDescription(): string {
			return "Test which route matches a given URL/path";
		}
		
		/**
		 * List the routes
		 * @param ConfigurationManager $config
		 * @return int
		 */
		public function execute(ConfigurationManager $config): int {
			// Fetch a list of matching routes for the given path
			$kernel = new Kernel();
			
			if (in_array($config->getPositional(0), ['GET', 'POST', 'DELETE', 'PATCH'], true)) {
				$request = Request::create($config->getPositional(1), $config->getPositional(0));
			} else {
				$request = Request::create($config->getPositional(0));
			}
			
			$urlResolver = new AnnotationResolver($kernel);
			$routes = $urlResolver->resolveAll($request);
			
			// Extend routes with AOP information
			foreach ($routes as $route) {
				$route['aspects'] = $this->getAspectsOfMethod($route['controller'], $route['method']);
			}

			// Transform route data into table format for display
			$tableData = array_map(function (array $entry) {
				return [
					// HTTP method
					implode(", ", $entry["http_methods"]),
					
					// Format route path with leading slash
					"/" . ltrim($entry['route']->getRoute(), '/'),
					
					// Format controller as ClassName@methodName
					$entry['controller'] . "@" . $entry['method'],
					
					// Format aspects as comma-separated list in brackets
					"[" . implode(",", $entry['aspects']) . "]",
				];
			}, $routes);
			
			// Display routes in a formatted table with headers
			$this->getOutput()->table(['HTTP methods', 'Route', 'Controller', 'Aspects'], $tableData);
			
			// Return success status
			return 0;
		}
	}