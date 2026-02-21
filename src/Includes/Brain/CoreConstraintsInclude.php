<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose(<<<'PURPOSE'
Defines essential runtime constraints for Brain orchestration operations.
Simplified version focused on delegation-level limits without detailed CI/CD or agent-specific metrics.
PURPOSE
)]
class CoreConstraintsInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // === RUNTIME LIMITS ===

        $this->guideline('constraint-token-limit')
            ->text('Keep responses concise. Prefer short, focused answers over exhaustive essays.')
            ->example('If output feels excessively long, split into delegation or summarize.')->key('intent');

        $this->guideline('constraint-execution-time')
            ->text('Avoid long-running single-step operations. Break complex work into delegated subtasks.')
            ->example('If a single agent call takes too long, reduce scope or split the task.')->key('intent');
    }
}
