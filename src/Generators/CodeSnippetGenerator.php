<?php

namespace ApiLens\Generators;

use ApiLens\Models\Endpoint;
use Illuminate\Support\Collection;

/**
 * Generates code snippets in multiple programming languages for each endpoint.
 * Supports: cURL, PHP (Guzzle), JavaScript (Fetch/Axios), Python (requests), Go, Dart/Flutter
 *
 * This is a MAJOR improvement over laravel-request-docs which only has cURL.
 */
class CodeSnippetGenerator
{
    /**
     * Generate code snippets for all endpoints.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function generate(Collection $endpoints): Collection
    {
        $languages = config('api-lens.code_snippets', ['curl', 'javascript', 'php', 'python']);

        foreach ($endpoints as $endpoint) {
            $snippets = [];

            foreach ($languages as $language) {
                $snippets[$language] = match ($language) {
                    'curl'       => $this->generateCurl($endpoint),
                    'javascript' => $this->generateJavaScript($endpoint),
                    'php'        => $this->generatePhp($endpoint),
                    'python'     => $this->generatePython($endpoint),
                    'go'         => $this->generateGo($endpoint),
                    'dart'       => $this->generateDart($endpoint),
                    default      => '',
                };
            }

            $endpoint->setCodeSnippets($snippets);
        }

        return $endpoints;
    }

    /**
     * Generate cURL command.
     */
    private function generateCurl(Endpoint $endpoint): string
    {
        $method = strtoupper($endpoint->getHttpMethod());
        $url = $this->buildUrl($endpoint);
        $lines = [];

        $lines[] = "curl -X {$method} \\";
        $lines[] = "  '{$url}' \\";
        $lines[] = "  -H 'Content-Type: application/json' \\";
        $lines[] = "  -H 'Accept: application/json'";

        if ($endpoint->getAuthType()) {
            $lines[count($lines) - 1] .= ' \\';
            $lines[] = "  -H 'Authorization: Bearer YOUR_TOKEN'";
        }

        $rules = $endpoint->getRules();
        if (!empty($rules) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $lines[count($lines) - 1] .= ' \\';
            $body = $this->generateExampleBody($rules);
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $lines[] = "  -d '{$jsonBody}'";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate JavaScript Fetch API code.
     */
    private function generateJavaScript(Endpoint $endpoint): string
    {
        $method = strtoupper($endpoint->getHttpMethod());
        $url = $this->buildUrl($endpoint);

        $code = "const response = await fetch('{$url}', {\n";
        $code .= "  method: '{$method}',\n";
        $code .= "  headers: {\n";
        $code .= "    'Content-Type': 'application/json',\n";
        $code .= "    'Accept': 'application/json',\n";

        if ($endpoint->getAuthType()) {
            $code .= "    'Authorization': 'Bearer YOUR_TOKEN',\n";
        }

        $code .= "  },\n";

        $rules = $endpoint->getRules();
        if (!empty($rules) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = $this->generateExampleBody($rules);
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "  body: JSON.stringify({$jsonBody}),\n";
        }

        $code .= "});\n\n";
        $code .= "const data = await response.json();\n";
        $code .= "console.log(data);";

        return $code;
    }

    /**
     * Generate PHP Guzzle HTTP code.
     */
    private function generatePhp(Endpoint $endpoint): string
    {
        $method = strtolower($endpoint->getHttpMethod());
        $url = $this->buildUrl($endpoint);

        $code = "\$client = new \\GuzzleHttp\\Client();\n\n";
        $code .= "\$response = \$client->{$method}('{$url}', [\n";
        $code .= "    'headers' => [\n";
        $code .= "        'Content-Type' => 'application/json',\n";
        $code .= "        'Accept' => 'application/json',\n";

        if ($endpoint->getAuthType()) {
            $code .= "        'Authorization' => 'Bearer YOUR_TOKEN',\n";
        }

        $code .= "    ],\n";

        $rules = $endpoint->getRules();
        if (!empty($rules) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $body = $this->generateExampleBody($rules);
            $code .= "    'json' => " . $this->arrayToPhpCode($body, 2) . ",\n";
        }

        $code .= "]);\n\n";
        $code .= "\$data = json_decode(\$response->getBody(), true);";

        return $code;
    }

    /**
     * Generate Python requests code.
     */
    private function generatePython(Endpoint $endpoint): string
    {
        $method = strtolower($endpoint->getHttpMethod());
        $url = $this->buildUrl($endpoint);

        $code = "import requests\n\n";
        $code .= "headers = {\n";
        $code .= "    'Content-Type': 'application/json',\n";
        $code .= "    'Accept': 'application/json',\n";

        if ($endpoint->getAuthType()) {
            $code .= "    'Authorization': 'Bearer YOUR_TOKEN',\n";
        }

        $code .= "}\n\n";

        $rules = $endpoint->getRules();
        if (!empty($rules) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $body = $this->generateExampleBody($rules);
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "payload = {$jsonBody}\n\n";
            $code .= "response = requests.{$method}('{$url}', json=payload, headers=headers)\n";
        } else {
            $code .= "response = requests.{$method}('{$url}', headers=headers)\n";
        }

        $code .= "print(response.json())";

        return $code;
    }

    /**
     * Generate Go net/http code.
     */
    private function generateGo(Endpoint $endpoint): string
    {
        $method = strtoupper($endpoint->getHttpMethod());
        $url = $this->buildUrl($endpoint);

        $code = "package main\n\n";
        $code .= "import (\n";
        $code .= "    \"fmt\"\n";
        $code .= "    \"io\"\n";
        $code .= "    \"net/http\"\n";

        $rules = $endpoint->getRules();
        $hasBody = !empty($rules) && in_array($method, ['POST', 'PUT', 'PATCH']);

        if ($hasBody) {
            $code .= "    \"bytes\"\n";
            $code .= "    \"encoding/json\"\n";
        }

        $code .= ")\n\n";
        $code .= "func main() {\n";

        if ($hasBody) {
            $body = $this->generateExampleBody($rules);
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "    payload := []byte(`{$jsonBody}`)\n";
            $code .= "    req, _ := http.NewRequest(\"{$method}\", \"{$url}\", bytes.NewBuffer(payload))\n";
        } else {
            $code .= "    req, _ := http.NewRequest(\"{$method}\", \"{$url}\", nil)\n";
        }

        $code .= "    req.Header.Set(\"Content-Type\", \"application/json\")\n";
        $code .= "    req.Header.Set(\"Accept\", \"application/json\")\n";

        if ($endpoint->getAuthType()) {
            $code .= "    req.Header.Set(\"Authorization\", \"Bearer YOUR_TOKEN\")\n";
        }

        $code .= "\n    client := &http.Client{}\n";
        $code .= "    resp, _ := client.Do(req)\n";
        $code .= "    defer resp.Body.Close()\n\n";
        $code .= "    body, _ := io.ReadAll(resp.Body)\n";
        $code .= "    fmt.Println(string(body))\n";
        $code .= "}";

        return $code;
    }

    /**
     * Generate Dart/Flutter http code.
     */
    private function generateDart(Endpoint $endpoint): string
    {
        $method = strtolower($endpoint->getHttpMethod());
        $url = $this->buildUrl($endpoint);

        $code = "import 'dart:convert';\n";
        $code .= "import 'package:http/http.dart' as http;\n\n";
        $code .= "Future<void> fetchData() async {\n";
        $code .= "  final uri = Uri.parse('{$url}');\n";
        $code .= "  final headers = {\n";
        $code .= "    'Content-Type': 'application/json',\n";
        $code .= "    'Accept': 'application/json',\n";

        if ($endpoint->getAuthType()) {
            $code .= "    'Authorization': 'Bearer YOUR_TOKEN',\n";
        }

        $code .= "  };\n\n";

        $rules = $endpoint->getRules();
        if (!empty($rules) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $body = $this->generateExampleBody($rules);
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $code .= "  final body = jsonEncode({$jsonBody});\n\n";
            $code .= "  final response = await http.{$method}(uri, headers: headers, body: body);\n";
        } else {
            $code .= "  final response = await http.{$method}(uri, headers: headers);\n";
        }

        $code .= "  print(jsonDecode(response.body));\n";
        $code .= "}";

        return $code;
    }

    /**
     * Build a full URL with path parameters replaced by example values.
     */
    private function buildUrl(Endpoint $endpoint): string
    {
        $baseUrl = config('api-lens.open_api.server_url', config('app.url', 'http://localhost'));
        $uri = $endpoint->getUri();

        // Replace path parameters with example values
        foreach ($endpoint->getPathParameters() as $param => $rules) {
            $exampleValue = $this->getExampleValueForPathParam($param, $rules);
            $uri = str_replace('{' . $param . '}', $exampleValue, $uri);
            $uri = str_replace('{' . $param . '?}', $exampleValue, $uri);
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($uri, '/');
    }

    /**
     * Generate example value for a path parameter.
     */
    private function getExampleValueForPathParam(string $name, array $rules): string
    {
        $ruleStr = is_array($rules) ? implode('|', $rules) : $rules;

        if (str_contains($name, 'id') || str_contains($ruleStr, 'integer')) {
            return '1';
        }

        if (str_contains($name, 'slug')) {
            return 'example-slug';
        }

        if (str_contains($name, 'uuid')) {
            return '550e8400-e29b-41d4-a716-446655440000';
        }

        return 'value';
    }

    /**
     * Generate an example request body based on validation rules.
     *
     * @param array<string, string[]> $rules
     * @return array<string, mixed>
     */
    private function generateExampleBody(array $rules): array
    {
        $body = [];

        foreach ($rules as $attribute => $attrRules) {
            $ruleStr = is_array($attrRules) ? implode('|', $attrRules) : $attrRules;
            $body[$attribute] = $this->getExampleValue($attribute, $ruleStr);
        }

        return $body;
    }

    /**
     * Generate an example value based on field name and validation rules.
     */
    private function getExampleValue(string $attribute, string $rules): mixed
    {
        // Boolean
        if (str_contains($rules, 'boolean')) {
            return true;
        }

        // Integer
        if (str_contains($rules, 'integer') || str_contains($rules, 'numeric')) {
            if (str_contains($attribute, 'id')) {
                return 1;
            }
            if (str_contains($attribute, 'age')) {
                return 25;
            }
            if (str_contains($attribute, 'quantity') || str_contains($attribute, 'count')) {
                return 10;
            }
            if (str_contains($attribute, 'price') || str_contains($attribute, 'amount')) {
                return 99.99;
            }
            return 42;
        }

        // Array
        if (str_contains($rules, 'array')) {
            return [];
        }

        // File
        if (str_contains($rules, 'file') || str_contains($rules, 'image')) {
            return '(binary)';
        }

        // Email
        if (str_contains($rules, 'email') || str_contains($attribute, 'email')) {
            return 'user@example.com';
        }

        // URL
        if (str_contains($rules, 'url') || str_contains($attribute, 'url') || str_contains($attribute, 'website')) {
            return 'https://example.com';
        }

        // Password
        if (str_contains($attribute, 'password')) {
            return 'P@ssw0rd123!';
        }

        // Phone
        if (str_contains($attribute, 'phone') || str_contains($attribute, 'tel')) {
            return '+1234567890';
        }

        // Name patterns
        if ($attribute === 'name' || str_contains($attribute, 'first_name')) {
            return 'John Doe';
        }
        if (str_contains($attribute, 'last_name')) {
            return 'Doe';
        }

        // Date
        if (str_contains($rules, 'date') || str_contains($attribute, 'date') || str_contains($attribute, '_at')) {
            return '2026-01-15T10:30:00Z';
        }

        // UUID
        if (str_contains($rules, 'uuid') || str_contains($attribute, 'uuid')) {
            return '550e8400-e29b-41d4-a716-446655440000';
        }

        // Generic string
        if (str_contains($attribute, 'title')) {
            return 'Example Title';
        }
        if (str_contains($attribute, 'description') || str_contains($attribute, 'content') || str_contains($attribute, 'body')) {
            return 'This is an example description.';
        }
        if (str_contains($attribute, 'address')) {
            return '123 Main St, City, Country';
        }
        if (str_contains($attribute, 'slug')) {
            return 'example-slug';
        }

        // Nullable
        if (str_contains($rules, 'nullable')) {
            return null;
        }

        return 'string';
    }

    /**
     * Convert a PHP array to code string representation.
     */
    private function arrayToPhpCode(array $array, int $indent = 1): string
    {
        $spaces = str_repeat('    ', $indent);
        $innerSpaces = str_repeat('    ', $indent + 1);
        $code = "[\n";

        foreach ($array as $key => $value) {
            $code .= $innerSpaces;

            if (is_string($key)) {
                $code .= "'{$key}' => ";
            }

            if (is_array($value)) {
                $code .= $this->arrayToPhpCode($value, $indent + 1);
            } elseif (is_string($value)) {
                $code .= "'{$value}'";
            } elseif (is_bool($value)) {
                $code .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $code .= 'null';
            } else {
                $code .= $value;
            }

            $code .= ",\n";
        }

        $code .= $spaces . "]";

        return $code;
    }
}
