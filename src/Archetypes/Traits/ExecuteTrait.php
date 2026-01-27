<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Execute;

/**
 * Execute trait for Commands.
 * Uses <execute> tag as semantic anchor for imperative actions.
 *
 * Based on prompt engineering research:
 * - XML tags work as semantic anchors
 * - Tag names should be meaningful and imperative for commands
 * - "execute" signals action, not description
 */
trait ExecuteTrait
{
    /**
     * Defines what this command executes/does.
     *
     * @param  non-empty-string  $text
     * @return static
     */
    public function execute(string $text): static
    {
        $this->createOfChild(Execute::class, text: $text);

        $this->setMeta(['executeText' => $text]);

        return $this;
    }
}
