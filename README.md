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

ObjectQuel provides an intuitive way to interact with your data with its powerful entity-based query language:

```php
<?php
// src/Controllers/BlogController.php

namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controllers\BaseController;
use App\Models\Post;
use App\Models\User;

class BlogController extends BaseController {
    
    /**
     * @Route("/posts")
     */
    public function index() {
        // Simple queries using findBy - perfect for basic filtering
        $posts = $this->em->findBy(Post::class, ['published' => true]);

        return $this->render('blog/index.tpl', compact('posts'));
    }
    
    /**
     * @Route("/posts/{id:int}")
     */
    public function show(int $id) {
        // Find individual records by primary key - fastest lookup method
        $post = $this->em->find(Post::class, $id);
                         
        if (!$post) {
            return $this->notFound('Post not found');
        }
        
        return $this->render('blog/show.tpl', compact('post'));
    }
    
    /**
     * @Route("/posts/tech")
     */
    public function techPosts() {
        // Advanced ObjectQuel queries with regex patterns and relationships
        $results = $this->em->executeQuery("
            range of p is App\\Entity\\PostEntity
            range of u is App\\Entity\\UserEntity via p.authorId
            retrieve (p, u.name) where p.title = /^Tech/i
            and p.published = :published
            sort by p.publishedAt desc
        ", [
            'published' => true
        ]);
            
        return $this->render('blog/tech.tpl', compact('results'));
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

use Quellabs\Canvas\Legacy\FileResolverInterface;

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

### Legacy Preprocessing and Limitations

Canvas includes sophisticated preprocessing capabilities to handle legacy PHP files that use common patterns like `header()`, `die()`, and `exit()` functions. Understanding these capabilities and their limitations is crucial for successful legacy integration.

#### Default Configuration

By default, legacy preprocessing is **enabled**. Canvas automatically handles common legacy patterns to maintain proper request flow:

```php
<?php
// public/index.php

$kernel = new Kernel([
    'debug_mode'           => true,
    'legacy_enabled'       => true,
    'legacy_path'          => dirname(__FILE__) . "/../legacy",
    'legacy_preprocessing' => true  // This is the default
]);
```

#### What Legacy Preprocessing Does

When preprocessing is enabled, Canvas automatically transforms legacy PHP code before execution:

**Header Function Conversion**: All calls to `header()` are converted to Canvas's internal header management system, allowing proper HTTP response handling.

**HTTP Response Code Conversion**: All calls to `http_response_code()` are transformed to Canvas's internal response code management, ensuring proper status code handling within the framework's request/response cycle.

**Exit/Die Conversion**: All `die()` and `exit()` calls are converted to Canvas exceptions, preserving the application's control flow instead of terminating the entire process.

```php
<?php
// Original legacy code
header('Content-Type: application/json');
echo json_encode(['data' => $data]);
die(); // This would normally terminate the entire application

// Canvas automatically converts this to maintain flow control
```

This preprocessing ensures that legacy files integrate smoothly with Canvas's request/response cycle without breaking the framework's execution flow.

#### Disabling Legacy Preprocessing

If your legacy application doesn't use `header()`, `die()`, or `exit()` functions, or if you need to disable preprocessing for debugging purposes, you can turn it off:

```php
<?php
// public/index.php

$kernel = new Kernel([
    'debug_mode'           => true,
    'legacy_enabled'       => true,
    'legacy_path'          => dirname(__FILE__) . "/../legacy",
    'legacy_preprocessing' => false  // Disable preprocessing
]);
```

**When to disable preprocessing:**
- Your legacy code doesn't use `header()`, `die()`, or `exit()`
- You're debugging preprocessing-related issues
- You have specific requirements for direct header/exit handling
- Your legacy files are already compatible with Canvas's request flow

#### Important Limitations

While Canvas's legacy preprocessing is powerful, there are important limitations to be aware of:

#### 🚨 Include/Require File Limitations

Canvas preprocessing **only applies to the main legacy file being executed**, not to files that are included or required by that file. This means:

```php
<?php
// legacy/main.php - This file gets preprocessed
header('Content-Type: text/html');  // ✅ Converted by Canvas
die('Stopping here');              // ✅ Converted to Canvas exception (maintains flow)

