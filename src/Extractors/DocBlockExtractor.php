<?php

namespace ApiLens\Extractors;

use ReflectionClass;
use ReflectionMethod;

class DocBlockExtractor
{
    /**
     * Extract @api-lens annotations and PHPDoc from a controller method.
     */
    public function extract(string $controllerClass, string $methodName): array
    {
        $result = [
            'summary' => null,
            'description' => null,
            'deprecated' => false,
            'deprecated_since' => null,
            'tags' => [],
            'group' => null,
            'auth_type' => null,
            'response_codes' => [],
        ];

        if (!class_exists($controllerClass)) {
            return $result;
        }

        try {
            $reflection = new ReflectionClass($controllerClass);

            if (!$reflection->hasMethod($methodName)) {
                return $result;
            }

            $method = $reflection->getMethod($methodName);
            $docComment = $method->getDocComment();

            if (!$docComment) {
                return $result;
            }

            return $this->parseDocBlock($docComment);
        } catch (\Throwable) {
            return $result;
        }
    }

    /**
     * Parse a PHPDoc block and extract API documentation.
     */
    protected function parseDocBlock(string $docComment): array
    {
        $result = [
            'summary' => null,
            'description' => null,
            'deprecated' => false,
            'deprecated_since' => null,
            'tags' => [],
            'group' => null,
            'auth_type' => null,
            'response_codes' => [],
        ];

        $lines = explode("\n", $docComment);
        $descriptionLines = [];
        $foundFirstTag = false;

        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, '/* ');
            $line = rtrim($line, ' */');
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Parse @tags
            if (str_starts_with($line, '@')) {
                $foundFirstTag = true;
                $this->parseTag($line, $result);
                continue;
            }

            // Before first tag = summary/description
            if (!$foundFirstTag) {
                if ($result['summary'] === null) {
                    $result['summary'] = $line;
                } else {
                    $descriptionLines[] = $line;
                }
            }
        }

        if (!empty($descriptionLines)) {
            $result['description'] = implode(' ', $descriptionLines);
        }

        return $result;
    }

    /**
     * Parse a single @tag line.
     */
    protected function parseTag(string $line, array &$result): void
    {
        // @deprecated since v2.0
        if (preg_match('/@deprecated\s*(.*)?/i', $line, $matches)) {
            $result['deprecated'] = true;
            $result['deprecated_since'] = trim($matches[1] ?? '');
        }

        // @api-lens-group Users
        if (preg_match('/@api-lens-group\s+(.+)/i', $line, $matches)) {
            $result['group'] = trim($matches[1]);
        }

        // @api-lens-auth bearer|basic|api-key
        if (preg_match('/@api-lens-auth\s+(.+)/i', $line, $matches)) {
            $result['auth_type'] = trim($matches[1]);
        }

        // @api-lens-tag {tagname}
        if (preg_match('/@api-lens-tag\s+(.+)/i', $line, $matches)) {
            $result['tags'][] = trim($matches[1]);
        }

        // @api-lens-response {code} {description}
        if (preg_match('/@api-lens-response\s+(\d+)\s*(.*)?/i', $line, $matches)) {
            $result['response_codes'][] = [
                'code' => (int)$matches[1],
                'description' => trim($matches[2] ?? ''),
            ];
        }
    }

    /**
     * Extract class-level documentation. (Group, auth from controller class docblock)
     */
    public function extractClassDoc(string $controllerClass): array
    {
        $result = [
            'group' => null,
            'auth_type' => null,
            'tags' => [],
        ];

        if (!class_exists($controllerClass)) {
            return $result;
        }

        try {
            $reflection = new ReflectionClass($controllerClass);
            $docComment = $reflection->getDocComment();

            if (!$docComment) {
                return $result;
            }

            $parsed = $this->parseDocBlock($docComment);

            return [
                'group' => $parsed['group'],
                'auth_type' => $parsed['auth_type'],
                'tags' => $parsed['tags'],
            ];
        } catch (\Throwable) {
            return $result;
        }
    }
}
