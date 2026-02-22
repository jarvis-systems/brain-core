<?php

declare(strict_types=1);

namespace BrainCore\Cortex;

use BrainCore\Architectures\CortexArchitecture;
use BrainCore\Blueprints\IronRule;

/**
 * Strict prohibitions/requirements with consequences for violation.
 */
class IronRules extends CortexArchitecture
{
    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'iron_rules';
    }

    /**
     * Add Rule
     *
     * @param  non-empty-string|null  $id
     * @return \BrainCore\Blueprints\IronRule
     */
    public function rule(string|null $id = null): IronRule
    {
        /** @var IronRule */
        return $this->findOrCreateChild(IronRule::class, $id);
    }
}
