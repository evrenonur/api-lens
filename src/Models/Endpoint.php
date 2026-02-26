<?php

namespace ApiLens\Models;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Represents a single API endpoint with all its metadata.
 * This is the core data model that carries route, rules, response schema,
 * code snippets, and runtime information.
 */
class Endpoint implements Arrayable, JsonSerializable
{
    private string $uri;

    /** @var string[] */
    private array $methods;

    private string $httpMethod;

    /** @var string[] */
    private array $middlewares;

    private string $controller;

    private string $controllerFullPath;

    private string $method;

    /** @var array<string, string[]> */
    private array $rules;

    /** @var array<string, string[]> */
    private array $pathParameters;

    private string $docBlock;

    /** @var string[] */
    private array $responses;

    /** @var array<string, mixed> */
    private array $responseSchema;

    /** @var array<string, string> */
    private array $codeSnippets;

    /** @var array<string, string> */
    private array $humanReadableRules;

    private string $group;

    private int $groupIndex;

    private ?string $description;

    private ?string $summary;

    /** @var string[] */
    private array $tags;

    private ?string $deprecatedSince;

    private ?string $authType;

    /** @var array{requests_per_minute?: int, requests_per_hour?: int} */
    private array $rateLimit;

    /** @var array<string, mixed> */
    private array $exampleRequest;

    /** @var array<string, mixed> */
    private array $exampleResponse;

    /**
     * @param string[] $methods
     * @param string[] $middlewares
     * @param array<string, string[]> $pathParameters
     * @param array<string, string[]> $rules
     */
    public function __construct(
        string $uri,
        array $methods,
        array $middlewares,
        string $controller,
        string $controllerFullPath,
        string $method,
        string $httpMethod = '',
        array $pathParameters = [],
        array $rules = [],
        string $docBlock = ''
    ) {
        $this->uri = $uri;
        $this->methods = $methods;
        $this->middlewares = $middlewares;
        $this->controller = $controller;
        $this->controllerFullPath = $controllerFullPath;
        $this->method = $method;
        $this->httpMethod = $httpMethod;
        $this->pathParameters = $pathParameters;
        $this->rules = $rules;
        $this->docBlock = $docBlock;
        $this->responses = [];
        $this->responseSchema = [];
        $this->codeSnippets = [];
        $this->humanReadableRules = [];
        $this->tags = [];
        $this->description = null;
        $this->summary = null;
        $this->deprecatedSince = null;
        $this->authType = null;
        $this->rateLimit = [];
        $this->exampleRequest = [];
        $this->exampleResponse = [];
    }

    // ─── URI ────────────────────────────────────────────────────────────

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    // ─── Methods ────────────────────────────────────────────────────────

    /** @return string[] */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /** @param string[] $methods */
    public function setMethods(array $methods): void
    {
        $this->methods = $methods;
    }

