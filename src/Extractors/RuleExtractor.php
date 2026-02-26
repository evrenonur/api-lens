<?php

namespace ApiLens\Extractors;

use ApiLens\Models\Endpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Extracts validation rules from Request classes and PHPDoc annotations.
 * Supports:
 *   - FormRequest rules() method
 *   - @LRDparam / @ApiParam custom params
 *   - @ApiResponse custom response codes
 *   - @ApiTag for endpoint tagging
 *   - @ApiDeprecated for deprecation marking
 *   - @ApiSummary for short endpoint descriptions
 *   - Regex-based fallback when rules() can't be instantiated
 */
class RuleExtractor
{
    /**
     * Parse request rules and PHPDoc for all endpoints.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function extract(Collection $endpoints): Collection
    {
        foreach ($endpoints as $endpoint) {
            if ($endpoint->isClosure()) {
                continue;
            }

            try {
                $this->extractForEndpoint($endpoint);
            } catch (Throwable $e) {
                // Skip endpoints that can't be analyzed
            }
        }

        return $endpoints;
    }

    /**
     * Extract rules, docs, tags, and metadata for a single endpoint.
     */
    private function extractForEndpoint(Endpoint $endpoint): void
    {
        $controllerReflection = new ReflectionMethod(
            $endpoint->getControllerFullPath(),
            $endpoint->getMethod()
        );

        // Parse controller method PHPDoc
        $controllerDoc = $this->getDocComment($controllerReflection);
        $controllerLrdComment = $this->parseLrdDocComment($controllerDoc);
        $controllerDocRules = $this->parseCustomParams($controllerDoc);

        // Set responses from PHPDoc
        $endpoint->setResponses($this->parseResponses($controllerDoc));

        // Set tags from PHPDoc
        $tags = $this->parseTags($controllerDoc);
        if (!empty($tags)) {
            $endpoint->setTags($tags);
        }

        // Set summary from PHPDoc
        $summary = $this->parseSummary($controllerDoc);
        if ($summary) {
            $endpoint->setSummary($summary);
        }

        // Set deprecation from PHPDoc
        $deprecated = $this->parseDeprecated($controllerDoc);
        if ($deprecated) {
            $endpoint->setDeprecatedSince($deprecated);
        }

        $lrdDocComments = [];

        // Iterate through controller method parameters to find Request classes
        foreach ($controllerReflection->getParameters() as $param) {
            $namedType = $param->getType();

            if (!$namedType || !method_exists($namedType, 'getName')) {
                continue;
            }

            try {
                $requestClassName = $namedType->getName();

                if (!class_exists($requestClassName)) {
                    continue;
                }

                $reflectionClass = new ReflectionClass($requestClassName);

                // Try to instantiate the request class
                try {
                    $requestObject = $reflectionClass->newInstance();
                } catch (Throwable $ex) {
                    $requestObject = $reflectionClass->newInstanceWithoutConstructor();
                }

                // Extract from configured rules methods (default: ['rules'])
                foreach (config('api-lens.rules_methods', ['rules']) as $requestMethod) {
                    if (!method_exists($requestObject, $requestMethod)) {
                        continue;
                    }

                    try {
                        $endpoint->mergeRules($this->flattenRules($requestObject->$requestMethod()));
                        $requestReflectionMethod = new ReflectionMethod($requestObject, $requestMethod);
                    } catch (Throwable $ex) {
                        // Fallback: regex-based rule extraction
                        $endpoint->mergeRules($this->rulesByRegex($requestClassName, $requestMethod));
                        $requestReflectionMethod = new ReflectionMethod($requestClassName, $requestMethod);
                    }

                    // Parse PHPDoc from the rules method as well
                    $requestMethodDoc = $this->getDocComment($requestReflectionMethod);
                    $requestMethodLrd = $this->parseLrdDocComment($requestMethodDoc);
                    $requestMethodRules = $this->parseCustomParams($requestMethodDoc);

                    $lrdDocComments[] = $requestMethodLrd;
                    $endpoint->mergeRules($requestMethodRules);
                }
            } catch (Throwable $ex) {
                // Do nothing - skip this parameter
            }
        }

        // Append controller-level doc comments
        $lrdDocComments[] = $controllerLrdComment;
        $lrdDocComments = array_filter($lrdDocComments, fn($s) => $s !== '');
        $endpoint->setDocBlock(join("\n", $lrdDocComments));
        $endpoint->mergeRules($controllerDocRules);

        // If no rules found yet, try to extract inline $request->validate() rules
        if (empty($endpoint->getRules())) {
            $inlineRules = $this->extractInlineValidateRules($controllerReflection);
            if (!empty($inlineRules)) {
                $endpoint->mergeRules($inlineRules);
            }
        }
    }

