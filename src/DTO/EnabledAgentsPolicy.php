<?php

declare(strict_types=1);

namespace BrainCore\DTO;

/**
 * Data Transfer Object for enabled agents policy.
 */
class EnabledAgentsPolicy
{
    /**
     * @param string $version Policy version (e.g., 1.0.0)
     * @param string[] $enabled Array of enabled agent IDs
     */
    public function __construct(
        public readonly string $version,
        public readonly array $enabled
    ) {
    }

    /**
     * Checks if an agent is enabled.
     *
     * @param string $id Agent ID
     * @return bool
     */
    public function isEnabled(string $id): bool
    {
        return in_array($id, $this->enabled, true);
    }
}
