<?php
	
	namespace Services\Controller;
	
	use Services\AnnotationsReader\Annotations\Route;
	use Services\Entity\ProductsEntity;
	use Services\EntityManager\EntityManager;
	use Services\Kernel\ClassModifier;
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
			$result = $this->entityManager->executeQuery("
				range of x is ProductsEntity
				range of y is ProductsDescriptionEntity
				range of z is JSON_SOURCE('F:\\test.json', '$.rows.*')
				retrieve(x.guid, z) where x.productsId=1492 and x.productsId=y.productsId
			");
			
			/*
			$result = $this->entityManager->executeQuery("
				range of y is JSON_SOURCE('F:\\test.json', '$.rows.*')
				retrieve (y.suppliers_id) where y.suppliers_id = 6
			");
			*/

			return new Response('Hello');
		}
	}