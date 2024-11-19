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
			$this->entityManager->executeQuery("
				range of x is ProductsEntity
				retrieve (empty = is_empty(x.productsId)) where is_empty(x.productsId)
			");
			
			/*
			$classModifier = new ClassModifier(ProductsEntity::class);
			$classModifier->addConstructorLine("echo 'test';");
			$classModifier->save("ProductsEntityModified.php");
			*/
			return new Response('Hello ' . $name . '!');
		}
	}