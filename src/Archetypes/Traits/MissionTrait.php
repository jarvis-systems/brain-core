<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\Mission;

/**
 * Mission trait for Agents.
 * Uses <mission> tag as semantic anchor for agent's goal/role.
 *
 * Based on prompt engineering research:
 * - XML tags work as semantic anchors
 * - "mission" signals goal-oriented role, not passive description
 */
trait MissionTrait
{
    /**
     * Defines the agent's mission/goal.
     *
     * @param  non-empty-string  $text
     * @return static
     */
    public function mission(string $text): static
    {
        $this->createOfChild(Mission::class, text: $text);

        $this->setMeta(['missionText' => $text]);

        return $this;
    }
}
