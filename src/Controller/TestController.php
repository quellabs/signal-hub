<?php
	
	namespace Services\Controller;
	
	use Services\AnnotationsReader\Annotations\Route;
	use Services\Entity\SeoUrlsEntity;
	use Services\EntityManager\EntityManager;
	use Symfony\Component\HttpFoundation\Response;
	
	class TestController {
		/**
		 * @Route("/hallo/{name}")
		 * @param string $name
		 * @return Response
		 */
		public function index(string $name,EntityManager $entityManager): Response {
			$entityManager->find(SeoUrlsEntity::class, 353);
			return new Response('Hello ' . $name . '!');
		}
	}