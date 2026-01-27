<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Architectures\Traits\ExtractMetaAttributesTrait;
use BrainCore\Attributes\Includes;
use BrainCore\Attributes\Purpose;

/**
 * Extract attributes for Agents.
 * Maps #[Purpose] → mission() method for <mission> tag rendering.
 */
trait ExtractAgentAttributesTrait
{
    use ExtractMetaAttributesTrait;

    /**
     * Extract class attributes.
     *
     * @return void
     */
    protected function extractAttributes(): void
    {
        $this->extractMetaAttributes();

        $reflection = static::reflection();
        $purposeAttributes = $reflection->getAttributes(Purpose::class);
        $includesAttributes = $reflection->getAttributes(Includes::class);

        // Agents: #[Purpose] → mission() → <mission> tag
        foreach ($purposeAttributes as $attribute) {
            /** @var Purpose $purposeInstance */
            $purposeInstance = $attribute->newInstance();
            $this->mission(
                $purposeInstance->getPurpose()
            );
        }

        foreach ($includesAttributes as $attribute) {
            /** @var Includes $includesInstance */
            $includesInstance = $attribute->newInstance();
            foreach ($includesInstance->classes as $includeClass) {
                $this->include($includeClass);
            }
        }
    }
}
