<?php
	
	namespace Quellabs\Canvas\Controller;
	
	use Quellabs\Canvas\Templating\TemplateEngineInterface;
	
	/**
	 * Base controller providing common functionality for all controllers.
	 */
	class BaseController {
		
		/**
		 * The view renderer instance used for rendering templates.
		 * @var TemplateEngineInterface
		 */
		protected TemplateEngineInterface $view;
		
		/**
		 * BaseController constructor.
		 * @param TemplateEngineInterface $templateEngine The template engine to use for rendering
		 */
		public function __construct(TemplateEngineInterface $templateEngine) {
			$this->view = $templateEngine;
		}
		
		/**
		 * Render a template using the injected view renderer
		 * @param string $template The template file path to render (relative to template directory)
		 * @param array $data Associative array of data to pass to the template as variables
		 * @return string The rendered template content as HTML/text
		 * @throws \RuntimeException If the template engine fails to render the template
		 */
		protected function render(string $template, array $data = []): string {
			// Delegate the actual rendering to the injected template engine
			// The template engine handles template loading, data binding, and output generation
			return $this->view->render($template, $data);
		}
	}