    /**
     * Parse markdown content from @lrd:start ... @lrd:end or @api:start ... @api:end blocks.
     */
    public function parseLrdDocComment(string $docComment): string
    {
        $comment = '';
        $counter = 0;

        foreach (explode("\n", $docComment) as $line) {
            $line = trim($line);

            // Check for start/end markers
            if (Str::contains($line, ['@lrd', '@api:start', '@api:end'])) {
                $counter++;
            }

            if ($counter !== 1 || Str::contains($line, ['@lrd', '@api:start', '@api:end'])) {
                continue;
            }

            if (Str::startsWith($line, '*')) {
                $line = substr($line, 1);
            }

            $comment .= $line . "\n";
        }

        return $comment;
    }

    /**
     * Flatten mixed validation rules into a standard format.
     *
     * @param array<string, mixed> $mixedRules
     * @return array<string, string[]>
     */
    public function flattenRules(array $mixedRules): array
    {
        $rules = [];

        foreach ($mixedRules as $attribute => $rule) {
            if (is_object($rule)) {
                $rules[$attribute][] = get_class($rule);
                continue;
            }

            if (is_array($rule)) {
                $rulesStrs = [];
                foreach ($rule as $ruleItem) {
                    $rulesStrs[] = is_object($ruleItem) ? get_class($ruleItem) : $ruleItem;
                }
                $rules[$attribute][] = implode('|', $rulesStrs);
                continue;
            }

            $rules[$attribute][] = $rule;
        }

        return $rules;
    }

