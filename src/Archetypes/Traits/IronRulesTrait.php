<?php

declare(strict_types=1);

namespace BrainCore\Archetypes\Traits;

use BrainCore\Blueprints\IronRule;
use BrainCore\Cortex\IronRules;

trait IronRulesTrait
{
    /**
     * Strict prohibitions/requirements with consequences for violation.
     *
     * @return \BrainCore\Cortex\IronRules
     */
    public function ironRules(): IronRules
    {
        /** @var IronRules */
        return $this->findOrCreateChild(IronRules::class);
    }

    /**
     * Adds a new iron rule.
     *
     * @param  non-empty-string|null  $id
     * @return \BrainCore\Blueprints\IronRule
     */
    public function rule(string|null $id): IronRule
    {
        return $this->ironRules()->rule($id);
    }
}
