<?php

namespace ApiLens\Commands;

use ApiLens\ApiLens;
use ApiLens\ApiLensToOpenApi;
use ErrorException;
use Illuminate\Console\Command;
use Throwable;

class ExportCommand extends Command
{
    protected $signature = 'api-lens:export
                            {path? : Export file location (default: api.json)}
                            {--sort=default : Sort strategy (default, route_names, method_names)}
                            {--groupby=default : Group strategy (default, api_uri, controller_full_path, tag)}
                            {--format=openapi : Export format (openapi, postman)}
                            {--force : Overwrite existing file without confirmation}';

    protected $description = 'Export API documentation to OpenAPI or Postman format';

    private ApiLens $apiLens;
    private ApiLensToOpenApi $openApi;
    private string $exportFilePath;

    public function __construct(ApiLens $apiLens, ApiLensToOpenApi $openApi)
    {
        parent::__construct();
        $this->apiLens = $apiLens;
        $this->openApi = $openApi;
    }

    public function handle(): int
    {
        $this->info('🔍 API Lens - Exporting API documentation...');

        if (!$this->confirmFilePath()) {
            return self::SUCCESS;
        }

        try {
            $excludedMethods = array_map(
                fn($item) => strtolower($item),
                config('api-lens.open_api.exclude_http_methods', [])
            );

            $showGet    = !in_array('get', $excludedMethods);
            $showPost   = !in_array('post', $excludedMethods);
            $showPut    = !in_array('put', $excludedMethods);
            $showPatch  = !in_array('patch', $excludedMethods);
            $showDelete = !in_array('delete', $excludedMethods);
            $showHead   = !in_array('head', $excludedMethods);

            $endpoints = $this->apiLens->getEndpoints(
                $showGet,
                $showPost,
                $showPut,
                $showPatch,
                $showDelete,
                $showHead
            );

            $endpoints = $this->apiLens->splitByMethods($endpoints);
            $endpoints = $this->apiLens->sortEndpoints(
                $endpoints,
                is_string($this->option('sort')) ? $this->option('sort') : 'default'
            );
            $endpoints = $this->apiLens->groupEndpoints(
                $endpoints,
                is_string($this->option('groupby')) ? $this->option('groupby') : 'default'
            );

            $this->info("  Found {$endpoints->count()} endpoints");

            $format = $this->option('format');

            if ($format === 'postman') {
                $content = $this->toPostmanCollection($endpoints);
            } else {
                $content = json_encode(
                    $this->openApi->openApi($endpoints->all())->toArray(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }

            if (!$this->writeToFile($content)) {
                throw new ErrorException("Failed to write to [{$this->exportFilePath}]");
            }

            $relativePath = str_replace(base_path('/'), '', $this->exportFilePath);
            $this->info("  ✅ Exported to: {$relativePath}");
            $this->info("  Format: " . strtoupper($format));
        } catch (Throwable $exception) {
            $this->error('Error: ' . $exception->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function confirmFilePath(): bool
    {
        $path = $this->argument('path');

        if (!$path) {
            $path = config('api-lens.export_path', 'api.json');
        }

        $this->exportFilePath = base_path($path);

        if (file_exists($this->exportFilePath) && !$this->option('force')) {
            $relativePath = str_replace(base_path('/'), '', $this->exportFilePath);
            return $this->confirm("File [{$relativePath}] already exists. Overwrite?", false);
        }

        return true;
    }

    private function writeToFile(string $content): bool
    {
        $directory = dirname($this->exportFilePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($this->exportFilePath, $content) !== false;
    }

    /**
     * Convert endpoints to Postman Collection v2.1 format.
     */
    private function toPostmanCollection($endpoints): string
    {
        $collection = [
            'info' => [
                'name'        => config('api-lens.open_api.title', 'API Documentation'),
                '_postman_id' => bin2hex(random_bytes(16)),
                'description' => config('api-lens.open_api.description', ''),
                'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [],
        ];

        $groups = [];

        foreach ($endpoints as $endpoint) {
            $group = $endpoint->getGroup() ?: 'General';

            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'name' => $group,
                    'item' => [],
                ];
            }

            $baseUrl = config('api-lens.open_api.server_url', config('app.url', 'http://localhost'));
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint->getUri(), '/');

            $item = [
                'name' => strtoupper($endpoint->getHttpMethod()) . ' ' . $endpoint->getUri(),
                'request' => [
                    'method' => strtoupper($endpoint->getHttpMethod()),
                    'header' => [
                        ['key' => 'Accept', 'value' => 'application/json'],
                        ['key' => 'Content-Type', 'value' => 'application/json'],
                    ],
                    'url' => [
                        'raw'  => $url,
                        'host' => [parse_url($baseUrl, PHP_URL_HOST)],
                        'path' => array_filter(explode('/', $endpoint->getUri())),
                    ],
                ],
            ];

            if ($endpoint->getDocBlock()) {
                $item['request']['description'] = $endpoint->getDocBlock();
            }

            // Add body for POST/PUT/PATCH
            $httpMethod = strtoupper($endpoint->getHttpMethod());
            if (in_array($httpMethod, ['POST', 'PUT', 'PATCH']) && !empty($endpoint->getRules())) {
                $body = [];
                foreach ($endpoint->getRules() as $field => $rules) {
                    $body[$field] = '';
                }

                $item['request']['body'] = [
                    'mode' => 'raw',
                    'raw'  => json_encode($body, JSON_PRETTY_PRINT),
                    'options' => [
                        'raw' => ['language' => 'json'],
                    ],
                ];
            }

            $groups[$group]['item'][] = $item;
        }

        $collection['item'] = array_values($groups);

        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
