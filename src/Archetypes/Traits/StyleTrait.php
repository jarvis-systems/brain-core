<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Style;

trait StyleTrait
{
    /**
     * Style and format.
     *
     * @return Style
     */
    public function style(): Style
    {
        /** @var Style */
        return $this->findOrCreateChild(Style::class);
    }
}
