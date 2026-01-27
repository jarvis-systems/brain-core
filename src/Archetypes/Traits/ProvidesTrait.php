<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Provides;

/**
 * Provides trait for Includes.
 * Uses <provides> tag as semantic anchor for what functionality is provided.
 *
 * Based on prompt engineering research:
 * - XML tags work as semantic anchors
 * - "provides" signals contribution, not passive description
 */
trait ProvidesTrait
{
    /**
     * Defines what this include provides/contributes.
     *
     * @param  non-empty-string  $text
     * @return static
     */
    public function provides(string $text): static
    {
        $this->createOfChild(Provides::class, text: $text);

        $this->setMeta(['providesText' => $text]);

        return $this;
    }
}
