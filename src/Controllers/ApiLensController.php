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
