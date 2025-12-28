<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Instructions;
use BrainCore\MD;

trait InstructionsTrait
{
    /**
     * Soft standards — planning, minimal changes.
     *
     * @param  string|array  $text
     * @return \BrainCore\Blueprints\Instructions
     */
    public function instructions(string|array $text): Instructions
    {
        return $this->findOrCreateChild(Instructions::class)
            ->text(is_array($text) ? MD::fromArray($text) : $text);
    }
}
