# Canvas

[![Packagist](https://img.shields.io/packagist/v/quellabs/canvas.svg)](https://packagist.org/packages/quellabs/canvas)

A modern, lightweight PHP framework that gets out of your way. Write clean controllers with route annotations, query your database with an intuitive ORM, and let contextual containers handle the complexity. **Built to work seamlessly alongside your existing PHP codebase** - modernize incrementally without breaking what already works.

## What Makes Canvas Different

- **🔄 Legacy-First Integration** - Drop into any existing PHP app without breaking existing URLs
- **🎯 Annotation-Based Routing** - Define routes directly in controllers with `@Route` annotations
- **🗄️ ObjectQuel ORM** - Query databases using intuitive, natural PHP syntax
- **📦 Contextual Containers** - Work with interfaces; Canvas resolves implementations by context
- **⚡ Aspect-Oriented Programming** - Add crosscutting concerns without cluttering business logic

## Quick Start

### Installation

```bash
# New project
composer create-project quellabs/canvas-skeleton my-app

# Existing project
composer require quellabs/canvas
```

### Bootstrap (public/index.php)

This bootstrap file is automatically generated when using the skeleton package. If you're not using the skeleton package, you'll need to create this file manually.

```php
<?php
use Quellabs\Canvas\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
```

### Controllers with Route Annotations

Canvas automatically discovers controllers and registers their routes using annotations:

```php
<?php
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controllers\BaseController;

class BlogController extends BaseController {
    
    /**
     * @Route("/")
     */
    public function index() {
        return $this->render('home.tpl');
    }
    
    /**
     * @Route("/posts")
     */
    public function list() {
        $posts = $this->em->findBy(Post::class, ['published' => true]);
        return $this->render('posts.tpl', $posts);
    }

    /**
     * @Route("/posts/{id:int}")
     */
    public function show(int $id) {
        $post = $this->em->find(Post::class, $id);
        return $this->render('post.tpl', $post);
    }
}
```

### ObjectQuel ORM

ObjectQuel lets you query data using syntax that feels natural to PHP developers. Inspired by QUEL (a declarative query language from early relational databases), it bridges traditional database querying with object-oriented programming.

#### Simple Entity Operations

```php
// Find by primary key - fastest lookup method
$user = $this->em->find(User::class, $id);

// Simple filtering using findBy - perfect for basic criteria
$activeUsers = $this->em->findBy(User::class, ['active' => true]);
$recentPosts = $this->em->findBy(Post::class, ['published' => true]);
```

#### Advanced ObjectQuel Queries

For complex queries, ObjectQuel provides a natural language syntax:

```php
// Basic ObjectQuel query
$results = $this->em->executeQuery("
    range of p is App\\Entity\\Post
    retrieve p where p.published = true
    sort by p.publishedAt desc
");

// Queries with relationships and parameters
$techPosts = $this->em->executeQuery("
    range of p is App\\Entity\\Post
    range of u is App\\Entity\\User via p.authorId
    retrieve (p, u.name) where p.title = /^Tech/i
    and p.published = :published
    sort by p.publishedAt desc
", [
    'published' => true
]);
```

#### Key Components

- **`range`** - Creates an alias for an entity class, similar to SQL's `FROM` clause. Think of it as "let p represent App\Entity\Post"
- **`retrieve`** - Functions like SQL's `SELECT`, specifying what data to return. You can retrieve entire entities (`p`) or specific properties (`u.name`)
- **`where`** - Standard filtering conditions, supporting parameters (`:published`) and regular expressions (`/^Tech/i` matches titles starting with "Tech", case-insensitive)
- **`sort by`** - Equivalent to SQL's `ORDER BY` for result ordering
- **`via`** - Establishes relationships between entities using foreign keys (`p.authorId` links posts to users)

#### ObjectQuel Features

- **Readability**: More intuitive than complex Doctrine DQL or QueryBuilder syntax
- **Type Safety**: Entity relationships are validated at query time
- **Parameter Binding**: Safe parameter substitution prevents SQL injection
- **Relationship Traversal**: Easily query across entity relationships with `via` keyword
- **Flexible Sorting**: Multi-column sorting with `sort by field1 asc, field2 desc`

## Legacy Integration

Canvas is designed to work seamlessly alongside existing PHP codebases, allowing you to modernize your applications incrementally without breaking existing functionality. The legacy integration system provides a smooth migration path from traditional PHP applications to Canvas.

### Quick Start with Legacy Code

**Start using Canvas today in your existing PHP application**. No rewrites required - Canvas's intelligent fallthrough system lets you modernize at your own pace.

First, enable legacy support by updating your `public/index.php`:

```php
<?php
// public/index.php
use Quellabs\Canvas\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel([
    'legacy_enabled' => true,          // Enable legacy support
    'legacy_path' => __DIR__ . '/../'  // Path to your existing files
]);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
```

**That's it!** Your existing application now has Canvas superpowers while everything continues to work exactly as before.

### Using Canvas Services in Legacy Files

Now you can immediately start using Canvas services in your existing files:

```php
<?php
// legacy/users.php - existing file, now enhanced with Canvas
use Quellabs\Canvas\Legacy\LegacyBridge;

// Access Canvas services in legacy code
$em = canvas('EntityManager');
$users = $em->findBy(User::class, ['active' => true]);

// Use ObjectQuel for complex queries
$recentUsers = $em->executeQuery("
    range of u is App\\Entity\\User
    retrieve u where u.active = true and u.createdAt > :since
    sort by u.createdAt desc
    limit 10
", ['since' => date('Y-m-d', strtotime('-30 days'))]);

echo "Found " . count($users) . " active users<br>";

foreach ($recentUsers as $user) {
    echo "<h3>{$user->name}</h3>";
    echo "<p>Joined: " . $user->createdAt->format('Y-m-d') . "</p>";
}
```

### How Route Fallthrough Works

Canvas uses an intelligent fallthrough system that tries Canvas routes first, then automatically looks for corresponding legacy PHP files:

```
URL Request: /users/profile
1. Try Canvas route: /users/profile → ❌ Not found in Canvas controllers
2. Try legacy files:
   - legacy/users/profile.php → ✅ Found! Execute this file
   - legacy/users/profile/index.php → Alternative location

Examples:
- `/users` → `legacy/users.php` or `legacy/users/index.php`
- `/admin/dashboard` → `legacy/admin/dashboard.php`
- `/api/data` → `legacy/api/data.php`
```

### Custom File Resolvers

If your legacy application has a different file structure, you can write custom file resolvers:

```php
<?php
// src/Legacy/CustomFileResolver.php
use Quellabs\Canvas\Legacy\FileResolverInterface;

class CustomFileResolver implements FileResolverInterface {
    
    public function resolve(string $path): ?string {
        // Handle WordPress-style routing
        if ($path === '/') {
            return $this->legacyPath . '/index.php';
        }
        
        // Map URLs to custom file structure
        if (str_starts_with($path, '/blog/')) {
            $slug = substr($path, 6);
            return $this->legacyPath . "/wp-content/posts/{$slug}.php";
        }
        
        // Handle custom admin structure
        if (str_starts_with($path, '/admin/')) {
            $adminPath = substr($path, 7);
            return $this->legacyPath . "/backend/modules/{$adminPath}.inc.php";
        }
        
        return null; // Fall back to default behavior
    }
}

// Register with kernel
$kernel->getLegacyHandler()->addResolver(new CustomFileResolver);
```

### Legacy Preprocessing

Canvas includes preprocessing capabilities to handle legacy PHP files that use common patterns like `header()`, `die()`, and `exit()` functions:

```php
<?php
// public/index.php
$kernel = new Kernel([
    'legacy_enabled' => true,
    'legacy_path' => __DIR__ . '/../legacy',
    'legacy_preprocessing' => true  // Default: enabled
]);
```

**What preprocessing does:**
- Converts `header()` calls to Canvas's internal header management
- Transforms `http_response_code()` to Canvas response handling
- Converts `die()` and `exit()` calls to Canvas exceptions (maintains flow control)

**Important limitation:** Preprocessing only applies to the main legacy file, not to included/required files. Files included by legacy scripts may need refactoring if they use these functions.

### Benefits of Legacy Integration

- **🚀 Zero Disruption**: Existing URLs continue to work unchanged
- **🔧 Enhanced Legacy Code**: Add Canvas services (ORM, caching, logging) to legacy files
- **🔄 Flexible Migration**: Start with services, move to controllers, then to full Canvas
- **📈 Immediate Benefits**: Better database abstraction, modern dependency injection, improved error handling

## Advanced Features

### Route Validation & Wildcards

```php
class ProductController extends BaseController {
    
    /**
     * @Route("/products/{id:int}")
     */
    public function show(int $id) {
        // Only matches numeric IDs
    }
    
    /**
     * @Route("/files/{path:**}")
     */
    public function files(string $path) {
        // Matches: /files/css/style.css → path = "css/style.css"
        return $this->serveFile($path);
    }
}
```

**Available validators:** `int`, `alpha`, `alnum`, `slug`, `uuid`, `email`

### Route Prefixes

```php
/**
 * @RoutePrefix("/api/v1")
 */
class ApiController extends BaseController {
    
    /**
     * @Route("/users")  // Actual route: /api/v1/users
     */
    public function users() {
        return $this->json($this->em->findBy(User::class, []));
    }
}
```

### Aspect-Oriented Programming

Canvas provides true AOP for controller methods, allowing you to separate crosscutting concerns from your business logic. Aspects execute at different stages of the request lifecycle.

#### Creating Aspects

Aspects implement interfaces based on when they should execute:

```php
<?php
namespace App\Aspects;

use Quellabs\Contracts\AOP\BeforeAspect;
use Quellabs\Contracts\AOP\AroundAspect;
use Quellabs\Contracts\AOP\AfterAspect;
use Symfony\Component\HttpFoundation\Response;

// Before Aspects - Execute before the method, can stop execution
class RequireAuthAspect implements BeforeAspect {
    public function __construct(private AuthService $auth) {}
    
    public function before(MethodContext $context): ?Response {
        if (!$this->auth->isAuthenticated()) {
            return new RedirectResponse('/login');
        }
        
        return null; // Continue execution
    }
}

// Around Aspects - Wrap the entire method execution
class CacheAspect implements AroundAspect {
    public function around(MethodContext $context, callable $proceed): mixed {
        $key = $this->generateCacheKey($context);
        
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }
        
        $result = $proceed(); // Call the original method
        $this->cache->set($key, $result, $this->ttl);
        return $result;
    }
}

// After Aspects - Execute after the method, can modify response
class AuditLogAspect implements AfterAspect {
    public function after(MethodContext $context, Response $response): void {
        $this->logger->info('Method executed', [
            'controller' => get_class($context->getTarget()),
            'method' => $context->getMethodName(),
            'user' => $this->auth->getCurrentUser()?->id
        ]);
    }
}
```

#### Applying Aspects

**Class-level aspects** apply to all methods in the controller:

```php
/**
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(AuditLogAspect::class)
 */
class UserController extends BaseController {
    // All methods automatically get authentication and audit logging
    
    /**
     * @Route("/users")
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function index() {
        // Gets: RequireAuth + AuditLog (inherited) + Cache (method-level)
        return $this->em->findBy(User::class, ['active' => true]);
    }
}
```

**Method-level aspects** apply to specific methods:

```php
class BlogController extends BaseController {
    
    /**
     * @Route("/posts")
     * @InterceptWith(CacheAspect::class, ttl=600)
     * @InterceptWith(RateLimitAspect::class, limit=100, window=3600)
     */
    public function list() {
        // Method gets caching and rate limiting
        return $this->em->findBy(Post::class, ['published' => true]);
    }
}
```

#### Aspect Parameters

Pass configuration to aspects through annotation parameters:

```php
/**
 * @InterceptWith(CacheAspect::class, ttl=3600, tags={"reports", "admin"})
 * @InterceptWith(RateLimitAspect::class, limit=10, window=60)
 */
public function expensiveReport() {
    // Cached for 1 hour with tags, rate limited to 10 requests per minute
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

#### Execution Order

Aspects execute in a predictable order:
1. **Before Aspects** - Authentication, validation, rate limiting
2. **Around Aspects** - Caching, transactions, timing
3. **After Aspects** - Logging, response modification

#### Inherited Aspects

Build controller hierarchies with shared crosscutting concerns:

```php
/**
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(AuditLogAspect::class)
 */
abstract class AuthenticatedController extends BaseController {
    // Base authenticated functionality
}

/**
 * @InterceptWith(RequireAdminAspect::class)
 * @InterceptWith(RateLimitAspect::class, limit=100)
 */
abstract class AdminController extends AuthenticatedController {
    // Admin-specific functionality - inherits auth + audit
}

class UserController extends AdminController {
    /**
     * @Route("/admin/users")
     */
    public function manage() {
        // Automatically inherits: RequireAuth + AuditLog + RequireAdmin + RateLimit
        return $this->em->findBy(User::class, []);
    }
}
```

### Form Validation

Canvas provides a powerful validation aspect that separates validation concerns from business logic:

```php
<?php
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Validation\ValidateAspect;
use App\Validation\UserValidation;

class UserController extends BaseController {
    
    /**
     * @Route("/users/create", methods={"GET", "POST"})
     * @InterceptWith(ValidateAspect::class, validate=UserValidation::class)
     */
    public function create(Request $request) {
        if ($request->isMethod('POST')) {
            // Check validation results set by ValidateAspect
            if ($request->attributes->get('validation_passed', false)) {
                // Process valid form data
                $user = new User();
                $user->setName($request->request->get('name'));
                $user->setEmail($request->request->get('email'));
                $user->setPassword(password_hash($request->request->get('password'), PASSWORD_DEFAULT));
                
                $this->em->persist($user);
                $this->em->flush();
                
                return $this->redirect('/users');
            }
            
            // Validation failed - render form with errors
            return $this->render('users/create.tpl', [
                'errors' => $request->attributes->get('validation_errors', []),
                'old' => $request->request->all()
            ]);
        }
        
        // Show empty form for GET requests
        return $this->render('users/create.tpl');
    }
}
```

#### Creating Validation Classes

```php
<?php
namespace App\Validation;

use Quellabs\Canvas\Validation\ValidationInterface;
use Quellabs\Canvas\Validation\Rules\NotBlank;
use Quellabs\Canvas\Validation\Rules\Email;
use Quellabs\Canvas\Validation\Rules\Length;

class UserValidation implements ValidationInterface {
    
    public function getRules(): array {
        return [
            'name' => [
                new NotBlank(['message' => 'Name is required']),
                new Length(['min' => 2, 'message' => 'Name must be at least {{min}} characters'])
            ],
            'email' => [
                new NotBlank(['message' => 'Email is required']),
                new Email(['message' => 'Please enter a valid email address'])
            ],
            'password' => [
                new NotBlank(['message' => 'Password is required']),
                new Length(['min' => 8, 'message' => 'Password must be at least {{min}} characters'])
            ]
        ];
    }
}
```

#### Auto-Response for APIs

Enable automatic JSON error responses for API endpoints:

```php
/**
 * @Route("/api/users", methods={"POST"})
 * @InterceptWith(ValidateAspect::class, validate=UserValidation::class, auto_respond=true)
 */
public function createUser(Request $request) {
    // For API requests, validation failures automatically return JSON:
    // {
    //   "message": "Validation failed", 
    //   "errors": {
    //     "email": ["Please enter a valid email address"],
    //     "password": ["Password must be at least 8 characters"]
    //   }
    // }
    
    // This code only runs if validation passes
    $user = $this->createUserFromRequest($request);
    return $this->json(['success' => true, 'user_id' => $user->getId()]);
}
```

### Contextual Services

Use different implementations based on context:

```php
// Different template engines
$twig = $this->container->for('twig')->get(TemplateEngineInterface::class);
$blade = $this->container->for('blade')->get(TemplateEngineInterface::class);

// Different cache backends
$redis = $this->container->for('redis')->get(CacheInterface::class);
$file = $this->container->for('file')->get(CacheInterface::class);
```

## CLI Commands

Canvas includes a command-line interface called Sculpt for managing your application:

### Route Management

```bash
# View all registered routes in your application
./vendor/bin/sculpt route:list
./vendor/bin/sculpt route:list --controller=UserController

# Test which controller and method handles a specific URL path
./vendor/bin/sculpt route:match /users/123
./vendor/bin/sculpt route:match GET /users/123

# Clear route cache
./vendor/bin/sculpt route:clear-cache
```

### Asset Publishing

Canvas provides a powerful asset publishing system to deploy configuration files, templates, and other resources:

```bash
# List all available publishers
./vendor/bin/sculpt canvas:publish --list

# Publish assets using a specific publisher
./vendor/bin/sculpt canvas:publish package:production

# Overwrite existing files
./vendor/bin/sculpt canvas:publish package:production --overwrite

# Skip confirmation prompts (for automated deployments)
./vendor/bin/sculpt canvas:publish package:production --force

# Show help for a specific publisher
./vendor/bin/sculpt canvas:publish package:production --help
```

**Key features:**
- **Safe publishing** with automatic backup creation
- **Transaction rollback** if operations fail
- **Interactive confirmation** with preview of changes
- **Extensible publisher system** for custom deployment needs

## Configuration

Canvas works with zero configuration, but you can customize when needed:

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

## Why Canvas?

- **Legacy Integration**: Works with existing PHP without breaking anything
- **Zero Config**: Start coding immediately with sensible defaults
- **Clean Code**: Annotations keep logic close to implementation
- **Performance**: Lazy loading, route caching, efficient matching
- **Flexibility**: Contextual containers and composable aspects
- **Growth**: Scales from simple sites to complex applications

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

## License

Canvas is open-sourced software licensed under the [MIT license](LICENSE).