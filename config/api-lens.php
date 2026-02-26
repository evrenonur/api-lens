<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Lens - Laravel API Documentation Generator
    |--------------------------------------------------------------------------
    |
    | Auto-generated API documentation with response schemas, code snippets,
    | human-readable validation rules, and much more.
    |
    */

    // Page title
    'title' => 'API Lens - API Documentation',

    // Enable/disable API Lens
    'enabled' => true,

    // Set to true to throw exceptions when rule extraction fails
    'debug' => false,

    /*
    |--------------------------------------------------------------------------
    | URL & Middleware
    |--------------------------------------------------------------------------
    */

    // Route where API Lens UI will be served
    'url' => 'api-lens',

    // Middlewares for the API Lens routes
    'middlewares' => [
        // \ApiLens\NotFoundWhenProduction::class,
    ],

    // Only include routes whose URI starts with this string
    'only_route_uri_start_with' => '',

    // Regex patterns to exclude routes
    'hide_matching' => [
        '#^telescope#',
        '#^horizon#',
        '#^docs#',
        '#^api-lens#',
        '#^request-docs#',
        '#^sanctum#',
        '#^_ignition#',
        '#^_debugbar#',
        '#^pulse#',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    */

    // Automatically extract response schema from JsonResource classes
    'extract_response_schema' => true,

    // Languages for code snippet generation
    'code_snippets' => ['curl', 'javascript', 'php', 'python'],

    // Methods on Request class to call for rules (default is just 'rules')
    'rules_methods' => ['rules'],

    // Default responses when not specified in annotations
    'default_responses' => ['200', '401', '403', '404', '422'],

    /*
    |--------------------------------------------------------------------------
    | Data Visibility
    |--------------------------------------------------------------------------
    */

    // Hide controller, method, and middleware information
    'hide_meta_data' => false,

    // Hide SQL query data from responses
    'hide_sql_data' => false,

    // Hide log data from responses
    'hide_logs_data' => false,

    // Hide Eloquent model event data
    'hide_models_data' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    */

    'default_headers' => [
        [
            'key'   => 'Accept',
            'value' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grouping
    |--------------------------------------------------------------------------
    */

    'group_by' => [
        'uri_patterns' => [
            // e.g., 'api/v1/', 'api/v2/'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    */

    'export_path' => 'api.json',

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Configuration
    |--------------------------------------------------------------------------
    */

    'open_api' => [
        // OpenAPI specification version
        'version' => '3.1.0',

        // API document version
        'document_version' => '1.0.0',

        // API title
        'title' => 'API Documentation',

        // API description
        'description' => 'Auto-generated API documentation by API Lens',

        // License
        'license' => 'Apache 2.0',
        'license_url' => 'https://www.apache.org/licenses/LICENSE-2.0.html',

        // Server URL (defaults to app.url)
        'server_url' => null,

        // DELETE method should include request body
        'delete_with_body' => false,

        // Exclude specific HTTP methods from export
        'exclude_http_methods' => ['HEAD'],

        // Security scheme
        'security' => [
            // null, 'bearer', 'basic', 'apikey', 'jwt', 'oauth2'
            'type' => null,
            'name' => null,
            'position' => 'header',

            // Only for oauth2
            'authorization_url' => null,
            'token_url' => null,
            'scopes' => [],
        ],

        // Default response definitions
        'responses' => [
            '200' => [
                'description' => 'Successful operation',
            ],
            '401' => [
                'description' => 'Unauthenticated',
            ],
            '403' => [
                'description' => 'Forbidden',
            ],
            '404' => [
                'description' => 'Resource not found',
            ],
            '422' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string'],
                                'errors' => [
                                    'type' => 'object',
                                    'additionalProperties' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '500' => [
                'description' => 'Internal server error',
            ],
            'default' => [
                'description' => 'Unexpected error',
            ],
        ],
    ],
];
