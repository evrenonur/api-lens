<?php

namespace ApiLens;

use ApiLens\Extractors\DocBlockExtractor;
use ApiLens\Extractors\ResponseExtractor;
use ApiLens\Extractors\RouteExtractor;
use ApiLens\Extractors\RuleExtractor;
use ApiLens\Generators\CodeSnippetGenerator;
use ApiLens\Generators\HumanReadableRules;
use ApiLens\Models\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Main API Lens engine.
 * Orchestrates route extraction, rule parsing, response schema detection,
 * code snippet generation, and human-readable rule translation.
 */
class ApiLens
{
    private RouteExtractor $routeExtractor;
    private RuleExtractor $ruleExtractor;
    private ResponseExtractor $responseExtractor;
    private DocBlockExtractor $docBlockExtractor;
    private CodeSnippetGenerator $codeSnippetGenerator;
    private HumanReadableRules $humanReadableRules;

    public function __construct(
        RouteExtractor $routeExtractor,
        RuleExtractor $ruleExtractor,
        ResponseExtractor $responseExtractor,
        DocBlockExtractor $docBlockExtractor,
        CodeSnippetGenerator $codeSnippetGenerator,
        HumanReadableRules $humanReadableRules
    ) {
        $this->routeExtractor = $routeExtractor;
        $this->ruleExtractor = $ruleExtractor;
        $this->responseExtractor = $responseExtractor;
        $this->docBlockExtractor = $docBlockExtractor;
        $this->codeSnippetGenerator = $codeSnippetGenerator;
        $this->humanReadableRules = $humanReadableRules;
    }

    /**
     * Get all endpoints with full metadata.
     *
     * @return Collection<int, Endpoint>
     */
    public function getEndpoints(
        bool $showGet = true,
        bool $showPost = true,
        bool $showPut = true,
        bool $showPatch = true,
        bool $showDelete = true,
        bool $showHead = false
    ): Collection {
        // 1. Determine which HTTP methods to include
        $methods = array_values(array_filter([
            $showGet ? Request::METHOD_GET : null,
            $showPost ? Request::METHOD_POST : null,
            $showPut ? Request::METHOD_PUT : null,
            $showPatch ? Request::METHOD_PATCH : null,
            $showDelete ? Request::METHOD_DELETE : null,
            $showHead ? Request::METHOD_HEAD : null,
        ]));

        // 2. Extract routes
        $endpoints = $this->routeExtractor->extract($methods);

        // 3. Extract validation rules from request classes
        $endpoints = $this->ruleExtractor->extract($endpoints);

        // 4. Extract @api-lens-* annotations from PHPDoc blocks
        $endpoints = $this->extractDocBlocks($endpoints);

        // 5. Extract response schemas from resource classes
        $endpoints = $this->responseExtractor->extract($endpoints);

        // 6. Generate code snippets in multiple languages
        $endpoints = $this->codeSnippetGenerator->generate($endpoints);

        // 7. Generate human-readable rule descriptions
        $endpoints = $this->humanReadableRules->generate($endpoints);

        return $endpoints;
    }

    /**
     * Apply DocBlockExtractor to all endpoints.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    private function extractDocBlocks(Collection $endpoints): Collection
    {
        foreach ($endpoints as $endpoint) {
            if ($endpoint->isClosure()) {
                continue;
            }

            try {
                $docData = $this->docBlockExtractor->extract(
                    $endpoint->getControllerFullPath(),
                    $endpoint->getMethod()
                );

                // Apply summary if not already set
                if (!empty($docData['summary']) && !$endpoint->getSummary()) {
                    $endpoint->setSummary($docData['summary']);
                }

                // Apply description
                if (!empty($docData['description']) && !$endpoint->getDescription()) {
                    $endpoint->setDescription($docData['description']);
                }

                // Apply auth type
                if (!empty($docData['auth_type']) && !$endpoint->getAuthType()) {
                    $endpoint->setAuthType($docData['auth_type']);
                }

                // Apply deprecation
                if ($docData['deprecated'] && !$endpoint->getDeprecatedSince()) {
                    $endpoint->setDeprecatedSince($docData['deprecated_since'] ?: 'deprecated');
                }

                // Apply tags
                if (!empty($docData['tags']) && empty($endpoint->getTags())) {
                    $endpoint->setTags($docData['tags']);
                }

                // Apply response codes and extract example responses
                if (!empty($docData['response_codes'])) {
                    $responseCodes = [];
                    foreach ($docData['response_codes'] as $rc) {
                        $responseCodes[] = (string) $rc['code'];

                        // If description looks like JSON, parse it as example response
                        $desc = trim($rc['description'] ?? '');
                        if (!empty($desc) && str_starts_with($desc, '{')) {
                            $decoded = json_decode($desc, true);
                            if ($decoded !== null && empty($endpoint->getExampleResponse())) {
                                $endpoint->setExampleResponse($decoded);
                            }
                        }
                    }

                    // When explicit @api-lens-response codes exist, replace defaults
                    $endpoint->setResponses($responseCodes);
                }

                // Apply group from class-level docblock
                $classDoc = $this->docBlockExtractor->extractClassDoc(
                    $endpoint->getControllerFullPath()
                );
                if (!empty($classDoc['group'])) {
                    $endpoint->setGroup($classDoc['group']);
                }
                if (!empty($classDoc['auth_type']) && !$endpoint->getAuthType()) {
                    $endpoint->setAuthType($classDoc['auth_type']);
                }
            } catch (\Throwable) {
                // Skip endpoints that can't be analyzed
            }
        }

        return $endpoints;
    }

    /**
     * Split endpoints by HTTP methods.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function splitByMethods(Collection $endpoints): Collection
    {
        return $this->routeExtractor->splitByMethods($endpoints);
    }

    /**
     * Sort endpoints.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function sortEndpoints(Collection $endpoints, ?string $sortBy = 'default'): Collection
    {
        return $this->routeExtractor->sortEndpoints($endpoints, $sortBy);
    }

    /**
     * Group endpoints.
     *
     * @param Collection<int, Endpoint> $endpoints
     * @return Collection<int, Endpoint>
     */
    public function groupEndpoints(Collection $endpoints, ?string $groupBy = 'default'): Collection
    {
        return $this->routeExtractor->groupEndpoints($endpoints, $groupBy);
    }
}
