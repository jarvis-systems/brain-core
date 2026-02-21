<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose(<<<'PURPOSE'
Defines Brain-level validation protocol executed before any action or tool invocation.
Ensures contextual stability, policy compliance, and safety before delegating execution to agents or tools.
PURPOSE
)]
class PreActionValidationInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->rule('context-stability')->high()
            ->text('Avoid starting new delegations when context feels overloaded or compaction/correction is active.')
            ->why('Prevents unstable or overloaded context from initiating operations.')
            ->onViolation('Delay execution until context stabilizes.');

        $this->rule('authorization')->critical()
            ->text('Every tool request must match registered capabilities and authorized agents.')
            ->why('Guarantees controlled and auditable tool usage across the Brain ecosystem.')
            ->onViolation('Reject the request and escalate to AgentMaster.');

        $this->rule('delegation-depth')->high()
            ->text('No chained delegation. Brain delegates to Agent only (Brain → Agent). Agents must not re-delegate to other agents.')
            ->why('Ensures maintainable and non-recursive validation pipelines.')
            ->onViolation('Reject the chain and reassign through AgentMaster.');

        $this->guideline('validation-workflow')
            ->text('Pre-action validation workflow: stability check -> authorization -> execute.')
            ->example()
                ->phase('check', 'Verify context is stable and no active compaction/correction.')
                ->phase('authorize', 'Confirm tool is registered and agent has permission.')
                ->phase('delegate', 'Pass to agent or tool with clear task context.')
                ->phase('fallback', 'On failure: delay, reassign, or escalate to AgentMaster.');
    }
}
