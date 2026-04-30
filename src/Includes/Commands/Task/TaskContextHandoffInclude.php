<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Compact context handoff protocol for task lifecycle commands. Reuses warm context only after task snapshot checks; never replaces task_get or lifecycle gates.')]
class TaskContextHandoffInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        $this->rule('context-handoff-source-of-truth')->critical()
            ->text('Context handoff is an optimization only. It NEVER replaces the mandatory ' . VectorTaskMcp::callValidatedJson('task_get', ['task_id' => '$VECTOR_TASK_ID']) . ', status checks, comment parsing, circuit breaker, sibling/parallel checks, or finalization safety net.')
            ->why('A handoff block can be stale, absent, truncated, or copied from another task. The task MCP state is the only lifecycle source of truth.')
            ->onViolation('Discard handoff. Execute cold path from task_get.');

        $this->rule('context-handoff-freshness')->critical()
            ->text('Reuse warm context ONLY when CONTEXT_MODE is not cold, a CONTEXT_HANDOFF v1 block is visible, handoff.task_id equals $VECTOR_TASK_ID, and handoff.task_fingerprint equals the current fingerprint built after task_get from id/title/content/comment/tags/status/parent_id/parallel/order. Missing, mismatched, or uncertain fingerprint = cold path.')
            ->why('This preserves correctness while avoiding repeated docs/memory/pattern reads when the same task state is already in context.')
            ->onViolation('Set REUSE_ALLOWED=false and reload context normally.');

        $this->rule('context-handoff-compact')->high()
            ->text('Handoff output MUST be compact pointers only: IDs, file paths, hashes/fingerprints, short labels. Never paste full docs, raw command output, full task bodies, secrets, logs, or agent JSON blobs.')
            ->why('The handoff exists to save tokens. Large handoff blocks become another context bloat source.')
            ->onViolation('Replace with IDs/paths/hashes and a short summary.');

        $this->guideline('context-handoff-gate')
            ->goal('Decide cold vs warm context after mandatory task_get')
            ->example()
            ->phase(Store::as('CONTEXT_MODE', 'cold if --cold; reuse if --reuse-context; auto otherwise (--cold wins)'))
            ->phase('After task_get + comment parse, build CURRENT_TASK_FINGERPRINT from stable task fields')
            ->phase(Operator::if('CONTEXT_MODE = cold OR no CONTEXT_HANDOFF v1 OR task_id/fingerprint mismatch', [
                Store::as('REUSE_ALLOWED', 'false'),
                'Run normal context loading',
            ], [
                Store::as('REUSE_ALLOWED', 'true'),
                'Reuse handoff pointers for parent/docs/memory/pattern summaries',
            ]));

        $this->guideline('context-handoff-boundaries')
            ->goal('What may be reused')
            ->example()
            ->phase('MAY reuse: parent summary, docs paths+hashes, memory IDs, known failure summaries, discovered files, existing pattern notes')
            ->phase('MUST refresh: assigned task_get, current status, task.comment, retry counters, stuck tag, active parallel sibling state, final status check, git diff/staging scope')
            ->phase('If reused context conflicts with fresh MCP/file state → fresh state wins; record mismatch in task comment only if it changes execution');

        $this->guideline('context-handoff-output')
            ->goal('Emit compact handoff before final RESULT/NEXT')
            ->example()
            ->phase('STATUS: [handoff] CONTEXT_HANDOFF v1 task_id=$VECTOR_TASK_ID; task_fingerprint={hash}; parent_id={id|null}; parent_fingerprint={hash|null}; docs=[path:hash]; files=[paths]; memory_ids=[#ids]; failure_hash={hash|null}; patterns_hash={hash|null}; next={NEXT}')
            ->phase('Keep under 20 lines. Do not store in vector memory. Append to task.comment only as CONTEXT_HINT when cross-session recovery matters.');
    }
}
