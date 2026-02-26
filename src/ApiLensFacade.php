<?php

namespace ApiLens;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array scan()
 * @method static array toOpenApi()
 * @method static string toJson()
 *
 * @see \ApiLens\ApiLens
 */
class ApiLensFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'api-lens';
    }
}
