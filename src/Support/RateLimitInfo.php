<?php

namespace ApiLens\Support;

use Illuminate\Routing\Route;

class RateLimitInfo
{
    /**
     * Extract rate limit information from a route's middleware.
     */
    public function extract(Route $route): ?array
    {
        $middlewares = [];

        try {
            $middlewares = $route->gatherMiddleware();
        } catch (\Throwable) {
            return null;
        }

        foreach ($middlewares as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            $rateLimitInfo = $this->parseThrottle($middleware);
            if ($rateLimitInfo !== null) {
                return $rateLimitInfo;
            }
        }

        return null;
    }

    /**
     * Parse throttle middleware string.
     *
     * Supports formats:
     * - throttle:60,1
     * - throttle:api
     * - throttle:uploads
     */
    protected function parseThrottle(string $middleware): ?array
    {
        if (!str_starts_with($middleware, 'throttle')) {
            return null;
        }

        $parts = explode(':', $middleware, 2);

        if (!isset($parts[1])) {
            return [
                'requests_per_minute' => 60,
                'decay_minutes' => 1,
                'limiter' => 'default',
            ];
        }

        $params = explode(',', $parts[1]);

        // Named limiter (throttle:api)
        if (count($params) === 1 && !is_numeric($params[0])) {
            return [
                'requests_per_minute' => null,
                'decay_minutes' => null,
                'limiter' => $params[0],
            ];
        }

        // Numeric limiter (throttle:60,1)
        return [
            'requests_per_minute' => (int)($params[0] ?? 60),
            'decay_minutes' => (int)($params[1] ?? 1),
            'limiter' => null,
        ];
    }
}
