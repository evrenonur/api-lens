# API Lens

**Auto-generated, interactive API documentation for Laravel.**

Zero config. Real-time testing. Modern Vue 3 UI. OpenAPI export.

---

## Features

- **Zero Configuration** — Drop in, visit `/api-lens`, done
- **Auto Route Scanning** — Discovers all API routes, controllers, validation rules
- **Smart Rule Extraction** — Parses `FormRequest`, inline `$request->validate()`, and controller rules
- **Response Schema Detection** — Analyzes `JsonResource` / `ResourceCollection` classes automatically
- **Path Parameter Analysis** — Detects route model binding types and constraints
- **PHPDoc Integration** — Reads `@api-lens-group`, `@api-lens-auth`, `@deprecated` annotations
- **Live API Testing** — Send requests directly from the browser, see response + SQL queries + memory
- **Code Snippets** — Auto-generated cURL, JavaScript (fetch/axios), Python, PHP (Guzzle/HTTP) snippets
- **Human-Readable Rules** — Transforms `required|string|max:255` into plain English
- **Example Generation** — Smart request body examples based on field names and rules
- **Rate Limit Info** — Extracts throttle middleware configuration
- **OpenAPI 3.1.0 Export** — Full spec export for Swagger UI, Postman, etc.
- **Postman Collection Export** — Direct Postman v2.1 collection generation
- **API Version Diff** — Compare endpoints between versions, generate changelogs
- **Dark Mode** — Beautiful dark/light theme with system preference detection
- **Keyboard Shortcuts** — `Ctrl+K` search, arrow navigation, Enter to select
- **Debug Metrics** — SQL query count/time, memory usage, execution time per request

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require api-lens/api-lens --dev
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=api-lens-config
```

Visit your app at:

```
http://your-app.test/api-lens
```

That's it!

## Configuration

```php
// config/api-lens.php

return [
    'enabled' => env('API_LENS_ENABLED', true),

    'path' => 'api-lens',

    'middleware' => ['web'],

    'include_patterns' => ['api/*'],

    'exclude_patterns' => [
        'sanctum/*',
        '_ignition/*',
        'telescope/*',
        'horizon/*',
        'api-lens/*',
    ],

    'auth' => [
        'enabled' => false,
        'middleware' => [],
    ],

    'features' => [
        'live_testing' => true,
        'code_snippets' => true,
        'openapi_export' => true,
        'debug_metrics' => true,
        'example_generation' => true,
    ],

    'code_snippets' => [
        'languages' => ['curl', 'javascript', 'python', 'php', 'axios', 'fetch', 'guzzle', 'http'],
    ],

    'cache' => [
        'enabled' => false,
        'ttl' => 3600,
    ],
];
```

## PHPDoc Annotations

Add annotations to your controller methods for richer documentation:

```php
/**
 * @api-lens-group Users
 */
class UserController extends Controller
{
    /**
     * List all users.
     *
     * Returns a paginated list of users with optional filtering.
     *
     * @api-lens-auth bearer
     * @api-lens-tag admin
     * @api-lens-response 200 {"data": [{"id": 1, "name": "John"}], "meta": {"total": 50}}
     */
    public function index(Request $request)
    {
        // ...
    }

    /**
     * Create a new user.
     *
     * @api-lens-response 201 {"data": {"id": 1, "name": "John"}, "message": "User created"}
     */
    public function store(StoreUserRequest $request)
    {
        // ...
    }

    /**
     * Delete user.
     *
     * @api-lens-deprecated 2025-06-01 Use PATCH /users/{user}/deactivate instead
     */
    public function destroy(User $user)
    {
        // ...
    }
}
```

### Available Annotations

| Annotation | Level | Description |
|------------|-------|-------------|
| `@api-lens-group Name` | Class | Group endpoints under a section |
| `@api-lens-auth bearer\|basic\|api-key` | Class/Method | Authentication type |
| `@api-lens-tag name` | Method | Custom tag |
| `@api-lens-response {code} {json?}` | Method | Response code with optional JSON example |
| `@api-lens-deprecated {date} {message}` | Method | Mark as deprecated with migration info |

## OpenAPI Export

### Via Artisan

```bash
# Export as OpenAPI 3.1.0 JSON
php artisan api-lens:export docs/openapi.json --format=openapi

# Export as Postman Collection
php artisan api-lens:export docs/postman.json --format=postman

# Default export (api.json in project root)
php artisan api-lens:export

# Overwrite without confirmation
php artisan api-lens:export docs/openapi.json --format=openapi --force
```

### Via API

```
GET /api-lens/export/openapi
GET /api-lens/export/postman
```

## Live Testing

The built-in API tester lets you:

1. Set custom headers (Authorization, Content-Type, etc.)
2. Edit JSON request body with smart defaults
3. See response status, headers, and formatted body
4. View debug metrics: SQL queries, memory usage, execution time

Enable the debug middleware in your config to capture metrics:

```php
'features' => [
    'debug_metrics' => true,
],
```

## Building the Frontend

The Vue 3 frontend is pre-built. To develop or customize:

```bash
cd ui
npm install
npm run dev    # Development with HMR
npm run build  # Production build
```

Built assets are served from the package automatically.

## Security

API Lens is intended for **development environments only**. To protect in production:

```php
// config/api-lens.php
'enabled' => env('API_LENS_ENABLED', false),

// Or add auth middleware
'auth' => [
    'enabled' => true,
    'middleware' => ['auth:sanctum', 'can:view-api-docs'],
],
```

## License

MIT License. See [LICENSE](LICENSE) for details.
