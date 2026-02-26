<?php

namespace ApiLens\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ApiLens\ApiLensServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiLensServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ApiLens' => \ApiLens\ApiLensFacade::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        $router->get('api/users', function () {
            return response()->json(['users' => []]);
        })->name('api.users.index');

        $router->post('api/users', function () {
            return response()->json(['user' => []], 201);
        })->name('api.users.store');
    }
}
