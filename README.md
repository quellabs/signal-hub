# Canvas

[![Packagist](https://img.shields.io/packagist/v/quellabs/canvas.svg)](https://packagist.org/packages/quellabs/canvas)

A modern, lightweight PHP framework that gets out of your way. Write clean controllers with route annotations, query your database with an intuitive ORM, and let contextual containers handle the complexity. **Built to work seamlessly alongside your existing PHP codebase** - modernize incrementally without breaking what already works.

## What Makes Canvas Different

Canvas combines four powerful concepts with **zero-friction legacy integration** to create a framework that feels natural to work with:

**🔄 Legacy-First Integration** - Drop Canvas into any existing PHP application. Your legacy URLs keep working while you gradually modernize with Canvas services and controllers.

**🎯 Annotation-Based Routing** - Define routes directly in your controllers using `@Route` annotations. No separate route files to maintain.

**🗄️ ObjectQuel ORM** - Query your database using an intuitive, purpose-built query language that feels like natural PHP.

**📦 Contextual Containers** - Work with interfaces directly. Canvas intelligently resolves the right implementation based on context.

**⚡ Aspect-Oriented Programming** - Add crosscutting concerns like caching, authentication, and logging without cluttering your business logic.

## Installation

```bash
composer require quellabs/canvas
```

Or create a new project using the Canvas skeleton:

```bash
composer create-project quellabs/canvas-skeleton my-canvas-app
cd my-canvas-app
```

**For existing applications:**

```bash
cd my-existing-app
composer require quellabs/canvas
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

## Canvas in Action

Here's what a complete Canvas application looks like, showcasing all its core features:

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

Now you can immediately start using Canvas services in your existing files:

```php
<?php
// legacy/users.php - This file is accessed when visiting /users
// Note: Canvas services only available when Canvas loads this file (not when run directly)
use Quellabs\Canvas\Legacy\LegacyBridge;

// Method 1: Using LegacyBridge directly
$users = LegacyBridge::get('EntityManager')->findBy(User::class, ['active' => true]);
$config = LegacyBridge::get('config');
$container = LegacyBridge::container();

// Method 2: Using the global canvas() function (simpler)
$em = canvas('EntityManager');
$posts = canvas('EntityManager')->findBy(Post::class, ['published' => true]);

// Count users using ObjectQuel
$allUsers = $em->findBy(User::class, []);
$totalUsers = count($allUsers);

echo "Found " . count($users) . " active users out of " . $totalUsers . " total users<br>";

foreach ($posts as $post) {
    echo "<h2>{$post->title}</h2>";
    echo "<p>" . substr($post->content, 0, 200) . "...</p>";
}
?>
```

### How It Works

#### Route Fallthrough System

Canvas uses an intelligent fallthrough system that tries Canvas routes first, then automatically looks for corresponding legacy PHP files:

```
URL Request: /users/profile
1. Try Canvas route: /users/profile → ❌ Not found in Canvas controllers
2. Try legacy files:
   - legacy/users/profile.php → ✅ Found! Execute this file
   - legacy/users/profile/index.php → Alternative location
   
For example:
- `/users` → `legacy/users.php`
- `/users` → `legacy/users/index.php`
- `/admin/dashboard` → `legacy/admin/dashboard.php`
- `/admin/dashboard` → `legacy/admin/dashboard/index.php`
```

### Custom File Resolvers

If your legacy application has a different file structure than Canvas's default conventions, you can write custom file resolvers to handle your specific patterns:

```php
<?php
// src/Legacy/CustomFileResolver.php

use Quellabs\Canvas\Legacy\Contracts\FileResolverInterface;

class CustomFileResolver implements FileResolverInterface {
    
    public function resolve(string $path): ?string {
        // Your custom logic for resolving legacy file paths
        
        // Example: Handle WordPress-style routing
        if ($path === '/') {
            return $this->legacyPath . '/index.php';
        }
        
        // Example: Map URLs to custom file structure
        if (str_starts_with($path, '/blog/')) {
            $slug = substr($path, 6); // Remove /blog/
            return $this->legacyPath . "/wp-content/posts/{$slug}.php";
        }
        
        // Example: Handle custom admin structure
        if (str_starts_with($path, '/admin/')) {
            $adminPath = substr($path, 7); // Remove /admin/
            return $this->legacyPath . "/backend/modules/{$adminPath}.inc.php";
        }
        
        // Fall back to default behavior
        return null;
    }
}
```

Register your custom resolver with the kernel:

```php
<?php
// public/index.php

$kernel = new Kernel([
    'legacy_enabled' => true,
    'legacy_path' => __DIR__ . '/../legacy/'
]);

$kernel->getLegacyHandler()->addResolver(new CustomFileResolver);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
```

This allows Canvas to work with any legacy file structure, making it compatible with existing WordPress sites, custom MVC frameworks, or any other PHP application structure.

### Real-World Examples

#### Legacy Admin Dashboard

```php
<?php
// legacy/admin/dashboard.php
use Quellabs\Canvas\Legacy\LegacyBridge;

$em = canvas('EntityManager');

// Get data using Canvas ORM
$recentUsers = $em->findBy(User::class, ['active' => true]);
$totalPosts = count($em->findBy(Post::class, []));
?>
<h1>Admin Dashboard</h1>
<p>Active Users: <?= count($recentUsers) ?></p>
<p>Total Posts: <?= $totalPosts ?></p>
```

#### Legacy API Endpoint

```php
<?php
// legacy/api/users.php
use Quellabs\Canvas\Legacy\LegacyBridge;

header('Content-Type: application/json');

$em = canvas('EntityManager');
$users = $em->findBy(User::class, ['active' => true]);

echo json_encode([
    'success' => true,
    'count' => count($users),
    'users' => array_map(fn($u) => ['id' => $u->id, 'name' => $u->name], $users)
]);
?>
```

### Error Pages

Canvas supports custom error pages for legacy applications:

#### Custom 404 Page

Create a custom 404 page:

```php
<?php
// legacy/404.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Page Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin: 50px; }
        .error-box { background: #f8f9fa; padding: 40px; border-radius: 8px; display: inline-block; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>404 - Page Not Found</h1>
        <p>Sorry, the page you're looking for doesn't exist.</p>
        <p><a href="/">Return to Homepage</a></p>
    </div>
</body>
</html>
```

#### Custom Error Page

Create a custom 500 error page:

```php
<?php
// legacy/500.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin: 50px; }
        .error-box { background: #f8f9fa; padding: 40px; border-radius: 8px; display: inline-block; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>500 - Server Error</h1>
        <p>Something went wrong on our end. Please try again later.</p>
        <p>If the problem persists, please contact support.</p>
        <p><a href="/">Return to Homepage</a></p>
    </div>
</body>
</html>
```

### Benefits of Legacy Integration

#### 🚀 **Zero Disruption**
- Existing URLs continue to work unchanged
- Legacy code runs without modification
- Gradual migration at your own pace

#### 🔧 **Enhanced Legacy Code**
- Add Canvas services (ORM, caching, logging) to legacy files
- Apply modern patterns without rewriting everything
- Improved performance through Canvas optimizations

#### 🔄 **Flexible Migration**
- Start with services, move to controllers, then to full Canvas
- Mix and match Canvas and legacy code as needed
- Roll back changes easily during transition

#### 📈 **Immediate Benefits**
- Better database abstraction through ObjectQuel ORM
- Access to Canvas configuration system
- Modern dependency injection
- Improved error handling and debugging

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
```

**Wildcard Types:**
- `{filename}` - Named parameter (matches single path segment)
- `{path:**}` - Named multi-segment wildcard (captures remaining path)
- `{content:.*}` - Alternative syntax for named multi-segment wildcard

Note: All route variables are automatically injected as method parameters.

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

## Inherited Annotations

Canvas supports automatic inheritance of class-level annotations in derived classes, allowing you to build powerful controller hierarchies with shared crosscutting concerns. This feature enables clean separation of concerns and reduces code duplication across related controllers.

### How Annotation Inheritance Works

When a controller extends another controller, it automatically inherits all class-level annotations from its parent class. This inheritance is deep - derived classes inherit from their entire ancestry chain, not just their immediate parent.

```php
<?php
namespace App\Controllers\Base;

use Quellabs\Canvas\Annotations\InterceptWith;
use Quellabs\Canvas\Controllers\BaseController;
use App\Aspects\RequireAuthAspect;
use App\Aspects\AuditLogAspect;

/**
 * Base controller for all authenticated areas
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(AuditLogAspect::class)
 */
abstract class AuthenticatedController extends BaseController {
    
    protected function getCurrentUser() {
        return $this->container->get('auth')->getCurrentUser();
    }
}
```

Now any controller extending `AuthenticatedController` automatically gets authentication and audit logging:

```php
<?php
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Controllers\Base\AuthenticatedController;
use App\Aspects\CacheAspect;

class UserController extends AuthenticatedController {
    
    /**
     * @Route("/users")
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function index() {
        // Automatically gets: RequireAuth + AuditLog + Cache
        $users = $this->em->findBy(User::class, ['active' => true]);
        return $this->render('users/index.tpl', compact('users'));
    }
    
    /**
     * @Route("/users/{id:int}")
     */
    public function show(int $id) {
        // Automatically gets: RequireAuth + AuditLog (inherited from parent)
        $user = $this->em->find(User::class, $id);
        return $this->render('users/show.tpl', compact('user'));
    }
}
```

### Multi-Level Inheritance

Annotation inheritance works through multiple levels of inheritance, allowing you to create sophisticated controller hierarchies:

```php
<?php
namespace App\Controllers\Base;

use Quellabs\Canvas\Annotations\InterceptWith;
use App\Aspects\RequireAuthAspect;
use App\Aspects\AuditLogAspect;

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
    // Admin-specific functionality
}

/**
 * @InterceptWith(RequireSuperAdminAspect::class)
 * @InterceptWith(SecurityAuditAspect::class)
 */
abstract class SuperAdminController extends AdminController {
    // Super admin functionality
}
```

A controller extending `SuperAdminController` automatically inherits all aspects from the entire chain:

```php
<?php
namespace App\Controllers\Admin;

use Quellabs\Canvas\Annotations\Route;
use App\Controllers\Base\SuperAdminController;

class SystemController extends SuperAdminController {
    
    /**
     * @Route("/admin/system/settings")
     */
    public function settings() {
        // Automatically gets all inherited aspects:
        // - RequireAuthAspect (from AuthenticatedController)
        // - AuditLogAspect (from AuthenticatedController)  
        // - RequireAdminAspect (from AdminController)
        // - RateLimitAspect (from AdminController)
        // - RequireSuperAdminAspect (from SuperAdminController)
        // - SecurityAuditAspect (from SuperAdminController)
        
        return $this->render('admin/system/settings.tpl');
    }
}
```

### Combining Inherited and Local Annotations

Child classes can add their own class-level annotations that combine with inherited ones:

```php
<?php
namespace App\Controllers\Api;

use Quellabs\Canvas\Annotations\InterceptWith;
use App\Controllers\Base\AuthenticatedController;
use App\Aspects\ContentNegotiationAspect;
use App\Aspects\CorsAspect;

/**
 * @InterceptWith(ContentNegotiationAspect::class)
 * @InterceptWith(CorsAspect::class)
 */
class ApiController extends AuthenticatedController {
    
    /**
     * @Route("/api/users")
     */
    public function users() {
        // Gets: RequireAuth + AuditLog (inherited) + ContentNegotiation + Cors (local)
        $users = $this->em->findBy(User::class, []);
        return $this->json($users);
    }
}
```

### Method-Level Annotation Inheritance

Method-level annotations are combined with all inherited class-level annotations:

```php
<?php
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Controllers\Base\AuthenticatedController;
use App\Aspects\CacheAspect;
use App\Aspects\RateLimitAspect;

class ReportController extends AuthenticatedController {
    
    /**
     * @Route("/reports/dashboard")
     * @InterceptWith(CacheAspect::class, ttl=600)
     */
    public function dashboard() {
        // Execution order:
        // 1. RequireAuthAspect (inherited from AuthenticatedController)
        // 2. AuditLogAspect (inherited from AuthenticatedController)
        // 3. CacheAspect (method-level)
        
        return $this->generateDashboard();
    }
    
    /**
     * @Route("/reports/export/{format:alpha}")
     * @InterceptWith(RateLimitAspect::class, limit=5, window=3600)
     * @InterceptWith(CacheAspect::class, ttl=1800)
     */
    public function export(string $format) {
        // Gets inherited aspects plus method-specific rate limiting and caching
        return $this->exportReport($format);
    }
}
```

### Practical Examples

#### Building a RESTful API Hierarchy

```php
<?php
namespace App\Controllers\Base;

use Quellabs\Canvas\Annotations\InterceptWith;
use App\Aspects\ContentNegotiationAspect;
use App\Aspects\CorsAspect;
use App\Aspects\RateLimitAspect;

/**
 * Base for all API controllers
 * @InterceptWith(ContentNegotiationAspect::class)
 * @InterceptWith(CorsAspect::class)
 * @InterceptWith(RateLimitAspect::class, limit=1000)
 */
abstract class ApiController extends BaseController {
    
    protected function jsonResponse($data, int $status = 200) {
        return $this->json($data, $status);
    }
}

/**
 * Base for authenticated API endpoints
 * @InterceptWith(RequireAuthAspect::class)
 * @InterceptWith(JwtValidationAspect::class)
 */
abstract class AuthenticatedApiController extends ApiController {
    // Inherits: ContentNegotiation + Cors + RateLimit + RequireAuth + JwtValidation
}

/**
 * Base for admin API endpoints
 * @InterceptWith(RequireAdminAspect::class)
 * @InterceptWith(SecurityAuditAspect::class)
 */
abstract class AdminApiController extends AuthenticatedApiController {
    // Inherits all above plus: RequireAdmin + SecurityAudit
}
```

Now implementing specific API controllers is clean and focused:

```php
<?php
namespace App\Controllers\Api;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\InterceptWith;
use App\Controllers\Base\AdminApiController;
use App\Aspects\CacheAspect;

class UserApiController extends AdminApiController {
    
    /**
     * @Route("/api/admin/users", methods={"GET"})
     * @InterceptWith(CacheAspect::class, ttl=300)
     */
    public function list() {
        // Automatically gets all inherited aspects plus caching
        $users = $this->em->findBy(User::class, []);
        return $this->jsonResponse($users);
    }
    
    /**
     * @Route("/api/admin/users", methods={"POST"})
     * @InterceptWith(ValidateJsonAspect::class, schema="user-create")
     */
    public function create() {
        // Gets inherited aspects plus JSON validation
        $data = $this->getJsonRequest();
        $user = $this->createUser($data);
        return $this->jsonResponse($user, 201);
    }
}
```

### Annotation Execution Order

When multiple annotation sources are present, Canvas executes them in a specific order to ensure predictable behavior:

1. **Inherited class-level annotations** (from parent to child in inheritance order)
2. **Local class-level annotations** (defined on the current class)
3. **Method-level annotations** (defined on the specific method)

```php
<?php
// Parent class annotations execute first
/**
 * @InterceptWith(ParentAspect1::class)
 * @InterceptWith(ParentAspect2::class)
 */
abstract class ParentController extends BaseController {}

// Child class annotations execute second
/**
 * @InterceptWith(ChildAspect1::class)
 * @InterceptWith(ChildAspect2::class)  
 */
class ChildController extends ParentController {
    
    /**
     * @Route("/example")
     * @InterceptWith(MethodAspect1::class)
     * @InterceptWith(MethodAspect2::class)
     */
    public function example() {
        // Execution order:
        // 1. ParentAspect1, ParentAspect2 (inherited)
        // 2. ChildAspect1, ChildAspect2 (local class)  
        // 3. MethodAspect1, MethodAspect2 (method)
    }
}
```

### Benefits of Annotation Inheritance

**DRY Principle**: Define common aspects once in base controllers rather than repeating them across multiple controllers.

**Maintainable Architecture**: Change security or logging policies in one place and have them apply across entire controller hierarchies.

**Flexible Composition**: Mix and match inherited aspects with method-specific aspects for precise control.

**Clear Separation of Concerns**: Base controllers handle crosscutting concerns while derived controllers focus on business logic.

**Simplified Testing**: Test aspects once in base controllers rather than in every implementation.

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
    public function after(MethodContext $context, Response $response): void {
        $this->logger->info('Method executed', [
            'controller' => get_class($context->getTarget()),
            'method' => $context->getMethodName(),
            'user' => $this->auth->getCurrentUser()?->id
        ]);
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

### Route Cache Management

Canvas provides commands to manage route caching for optimal performance:

#### Clear Route Cache

Clear the compiled route cache to force route re-discovery:

```bash
./vendor/bin/sculpt route:clear_cache
```

This is useful when:
- You've added new routes that aren't being recognized
- Route changes aren't being reflected in your application
- You're experiencing routing issues after deploying changes
- You want to force a fresh route compilation

#### Debug Mode Configuration

For development environments, you can disable route caching entirely by setting debug mode in your application configuration:

```php
<?php
// config/app.php

return [
    'debug_mode' => true,  // Disables route caching
    // other configuration options...
];
```

When `debug_mode` is enabled:
- Routes are discovered and compiled on every request
- Changes to route annotations are immediately reflected
- No need to manually clear route cache during development
- Performance is slightly reduced but development experience is improved

**Note**: Always ensure `debug_mode` is set to `false` in production environments to maintain optimal performance with route caching enabled.

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

**For Legacy Applications**: Drop into any existing PHP codebase without breaking anything. Start using modern patterns immediately while keeping everything working.

**For Rapid Development**: Start coding immediately with zero configuration. Routes, ORM, dependency injection, and AOP work out of the box.

**For Clean Code**: Annotation-based routes and aspects keep logic close to implementation. ObjectQuel queries read like natural language.

**For Flexibility**: Contextual containers and composable aspects let you use different implementations without complex configuration.

**For Growth**: Modular architecture scales from simple websites to complex applications with enterprise-grade crosscutting concerns.

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