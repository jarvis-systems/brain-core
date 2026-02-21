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
                ->example('Report delegation failure details to user (agent name, task, error reason)')->key('action')
                ->example('Try alternative agent from same domain if available')->key('fallback');

            $this->guideline('error-agent-timeout')
                ->text('Agent exceeded execution time limit.')
                ->example('Agent taking excessively long to respond or appears stuck')->key('trigger')
                ->example('Abort agent execution and retrieve partial results if available')->key('response')
                ->example('Report timeout to user with agent name and elapsed time')->key('action')
                ->example('Retry with reduced scope or delegate to different agent')->key('fallback');

            $this->guideline('error-invalid-response')
                ->text('Agent response failed validation checks.')
                ->example('Response validation failed semantic, structural, or policy checks')->key('trigger')
                ->example('Request agent clarification with specific validation failure details')->key('response')
                ->example('Report validation failure to user with specific failure reasons')->key('action')
                ->example('Re-delegate task if clarification fails or response quality unrecoverable')->key('fallback');

            $this->guideline('error-context-loss')
                ->text('Brain context corrupted or lost during delegation.')
                ->example('Conversation compacted unexpectedly, or agent returned incoherent state')->key('trigger')
                ->example('Re-read critical context from source files or vector memory')->key('response')
                ->example('Verify understanding of current task before resuming')->key('action')
                ->example('Abort current task and notify user if context unrecoverable')->key('fallback');

            $this->guideline('error-resource-exceeded')
                ->text('Brain context feels overloaded during operation.')
                ->example('Context window filling up, responses becoming incoherent, or repeated failures')->key('trigger')
                ->example('Summarize progress and reduce active context')->key('response')
                ->example('Commit partial progress and defer remaining work')->key('action')
                ->example('Resume after context freed up or in new session')->key('fallback');
        }

        // === ALWAYS-ON: Escalation policy ===

        $this->guideline('escalation-policy')
            ->text('Error escalation guidelines for Brain operations.')
            ->example('Standard errors: Log, apply fallback, continue operations')->key('standard')
            ->example('Critical errors: Pause current operation, inform user, request guidance')->key('critical')
            ->example('Unrecoverable errors: Abort task, notify user, trigger manual review')->key('unrecoverable');
    }
}
