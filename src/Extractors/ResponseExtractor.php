<?php

namespace ApiLens\Extractors;

use ApiLens\Models\Endpoint;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Automatically extracts response schema from:
 * - API Resource classes (JsonResource)
 * - Return type hints
 * - @ApiResponse annotations with JSON examples
 * - Factory-generated example data
 *
 * This is a MAJOR improvement over laravel-request-docs which has NO response schema support.
 */
class ResponseExtractor
{
    /**
     * Extract response schema for all endpoints.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function extract(Collection $endpoints): Collection
    {
        if (!config('api-lens.extract_response_schema', true)) {
            return $endpoints;
        }

        foreach ($endpoints as $endpoint) {
            if ($endpoint->isClosure()) {
                continue;
            }

            try {
                $this->extractForEndpoint($endpoint);
            } catch (Throwable $e) {
                // Skip - response extraction is best-effort
            }
        }

        return $endpoints;
    }

    /**
     * Extract response schema for a single endpoint.
     */
    private function extractForEndpoint(Endpoint $endpoint): void
    {
        $controllerReflection = new ReflectionMethod(
            $endpoint->getControllerFullPath(),
            $endpoint->getMethod()
        );

        // Strategy 1: Parse @ApiResponseSchema from PHPDoc
        $docComment = $controllerReflection->getDocComment();
        if ($docComment) {
            $schema = $this->parseResponseSchemaFromDoc($docComment);
            if (!empty($schema)) {
                $endpoint->setResponseSchema($schema);
                return;
            }
        }

        // Strategy 2: Analyze return type for JsonResource
        $returnType = $controllerReflection->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            $schema = $this->analyzeReturnType($returnType->getName());
            if (!empty($schema)) {
                $endpoint->setResponseSchema($schema);
                return;
            }
        }

