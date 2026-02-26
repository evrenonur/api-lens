<?php

namespace ApiLens\Support;

class VersionDiff
{
    /**
     * Compare two API versions and return the differences.
     */
    public function diff(array $oldEndpoints, array $newEndpoints): array
    {
        $oldMap = $this->buildMap($oldEndpoints);
        $newMap = $this->buildMap($newEndpoints);

        $added = [];
        $removed = [];
        $modified = [];

        // Find added and modified endpoints
        foreach ($newMap as $key => $endpoint) {
            if (!isset($oldMap[$key])) {
                $added[] = $endpoint;
            } else {
                $changes = $this->compareEndpoints($oldMap[$key], $endpoint);
                if (!empty($changes)) {
                    $modified[] = [
                        'endpoint' => $endpoint,
                        'changes' => $changes,
                    ];
                }
            }
        }

        // Find removed endpoints
        foreach ($oldMap as $key => $endpoint) {
            if (!isset($newMap[$key])) {
                $removed[] = $endpoint;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'summary' => [
                'total_added' => count($added),
                'total_removed' => count($removed),
                'total_modified' => count($modified),
            ],
        ];
    }

    /**
     * Build a map of endpoints keyed by method+uri.
     */
    protected function buildMap(array $endpoints): array
    {
        $map = [];
        foreach ($endpoints as $endpoint) {
            $key = ($endpoint['http_method'] ?? 'GET') . ':' . ($endpoint['uri'] ?? '');
            $map[$key] = $endpoint;
        }
        return $map;
    }

    /**
     * Compare two endpoints and return the changes.
     */
    protected function compareEndpoints(array $old, array $new): array
    {
        $changes = [];

        // Compare rules
        $oldRules = $old['rules'] ?? [];
        $newRules = $new['rules'] ?? [];

        $addedParams = array_diff_key($newRules, $oldRules);
        $removedParams = array_diff_key($oldRules, $newRules);
        $modifiedParams = [];

        foreach ($newRules as $param => $rules) {
            if (isset($oldRules[$param]) && $oldRules[$param] !== $rules) {
                $modifiedParams[$param] = [
                    'old' => $oldRules[$param],
                    'new' => $rules,
                ];
            }
        }

        if (!empty($addedParams)) {
            $changes['params_added'] = array_keys($addedParams);
        }
        if (!empty($removedParams)) {
            $changes['params_removed'] = array_keys($removedParams);
        }
        if (!empty($modifiedParams)) {
            $changes['params_modified'] = $modifiedParams;
        }

        // Compare middlewares
        $oldMiddleware = $old['middlewares'] ?? [];
        $newMiddleware = $new['middlewares'] ?? [];

        if ($oldMiddleware !== $newMiddleware) {
            $changes['middleware_changed'] = [
                'added' => array_diff($newMiddleware, $oldMiddleware),
                'removed' => array_diff($oldMiddleware, $newMiddleware),
            ];
        }

        // Check deprecation status change
        $oldDeprecated = $old['deprecated_since'] ?? null;
        $newDeprecated = $new['deprecated_since'] ?? null;
        if ($oldDeprecated !== $newDeprecated) {
            $changes['deprecation_changed'] = [
                'old' => $oldDeprecated,
                'new' => $newDeprecated,
            ];
        }

        return $changes;
    }

    /**
     * Generate a human-readable changelog from diff results.
     */
    public function toChangelog(array $diff): string
    {
        $lines = [];
        $lines[] = "# API Changelog\n";
        $lines[] = "Generated: " . now()->format('Y-m-d H:i:s') . "\n";

        if (!empty($diff['added'])) {
            $lines[] = "## ✅ Added Endpoints\n";
            foreach ($diff['added'] as $endpoint) {
                $lines[] = "- `{$endpoint['http_method']}` `/{$endpoint['uri']}`";
            }
            $lines[] = '';
        }

        if (!empty($diff['removed'])) {
            $lines[] = "## ❌ Removed Endpoints\n";
            foreach ($diff['removed'] as $endpoint) {
                $lines[] = "- `{$endpoint['http_method']}` `/{$endpoint['uri']}`";
            }
            $lines[] = '';
        }

        if (!empty($diff['modified'])) {
            $lines[] = "## 🔄 Modified Endpoints\n";
            foreach ($diff['modified'] as $mod) {
                $e = $mod['endpoint'];
                $lines[] = "### `{$e['http_method']}` `/{$e['uri']}`\n";

                $changes = $mod['changes'];
                if (!empty($changes['params_added'])) {
                    $lines[] = "- **Added params:** " . implode(', ', $changes['params_added']);
                }
                if (!empty($changes['params_removed'])) {
                    $lines[] = "- **Removed params:** " . implode(', ', $changes['params_removed']);
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
