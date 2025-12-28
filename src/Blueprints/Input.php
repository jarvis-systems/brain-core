<?php

declare(strict_types=1);

namespace BrainCore\Blueprints;

use BrainCore\Architectures\BlueprintArchitecture;

class Input extends BlueprintArchitecture
{
    /**
     * @param  non-empty-string|null  $id
     */
    public function __construct(
        protected string|null $id,
    ) {
    }

    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'input';
    }
}
