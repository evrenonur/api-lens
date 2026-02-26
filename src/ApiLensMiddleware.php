<?php

namespace ApiLens;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that captures runtime information when making API requests through API Lens UI.
 * Captures: SQL queries, logs, Eloquent events, memory usage, execution time.
 *
 * Activated by the X-Request-LRD or X-Api-Lens header.
 */
class ApiLensMiddleware
{
    /** @var array<int, array{time: float, connection_name: string, sql: string, bindings?: array}> */
    private array $queries = [];

    /** @var array<int, object> */
    private array $logs = [];

    /** @var array<class-string, array<string, int>> */
    private array $models = [];

    /** @var array<int, array{event: string, model: class-string, timestamp?: float}> */
    private array $modelsTimeline = [];

    private float $startTime;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('api-lens.enabled')) {
            return $next($request);
        }

        // Only activate for API Lens requests
        $isApiLensRequest = $request->headers->has('X-Request-LRD')
            || $request->headers->has('X-Api-Lens');

        if (!$isApiLensRequest) {
            return $next($request);
        }

        // In non-debug mode, just wrap response
        if (!config('app.debug')) {
            $response = $next($request);

            if ($response instanceof JsonResponse) {
                $jsonContent = json_encode(['data' => $response->getData()]);
                $response->setContent((string) $jsonContent);
            }

            return $response;
        }

        $this->startTime = microtime(true);

        // Listen to database queries
        if (!config('api-lens.hide_sql_data')) {
            $this->listenToDatabase();
        }

        // Listen to logs
        if (!config('api-lens.hide_logs_data')) {
            $this->listenToLogs();
        }

        // Listen to model events
        if (!config('api-lens.hide_models_data')) {
            $this->listenToModels();
        }

        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $executionTime = round((microtime(true) - $this->startTime) * 1000, 2);

        $content = [
            'data' => $response->getData(),
            '_api_lens' => [
                'queries'         => $this->queries,
                'queries_count'   => count($this->queries),
                'queries_time_ms' => round(array_sum(array_column($this->queries, 'time')), 2),
                'logs'            => $this->logs,
                'models'          => $this->models,
                'models_timeline' => array_unique($this->modelsTimeline, SORT_REGULAR),
                'memory'          => $this->formatMemory(memory_get_peak_usage(true)),
                'execution_ms'    => $executionTime,
            ],
        ];

        $jsonContent = json_encode($content);

        if (!$jsonContent) {
            return $response;
        }

        // Gzip compression if supported
        if (in_array('gzip', $request->getEncodings()) && function_exists('gzencode')) {
            $compressed = gzencode($jsonContent, 9);

            if ($compressed !== false) {
                return new Response($compressed, 200, [
                    'Content-Type'     => 'application/json; charset=utf-8',
                    'Content-Length'   => strlen($compressed),
                    'Content-Encoding' => 'gzip',
                ]);
            }
        }

        return new Response($jsonContent, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    private function listenToDatabase(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $this->queries[] = [
                'sql'             => $query->sql,
                'bindings'        => $query->bindings,
                'time'            => $query->time,
                'connection_name' => $query->connectionName,
            ];
        });
    }

    private function listenToLogs(): void
    {
        Log::listen(function ($message): void {
            $this->logs[] = $message;
        });
    }

    private function listenToModels(): void
    {
        Event::listen('eloquent.*', function (string $event, array $models): void {
            foreach (array_filter($models) as $model) {
                if ($this->shouldIgnoreEvent($event)) {
                    continue;
                }

                $event = explode(':', $event)[0];
                $event = Str::replace('eloquent.', '', $event);
                $class = get_class($model);

                $this->modelsTimeline[] = [
                    'event'     => $event,
                    'model'     => $class,
                    'timestamp' => microtime(true) - $this->startTime,
                ];

                if (!isset($this->models[$class])) {
                    $this->models[$class] = [];
                }

                if (!isset($this->models[$class][$event])) {
                    $this->models[$class][$event] = 0;
                }

                $this->models[$class][$event]++;
            }
        });
    }

    private function shouldIgnoreEvent(string $event): bool
    {
        return Str::startsWith($event, 'eloquent.booting')
            || Str::startsWith($event, 'eloquent.booted')
            || Str::startsWith($event, 'eloquent.retrieving')
            || Str::startsWith($event, 'eloquent.creating')
            || Str::startsWith($event, 'eloquent.saving')
            || Str::startsWith($event, 'eloquent.updating')
            || Str::startsWith($event, 'eloquent.deleting');
    }

    private function formatMemory(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . 'GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . 'MB';
        }
        return round($bytes / 1024, 2) . 'KB';
    }
}
