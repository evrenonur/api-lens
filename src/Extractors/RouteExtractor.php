<?php

namespace ApiLens\Extractors;

use ApiLens\Models\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Extracts route information from Laravel's router and builds Endpoint models.
 */
class RouteExtractor
{
    private PathParameterExtractor $pathParameterExtractor;

    public function __construct(PathParameterExtractor $pathParameterExtractor)
    {
        $this->pathParameterExtractor = $pathParameterExtractor;
    }

    /**
     * Extract all routes and return as Endpoint collection.
     *
     * @param string[] $onlyMethods HTTP methods to include
     * @return Collection<int, Endpoint>
     */
    public function extract(array $onlyMethods): Collection
    {
        $endpoints = collect();

        $routes = Route::getRoutes()->getRoutes();

        $onlyRouteStartWith = config('api-lens.only_route_uri_start_with', '');
        $excludePatterns = config('api-lens.hide_matching', []);

        foreach ($routes as $route) {
            // Filter by prefix
            if ($onlyRouteStartWith && !Str::startsWith($route->uri, $onlyRouteStartWith)) {
                continue;
            }

            // Filter by exclusion patterns
            foreach ($excludePatterns as $regex) {
                if (preg_match($regex, $route->uri)) {
                    continue 2;
                }
            }

            // Filter by HTTP methods
            $routeMethods = array_intersect($route->methods, $onlyMethods);
            if (count($routeMethods) === 0) {
                continue;
            }

            $controllerName = '';
            $controllerFullPath = '';
            $method = '';

            // Parse controller info if not closure
            if (
                is_string($route->action['uses'] ?? null) &&
                !RouteAction::containsSerializedClosure($route->action)
            ) {
                $controllerCallback = Str::parseCallback($route->action['uses']);
                $controllerFullPath = $controllerCallback[0];
                $method = $controllerCallback[1] ?? '';

                try {
                    $controllerName = (new ReflectionClass($controllerFullPath))->getShortName();
                } catch (\Throwable $e) {
                    $controllerName = class_basename($controllerFullPath);
                }
            }

            // Extract path parameters
            $pathParameters = [];
            try {
                $pp = $this->pathParameterExtractor->extract($route);
                foreach ($pp as $k => $v) {
                    $pathParameters[$k] = [$v];
                }
            } catch (\Throwable $e) {
                // Silently skip path parameter extraction errors
            }

            /** @var string[] $middlewares */
            $middlewares = $route->middleware();

            $hideMeta = config('api-lens.hide_meta_data', false);

            $endpoint = new Endpoint(
                uri: $route->uri,
                methods: $routeMethods,
                middlewares: $hideMeta ? [] : $middlewares,
                controller: $hideMeta ? '' : $controllerName,
                controllerFullPath: $hideMeta ? '' : $controllerFullPath,
                method: $hideMeta ? '' : $method,
                httpMethod: '',
                pathParameters: $pathParameters,
                rules: [],
                docBlock: ''
            );

            // Detect auth type from middleware
            $endpoint->setAuthType($this->detectAuthType($middlewares));

            // Detect rate limit from middleware
            $endpoint->setRateLimit($this->detectRateLimit($middlewares));

            $endpoints->push($endpoint);
        }

        return $endpoints;
    }

