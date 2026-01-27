<?php

declare(strict_types=1);

namespace BrainCore\Blueprints;

use BrainCore\Architectures\BlueprintArchitecture;

/**
 * Mission blueprint for Agents.
 * Semantic anchor: agent's goal and role.
 */
class Mission extends BlueprintArchitecture
{
    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'mission';
    }
}
