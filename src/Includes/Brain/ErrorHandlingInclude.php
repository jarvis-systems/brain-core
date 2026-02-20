<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;

#[Purpose(<<<'PURPOSE'
Defines basic error handling for Brain delegation operations.
Provides simple fallback guidelines for common delegation failures without detailed agent-level error procedures.
PURPOSE
)]
class ErrorHandlingInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // === DEEP-COGNITIVE-ONLY: Detailed error handling guidelines ===

        if ($this->isDeepCognitive()) {
            $this->guideline('error-delegation-failed')
                ->text('Delegation to agent failed or rejected.')
                ->example('Agent unavailable, context mismatch, or permission denied')->key('trigger')
                ->example('Reassign task to AgentMaster for redistribution')->key('response')
                ->example('Log delegation failure with agent_id, task_id, and error code')->key('action')
                ->example('Try alternative agent from same domain if available')->key('fallback');

            $this->guideline('error-agent-timeout')
                ->text('Agent exceeded execution time limit.')
                ->example('Agent execution time > max-execution-seconds from constraints')->key('trigger')
                ->example('Abort agent execution and retrieve partial results if available')->key('response')
                ->example('Log timeout event with agent_id and elapsed time')->key('action')
                ->example('Retry with reduced scope or delegate to different agent')->key('fallback');

            $this->guideline('error-invalid-response')
                ->text('Agent response failed validation checks.')
                ->example('Response validation failed semantic, structural, or policy checks')->key('trigger')
                ->example('Request agent clarification with specific validation failure details')->key('response')
                ->example('Log validation failure with response_id and failure reasons')->key('action')
                ->example('Re-delegate task if clarification fails or response quality unrecoverable')->key('fallback');

            $this->guideline('error-context-loss')
                ->text('Brain context corrupted or lost during delegation.')
                ->example('Context hash mismatch, memory desync, or state corruption detected')->key('trigger')
                ->example('Restore context from last stable checkpoint in vector memory')->key('response')
                ->example('Validate restored context integrity before resuming operations')->key('action')
                ->example('Abort current task and notify user if context unrecoverable')->key('fallback');

            $this->guideline('error-resource-exceeded')
                ->text('Brain exceeded resource limits during operation.')
                ->example('Token usage ≥ 90%, memory usage > threshold, or constraint violation')->key('trigger')
                ->example('Trigger compaction policy to preserve critical reasoning')->key('response')
                ->example('Commit partial progress and defer remaining work')->key('action')
                ->example('Resume from checkpoint after resource limits restored')->key('fallback');
        }

        // === ALWAYS-ON: Escalation policy ===

        $this->guideline('escalation-policy')
            ->text('Error escalation guidelines for Brain operations.')
            ->example('Standard errors: Log, apply fallback, continue operations')->key('standard')
            ->example('Critical errors: Suspend operation, restore state, notify AgentMaster')->key('critical')
            ->example('Unrecoverable errors: Abort task, notify user, trigger manual review')->key('unrecoverable');
    }
}
