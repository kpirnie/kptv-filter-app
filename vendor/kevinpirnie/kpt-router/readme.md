# KPT Router

[![Build Main](https://img.shields.io/github/actions/workflow/status/kpirnie/kp-router/ci.yml?branch=main&label=Main&logoColor=white&logo=github&labelColor=000&style=for-the-badge)](https://github.com/kpirnie/kp-router/actions?query=workflow%3A%22CI%22+branch%3Amain)
[![GitHub Issues](https://img.shields.io/github/issues/kpirnie/kp-router?style=for-the-badge&logo=github&color=006400&logoColor=white&labelColor=000)](https://github.com/kpirnie/kp-router/issues)
[![Last Commit](https://img.shields.io/github/last-commit/kpirnie/kp-router?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHJlY3QgeD0iMyIgeT0iNC41IiB3aWR0aD0iMTgiIGhlaWdodD0iMTYuNSIgcng9IjIiLz48bGluZSB4MT0iMyIgeTE9IjkuNSIgeDI9IjIxIiB5Mj0iOS41Ii8+PGxpbmUgeDE9IjgiIHkxPSIyLjUiIHgyPSI4IiB5Mj0iNi41Ii8+PGxpbmUgeDE9IjE2IiB5MT0iMi41IiB4Mj0iMTYiIHkyPSI2LjUiLz48L3N2Zz4=)](https://github.com/kpirnie/kp-router/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)
[![PHP](https://img.shields.io/badge/Min.-php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![Packagist](https://img.shields.io/packagist/v/kevinpirnie/kpt-router?style=for-the-badge&logo=packagist&logoColor=white&color=F28D1A&labelColor=000&label=Packagist)](https://packagist.org/packages/kevinpirnie/kpt-router)
[![Kevin Pirnie](https://img.shields.io/badge/-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

A comprehensive PHP routing library with middleware support, rate limiting, view rendering, and controller resolution capabilities.

## Features

- **HTTP Method Support**: Full support for GET, POST, PUT, PATCH, DELETE, HEAD, and OPTIONS methods
- **Middleware Pipeline**: Global and route-specific middleware with execution control
- **Rate Limiting**: Built-in rate limiting with Redis and file-based storage backends
- **View Rendering**: Template rendering system with data sharing and caching
- **Controller Resolution**: Automatic controller instantiation and method calling
- **Route Parameters**: Dynamic route parameters with named capture groups
- **Error Handling**: Comprehensive error handling and logging
- **Caching**: Built-in caching support for views and controllers
- **Method Override**: Support for HTTP method override in forms

## Requirements

- PHP 8.4 or higher
- Redis extension (optional, for Redis-based rate limiting)

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-router
```

## Web Server Configuration

For the router to work properly, you need to configure your web server to redirect all requests to your main PHP file (usually `index.php`). Here are the configurations for popular web servers:

### Apache (.htaccess)

Create an `.htaccess` file in your document root:

```apache
RewriteEngine On

# Handle Angular and other client-side routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Optional: Redirect trailing slashes
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)/$ /$1 [R=301,L]

# Security headers (optional)
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
```

### Nginx

Add this to your Nginx server block:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your/app;
    index index.php;

    # Route all requests to index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handler
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Adjust PHP version
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security: Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(vendor|tmp|cache)/ {
        deny all;
    }
}
```

### IIS (web.config)

For Windows IIS servers, create a `web.config` file:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Main Rule" stopProcessing="true">
                    <match url=".*" />
                    <conditions logicalGrouping="MatchAll">
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
```

### Built-in PHP Server (Development)

For development, you can use PHP's built-in server:

```bash
# From your app directory
php -S localhost:8000 -t . index.php
```

Or create a simple router file (`router.php`):

```php
<?php
// router.php for PHP built-in server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route everything else to index.php
require_once __DIR__ . '/index.php';
```

Then run:
```bash
php -S localhost:8000 router.php
```

## Basic Usage

### Constructor Parameters

```php
new Router($basePath = '', $appPath = '')
```

- **`$basePath`** (string): URL prefix for all routes (e.g., `/api/v1`, `/admin`)
- **`$appPath`** (string): File system path to your application root. Defaults to `getcwd()`

### Directory Structure

The router expects this directory structure:
```
your-app/
├── views/           # Templates (auto-detected as {appPath}/views)
├── tmp/            # Cache and rate limiting data (auto-created)
│   └── kpt_rate_limits/
├── vendor/         # Composer dependencies
└── index.php       # Your app entry point
```

**Note:** The router will automatically create the `tmp/kpt_rate_limits` directory if it doesn't exist. Ensure your application has write permissions to the app directory.

### Creating a Router

```php
<?php
use KPT\Router;

// Create router instance
$router = new Router('/api/v1', '/path/to/your/app'); // Optional base path and app path
// OR
$router = new Router('', __DIR__); // No URL prefix, set app path to current directory
// OR  
$router = new Router(); // Uses current working directory as app path

// Basic route registration
$router->get('/', function() {
    return 'Hello World!';
});

$router->post('/users', function() {
    return 'Create user';
});

// Route with parameters
$router->get('/users/{id}', function($id) {
    return "User ID: $id";
});

// Dispatch the router
$router->dispatch();
```

### Array-Based Route Registration

```php
$routes = [
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'HomeController@index',
        'middleware' => ['auth', 'throttle'],
        'should_cache' => true,
        'cache_length' => 3600
    ],
    [
        'method' => 'POST',
        'path' => '/users',
        'handler' => 'UserController@store',
        'middleware' => ['auth']
    ],
    [
        'method' => 'GET',
        'path' => '/profile/{id}',
        'handler' => 'view:profile.html',
        'data' => ['title' => 'User Profile']
    ]
];

$router->registerRoutes($routes);
```

## Advanced Features

### Middleware

#### Global Middleware

```php
// Add global middleware
$router->addMiddleware(function() {
    // Authentication check
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo 'Unauthorized';
        return false; // Stop execution
    }
    return true; // Continue
});
```

#### Named Middleware

```php
// Register middleware definitions
$router->registerMiddlewareDefinitions([
    'auth' => function() {
        return isset($_SESSION['user']);
    },
    'admin' => function() {
        return $_SESSION['user']['role'] === 'admin';
    },
    'throttle' => function() {
        // Rate limiting logic
        return true;
    }
]);

// Use in routes
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/admin',
        'handler' => 'AdminController@dashboard',
        'middleware' => ['auth', 'admin']
    ]
]);
```

### Rate Limiting

#### Enable Rate Limiting

```php
// With Redis (preferred)
$router->enableRateLimiter([
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => 'your_redis_password', // optional
    'database' => 1, // optional, default 1
    'prefix' => 'myapp' // optional, default 'kpt_router'
]);

// File-based fallback is automatic if Redis unavailable
```

#### Configure Rate Limits

Rate limiting is applied globally with default settings:
- **Limit**: 100 requests
- **Window**: 60 seconds
- **Storage**: Auto-detect (Redis preferred, file fallback)

Rate limiting runs before global middleware, so rejected requests never reach your auth, database, or session setup.

#### Behind a Proxy or Load Balancer

By default the client IP is `REMOTE_ADDR`. If the app sits behind a reverse proxy, load balancer, or Docker network, set the trusted proxies so `X-Forwarded-For` is honored. Otherwise every client shares the proxy's IP and its rate limit bucket.

```php
Router::setTrustedProxies(['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1']);
```

`X-Forwarded-For` is only read when the request comes from a trusted proxy. It is walked right to left, and the first untrusted address is used as the client IP.

### View Rendering

#### Set Views Directory

```php
// Views directory is automatically set to {appPath}/views
// You can override it if needed:
$router->setViewsPath('/custom/path/to/views');
```

#### Render Views

```php
// Direct view rendering
$router->get('/home', function() use ($router) {
    return $router->view('home.php', [
        'title' => 'Welcome',
        'user' => $_SESSION['user']
    ]);
});

// String-based view handler
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/about',
        'handler' => 'view:about.html',
        'data' => ['title' => 'About Us']
    ]
]);
```

#### Share Data with All Views

```php
$router->share('site_name', 'My Website');
$router->share([
    'version' => '1.0.0',
    'environment' => 'production'
]);
```

### Controller Resolution

#### Basic Controller Usage

```php
// Controller format: ClassName@methodName
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
$router->get('/users/{id}', 'UserController@show');
```

#### Array-Based Controller Routes

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/dashboard',
        'handler' => 'controller:DashboardController@index',
        'middleware' => ['auth'],
        'should_cache' => true
    ]
]);
```

### Caching

#### View Caching

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/heavy-page',
        'handler' => 'view:heavy-page.php',
        'should_cache' => true,
        'cache_length' => 7200 // 2 hours
    ]
]);
```

#### Controller Caching

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/api/stats',
        'handler' => 'StatsController@getData',
        'should_cache' => true,
        'cache_length' => 900 // 15 minutes
    ]
]);
```

