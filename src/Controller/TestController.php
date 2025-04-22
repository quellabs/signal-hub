<?php
	
	namespace Quellabs\ObjectQuel\Controller;
	
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Route;
	use Quellabs\ObjectQuel\Entity\ProductsEntity;
	use Quellabs\ObjectQuel\EntityManager\EntityManager;
	use Quellabs\ObjectQuel\Kernel\ClassModifier;
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
				retrieve(x.guid, z) where x.guid='8ed51c45-e34c-4d5f-b29b-83a5ee0ecbe2' and x.productsId=y.productsId and x.guid=z.guid
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