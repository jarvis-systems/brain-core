<?php

declare(strict_types=1);

namespace BrainCore\Cortex;

use BrainCore\Architectures\CortexArchitecture;
use BrainCore\Blueprints\Guideline;

/**
 * Soft standards — planning, minimal changes.
 */
class Guidelines extends CortexArchitecture
{
    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'guidelines';
    }

    /**
     * Add guideline
     *
     * @param  non-empty-string|null  $id
     * @return \BrainCore\Blueprints\Guideline
     */
    public function guideline(string|null $id = null): Guideline
    {
        /** @var Guideline */
        return $this->findOrCreateChild(Guideline::class, $id);
    }
}
