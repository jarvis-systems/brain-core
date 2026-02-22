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
    public function globalInput(string|array $text): Input
    {
        /** @var Input $input */
        $input = $this->findOrCreateChild(Input::class);

        return $input->text(is_array($text) ? MD::fromArray($text) : $text);
    }
}
