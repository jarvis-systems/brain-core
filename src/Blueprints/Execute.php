<?php

declare(strict_types=1);

namespace BrainCore\Blueprints;

use BrainCore\Architectures\BlueprintArchitecture;

/**
 * Execute blueprint for Commands.
 * Semantic anchor: imperative action - what to DO.
 */
class Execute extends BlueprintArchitecture
{
    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'execute';
    }
}
