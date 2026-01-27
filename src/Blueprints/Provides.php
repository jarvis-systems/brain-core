<?php

declare(strict_types=1);

namespace BrainCore\Blueprints;

use BrainCore\Architectures\BlueprintArchitecture;

/**
 * Provides blueprint for Includes.
 * Semantic anchor: what functionality is provided/defined.
 */
class Provides extends BlueprintArchitecture
{
    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'provides';
    }
}
