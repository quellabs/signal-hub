<?php
	
	namespace Quellabs\Canvas\Controllers;
	
	use Quellabs\Contracts\Templates\TemplateEngineInterface;
	use Quellabs\ObjectQuel\EntityManager;
	use Symfony\Component\HttpFoundation\JsonResponse;
	use Symfony\Component\HttpFoundation\Response;
	
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
		 * The EntityManager (ObjectQuel)
		 * @var EntityManager
		 */
		protected EntityManager $em;
		
		/**
		 * BaseController constructor.
		 * @param TemplateEngineInterface $templateEngine The template engine to use for rendering
		 */
		public function __construct(TemplateEngineInterface $templateEngine, EntityManager $entityManager) {
			$this->view = $templateEngine;
			$this->em = $entityManager;
		}
		
		/**
		 * Render a template using the injected view renderer
		 * @param string $template The template file path to render (relative to template directory)
		 * @param int $statusCode The http status code to return
		 * @param array $data Associative array of data to pass to the template as variables
		 * @return Response The response with the rendered template content attached
		 */
		protected function render(string $template, array $data = [], int $statusCode=200): Response {
			// Delegate the actual rendering to the injected template engine
			// The template engine handles template loading, data binding, and output generation
			return new Response($this->view->render($template, $data), $statusCode);
		}
		
		/**
		 * Returns JSON data
		 * @param array $data
		 * @param int $statusCode The http status code to return
		 * @return Response
		 */
		protected function json(array $data = [], int $statusCode=200): Response {
			return new JsonResponse($data, $statusCode);
		}
	}