include 'includes/helper.php';       // ❌ This file is NOT preprocessed
?>
```

```php
<?php
// legacy/includes/helper.php - This file is NOT preprocessed
header('Location: /redirect');  // ❌ May cause issues
die('Stopping execution');     // ❌ Will terminate the entire application (preprocessing disabled)
?>
```

#### Impact on Legacy Applications

This limitation can affect legacy applications in several ways:

**Header Management**: If included files call `header()`, those headers may not be properly managed by Canvas since included files are never preprocessed.

**Flow Control**: If included files call `die()` or `exit()`, they will terminate the entire Canvas application, not just the legacy script, since included files bypass preprocessing entirely. When preprocessing is enabled, Canvas handles these calls properly in the main file but cannot process them in included files.

**Error Handling**: Canvas cannot catch or handle exceptions from `die()`/`exit()` calls in included files that bypass preprocessing.

## Best Practices for Legacy Integration

**Audit Include Files**: Before integrating legacy code, audit all included/required files for `header()`, `die()`, and `exit()` calls.

**Refactor Problematic Includes**: Consider refactoring included files that use these functions:

```php
<?php
// Instead of this in included files:
die('Error occurred');

// Use this pattern:
return false; // Or throw an exception
?>
```

**Test Thoroughly**: Test legacy routes that include other files to ensure they work correctly with Canvas preprocessing.

**Use Error Handling**: Implement proper error handling in legacy files rather than relying on `die()`/`exit()`:

```php
<?php
// legacy/api.php
include 'includes/database.php';

if (!$connection) {
    // Instead of die(), use proper error handling
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    return; // Exit gracefully
}
?>
```

**Gradual Migration**: Use Canvas's legacy support as a migration tool. Gradually move functionality from problematic included files into Canvas controllers:

```php
<?php
// Start migrating problematic includes to Canvas services
$databaseService = canvas('DatabaseService');
$result = $databaseService->query('SELECT * FROM users');
?>
```

## Debugging Legacy Issues

If you encounter issues with legacy file execution:

1. **Enable debug mode** and check Canvas logs for preprocessing warnings
2. **Temporarily disable preprocessing** to isolate the issue
3. **Check included files** for problematic function calls
4. **Use Canvas's error reporting** to identify where issues occur

```php
<?php
// Enable detailed error reporting for legacy debugging
$kernel = new Kernel([
    'debug_mode'           => true,
    'legacy_enabled'       => true,
    'legacy_preprocessing' => true,
    'error_reporting'      => E_ALL
]);
?>
```

Understanding these preprocessing capabilities and limitations will help you successfully integrate Canvas with existing PHP applications while planning an effective migration strategy.

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

## Advanced Features

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

### Route Prefixes with @RoutePrefix

Canvas supports route prefixes through the `@RoutePrefix` annotation, which can be applied at the class level to automatically prefix all routes within that controller. This feature also supports inheritance, allowing parent classes to define prefixes that child classes inherit.

#### Basic Route Prefixes

Apply a route prefix to all methods in a controller:

```php
<?php
namespace App\Controllers;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\RoutePrefix;
use Quellabs\Canvas\Controllers\BaseController;

/**
 * @RoutePrefix("/api/v1")
 */
class ApiController extends BaseController {
    
    /**
     * @Route("/users")
     */
    public function getUsers() {
        // Actual route: /api/v1/users
        return $this->json(['users' => []]);
    }
    
    /**
     * @Route("/users/{id:int}")
     */
    public function getUser(int $id) {
        // Actual route: /api/v1/users/{id}
        return $this->json(['user' => $this->findUser($id)]);
    }
    
    /**
     * @Route("/posts")
     */
    public function getPosts() {
        // Actual route: /api/v1/posts
        return $this->json(['posts' => []]);
    }
}
```

#### Route Prefix Inheritance

Route prefixes are inherited from the entire inheritance chain.

```php
<?php
use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Annotations\RoutePrefix;

/**
 * @RoutePrefix("/api")
 */
abstract class AdminApiController extends BaseController {
    // Base admin functionality
}

class UserManagementController extends AdminApiController {
    
    /**
     * @Route("/users")
     */
    public function listUsers() {
        // Actual route: /api/users
        // Uses the @RoutePrefix("/api") from AdminApiController
        return $this->json(['users' => $this->getAllUsers()]);
    }
    
    /**
     * @Route("/users/{id:int}")
     */
    public function getUser(int $id) {
        // Actual route: /api/users/{id}
        return $this->json(['user' => $this->findUser($id)]);
    }
}
```

#### Benefits of Route Prefixes

**Organization**: Group related routes under logical URL segments without repeating prefixes in every route definition.

**Maintainability**: Change the prefix structure for entire controller hierarchies in one place.

**DRY Principle**: Eliminate repetition of common URL segments across multiple route definitions.

**Versioning**: Easily create versioned APIs by using different prefix hierarchies for different API versions.