    // ─── HTTP Method ────────────────────────────────────────────────────

    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): void
    {
        $this->httpMethod = $httpMethod;
    }

    // ─── Middlewares ────────────────────────────────────────────────────

    /** @return string[] */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /** @param string[] $middlewares */
    public function setMiddlewares(array $middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    // ─── Controller ─────────────────────────────────────────────────────

    public function getController(): string
    {
        return $this->controller;
    }

    public function setController(string $controller): void
    {
        $this->controller = $controller;
    }

    public function getControllerFullPath(): string
    {
        return $this->controllerFullPath;
    }

    public function setControllerFullPath(string $controllerFullPath): void
    {
        $this->controllerFullPath = $controllerFullPath;
    }

    // ─── Method ─────────────────────────────────────────────────────────

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    // ─── Rules ──────────────────────────────────────────────────────────

    /** @return array<string, string[]> */
    public function getRules(): array
    {
        return $this->rules;
    }

    /** @param array<string, string[]> $rules */
    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

    /** @param array<string, string[]> $rules */
    public function mergeRules(array $rules): void
    {
        $this->rules = array_merge($this->rules, $rules);
    }

    // ─── Path Parameters ────────────────────────────────────────────────

    /** @return array<string, string[]> */
    public function getPathParameters(): array
    {
        return $this->pathParameters;
    }

    /** @param array<string, string[]> $pathParameters */
    public function setPathParameters(array $pathParameters): void
    {
        $this->pathParameters = $pathParameters;
    }

    // ─── DocBlock ───────────────────────────────────────────────────────

    public function getDocBlock(): string
    {
        return $this->docBlock;
    }

    public function setDocBlock(string $docBlock): void
    {
        $this->docBlock = $docBlock;
    }

    // ─── Responses ──────────────────────────────────────────────────────

    /** @return string[] */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /** @param string[] $responses */
    public function setResponses(array $responses): void
    {
        $this->responses = $responses;
    }

    // ─── Response Schema ────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getResponseSchema(): array
    {
        return $this->responseSchema;
    }

    /** @param array<string, mixed> $schema */
    public function setResponseSchema(array $schema): void
    {
        $this->responseSchema = $schema;
    }

    // ─── Code Snippets ──────────────────────────────────────────────────

    /** @return array<string, string> */
    public function getCodeSnippets(): array
    {
        return $this->codeSnippets;
    }

    /** @param array<string, string> $snippets */
    public function setCodeSnippets(array $snippets): void
    {
        $this->codeSnippets = $snippets;
    }

    // ─── Human Readable Rules ───────────────────────────────────────────

    /** @return array<string, string> */
    public function getHumanReadableRules(): array
    {
        return $this->humanReadableRules;
    }

    /** @param array<string, string> $rules */
    public function setHumanReadableRules(array $rules): void
    {
        $this->humanReadableRules = $rules;
    }

    // ─── Group ──────────────────────────────────────────────────────────

    public function getGroup(): string
    {
        return $this->group ?? '';
    }

    public function setGroup(string $group): void
    {
        $this->group = $group;
    }

    public function getGroupIndex(): int
    {
        return $this->groupIndex ?? 0;
    }

    public function setGroupIndex(int $index): void
    {
        $this->groupIndex = $index;
    }

    // ─── Description & Summary ──────────────────────────────────────────

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    // ─── Tags ───────────────────────────────────────────────────────────

    /** @return string[] */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param string[] $tags */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    // ─── Deprecated ─────────────────────────────────────────────────────

    public function getDeprecatedSince(): ?string
    {
        return $this->deprecatedSince;
    }

    public function setDeprecatedSince(?string $since): void
    {
        $this->deprecatedSince = $since;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecatedSince !== null;
    }

    // ─── Auth Type ──────────────────────────────────────────────────────

    public function getAuthType(): ?string
    {
        return $this->authType;
    }

    public function setAuthType(?string $type): void
    {
        $this->authType = $type;
    }

    // ─── Rate Limit ─────────────────────────────────────────────────────

    /** @return array{requests_per_minute?: int, requests_per_hour?: int} */
    public function getRateLimit(): array
    {
        return $this->rateLimit;
    }

    /** @param array{requests_per_minute?: int, requests_per_hour?: int} $rateLimit */
    public function setRateLimit(array $rateLimit): void
    {
        $this->rateLimit = $rateLimit;
    }

    // ─── Examples ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getExampleRequest(): array
    {
        return $this->exampleRequest;
    }

    /** @param array<string, mixed> $example */
    public function setExampleRequest(array $example): void
    {
        $this->exampleRequest = $example;
    }

    /** @return array<string, mixed> */
    public function getExampleResponse(): array
    {
        return $this->exampleResponse;
    }

    /** @param array<string, mixed> $example */
    public function setExampleResponse(array $example): void
    {
        $this->exampleResponse = $example;
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function isClosure(): bool
    {
        return $this->controller === '';
    }

    public function clone(): self
    {
        return clone $this;
    }

    // ─── Serialization ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [
            'uri'                  => $this->uri,
            'methods'              => $this->methods,
            'http_method'          => $this->httpMethod,
            'middlewares'          => $this->middlewares,
            'controller'           => $this->controller,
            'controller_full_path' => $this->controllerFullPath,
            'method'               => $this->method,
            'rules'                => $this->rules,
            'path_parameters'      => $this->pathParameters,
            'doc_block'            => $this->docBlock,
            'responses'            => $this->responses,
            'response_schema'      => $this->responseSchema,
            'code_snippets'        => $this->codeSnippets,
            'human_readable_rules' => $this->humanReadableRules,
            'description'          => $this->description,
            'summary'              => $this->summary,
            'tags'                 => $this->tags,
            'deprecated_since'     => $this->deprecatedSince,
            'auth_type'            => $this->authType,
            'rate_limit'           => $this->rateLimit,
            'example_request'      => $this->exampleRequest,
            'example_response'     => $this->exampleResponse,
        ];

        if (isset($this->group)) {
            $result['group'] = $this->group;
        }

        if (isset($this->groupIndex)) {
            $result['group_index'] = $this->groupIndex;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
