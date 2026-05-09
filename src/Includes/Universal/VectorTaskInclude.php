<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Vector task iron rules with cookbook delegation.')]
class VectorTaskInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        $this->rule('mcp-json-only')->critical()
            ->text('ALL task operations MUST use MCP tool with JSON object payload.')
            ->why('MCP ensures embedding generation and data integrity.')
            ->onViolation(VectorTaskMcp::callValidatedJson('task_list', ['status' => 'in_progress', 'limit' => 50]));

        $this->rule('explore-before-execute')->critical()
            ->text('MUST explore task context (parent, children) BEFORE execution.')
            ->why('Prevents duplicate work, ensures alignment, discovers dependencies.')
            ->onViolation(VectorTaskMcp::callValidatedJson('task_get', ['task_id' => '{task_id}']) . ' + parent + children BEFORE task_update');

        $this->rule('parent-readonly')->critical()
            ->text('$PARENT task is READ-ONLY. NEVER update parent.')
            ->why('Parent lifecycle managed externally. Prevents loops, corruption.')
            ->onViolation('Only task_update on assigned $TASK.');

        $this->rule('timestamps-auto')->critical()
            ->text('NEVER set start_at/finish_at manually.')
            ->why('Manual values corrupt timeline.')
            ->onViolation('Remove from task_update call.');

        $this->rule('no-mode-self-switch')->critical()
            ->text('NEVER change strict/cognitive mode at runtime. Only RECOMMEND mode with risk explanation.')
            ->why('Mode is a compile-time decision. Runtime switching corrupts single-mode invariant.')
            ->onViolation('Remove mode change. Add recommendation as task comment with risk analysis.');
    }
}
