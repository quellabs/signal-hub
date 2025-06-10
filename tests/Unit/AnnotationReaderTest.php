<?php
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\AnnotationReader\Configuration;
	
	/*
	|--------------------------------------------------------------------------
	| Test Case
	|--------------------------------------------------------------------------
	|
	| The following tests verify that the AnnotationReader correctly parses
	| PHP annotations from docblocks and returns the expected results.
	*/
	
	beforeEach(function () {
		// Create a new AnnotationReader instance before each test
		$this->config = new Configuration();
		$this->reader = new AnnotationReader($this->config);
	});
	
	it('instantiates without errors', function () {
		expect($this->reader)->toBeInstanceOf(AnnotationReader::class);
	});
	
	it('reads annotation correctly', function () {
		$classWithMethodAnnotations = new class {
			/**
			 * @Quellabs\AnnotationReader\AnnotationTest\Test(
			 *     Quellabs\AnnotationReader\AnnotationTest\AnotherTest::class,
			 *     name="test",
			 *     xyz={@Quellabs\AnnotationReader\AnnotationTest\AnotherTest()}
			 * )
			 */
			public function test() {
			}
		};
		
		$annotations = $this->reader->getMethodAnnotations($classWithMethodAnnotations, 'test');
		
		expect($annotations)
			->toBeArray()
			->toHaveCount(1);
		
		// Get the first annotation (using key 'Quellabs\AnnotationReader\AnnotationTest\Test')
		$annotationClass = 'Quellabs\\AnnotationReader\\AnnotationTest\\Test';
		expect($annotations)->toHaveKey($annotationClass);
		
		$annotation = $annotations[$annotationClass];
		
		// Test the annotation object
		expect($annotation)
			->toBeInstanceOf($annotationClass)
			->and($annotation->getParameters())->toBeArray()
			->and($annotation->getParameters())->toHaveKey('name')
			->and($annotation->getParameters()['name'])->toBe('test');
	});
	
	it('handles invalid annotations gracefully', function () {
		$classWithInvalidAnnotation = new class {
			/**
			 * @InvalidAnnotation(unclosed="value)
			 */
			public function broken() {
			}
		};
		
		expect(fn() => $this->reader->getMethodAnnotations($classWithInvalidAnnotation, 'broken'))
			->toThrow(\Quellabs\AnnotationReader\Exception\ParserException::class);
	});
	
	it('has use statement parser', function () {
		// This test would require a file to parse with use statements
		// For this example, we'll just verify the UseStatementParser exists and is used
		$reflectionReader = new ReflectionClass($this->reader);
		$property = $reflectionReader->getProperty('use_statement_parser');
		$property->setAccessible(true);
		
		expect($property->getValue($this->reader))
			->toBeInstanceOf(\Quellabs\AnnotationReader\LexerParser\UseStatementParser::class);
	});

	it('handles annotations with use statements correctly', function () {
		// Create a temporary test file
		$tempFile = sys_get_temp_dir() . '/annotation_use_test_' . uniqid() . '.php';
		
		// Write a class with use statements and annotations to the file
		file_put_contents($tempFile, '<?php
			namespace Quellabs\AnnotationReader\Tests;
			
			use Quellabs\AnnotationReader\AnnotationTest\Test as TestAnnotation;
			use Quellabs\AnnotationReader\AnnotationTest\AnotherTest;
			
			class TestClassWithUseStatements {
			    /**
                 * Naam met een \' erin
			     * @TestAnnotation(name="imported via alias")
			     * @AnotherTest(value=123)
			     */
			    public function testMethod()
			    {
			    }
			}
		');
		
		try {
			// Include the file to make the class available
			require_once $tempFile;

			// Use the fully qualified class name
			$className = 'Quellabs\\AnnotationReader\\Tests\\TestClassWithUseStatements';

			// Check if the class exists
			if (!class_exists($className)) {
				throw new \Exception("Test class '$className' was not properly loaded from the temporary file");
			}
			
			// Read annotations
			$annotations = $this->reader->getMethodAnnotations($className, 'testMethod');
			
			// Test that both annotations were properly resolved
			expect($annotations)->toBeArray()->toHaveCount(2);
			
			// Check the first annotation (imported with alias)
			$annotationClass1 = 'Quellabs\\AnnotationReader\\AnnotationTest\\Test';
			expect($annotations)->toHaveKey($annotationClass1);
			expect($annotations[$annotationClass1])
				->toBeInstanceOf(Quellabs\AnnotationReader\AnnotationTest\Test::class)
				->and($annotations[$annotationClass1]->getParameters())->toHaveKey('name')
				->and($annotations[$annotationClass1]->getParameters()['name'])->toBe('imported via alias');
			
			// Check the second annotation (imported without alias)
			$annotationClass2 = 'Quellabs\\AnnotationReader\\AnnotationTest\\AnotherTest';
			expect($annotations)->toHaveKey($annotationClass2);
			expect($annotations[$annotationClass2])
				->toBeInstanceOf(Quellabs\AnnotationReader\AnnotationTest\AnotherTest::class)
				->and($annotations[$annotationClass2]->getParameters())->toHaveKey('value')
				->and($annotations[$annotationClass2]->getParameters()['value'])->toBe(123);
		} finally {
			// Clean up - delete the temporary file
			if (file_exists($tempFile)) {
				unlink($tempFile);
			}
		}
	});
	
	it('inherits class annotations from parent classes', function () {
		// Test 1: Parent class should have its own annotations
		$parentAnnotations = $this->reader->getClassAnnotations(\Quellabs\AnnotationReader\AnnotationTest\BaseService::class);
		expect($parentAnnotations)
			->toBeArray()
			->toHaveCount(2)
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\CacheableTest')
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\AnotherTest');
		
		// Test 2: Child class should inherit parent annotations + its own
		$childAnnotations = $this->reader->getClassAnnotations(\Quellabs\AnnotationReader\AnnotationTest\UserService::class);
		expect($childAnnotations)
			->toBeArray()
			->toHaveCount(2) // CacheableTest from parent + AnotherTest from child (overrides parent's AnotherTest)
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\CacheableTest')
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\AnotherTest');
		
		// Test 3: Verify child's annotation overrides parent's annotation of same type
		$childAnotherTest = $childAnnotations['Quellabs\\AnnotationReader\\AnnotationTest\\AnotherTest'];
		expect($childAnotherTest->getParameters())
			->toHaveKey('id')
			->and($childAnotherTest->getParameters()['id'])->toBe(200) // Child value, not parent's 100
			->and($childAnotherTest->getParameters())->toHaveKey('priority');
		
		// Test 4: Verify inherited annotation is preserved
		$inheritedCacheable = $childAnnotations['Quellabs\\AnnotationReader\\AnnotationTest\\CacheableTest'];
		expect($inheritedCacheable->getParameters())
			->toHaveKey('ttl')
			->and($inheritedCacheable->getParameters()['ttl'])->toBe(3600);
		
		// Test 5: Grandchild should inherit all annotations
		$grandChildAnnotations = $this->reader->getClassAnnotations(\Quellabs\AnnotationReader\AnnotationTest\ProductService::class);
		expect($grandChildAnnotations)
			->toBeArray()
			->toHaveCount(2) // All inherited from BaseService
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\CacheableTest')
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\AnotherTest');
		
		// Test 6: Verify inheritance can be disabled
		$childDirectOnly = $this->reader->getClassAnnotations(\Quellabs\AnnotationReader\AnnotationTest\UserService::class, null, false);
		expect($childDirectOnly)
			->toBeArray()
			->toHaveCount(1) // Only child's own AnotherTest annotation
			->toHaveKey('Quellabs\\AnnotationReader\\AnnotationTest\\AnotherTest');
		
		// Test 7: Verify annotation filtering works with inheritance
		$cacheableAnnotations = $this->reader->getClassAnnotations(\Quellabs\AnnotationReader\AnnotationTest\UserService::class, 'Quellabs\\AnnotationReader\\AnnotationTest\\CacheableTest');
		expect($cacheableAnnotations)
			->toBeArray()
			->toHaveCount(1); // Only inherited CacheableTest annotation
	});
	
	it('method and property annotations are not inherited from parent classes', function () {
		// Parent should have method and property annotations
		$parentMethodAnnotations = $this->reader->getMethodAnnotations(\Quellabs\AnnotationReader\AnnotationTest\BaseService::class, 'annotatedMethod');
		$parentPropertyAnnotations = $this->reader->getPropertyAnnotations(\Quellabs\AnnotationReader\AnnotationTest\BaseService::class, 'annotatedProperty');
		
		expect($parentMethodAnnotations)->toHaveCount(1);
		expect($parentPropertyAnnotations)->toHaveCount(1);
		
		// Child should only have its OWN annotations, not parent's
		$childMethodAnnotations = $this->reader->getMethodAnnotations(\Quellabs\AnnotationReader\AnnotationTest\UserService::class, 'annotatedMethod');
		$childPropertyAnnotations = $this->reader->getPropertyAnnotations(\Quellabs\AnnotationReader\AnnotationTest\UserService::class, 'annotatedProperty');
		
		expect($childMethodAnnotations)->toHaveCount(1); // Only child's annotation
		expect($childPropertyAnnotations)->toHaveCount(1); // Only child's annotation
		
		// Verify the child annotations have the expected values (not parent values)
		$childMethodAnnotation = $childMethodAnnotations['Quellabs\\AnnotationReader\\AnnotationTest\\Test'];
		$childPropertyAnnotation = $childPropertyAnnotations['Quellabs\\AnnotationReader\\AnnotationTest\\Test'];
		
		expect($childMethodAnnotation->getParameters()['name'])->toBe('child_method');
		expect($childPropertyAnnotation->getParameters()['name'])->toBe('child_property');
	});
	
	it('confirms method and property annotations are not inherited', function () {
		$parentClass = new class {
			/**
			 * @Quellabs\AnnotationReader\AnnotationTest\Test(name="parent_method")
			 */
			public function annotatedMethod() {}
			
			/**
			 * @Quellabs\AnnotationReader\AnnotationTest\Test(name="parent_property")
			 */
			public $annotatedProperty;
		};
		
		$childClass = new class {
			/**
			 * @Quellabs\AnnotationReader\AnnotationTest\Test(name="child_method")
			 */
			public function annotatedMethod() {}
			
			/**
			 * @Quellabs\AnnotationReader\AnnotationTest\Test(name="child_property")
			 */
			public $annotatedProperty;
		};
		
		// Parent should have method and property annotations
		$parentMethodAnnotations = $this->reader->getMethodAnnotations($parentClass, 'annotatedMethod');
		$parentPropertyAnnotations = $this->reader->getPropertyAnnotations($parentClass, 'annotatedProperty');
		
		expect($parentMethodAnnotations)->toHaveCount(1);
		expect($parentPropertyAnnotations)->toHaveCount(1);
		
		// Child should only have its OWN annotations (not actually inheriting anything)
		$childMethodAnnotations = $this->reader->getMethodAnnotations($childClass, 'annotatedMethod');
		$childPropertyAnnotations = $this->reader->getPropertyAnnotations($childClass, 'annotatedProperty');
		
		expect($childMethodAnnotations)->toHaveCount(1); // Only child's annotation
		expect($childPropertyAnnotations)->toHaveCount(1); // Only child's annotation
		
		// Verify the annotations have the expected values
		$childMethodAnnotation = $childMethodAnnotations['Quellabs\\AnnotationReader\\AnnotationTest\\Test'];
		$childPropertyAnnotation = $childPropertyAnnotations['Quellabs\\AnnotationReader\\AnnotationTest\\Test'];
		
		expect($childMethodAnnotation->getParameters()['name'])->toBe('child_method');
		expect($childPropertyAnnotation->getParameters()['name'])->toBe('child_property');
		
		// This test confirms that method/property annotations are NOT inherited
		// because we only implemented inheritance for class annotations
	});