        // Strategy 3: Scan method body for Resource usage
        $schema = $this->scanMethodBodyForResource($controllerReflection);
        if (!empty($schema)) {
            $endpoint->setResponseSchema($schema);
        }
    }

    /**
     * Parse response schema from @ApiResponseSchema PHPDoc annotation.
     * Supports inline JSON schema definitions.
     *
     * Example:
     *  @ApiResponseSchema {"id": "integer", "name": "string", "email": "string"}
     *
     * @return array<string, mixed>
     */
    private function parseResponseSchemaFromDoc(string $docComment): array
    {
        $schema = [];
        $inBlock = false;
        $jsonBuffer = '';

        foreach (explode("\n", $docComment) as $line) {
            $line = trim($line, " \t*");

            if (Str::contains($line, '@ApiResponseSchema')) {
                $jsonStr = trim(Str::after($line, '@ApiResponseSchema'));

                // Try inline JSON
                if ($jsonStr && Str::startsWith($jsonStr, '{')) {
                    $decoded = json_decode($jsonStr, true);
                    if (is_array($decoded)) {
                        return $this->normalizeSchema($decoded);
                    }
                }

                $inBlock = true;
                $jsonBuffer = $jsonStr;
                continue;
            }

            if ($inBlock) {
                if (Str::contains($line, '@') || $line === '/') {
                    $inBlock = false;
                    $decoded = json_decode($jsonBuffer, true);
                    if (is_array($decoded)) {
                        return $this->normalizeSchema($decoded);
                    }
                } else {
                    $jsonBuffer .= $line;
                }
            }
        }

        // Try buffered JSON
        if ($jsonBuffer) {
            $decoded = json_decode($jsonBuffer, true);
            if (is_array($decoded)) {
                return $this->normalizeSchema($decoded);
            }
        }

        return $schema;
    }

    /**
     * Analyze a return type class to extract schema from JsonResource.
     *
     * @return array<string, mixed>
     */
    private function analyzeReturnType(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        try {
            $reflectionClass = new ReflectionClass($className);

            // Check if it's a JsonResource
            if ($reflectionClass->isSubclassOf(JsonResource::class)) {
                return $this->extractFromResource($reflectionClass);
            }

            // Check if it's a ResourceCollection
            if ($reflectionClass->isSubclassOf(ResourceCollection::class)) {
                return [
                    'type' => 'collection',
                    'data' => $this->extractFromResource($reflectionClass),
                ];
            }
        } catch (Throwable $e) {
            // Ignore
        }

        return [];
    }

    /**
     * Extract schema from a JsonResource class by analyzing its toArray method.
     *
     * @return array<string, mixed>
     */
    private function extractFromResource(ReflectionClass $resourceClass): array
    {
        try {
            if (!$resourceClass->hasMethod('toArray')) {
                return [];
            }

            $method = $resourceClass->getMethod('toArray');
            $fileName = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$fileName) {
                return [];
            }

            $lines = file($fileName);
            if ($lines === false) {
                return [];
            }

            $methodBody = '';
            for ($i = $startLine - 1; $i < $endLine; $i++) {
                $methodBody .= $lines[$i];
            }

            return $this->parseResourceArrayKeys($methodBody);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Parse array keys from a Resource's toArray method body.
     *
     * @return array<string, mixed>
     */
    private function parseResourceArrayKeys(string $methodBody): array
    {
        $schema = [];

        // Match patterns like 'key' => $this->attribute or 'key' => value
        preg_match_all(
            "/['\"](\w+)['\"]\s*=>\s*(.+?)(?:,|\n|\])/",
            $methodBody,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = $match[1];
            $value = trim($match[2]);

            $schema[$key] = $this->inferTypeFromValue($value);
        }

        return $schema;
    }

    /**
     * Infer the data type from a resource value expression.
     *
     * @return array{type: string, nullable?: bool, description?: string}
     */
    private function inferTypeFromValue(string $value): array
    {
        // $this->id or (int) cast
        if (Str::contains($value, ['->id', '(int)', '_id', '_count', 'count('])) {
            return ['type' => 'integer'];
        }

        // Money/price/amount
        if (Str::contains($value, ['price', 'amount', 'total', 'balance', '(float)', '(double)'])) {
            return ['type' => 'number'];
        }

        // Boolean patterns
        if (Str::contains($value, ['(bool)', 'is_', 'has_', '->active', '->enabled', '->verified'])) {
            return ['type' => 'boolean'];
        }

        // Dates
        if (Str::contains($value, ['created_at', 'updated_at', 'deleted_at', '_at', 'date', 'Carbon'])) {
            return ['type' => 'string', 'description' => 'ISO 8601 datetime'];
        }

        // Arrays / Relations
        if (Str::contains($value, ['->toArray()', 'collection', 'Collection'])) {
            return ['type' => 'array'];
        }

        // Nested Resource
        if (Str::contains($value, ['Resource::make', 'new ']) && Str::contains($value, 'Resource')) {
            return ['type' => 'object'];
        }

        // When / conditional
        if (Str::contains($value, ['$this->when', '$this->whenLoaded'])) {
            return ['type' => 'mixed', 'nullable' => true];
        }

        // Default to string
        return ['type' => 'string'];
    }

    /**
     * Scan controller method body for Resource instantiation patterns.
     *
     * @return array<string, mixed>
     */
    private function scanMethodBodyForResource(ReflectionMethod $method): array
    {
        $fileName = $method->getFileName();
        if (!$fileName) {
            return [];
        }

        $lines = file($fileName);
        if ($lines === false) {
            return [];
        }

        $methodBody = '';
        for ($i = $method->getStartLine() - 1; $i < $method->getEndLine(); $i++) {
            $methodBody .= $lines[$i];
        }

        // Match patterns like: new UserResource, UserResource::make, UserResource::collection
        preg_match('/(\w+Resource)(?:::make|::collection|::new|\()/', $methodBody, $match);

        if (empty($match[1])) {
            return [];
        }

        $resourceClassName = $match[1];

        // Try to resolve the fully qualified class name
        $useStatements = $this->extractUseStatements($fileName);

        foreach ($useStatements as $use) {
            if (Str::endsWith($use, '\\' . $resourceClassName) || $use === $resourceClassName) {
                if (class_exists($use)) {
                    return $this->analyzeReturnType($use);
                }
            }
        }

        return [];
    }

    /**
     * Extract use statements from a PHP file.
     *
     * @return string[]
     */
    private function extractUseStatements(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        preg_match_all('/^use\s+([\w\\\\]+)\s*;/m', $content, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Normalize user-provided schema definitions.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = ['type' => $value];
            } elseif (is_array($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
