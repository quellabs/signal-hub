# Canvas

[![Packagist](https://img.shields.io/packagist/v/quellabs/canvas.svg)](https://packagist.org/packages/quellabs/canvas)

A modern, lightweight PHP framework that gets out of your way. Write clean controllers with route annotations, query your database with an intuitive ORM, and let contextual containers handle the complexity.

## What Makes Canvas Different

Canvas combines three powerful concepts to create a framework that feels natural to work with:

**🎯 Annotation-Based Routing** - Define routes directly in your controllers using `@Route` annotations. No separate route files to maintain.

**🗄️ ObjectQuel ORM** - Query your database using an intuitive, purpose-built query language that feels like natural PHP.

**📦 Contextual Containers** - Work with interfaces directly. Canvas intelligently resolves the right implementation based on context.

```php
<?php
namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controller\BaseController;
use App\Models\User;

class UserController extends BaseController {
    
    /**
     * @Route("/users")
     */
    public function index() {
        // ObjectQuel ORM - clean, intuitive queries
        $users = $this->em->findBy(User::class, ['active' => true]);
        
        // Contextual template resolution
        return $this->render('users/index.tpl', compact('users'));
    }
    
    /**
     * @Route("/users/{id}")
     */
    public function show(int $id) {
        $user = $this->em->find(User::class, $id);
        return $this->render('users/show.tpl', compact('user'));
    }
}
```

## Installation

```bash
composer require quellabs/canvas
```

Or create a new project:

```bash
mkdir my-canvas-app
cd my-canvas-app
composer init
composer require quellabs/canvas
```

## Quick Start

### 1. Bootstrap Your Application

```php
<?php
// public/index.php

use Quellabs\Canvas\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
```

### 2. Create Controllers with Route Annotations

Canvas automatically discovers your controllers and registers their routes:

```php
<?php
// src/Controller/HomeController.php

namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends BaseController {
    
    /**
     * @Route("/")
     */
    public function index(): Response {
        return new Response('Hello, Canvas!');
    }
    
    /**
     * @Route("/welcome/{name}")
     */
    public function welcome(string $name): Response {
        return $this->render('welcome.tpl', ['name' => $name]);
    }
}
```

### 3. Work with Your Database Using ObjectQuel

ObjectQuel provides an intuitive way to interact with your data:

```php
<?php
// src/Controller/BlogController.php

namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controller\BaseController;
use App\Models\Post;

class BlogController extends BaseController {
    
    /**
     * @Route("/posts")
     */
    public function index() {
        // Simple ObjectQuel queries
        $posts = $this->em->findBy(Post::class, ['published' => true]);
                          
        return $this->render('blog/index.tpl', compact('posts'));
    }
    
    /**
     * @Route("/posts/{slug}")
     */
    public function show(string $slug) {
        // Find individual records
        $post = $this->em->find(Post::class, $slug);
                         
        if (!$post) {
            throw new NotFoundHttpException();
        }
        
        return $this->render('blog/show.tpl', compact('post'));
    }
}
```

## Key Features

### Annotation-Based Routing
No separate route files to maintain. Define routes directly where they belong:

```php
/**
 * @Route("/api/users/{id}", methods={"GET", "PUT", "DELETE"})
 * @Route("/users/{id}/edit", name="user.edit")
 */
public function edit(int $id) {
    // Controller logic here
}
```

### ObjectQuel ORM
A modern ORM with Data Mapper pattern:

```php
// Find records with conditions
$users = $em->findBy(User::class, ['role' => 'admin']);

// Find individual records
$user = $em->find(User::class, $id);

// Work with your entities naturally
$user = new User();
$user->name = 'John';
$user->email = 'john@example.com';
// ObjectQuel handles persistence automatically
```

### Contextual Service Resolution
When you need different implementations of the same interface, context makes it simple:

```php
// Use different template engines based on context
$twig = $this->container->for('twig')->get(TemplateEngineInterface::class);
$blade = $this->container->for('blade')->get(TemplateEngineInterface::class);

// Different cache implementations
$redis = $this->container->for('redis')->get(CacheInterface::class);
$file = $this->container->for('file')->get(CacheInterface::class);
```

### Automatic Discovery
Add functionality by simply requiring packages:

```bash
composer require quellabs/canvas-twig      # Twig template engine
composer require quellabs/canvas-blade     # Blade template engine  
composer require quellabs/canvas-redis     # Redis integration
```

Canvas automatically discovers and configures new services through Composer metadata.

## Configuration

Canvas follows convention-over-configuration. Create config files only when you need them:

```php
<?php
// src/config/database.php

return [
    'driver'   => 'mysql',
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_NAME') ?: 'canvas',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
];
```

Register it in `composer.json`:

```json
{
  "extra": {
    "discover": {
      "canvas": {
        "providers": [
          {
            "class": "Quellabs\\Canvas\\Discover\\DatabaseServiceProvider",
            "config": "src/config/database.php"
          }
        ]
      }
    }
  }
}
```

## Advanced Examples

### RESTful API Controller

```php
<?php
namespace App\Controller\Api;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controller\BaseController;
use App\Models\Product;

/**
 * @Route("/api/products")
 */
class ProductController extends BaseController {
    
    /**
     * @Route("/", methods={"GET"})
     */
    public function index() {
        $products = $this->em->findBy(Product::class, ['active' => true]);
                             
        return $this->json($products);
    }
    
    /**
     * @Route("/", methods={"POST"})
     */
    public function create() {
        $data = $this->getJsonRequest();
        $product = new Product();
        // Set properties from request data
        foreach ($data as $key => $value) {
            $product->$key = $value;
        }
        
        return $this->json($product, 201);
    }
    
    /**
     * @Route("/{id}", methods={"PUT"})
     */
    public function update(int $id) {
        $product = $this->em->find(Product::class, $id);
        $data = $this->getJsonRequest();
        
        foreach ($data as $key => $value) {
            $product->$key = $value;
        }
        
        return $this->json($product);
    }
}
```

### Custom Service Provider

Create discoverable packages for Canvas:

```php
<?php
use Quellabs\Canvas\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider {
    
    public function supports(string $className, array $context = []): bool {
        return $className === PaymentInterface::class
            && ($context['provider'] ?? null) === 'stripe';
    }
    
    public function createInstance(string $className, array $dependencies): object {
        return new StripePaymentService($this->config['api_key']);
    }
}
```

## Performance

Canvas is built for performance:

- **Lazy Loading**: Services instantiated only when needed
- **Route Caching**: Annotation routes cached in production
- **ObjectQuel Optimization**: Built-in query caching and optimization
- **Minimal Reflection**: Efficient autowiring with caching
- **Zero Configuration Overhead**: Sensible defaults eliminate config parsing

## Why Canvas?

**For Rapid Development**: Start coding immediately with zero configuration. Routes, ORM, and dependency injection work out of the box.

**For Clean Code**: Annotation-based routes keep logic close to implementation. ObjectQuel queries read like natural language.

**For Flexibility**: Contextual containers let you use different implementations without complex configuration.

**For Growth**: Modular architecture scales from simple websites to complex applications.

## Contributing

We welcome contributions! Please open an issue or submit a pull request.

## License

Canvas is open-sourced software licensed under the [MIT license](LICENSE).