    /**
     * Extract rules from inline $request->validate([...]) or Validator::make([...]) calls.
     * This handles cases where validation is done directly in the controller method
     * instead of using a FormRequest class.
     *
     * @return array<string, string[]>
     */
    private function extractInlineValidateRules(ReflectionMethod $method): array
    {
        $fileName = $method->getFileName();

        if ($fileName === false) {
            return [];
        }

        $lines = file($fileName);

        if ($lines === false) {
            return [];
        }

        // Get only the lines within this method
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine() - 1;
        $methodBody = '';

        for ($i = $startLine; $i <= $endLine; $i++) {
            $methodBody .= $lines[$i];
        }

        // Match $request->validate([...]) or $this->validate($request, [...]) or Validator::make($request->all(), [...])
        // We need to find the array argument containing rule definitions
        $patterns = [
            // $request->validate([...])
            '/\$\w+->validate\s*\(\s*\[/s',
            // Validator::make(..., [...])
            '/Validator::make\s*\([^,]+,\s*\[/s',
            // $this->validate($request, [...])
            '/\$this->validate\s*\([^,]+,\s*\[/s',
        ];

        $validateArrayContent = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $methodBody, $match, PREG_OFFSET_CAPTURE)) {
                // Find the opening bracket of the rules array
                $offset = $match[0][1] + strlen($match[0][0]) - 1; // position of '['
                $validateArrayContent = $this->extractArrayContent($methodBody, $offset);
                break;
            }
        }

        if ($validateArrayContent === null) {
            return [];
        }

        // Parse the array content to extract field => rules pairs
        return $this->parseRulesFromArrayString($validateArrayContent);
    }

    /**
     * Extract balanced array content starting from the '[' at the given offset.
     */
    private function extractArrayContent(string $source, int $offset): ?string
    {
        if (!isset($source[$offset]) || $source[$offset] !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($source);
        $start = $offset;

        for ($i = $offset; $i < $length; $i++) {
            if ($source[$i] === '[') {
                $depth++;
            } elseif ($source[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Parse a PHP array string like "['email' => 'required|email', 'password' => 'required|string|min:8']"
     * into rules array.
     *
     * @return array<string, string[]>
     */
    private function parseRulesFromArrayString(string $arrayString): array
    {
        $rules = [];

        // Match 'field' => 'rule1|rule2' or "field" => "rule1|rule2" or 'field' => ['rule1', 'rule2']
        // Field name pattern
        $fieldPattern = "/(?:'|\")([^'\"]+)(?:'|\")\s*=>/";

        // Split by lines and process
        $lines = explode("\n", $arrayString);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip comments
            if (Str::startsWith($trimmed, ['//', '#', '/*', '*'])) {
                continue;
            }

            if (!Str::contains($line, '=>')) {
                continue;
            }

            preg_match_all("/(?:'|\")([^'\"]*?)(?:'|\")/", $line, $matches);

            if (empty($matches[1]) || count($matches[1]) < 2) {
                continue;
            }

            $fieldName = $matches[1][0];
            $fieldRules = array_slice($matches[1], 1);

            // If rules contain pipe-separated values in a single string, keep as-is
            $rules[$fieldName] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Extract rules by reading and parsing the source file with regex.
     * Used as fallback when rules() method can't be called.
     *
     * @return array<string, string[]>
     */
    public function rulesByRegex(string $requestClassName, string $methodName): array
    {
        $data = new ReflectionMethod($requestClassName, $methodName);
        $lines = file((string) $data->getFileName());

        if ($lines === false) {
            return [];
        }

        $rules = [];

        for ($i = $data->getStartLine() - 1; $i <= $data->getEndLine() - 1; $i++) {
            $trimmed = trim($lines[$i]);

            if (Str::startsWith($trimmed, '//') || Str::startsWith($trimmed, '#')) {
                continue;
            }

            if (!Str::contains($lines[$i], '=>')) {
                continue;
            }

            preg_match_all("/(?:'|\").*?(?:'|\")/", $lines[$i], $matches);
            $rules[] = $matches;
        }

        return collect($rules)
            ->filter(fn($item) => count($item[0]) > 0)
            ->map(function (array $item) {
                $fieldName = Str::of($item[0][0])->replace(['"', "'"], '');
                $definedFieldRules = collect(array_slice($item[0], 1))
                    ->transform(fn($rule) => Str::of($rule)->replace(['"', "'"], '')->__toString())
                    ->toArray();

                return ['key' => $fieldName, 'rules' => $definedFieldRules];
            })
            ->keyBy('key')
            ->map(fn($item) => $item['rules'])
            ->toArray();
    }

    /**
     * Parse custom parameters from @LRDparam or @ApiParam annotations.
     *
     * @return array<string, string[]>
     */
    private function parseCustomParams(string $docComment): array
    {
        $params = [];

        foreach (explode("\n", $docComment) as $line) {
            if (!Str::contains($line, ['@LRDparam', '@ApiParam'])) {
                continue;
            }

            $line = trim(Str::replace(['@LRDparam', '@ApiParam', '*'], '', $line));
            $parts = $this->multiExplode([' ', '|'], $line);

            if (count($parts) <= 0) {
                continue;
            }

            $params[$parts[0]] = array_values(array_filter($parts, fn($item) => $item !== $parts[0]));
        }

        return $params;
    }

    /**
     * Parse response codes from @LRDresponses or @ApiResponses annotations.
     *
     * @return string[]
     */
    private function parseResponses(string $docComment): array
    {
        $params = [];

        foreach (explode("\n", $docComment) as $line) {
            if (!Str::contains($line, ['@LRDresponses', '@ApiResponses'])) {
                continue;
            }

            $line = trim(Str::replace(['@LRDresponses', '@ApiResponses', '*'], '', $line));
            $params = $this->multiExplode([' ', '|'], $line);
        }

        if (count($params) === 0) {
            return config('api-lens.default_responses', []);
        }

        return $params;
    }

    /**
     * Parse tags from @ApiTag annotations.
     *
     * @return string[]
     */
    private function parseTags(string $docComment): array
    {
        $tags = [];

        foreach (explode("\n", $docComment) as $line) {
            if (!Str::contains($line, '@ApiTag')) {
                continue;
            }

            $line = trim(Str::replace(['@ApiTag', '*'], '', $line));
            $tags = array_merge($tags, $this->multiExplode([' ', ',', '|'], $line));
        }

        return array_values(array_filter($tags));
    }

    /**
     * Parse summary from @ApiSummary annotation.
     */
    private function parseSummary(string $docComment): ?string
    {
        foreach (explode("\n", $docComment) as $line) {
            if (!Str::contains($line, '@ApiSummary')) {
                continue;
            }

            return trim(Str::replace(['@ApiSummary', '*'], '', $line));
        }

        return null;
    }

    /**
     * Parse deprecation from @ApiDeprecated annotation.
     */
    private function parseDeprecated(string $docComment): ?string
    {
        foreach (explode("\n", $docComment) as $line) {
            if (!Str::contains($line, '@ApiDeprecated')) {
                continue;
            }

            $value = trim(Str::replace(['@ApiDeprecated', '*'], '', $line));
            return $value ?: 'deprecated';
        }

        return null;
    }

    private function getDocComment(ReflectionMethod $method): string
    {
        $doc = $method->getDocComment();
        return $doc === false ? '' : $doc;
    }

    /**
     * @param array<non-empty-string> $delimiters
     * @return string[]
     */
    private function multiExplode(array $delimiters, string $string): array
    {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        return array_filter(explode($delimiters[0], $ready), fn($s) => $s !== '');
    }
}
