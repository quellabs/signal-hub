<?php
	
	namespace Quellabs\ObjectQuel\Controller;
	
	use Quellabs\ObjectQuel\Entity\ProductsEntity;
	use Quellabs\ObjectQuel\EntityManager\EntityManager;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Route;
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Validation\Type;
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
			$entity = $this->entityManager->find(ProductsEntity::class, 1498);
			
			$this->entityManager->remove($entity);
			$this->entityManager->flush();
			
			return new Response('Hello');
		}
	}