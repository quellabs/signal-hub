<?php
	
	namespace Quellabs\ObjectQuel\Controller;
	
	use Quellabs\ObjectQuel\Entity\ProductsEntity;
	use Quellabs\ObjectQuel\EntityManager\EntityManager;
	use Quellabs\ObjectQuel\Kernel\Kernel;
	use Symfony\Component\HttpFoundation\Response;
	
	use Quellabs\ObjectQuel\Annotations\Route;
	use Quellabs\ObjectQuel\Annotations\Validation\Type;
	
	class TestController {
		
		/**
		 * @Route("/hallo/{name}")
		 * @Validation\Type(property="name", type="string")
		 * @param string $name
		 * @return Response
		 */
		public function index(Kernel $kernel, string $name): Response {
			$entityManager = new EntityManager($kernel->getConfiguration());
			
			$entity = $entityManager->find(ProductsEntity::class, 1469);
			
			$entity->setGuid('hoi');
			$entityManager->flush();
			
			return new Response('Hello');
		}
	}