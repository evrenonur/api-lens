<?php

namespace ApiLens\Extractors;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Reflector;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Extracts and analyzes path parameters from Laravel routes.
 * Handles model binding, type inference, optional parameters, and regex constraints.
 */
class PathParameterExtractor
{
    private const TYPE_MAP = [
        'bool' => 'boolean',
        'int'  => 'integer',
    ];

    /**
     * @return array<string, string>
     * @throws \ReflectionException
     */
    public function extract(Route $route): array
    {
        $pathParameters = $this->initAllParametersWithStringType($route);
        $pathParameters = $this->setParameterType($route, $pathParameters);
        $pathParameters = $this->setOptional($route, $pathParameters);
        $pathParameters = $this->mutateKeyNameWithBindingField($route, $pathParameters);

        return $this->setRegex($route, $pathParameters);
    }

    /**
     * @param array<string, string> $pathParameters
     * @return array<string, string>
     */
    private function setParameterType(Route $route, array $pathParameters): array
    {
        $bindableParameters = $this->getBindableParameters($route);

        foreach ($route->parameterNames() as $position => $parameterName) {
            if (!isset($bindableParameters[$position])) {
                continue;
            }

            $bindableParameter = $bindableParameters[$position];

            if ($bindableParameter['class'] === null) {
                $pathParameters[$parameterName] = $this->getParameterType($bindableParameter['parameter']);
                continue;
            }

            $resolved = $bindableParameter['class'];

            if (!$resolved->isSubclassOf(Model::class)) {
                continue;
            }

            if ($bindableParameter['parameter']->getName() !== $parameterName) {
                continue;
            }

            $model = $resolved->newInstance();

            $bindingField = $route->bindingFieldFor($parameterName);
            if ($bindingField !== null && $bindingField !== $model->getKeyName()) {
                continue;
            }

            if ($model->getKeyName() !== $model->getRouteKeyName()) {
                continue;
            }

            $pathParameters[$parameterName] = self::TYPE_MAP[$model->getKeyType()] ?? $model->getKeyType();
        }

        return $pathParameters;
    }

    /**
     * @return array<int, string>
     */
    private function getOptionalParameterNames(string $uri): array
    {
        preg_match_all('/\{(\w+?)\?\}/', $uri, $matches);
        return $matches[1] ?? [];
    }

    /**
     * @return array<int, array{parameter: ReflectionParameter, class: ReflectionClass|null}>
     * @throws \ReflectionException
     */
    private function getBindableParameters(Route $route): array
    {
        $parameters = [];

        foreach ($route->signatureParameters() as $reflectionParameter) {
            $className = Reflector::getParameterClassName($reflectionParameter);

            if ($className === null) {
                $parameters[] = [
                    'parameter' => $reflectionParameter,
                    'class'     => null,
                ];
                continue;
            }

            $reflectionClass = new ReflectionClass($className);

            if (!$reflectionClass->implementsInterface(UrlRoutable::class)) {
                continue;
            }

            $parameters[] = [
                'parameter' => $reflectionParameter,
                'class'     => $reflectionClass,
            ];
        }

        return $parameters;
    }

    /**
     * @param array<string, string> $pathParameters
     * @return array<string, string>
     */
    private function setOptional(Route $route, array $pathParameters): array
    {
        $optionalParameters = $this->getOptionalParameterNames($route->uri);

        foreach ($pathParameters as $parameter => $rule) {
            if (in_array($parameter, $optionalParameters)) {
                $pathParameters[$parameter] .= '|nullable';
                continue;
            }
            $pathParameters[$parameter] .= '|required';
        }

        return $pathParameters;
    }

    /**
     * @param array<string, string> $pathParameters
     * @return array<string, string>
     */
    private function setRegex(Route $route, array $pathParameters): array
    {
        foreach ($pathParameters as $parameter => $rule) {
            if (!isset($route->wheres[$parameter])) {
                continue;
            }
            $pathParameters[$parameter] .= '|regex:/' . $route->wheres[$parameter] . '/';
        }

        return $pathParameters;
    }

    /**
     * @return array<string, string>
     */
    private function initAllParametersWithStringType(Route $route): array
    {
        return array_fill_keys($route->parameterNames(), 'string');
    }

    private function getParameterType(ReflectionParameter $methodParameter): string
    {
        $reflectionNamedType = $methodParameter->getType();

        if ($reflectionNamedType === null) {
            return 'string';
        }

        if (!$reflectionNamedType instanceof ReflectionNamedType) {
            return 'string';
        }

        return self::TYPE_MAP[$reflectionNamedType->getName()] ?? $reflectionNamedType->getName();
    }

    /**
     * @param array<string, string> $pathParameters
     * @return array<string, string>
     */
    private function mutateKeyNameWithBindingField(Route $route, array $pathParameters): array
    {
        $mutatedPath = [];

        foreach ($route->parameterNames() as $name) {
            $bindingName = $route->bindingFieldFor($name);

            if ($bindingName === null) {
                $mutatedPath[$name] = $pathParameters[$name];
                continue;
            }

            $mutatedPath["$name:$bindingName"] = $pathParameters[$name];
        }

        return $mutatedPath;
    }
}