    /**
     * Split endpoints by HTTP methods (Route::match will generate multiple endpoints).
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function splitByMethods(Collection $endpoints): Collection
    {
        $split = collect();

        foreach ($endpoints as $endpoint) {
            foreach ($endpoint->getMethods() as $method) {
                $cloned = $endpoint->clone();
                $cloned->setMethods([$method]);
                $cloned->setHttpMethod($method);
                $split->push($cloned);
            }
        }

        return $split;
    }

    /**
     * Sort endpoints by the given strategy.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function sortEndpoints(Collection $endpoints, ?string $sortBy = 'default'): Collection
    {
        if ($sortBy === 'route_names') {
            return $endpoints->sortBy(fn(Endpoint $e) => $e->getUri())->values();
        }

        if ($sortBy === 'method_names') {
            $methodOrder = [
                Request::METHOD_GET => 0,
                Request::METHOD_POST => 1,
                Request::METHOD_PUT => 2,
                Request::METHOD_PATCH => 3,
                Request::METHOD_DELETE => 4,
                Request::METHOD_HEAD => 5,
            ];

            return $endpoints->sortBy(
                fn(Endpoint $e) => $methodOrder[$e->getHttpMethod()] ?? 99,
                SORT_NUMERIC
            )->values();
        }

        return $endpoints;
    }

    /**
     * Group endpoints by the given strategy.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function groupEndpoints(Collection $endpoints, ?string $groupBy = 'default'): Collection
    {
        if ($groupBy === 'api_uri') {
            $this->groupByApiUri($endpoints);
        } elseif ($groupBy === 'controller_full_path') {
            $this->groupByController($endpoints);
        } elseif ($groupBy === 'tag') {
            $this->groupByTag($endpoints);
        } else {
            return $endpoints;
        }

        return $endpoints
            ->sortBy(fn(Endpoint $e) => $e->getGroup() . str_pad((string) $e->getGroupIndex(), 5, '0', STR_PAD_LEFT), SORT_NATURAL)
            ->values();
    }

    /**
     * Detect authentication type from middleware list.
     */
    private function detectAuthType(array $middlewares): ?string
    {
        foreach ($middlewares as $middleware) {
            if (Str::startsWith($middleware, 'auth:sanctum')) {
                return 'sanctum';
            }
            if (Str::startsWith($middleware, 'auth:api')) {
                return 'bearer';
            }
            if ($middleware === 'auth' || Str::startsWith($middleware, 'auth:')) {
                return 'session';
            }
            if (Str::contains($middleware, 'passport')) {
                return 'oauth2';
            }
        }

        return null;
    }

    /**
     * Detect rate limiting information from middleware.
     *
     * @return array{requests_per_minute?: int, requests_per_hour?: int}
     */
    private function detectRateLimit(array $middlewares): array
    {
        foreach ($middlewares as $middleware) {
            if (Str::startsWith($middleware, 'throttle:')) {
                $parts = explode(',', Str::after($middleware, 'throttle:'));
                $limit = (int) ($parts[0] ?? 60);
                $minutes = (int) ($parts[1] ?? 1);

                if ($minutes <= 1) {
                    return ['requests_per_minute' => $limit];
                }

                return ['requests_per_minute' => (int) ceil($limit / $minutes)];
            }
        }

        return [];
    }

    /**
     * Group endpoints by URI prefix.
     *
     * @param Collection<int, Endpoint> $endpoints
     */
    private function groupByApiUri(Collection $endpoints): void
    {
        $patterns = config('api-lens.group_by.uri_patterns', []);
        $regex = count($patterns) > 0 ? '(' . implode('|', $patterns) . ')' : '';

        $groupIndexes = collect();

        foreach ($endpoints as $endpoint) {
            $prefix = '';
            if ($regex !== '') {
                $prefix = Str::match($regex, $endpoint->getUri());
            }

            $group = $this->getGroupByUri($prefix, $endpoint->getUri());
            $this->rememberGroupIndex($groupIndexes, $group);
            $endpoint->setGroup($group);
            $endpoint->setGroupIndex((int) $groupIndexes->get($group));
        }
    }

    /**
     * Group endpoints by controller fully qualified name.
     *
     * @param Collection<int, Endpoint> $endpoints
     */
    private function groupByController(Collection $endpoints): void
    {
        $groupIndexes = collect();

        foreach ($endpoints as $endpoint) {
            $group = $endpoint->getControllerFullPath() ?: 'Closures';
            $this->rememberGroupIndex($groupIndexes, $group);
            $endpoint->setGroup($group);
            $endpoint->setGroupIndex((int) $groupIndexes->get($group));
        }
    }

    /**
     * Group endpoints by their tags.
     *
     * @param Collection<int, Endpoint> $endpoints
     */
    private function groupByTag(Collection $endpoints): void
    {
        $groupIndexes = collect();

        foreach ($endpoints as $endpoint) {
            $tags = $endpoint->getTags();
            $group = !empty($tags) ? $tags[0] : ($endpoint->getController() ?: 'General');
            $this->rememberGroupIndex($groupIndexes, $group);
            $endpoint->setGroup($group);
            $endpoint->setGroupIndex((int) $groupIndexes->get($group));
        }
    }

    private function getGroupByUri(string $prefix, string $uri): string
    {
        if ($prefix === '') {
            $paths = explode('/', $uri);
            return $paths[0];
        }

        $after = Str::after($uri, $prefix);
        $paths = explode('/', $after);
        return $prefix . $paths[0];
    }

    private function rememberGroupIndex(Collection $groupIndexes, string $key): void
    {
        if (!$groupIndexes->has($key)) {
            $groupIndexes->put($key, 0);
            return;
        }

        $groupIndexes->put($key, $groupIndexes->get($key) + 1);
    }
}
