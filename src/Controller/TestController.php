<?php
	
	namespace Services\Controller;
	
	use Services\AnnotationsReader\Annotations\Route;
	use Services\Entity\SeoUrlsEntity;
	use Services\EntityManager\EntityManager;
	use Symfony\Component\HttpFoundation\Response;
	
	class TestController {
		
		private EntityManager $entityManager;
		
		/**
		 * TestController controller
		 * @param EntityManager $entityManager
		 */
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
		}
		
		/**
		 * @Route("/hallo/{name}")
		 * @Validation\Type(property="name", type="string")
		 * @param string $name
		 * @return Response
		 */
		public function index(string $name): Response {
			$entity = $this->entityManager->find(SeoUrlsEntity::class, 353);
			
			print_r($entity->getLanguageId());
			
			return new Response('Hello ' . $name . '!');
		}
	}