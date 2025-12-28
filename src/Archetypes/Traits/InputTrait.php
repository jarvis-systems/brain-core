<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Input;
use BrainCore\MD;

trait InputTrait
{
    /**
     * Soft standards — planning, minimal changes.
     *
     * @param  string|array  $text
     * @return \BrainCore\Blueprints\Input
     */
    public function input(string|array $text): Input
    {
        return $this->findOrCreateChild(Input::class)
            ->text(is_array($text) ? MD::fromArray($text) : $text);
    }
}
