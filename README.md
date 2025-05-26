# Canvas

[![Packagist](https://img.shields.io/packagist/v/quellabs/canvas.svg)](https://packagist.org/packages/quellabs/canvas)

A modern, lightweight PHP framework that gets out of your way. Write clean controllers with route annotations, query your database with an intuitive ORM, and let contextual containers handle the complexity.

## What Makes Canvas Different

Canvas combines four powerful concepts to create a framework that feels natural to work with:

**🎯 Annotation-Based Routing** - Define routes directly in your controllers using `@Route` annotations. No separate route files to maintain.

**🗄️ ObjectQuel ORM** - Query your database using an intuitive, purpose-built query language that feels like natural PHP.

**📦 Contextual Containers** - Work with interfaces directly. Canvas intelligently resolves the right implementation based on context.

**⚡ Aspect-Oriented Programming** - Add crosscutting concerns like caching, authentication, and logging without cluttering your business logic.

```php
<?php
namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controller\BaseController;
use App\Aspects\RequireAuthAspect;
use App\Aspects\CacheAspect;
use App\Models\User;

/**
 * @InterceptWith(RequireAuthAspect::class)
 */
class UserController extends BaseController {
    
    /**
     * @Route("/users")
     * @InterceptWith(CacheAspect::class, ttl=300)
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
        // Inherits RequireAuth from class level
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

### 4. Add Crosscutting Concerns with AOP

Keep your controllers clean by using aspects for authentication, caching, logging, and more:

```php
<?php
// src/Controller/AdminController.php

namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controller\BaseController;
use App\Aspects\RequireAuthAspect;
use App\Aspects\RequireAdminAspect;
use App\Aspects\AuditLogAspect;

/**
 * All admin methods require authentication and admin role
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(RequireAdminAspect::class)
 * @InterceptWith(AuditLogAspect::class)
 */
class AdminController extends BaseController {
    
    /**
     * @Route("/admin/users")
     */
    public function users() {
        // Pure business logic - aspects handle auth, admin check, and audit logging
        $users = $this->em->findBy(User::class, []);
        return $this->render('admin/users.tpl', compact('users'));
    }
    
    /**
     * @Route("/admin/reports")
     * @InterceptWith(CacheAspect::class, ttl=3600)
     */
    public function reports() {
        // Inherits auth + admin + audit, adds caching
        $reports = $this->generateReports();
        return $this->render('admin/reports.tpl', compact('reports'));
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

### Aspect-Oriented Programming
Add crosscutting concerns without polluting your business logic:

```php
// Create reusable aspects
class CacheAspect implements AroundAspect {
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 300
    ) {}
    
    public function around(MethodContext $context, callable $proceed): mixed {
        $key = $this->generateCacheKey($context);
        
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }
        
        $result = $proceed();
        $this->cache->set($key, $result, $this->ttl);
        return $result;
    }
}

// Apply to any controller method
/**
 * @Route("/expensive-operation")
 * @InterceptWith(CacheAspect::class, ttl=3600)
 */
public function expensiveOperation() {
    // Method automatically cached for 1 hour
}
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

## CLI Commands

Canvas includes a command-line interface called Sculpt for managing your application:

### Listing Routes

View all registered routes in your application:

```bash
./vendor/bin/sculpt route:list
```

This displays a formatted table showing your routes, controllers, and applied aspects:

```
+-------+---------------------------------------+---------+
| Route | Controller                            | Aspects |
+-------+---------------------------------------+---------+
| /henk | Quellabs\Canvas\Controller\Test@index | [XYZ]   |
+-------+---------------------------------------+---------+
```

The route list helps you:
- See all available routes at a glance
- Verify route patterns and controller mappings
- Check which aspects are applied to each route
- Debug routing issues during development

## Aspect-Oriented Programming in Detail

Canvas provides true AOP for controller methods, allowing you to separate crosscutting concerns from your business logic.

### Creating Aspects

Aspects implement one of three interfaces depending on when they should execute:

```php
<?php
namespace App\Aspects;

use Quellabs\Canvas\AOP\Contracts\BeforeAspect;
use Quellabs\Canvas\AOP\MethodContext;
use Symfony\Component\HttpFoundation\Response;

class RequireAuthAspect implements BeforeAspect {
    public function __construct(private AuthService $auth) {}
    
    public function before(MethodContext $context): ?Response {
        if (!$this->auth->isAuthenticated()) {
            return new RedirectResponse('/login');
        }
        
        return null; // Continue execution
    }
}
```

### Aspect Types

**Before Aspects** - Execute before the method, can stop execution:
```php
class RateLimitAspect implements BeforeAspect {
    public function before(MethodContext $context): ?Response {
        if ($this->rateLimiter->isExceeded()) {
            return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
        }
        return null;
    }
}
```

**After Aspects** - Execute after the method, can modify the response:
```php
class AuditLogAspect implements AfterAspect {
    public function after(MethodContext $context, mixed $result): ?Response {
        $this->logger->info('Method executed', [
            'controller' => get_class($context->getTarget()),
            'method' => $context->getMethodName(),
            'user' => $this->auth->getCurrentUser()?->id
        ]);
        
        return null; // Don't modify response
    }
}
```

**Around Aspects** - Wrap the entire method execution:
```php
class TransactionAspect implements AroundAspect {
    public function around(MethodContext $context, callable $proceed): mixed {
        $this->db->beginTransaction();
        
        try {
            $result = $proceed();
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
```

### Applying Aspects

**Class-level aspects** apply to all methods:
```php
/**
 * @InterceptWith(RequireAuthAspect::class)
 */
class UserController extends BaseController {
    // All methods require authentication
}
```

**Method-level aspects** apply to specific methods:
```php
class UserController extends BaseController {
    
    /**
     * @Route("/users")
     * @InterceptWith(CacheAspect::class, ttl=300)
     * @InterceptWith(RateLimitAspect::class, limit=100)
     */
    public function index() {
        // Method has caching and rate limiting
    }
}
```

**Combined aspects** - class-level and method-level merge:
```php
/**
 * @InterceptWith(RequireAuthAspect::class)
 */
class AdminController extends BaseController {
    
    /**
     * @Route("/admin/reports")
     * @InterceptWith(RequireAdminAspect::class)
     * @InterceptWith(CacheAspect::class, ttl=3600)
     */
    public function reports() {
        // Gets: RequireAuth + RequireAdmin + Cache
    }
}
```

### Aspect Parameters

Pass configuration to aspects through annotation parameters:

```php
/**
 * @InterceptWith(CacheAspect::class, ttl=3600, tags={"reports", "admin"})
 * @InterceptWith(RateLimitAspect::class, limit=10, window=60)
 */
public function heavyOperation() {
    // Cached for 1 hour with tags, rate limited to 10/minute
}
```

The aspect receives these as constructor parameters:

```php
class CacheAspect implements AroundAspect {
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 300,
        private array $tags = []
    ) {}
}
```

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

### RESTful API with AOP

```php
<?php
namespace App\Controller\Api;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controller\BaseController;
use App\Aspects\RequireAuthAspect;
use App\Aspects\RateLimitAspect;
use App\Aspects\ValidateJsonAspect;
use App\Models\Product;

/**
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(RateLimitAspect::class, limit=100)
 */
class ProductController extends BaseController {
    
    /**
     * @Route("/api/products", methods={"GET"})
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function index() {
        $products = $this->em->findBy(Product::class, ['active' => true]);
        return $this->json($products);
    }
    
    /**
     * @Route("/api/products", methods={"POST"})
     * @InterceptWith(ValidateJsonAspect::class, schema="product-create")
     * @InterceptWith(RateLimitAspect::class, limit=10)
     */
    public function create() {
        $data = $this->getJsonRequest();
        $product = new Product();
        
        foreach ($data as $key => $value) {
            $product->$key = $value;
        }
        
        return $this->json($product, 201);
    }
}
```

### Complex Aspect Composition

```php
<?php
namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Aspects\{
    RequireAuthAspect,
    RequirePermissionAspect,
    CacheAspect,
    AuditLogAspect,
    PerformanceTrackingAspect,
    TransactionAspect
};

/**
 * Base security and logging for all admin operations
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(AuditLogAspect::class)
 * @InterceptWith(PerformanceTrackingAspect::class)
 */
class AdminController extends BaseController {
    
    /**
     * @Route("/admin/users/{id}")
     * @InterceptWith(RequirePermissionAspect::class, permission="users.view")
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function showUser(int $id) {
        // Gets: Auth + Audit + Performance + Permission + Cache
        return $this->em->find(User::class, $id);
    }
    
    /**
     * @Route("/admin/users/{id}", methods={"PUT"})
     * @InterceptWith(RequirePermissionAspect::class, permission="users.edit")
     * @InterceptWith(TransactionAspect::class)
     * @InterceptWith(RateLimitAspect::class, limit=5, window=60)
     */
    public function updateUser(int $id) {
        // Gets: Auth + Audit + Performance + Permission + Transaction + RateLimit
        $user = $this->em->find(User::class, $id);
        $data = $this->getJsonRequest();
        
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        
        return $this->json($user);
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
- **Efficient AOP**: Aspects only applied when methods are called, no global overhead

## Why Canvas?

**For Rapid Development**: Start coding immediately with zero configuration. Routes, ORM, dependency injection, and AOP work out of the box.

**For Clean Code**: Annotation-based routes and aspects keep logic close to implementation. ObjectQuel queries read like natural language.

**For Flexibility**: Contextual containers and composable aspects let you use different implementations without complex configuration.

**For Growth**: Modular architecture scales from simple websites to complex applications with enterprise-grade crosscutting concerns.

## Contributing

We welcome contributions! Please open an issue or submit a pull request.

## License

Canvas is open-sourced software licensed under the [MIT license](LICENSE).