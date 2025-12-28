<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Context;
use BrainCore\MD;

trait ContextTrait
{
    /**
     * Soft standards — planning, minimal changes.
     *
     * @param  string|array  $text
     * @return \BrainCore\Blueprints\Context
     */
    public function globalContext(string|array $text): Context
    {
        return $this->findOrCreateChild(Context::class)
            ->text(is_array($text) ? MD::fromArray($text) : $text);
    }
}
