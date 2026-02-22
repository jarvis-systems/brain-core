<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Guideline;
use BrainCore\Cortex\Guidelines;

trait GuidelinesTrait
{
    /**
     * Soft standards — planning, minimal changes.
     *
     * @return \BrainCore\Cortex\Guidelines
     */
    public function guidelines(): Guidelines
    {
        /** @var Guidelines */
        return $this->findOrCreateChild(Guidelines::class);
    }

    /**
     * Adds a new guideline.
     *
     * @param  non-empty-string|null  $id
     * @return \BrainCore\Blueprints\Guideline
     */
    public function guideline(string|null $id = null): Guideline
    {
        return $this->guidelines()->guideline($id);
    }
}
