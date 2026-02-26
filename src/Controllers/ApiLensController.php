<?php

namespace ApiLens\Controllers;

use ApiLens\ApiLens;
use ApiLens\ApiLensToOpenApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serves the API Lens UI and API endpoints.
 */
class ApiLensController extends Controller
{
    private ApiLens $apiLens;
    private ApiLensToOpenApi $openApi;

    public function __construct(ApiLens $apiLens, ApiLensToOpenApi $openApi)
    {
        $this->apiLens = $apiLens;
        $this->openApi = $openApi;
    }

    /**
     * Serve the main API Lens UI.
     */
    public function index(Request $request): Response
    {
        return response()->view('api-lens::index');
    }

    /**
     * Return API endpoint data as JSON.
     *
     * @throws \ReflectionException
     */
    public function api(Request $request): JsonResponse
    {
        $showGet    = !$request->has('showGet') || $request->input('showGet') === 'true';
        $showPost   = !$request->has('showPost') || $request->input('showPost') === 'true';
        $showPut    = !$request->has('showPut') || $request->input('showPut') === 'true';
        $showPatch  = !$request->has('showPatch') || $request->input('showPatch') === 'true';
        $showDelete = !$request->has('showDelete') || $request->input('showDelete') === 'true';
        $showHead   = !$request->has('showHead') || $request->input('showHead') === 'true';

        $endpoints = $this->apiLens->getEndpoints(
            $showGet,
            $showPost,
            $showPut,
            $showPatch,
            $showDelete,
            $showHead
        );

        $endpoints = $this->apiLens->splitByMethods($endpoints);
        $endpoints = $this->apiLens->sortEndpoints($endpoints, $request->input('sort'));
        $endpoints = $this->apiLens->groupEndpoints($endpoints, $request->input('groupby'));

        // Return OpenAPI format if requested
        if ($request->input('openapi')) {
            return response()->json(
                $this->openApi->openApi($endpoints->all())->toArray(),
                Response::HTTP_OK,
                ['Content-type' => 'application/json; charset=utf-8'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        return response()->json(
            $endpoints,
            Response::HTTP_OK,
            ['Content-type' => 'application/json; charset=utf-8'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Return API Lens configuration.
     */
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'title'           => config('api-lens.title', 'API Lens'),
            'default_headers' => config('api-lens.default_headers', []),
            'code_snippets'   => config('api-lens.code_snippets', ['curl', 'javascript', 'php', 'python']),
            'features'        => [
                'response_schema'     => config('api-lens.extract_response_schema', true),
                'code_snippets'       => true,
                'human_readable_rules' => true,
                'rate_limit_info'     => true,
                'auth_detection'      => true,
            ],
        ]);
    }

    /**
     * Export API documentation as OpenAPI 3.1.0 JSON.
     */
    public function exportOpenApi(Request $request): JsonResponse
    {
        $endpoints = $this->getProcessedEndpoints($request);

        return response()->json(
            $this->openApi->openApi($endpoints->all())->toArray(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => $request->has('download')
                    ? 'attachment; filename="openapi.json"'
                    : 'inline',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Export API documentation as Postman Collection v2.1.
     */
    public function exportPostman(Request $request): JsonResponse
    {
        $endpoints = $this->getProcessedEndpoints($request);
        $collection = $this->toPostmanCollection($endpoints);

        return response()->json(
            $collection,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => $request->has('download')
                    ? 'attachment; filename="postman_collection.json"'
                    : 'inline',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Get processed endpoints (extracted, split, sorted, grouped).
     */
    private function getProcessedEndpoints(Request $request)
    {
        $endpoints = $this->apiLens->getEndpoints();
        $endpoints = $this->apiLens->splitByMethods($endpoints);
        $endpoints = $this->apiLens->sortEndpoints($endpoints, $request->input('sort'));
        $endpoints = $this->apiLens->groupEndpoints($endpoints, $request->input('groupby'));

        return $endpoints;
    }

    /**
     * Convert endpoints to Postman Collection v2.1 format.
     */
    private function toPostmanCollection($endpoints): array
    {
        $collection = [
            'info' => [
                'name'        => config('api-lens.open_api.title', config('api-lens.title', 'API Documentation')),
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

        return $collection;
    }

    /**
     * Serve static assets (JS, CSS, fonts, images).
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
     */
    public function assets(Request $request)
    {
        $path = explode('/', $request->path());
        $fileName = end($path);

        $basePath = base_path('vendor/api-lens/api-lens/resources/dist/assets/');
        $filePath = $basePath . $fileName;

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $mimeTypes = [
            'js'    => 'application/javascript',
            'css'   => 'text/css',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'map'   => 'application/json',
        ];

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentType = $mimeTypes[$ext] ?? 'text/plain';

        return response()->file($filePath, [
            'Content-Type'  => $contentType,
            'Cache-Control' => 'public, max-age=3600',
            'Expires'       => gmdate('D, d M Y H:i:s \G\M\T', time() + 3600),
        ]);
    }
}
