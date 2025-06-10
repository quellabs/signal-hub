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
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controllers\BaseController;
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
     * @Route("/users/{id:int}")
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

Or create a new project using the Canvas skeleton:

```bash
composer create-project quellabs/canvas-skeleton my-canvas-app
cd my-canvas-app
```

Or create a project manually:

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
// src/Controllers/HomeController.php

namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controllers\BaseController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends BaseController {
    
    /**
     * @Route("/")
     */
    public function index(): Response {
        return new Response('Hello, Canvas!');
    }
    
    /**
     * @Route("/welcome/{name:alpha}")
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
// src/Controllers/BlogController.php

namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controllers\BaseController;
use App\Models\Post;

class BlogController extends BaseController {
    
    /**
     * @Route("/posts")
     */
    public function index() {
        // Simple ObjectQuel queries
        $posts = $this->em->findBy(Post::class, ['published' => true]);

        // Render the tpl file                          
        return $this->render('blog/index.tpl', compact('posts'));
    }
    
    /**
     * @Route("/posts/{slug:slug}")
     */
    public function show(string $slug) {
        // Find individual records
        $post = $this->em->find(Post::class, $slug);
                         
        if (!$post) {
            return $this->notFound('Post not found');
        }
        
        return $this->render('blog/show.tpl', compact('post'));
    }
}
```

### 4. Add Crosscutting Concerns with AOP

Keep your controllers clean by using aspects for authentication, caching, logging, and more:

```php
<?php
// src/Controllers/AdminController.php

namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controllers\BaseController;
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

### Advanced Routing with Variable Validation and Wildcards

Canvas provides powerful routing capabilities with built-in parameter validation and flexible wildcard matching.

#### Route Parameter Validation

Validate route parameters directly in your route definitions using type constraints:

```php
class ProductController extends BaseController {
    
    /**
     * @Route("/products/{id:int}")
     */
    public function show(int $id) {
        // Only matches numeric IDs: /products/123 ✓, /products/abc ✗
    }
    
    /**
     * @Route("/users/{username:alpha}")
     */
    public function profile(string $username) {
        // Only matches alphabetic usernames: /users/john ✓, /users/john123 ✗
    }
    
    /**
     * @Route("/posts/{slug:slug}")
     */
    public function post(string $slug) {
        // Only matches URL-friendly slugs: /posts/my-blog-post ✓
    }
    
    /**
     * @Route("/api/items/{uuid:uuid}")
     */
    public function item(string $uuid) {
        // Only matches valid UUIDs: /api/items/550e8400-e29b-41d4-a716-446655440000 ✓
    }
    
    /**
     * @Route("/contact/{email:email}")
     */
    public function contact(string $email) {
        // Only matches valid email addresses: /contact/user@example.com ✓
    }
    
    /**
     * @Route("/categories/{name:alnum}")
     */
    public function category(string $name) {
        // Only matches alphanumeric: /categories/tech2024 ✓, /categories/tech-news ✗
    }
}
```

**Available Validation Types:**
- `{id:int}` - Integers only (`\d+`)
- `{slug:alpha}` - Alphabetic characters only (`[a-zA-Z]+`)
- `{name:alnum}` - Alphanumeric characters (`[a-zA-Z0-9]+`)
- `{item:slug}` - URL-friendly slugs (`[a-zA-Z0-9\-]+`)
- `{user:uuid}` - UUID format validation
- `{email:email}` - Email address validation

#### Wildcard Route Matching

Handle dynamic paths and file serving with powerful wildcard support:

```php
class FileController extends BaseController {
    
    /**
     * @Route("/files/{filename}")
     */
    public function serve(string $filename) {
        // Named parameter - automatically injected
        // Matches: /files/document.pdf, /files/image.jpg
        return $this->serveFile($filename);
    }
    
    /**
     * @Route("/assets/{path:**}")
     */
    public function assets(string $path) {
        // Named multi-segment wildcard
        // Matches: /assets/css/style.css → path = "css/style.css"
        // Matches: /assets/js/vendor/jquery.min.js → path = "js/vendor/jquery.min.js"
        return $this->serveAsset($path);
    }
    
    /**
     * @Route("/downloads/{content:.*}")
     */
    public function downloads(string $content) {
        // Alternative syntax for multi-segment wildcard
        // Matches: /downloads/files/reports/2024/january.pdf → content = "files/reports/2024/january.pdf"
        return $this->handleDownload($content);
    }
}

class ApiController extends BaseController {
    
    /**
     * @Route("/api/{remaining:**}")
     */
    public function proxy(string $remaining) {
        // Named multi-segment wildcard - automatically injected
        // Matches: /api/v1/users/123, /api/v2/posts/456/comments
        return $this->proxyToMicroservice($remaining);
    }
    
    /**
     * @Route("/api/{version}/users/{id:int}")
     */
    public function user(string $version, int $id) {
        // Mixed parameters: version string and typed ID
        // Matches: /api/v1/users/123 → version = "v1", id = 123
        // Matches: /api/v2.1/users/456 → version = "v2.1", id = 456
        return $this->getUserByVersion($version, $id);
    }
}
```

