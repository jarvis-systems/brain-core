<?php

declare(strict_types=1);

namespace BrainCore\Support;

use BrainCore\Core;
use Illuminate\Support\Facades\Facade;

/**
 * @see Core
 */
class Brain extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Core::class;
    }
}