#### Cache Rules

- Only `GET` and `HEAD` requests are cached
- The cache key varies by the query string
- Requests carrying a session cookie are not cached unless the route has a `cache_vary` callback
- A `POST` to the route purges its cached entry

#### Varying the Cache

Use `cache_vary` to cache per user, role, or anything else. Its return value is added to the cache key, and it lets requests with a session cookie be cached.

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/dashboard',
        'handler' => 'view:dashboard.php',
        'should_cache' => true,
        'cache_vary' => fn() => $_SESSION['user_id'] ?? 0
    ]
]);
```

#### Purging the Cache

The `?cachedel` query string purges a route's cache only when the route's `cache_delete` callback returns `true`. Without it, `?cachedel` is ignored.

```php
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/heavy-page',
        'handler' => 'view:heavy-page.php',
        'should_cache' => true,
        'cache_delete' => fn() => ($_SESSION['role'] ?? '') === 'admin'
    ]
]);
```

### Error Handling

#### Custom 404 Handler

```php
$router->notFound(function() {
    http_response_code(404);
    return '<h1>Page Not Found</h1><p>The requested page could not be found.</p>';
});
```

## Route Parameters

### Named Parameters

```php
$router->get('/users/{id}/posts/{slug}', function($id, $slug) {
    return "User $id, Post: $slug";
});
```

Route matching is case-sensitive: `/Users/5` does not match `/users/{id}`.

### Getting Current Route Information

```php
$router->get('/current-route', function() {
    $route = Router::getCurrentRoute();
    return json_encode([
        'method' => $route->method,
        'path' => $route->path,
        'params' => $route->params,
        'matched' => $route->matched
    ]);
});
```

## Utility Methods

### Get User IP Address

```php
$userIp = Router::getUserIp();
// Honors X-Forwarded-For only from proxies set with Router::setTrustedProxies()
```

### Get Current URI

```php
$currentUri = Router::getUserUri();
```

### Path Sanitization

```php
$cleanPath = Router::sanitizePath('/path//with///slashes/');
// Result: /path/with/slashes
```

## Configuration

### Environment Setup

No constants are required. The Redis rate limit key prefix and database are set with the `prefix` and `database` keys passed to `enableRateLimiter()`.

## Example Application

```php
<?php
require_once 'vendor/autoload.php';

use KPT\Router;

// Initialize router with app path
$router = new Router('', __DIR__); // No URL prefix, app runs from current directory

// Views directory is automatically set to __DIR__ . '/views'
// You can override if needed: $router->setViewsPath(__DIR__ . '/custom/views');

// Share common data
$router->share('app_name', 'My Application');

// Enable rate limiting
$router->enableRateLimiter();

// Add authentication middleware
$router->registerMiddleware('auth', function() {
    session_start();
    return isset($_SESSION['user_id']);
});

// Register routes
$router->registerRoutes([
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'view:home.php',
        'should_cache' => true
    ],
    [
        'method' => 'GET',
        'path' => '/dashboard',
        'handler' => 'DashboardController@index',
        'middleware' => ['auth']
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{id}',
        'handler' => 'UserController@show',
        'middleware' => ['auth']
    ]
]);

// Set 404 handler
$router->notFound(function() {
    return '<h1>404 - Page Not Found</h1>';
});

// Dispatch
$router->dispatch();
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Author

**Kevin Pirnie** - [iam@kevinpirnie.com](mailto:iam@kevinpirnie.com)