**Wildcard Types:**
- `{filename}` - Named parameter (matches single path segment)
- `{path:**}` - Named multi-segment wildcard (captures remaining path)
- `{content:.*}` - Alternative syntax for named multi-segment wildcard

Note: All route variables are automatically injected as method parameters.

#### Complex Route Examples

Combine validation and wildcards for sophisticated routing:

```php
class AdvancedController extends BaseController {
    
    /**
     * @Route("/users/{id:int}/files/{path:**}")
     */
    public function userFiles(int $id, string $path) {
        // /users/123/files/documents/report.pdf
        // id = 123, path = "documents/report.pdf"
    }
    
    /**
     * @Route("/shop/{category:slug}/products/{id:int}")
     */
    public function product(string $category, int $id) {
        // /shop/electronics/products/456
        // category = "electronics", id = 456
    }
    
    /**
     * @Route("/proxy/{service:alnum}/{endpoint:**}")
     */
    public function serviceProxy(string $service, string $endpoint) {
        // /proxy/auth/api/v1/login
        // service = "auth", endpoint = "api/v1/login"
    }
}
```

### Annotation-Based Routing

No separate route files to maintain. Define routes directly where they belong:

```php
/**
 * @Route("/api/users/{id:int}", methods={"GET", "PUT", "DELETE"})
 * @Route("/users/{id:int}/edit", name="user.edit")
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

// Tell the EntityManager to keep track of entity changes
$em->persist($user);

// Flush the changes to the database
$em->flush();
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
        
        // Fetch response from cache
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }
        
        // Call the original function
        $result = $proceed();
        
        // Put the result in cache
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

Filter routes by controller to focus on specific functionality:

```bash
./vendor/bin/sculpt route:list --controller=UserController
```

### Route Matching

Test which controller and method handles a specific URL path:

```bash
./vendor/bin/sculpt route:match /url/path/10
```

You can also specify the HTTP method to test method-specific routing:

```bash
./vendor/bin/sculpt route:match GET /url/path/10
```

This shows you exactly which controller method will be called for a given URL and HTTP method, helping you:
- Debug routing issues
- Verify parameter extraction and validation
- Test wildcard route matching
- Understand route precedence and matching order
- Test method-specific route handling (GET, POST, PUT, DELETE, etc.)

## Aspect-Oriented Programming in Detail

Canvas provides true AOP for controller methods, allowing you to separate crosscutting concerns from your business logic. Canvas supports four types of aspects that execute at different stages of the request lifecycle.

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

### Execution Order

Aspects execute in a specific order to ensure proper request processing:

1. **Request Aspects** - Transform request data and add context
2. **Before Aspects** - Handle authentication, validation, rate limiting
3. **Around Aspects** - Wrap method execution with caching, transactions
4. **After Aspects** - Log results, modify responses

```php
/**
 * @InterceptWith(SecuritySanitizationAspect::class)    // 1. Request transformation
 * @InterceptWith(RequireAuthAspect::class)             // 2. Before execution
 * @InterceptWith(TransactionAspect::class)             // 3. Around execution  
 * @InterceptWith(AuditLogAspect::class)                // 4. After execution
 */
class AdminController extends BaseController {
    
    /**
     * @Route("/admin/users")
     * @InterceptWith(CacheAspect::class, ttl=300)       // Additional around aspect
     */
    public function users() {
        // Method executes with clean request, authentication, transaction, and caching
        return $this->em->findBy(User::class, []);
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

### RESTful API with AOP and Advanced Routing

```php
<?php
namespace App\Controllers\Api;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controllers\BaseController;
use App\Aspects\RequireAuthAspect;
use App\Aspects\RateLimitAspect;
use App\Aspects\ValidateJsonAspect;
use App\Aspects\ContentNegotiationAspect;
use App\Aspects\SecuritySanitizationAspect;
use App\Models\Product;

/**
 * @InterceptWith(SecuritySanitizationAspect::class)
 * @InterceptWith(ContentNegotiationAspect::class)
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(RateLimitAspect::class, limit=100)
 */
class ProductController extends BaseController {
    
    /**
     * @Route("/api/products", methods={"GET"})
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function index() {
        // Request already sanitized and content negotiated
        $format = $this->request->attributes->get('response_format', 'json');
        $products = $this->em->findBy(Product::class, ['active' => true]);
        return $this->json($products);
    }
    
    /**
     * @Route("/api/products/{id:int}", methods={"GET"})
     * @InterceptWith(CacheAspect::class, ttl=600)
     */
    public function show(int $id) {
        // Only accepts integer IDs
        $product = $this->em->find(Product::class, $id);
        return $this->json($product);
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
    
    /**
     * @Route("/api/products/{id:int}/images/{path:**}")
     */
    public function productImages(int $id, string $path) {
        // Handles: /api/products/123/images/thumbnails/large.jpg
        // id = 123, path = "thumbnails/large.jpg"
        return $this->serveProductImage($id, $path);
    }
}
```

### File Management with Wildcards

```php
<?php
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Aspects\RequireAuthAspect;
use App\Aspects\FileSecurityAspect;

/**
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(FileSecurityAspect::class)
 */
class FileController extends BaseController {
    
    /**
     * @Route("/files/{userId:int}/{path:**}")
     */
    public function userFiles(int $userId, string $path) {
        // Handles deep file paths for specific users
        // /files/123/documents/2024/reports/quarterly.pdf
        // userId = 123, path = "documents/2024/reports/quarterly.pdf"
        
        $this->validateUserAccess($userId);
        return $this->serveUserFile($userId, $path);
    }
    
    /**
     * @Route("/public/assets/{assetPath:**}")
     */
    public function publicAssets(string $assetPath) {
        // Named wildcard parameter injected automatically
        // /public/assets/css/bootstrap.min.css → assetPath = "css/bootstrap.min.css"
        return $this->servePublicAsset($assetPath);
    }
    
    /**
     * @Route("/uploads/{type:alpha}/{filename}")
     */
    public function download(string $type, string $filename) {
        // Both parameters injected: type validation + filename capture
        // /uploads/images/user-avatar-123.jpg → type = "images", filename = "user-avatar-123.jpg"
        return $this->downloadFile($type, $filename);
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
     * @Route("/admin/users/{id:int}")
     * @InterceptWith(RequirePermissionAspect::class, permission="users.view")
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function showUser(int $id) {
        // Gets: Auth + Audit + Performance + Permission + Cache
        return $this->em->find(User::class, $id);
    }
    
    /**
     * @Route("/admin/users/{id:int}", methods={"PUT"})
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
- **Efficient Route Matching**: Optimized wildcard and validation patterns
- **ObjectQuel Optimization**: Built-in query caching and optimization
- **Minimal Reflection**: Efficient autowiring with caching
- **Zero Configuration Overhead**: Sensible defaults eliminate config parsing
- **Efficient AOP**: Aspects only applied when methods are called, no global overhead

## Why Canvas?

**For Rapid Development**: Start coding immediately with zero configuration. Routes, ORM, dependency injection, and AOP work out of the box.

**For Clean Code**: Annotation-based routes and aspects keep logic close to implementation. ObjectQuel queries read like natural language.

**For Flexibility**: Contextual containers and composable aspects let you use different implementations without complex configuration.

**For Growth**: Modular architecture scales from simple websites to complex applications with enterprise-grade crosscutting concerns.

## Error Handling and Route Fallbacks

Handle routing errors gracefully with custom error controllers and fallback routes.

### Custom 404 Handling

```php
<?php
namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controllers\BaseController;

class ErrorController extends BaseController {
    
    /**
     * @Route("/404", name="error.not_found")
     */
    public function notFound() {
        return $this->render('errors/404.tpl', [], 404);
    }
    
    /**
     * @Route("/error/{code:int}", name="error.generic")
     */
    public function error(int $code) {
        $message = $this->getErrorMessage($code);
        return $this->render('errors/generic.tpl', compact('code', 'message'), $code);
    }
}
```

### Fallback Routes

Use wildcards to create intelligent fallback handling:

```php
class FallbackController extends BaseController {
    
    /**
     * @Route("/api/{remaining:**}", priority=-100)
     */
    public function apiNotFound(string $remaining) {
        // Catch all unmatched API routes
        return $this->json([
            'error' => 'Endpoint not found',
            'path' => $remaining,
            'suggestion' => $this->suggestEndpoint($remaining)
        ], 404);
    }
    
    /**
     * @Route("/{path:**}", priority=-200)
     */
    public function pageNotFound(string $path) {
        // Ultimate fallback for any unmatched route
        // Try to find similar pages
        $suggestions = $this->findSimilarPages($path);
        
        return $this->render('errors/404.tpl', compact('path', 'suggestions'), 404);
    }
}
```

## Security Best Practices

Canvas routing includes several security features to protect your application.

### Input Validation

Route validation patterns help prevent malicious input:

```php
class SecureController extends BaseController {
    
    /**
     * @Route("/files/{filename:filename}")
     * @InterceptWith(FileSecurityAspect::class)
     */
    public function serveFile(string $filename) {
        // filename pattern prevents path traversal: ../../../etc/passwd
        // FileSecurityAspect provides additional validation
        return $this->serveSecureFile($filename);
    }
    
    /**
     * @Route("/users/{id:int}")
     */
    public function user(int $id) {
        // int validation prevents SQL injection attempts in URL
        if ($id <= 0) {
            return $this->badRequest('Invalid user ID');
        }
        
        return $this->em->find(User::class, $id);
    }
}
```

### Rate Limiting by Route Pattern

Apply different rate limits based on route patterns:

```php
class RateLimitedController extends BaseController {
    
    /**
     * @Route("/api/public/{endpoint:**}")
     * @InterceptWith(RateLimitAspect::class, limit=1000, window=3600)
     */
    public function publicApi(string $endpoint) {
        // Generous limits for public API
        return $this->handlePublicRequest($endpoint);
    }
    
    /**
     * @Route("/api/admin/{endpoint:**}")
     * @InterceptWith(RequireAuthAspect::class)
     * @InterceptWith(RateLimitAspect::class, limit=100, window=3600)
     */
    public function adminApi(string $endpoint) {
        // Stricter limits for admin operations
        return $this->handleAdminRequest($endpoint);
    }
}
```

## Performance Optimization

### Route Caching

Canvas automatically caches compiled routes in production:

```php
<?php
// config/cache.php
return [
    'routes' => [
        'enabled' => true,
        'path' => storage_path('cache/routes.php'),
        'ttl' => 3600,
        'warm_up' => true  // Pre-compile routes on deploy
    ]
];
```

### Optimizing Wildcard Routes

Order routes from most specific to least specific for optimal performance:

```php
class OptimizedController extends BaseController {
    
    /**
     * @Route("/files/system/config.json", priority=100)
     */
    public function systemConfig() {
        // Most specific - checked first
        return $this->getSystemConfig();
    }
    
    /**
     * @Route("/files/system/{file:filename}", priority=50)
     */
    public function systemFile(string $file) {
        // More specific than wildcard
        return $this->getSystemFile($file);
    }
    
    /**
     * @Route("/files/{remaining:**}", priority=1)
     */
    public function files(string $remaining) {
        // Least specific - checked last
        return $this->getFile($remaining);
    }
}
```

## Integration Examples

### RESTful API with Full CRUD

```php
<?php
namespace App\Controller\Api;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Models\Article;
use App\Aspects\{RequireAuthAspect, ValidateJsonAspect, CacheAspect};

/**
 * @InterceptWith(RequireAuthAspect::class)
 */
class ArticleApiController extends BaseController {
    
    /**
     * @Route("/api/articles", methods={"GET"})
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function index() {
        $articles = $this->em->findBy(Article::class, ['published' => true]);
        return $this->json($articles);
    }
    
    /**
     * @Route("/api/articles/{id:int}", methods={"GET"})
     * @InterceptWith(CacheAspect::class, ttl=600)
     */
    public function show(int $id) {
        $article = $this->em->find(Article::class, $id);
        
        if (!$article) {
            return $this->notFound('Article not found');
        }
        
        return $this->json($article);
    }
    
    /**
     * @Route("/api/articles", methods={"POST"})
     * @InterceptWith(ValidateJsonAspect::class, schema="article-create")
     */
    public function create() {
        $data = $this->getJsonRequest();
        
        $article = new Article();
        $article->title = $data['title'];
        $article->content = $data['content'];
        $article->author_id = $this->getCurrentUser()->id;
        
        $this->em->persist($article);
        $this->em->flush();
        
        return $this->json($article, 201);
    }
    
    /**
     * @Route("/api/articles/{id:int}", methods={"PUT"})
     * @InterceptWith(ValidateJsonAspect::class, schema="article-update")
     */
    public function update(int $id) {
        $article = $this->em->find(Article::class, $id);
        
        if (!$article) {
            return $this->notFound('Article not found');
        }
        
        $data = $this->getJsonRequest();
        
        if (isset($data['title'])) $article->title = $data['title'];
        if (isset($data['content'])) $article->content = $data['content'];
        
        $this->em->flush();
        
        return $this->json($article);
    }
    
    /**
     * @Route("/api/articles/{id:int}", methods={"DELETE"})
     */
    public function delete(int $id) {
        $article = $this->em->find(Article::class, $id);
        
        if (!$article) {
            return $this->notFound('Article not found');
        }
        
        $this->em->remove($article);
        $this->em->flush();
        
        return $this->json(['message' => 'Article deleted'], 200);
    }
    
    /**
     * @Route("/api/articles/{id:int}/attachments/{path:**}")
     */
    public function attachments(int $id, string $path) {
        $article = $this->em->find(Article::class, $id);
        
        if (!$article) {
            return $this->notFound('Article not found');
        }
        
        return $this->serveAttachment($article, $path);
    }
}
```

### E-commerce Application

```php
<?php
namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Aspects\{RequireAuthAspect, CacheAspect};
use App\Models\{Product, Category, Order};

class ShopController extends BaseController {
    
    /**
     * @Route("/shop")
     * @InterceptWith(CacheAspect::class, ttl=600)
     */
    public function index() {
        $categories = $this->em->findBy(Category::class, ['active' => true]);
        $featuredProducts = $this->em->findBy(Product::class, ['featured' => true]);
        
        return $this->render('shop/index.tpl', compact('categories', 'featuredProducts'));
    }
    
    /**
     * @Route("/shop/categories/{slug:slug}")
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function category(string $slug) {
        $category = $this->em->findOneBy(Category::class, ['slug' => $slug]);
        
        if (!$category) {
            return $this->notFound('Category not found');
        }
        
        $products = $this->em->findBy(Product::class, ['category_id' => $category->id]);
        
        return $this->render('shop/category.tpl', compact('category', 'products'));
    }
    
    /**
     * @Route("/shop/products/{id:int}")
     */
    public function product(int $id) {
        $product = $this->em->find(Product::class, $id);
        
        if (!$product) {
            return $this->notFound('Product not found');
        }
        
        $relatedProducts = $this->em->findBy(Product::class, [
            'category_id' => $product->category_id
        ]);
        
        return $this->render('shop/product.tpl', compact('product', 'relatedProducts'));
    }
    
    /**
     * @Route("/shop/orders/{id:int}")
     * @InterceptWith(RequireAuthAspect::class)
     */
    public function order(int $id) {
        $order = $this->em->find(Order::class, $id);
        
        if (!$order) {
            return $this->notFound('Order not found');
        }
        
        return $this->render('shop/order.tpl', compact('order'));
    }
    
    /**
     * @Route("/shop/downloads/{orderItem:int}/{filename}")
     * @InterceptWith(RequireAuthAspect::class)
     */
    public function download(int $orderItem, string $filename) {
        // Validate user owns this order item and can download
        $item = $this->validateDownloadAccess($orderItem, $filename);
        
        if (!$item) {
            return $this->forbidden('Download not authorized');
        }
        
        // Serve the file (implementation would depend on your file storage)
        return $this->redirect("/protected-downloads/{$filename}");
    }
}
```

## Contributing

We welcome contributions! Here's how you can help improve Canvas:

### Reporting Issues

- Use GitHub issues for bug reports and feature requests
- Include minimal reproduction cases
- Specify Canvas version and PHP version

### Contributing Code

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Follow PSR-12 coding standards
4. Add tests for new functionality
5. Update documentation for new features
6. Submit a pull request

### Contributing Documentation

Documentation improvements are always welcome:
- Fix typos and improve clarity
- Add more examples and use cases
- Improve code comments
- Create tutorials and guides

## License

Canvas is open-sourced software licensed under the [MIT license](LICENSE).