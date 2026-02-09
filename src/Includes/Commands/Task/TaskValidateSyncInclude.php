<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\GrepTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Sync validation of completed vector task. Direct tools (no agents). Validates task.content requirements, code quality, tests. Cosmetic fixed inline. Functional issues → fix-tasks. Idempotent.')]
class TaskValidateSyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON RULES
        $this->rule('task-get-first')->critical()
            ->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN validate.');

        $this->rule('no-hallucination')->critical()
            ->text('NEVER output results without ACTUALLY calling tools. Fake results = CRITICAL VIOLATION.');

        $this->rule('no-delegation')->critical()
            ->text('SYNC validation = direct tools only. NO Task() delegation. Use: Read, Edit, Glob, Grep, Bash.');

        $this->rule('validation-only')->critical()
            ->text('VALIDATION reads and audits. NEVER implement or fix functional code. Functional issues → create fix-task.');

        $this->rule('auto-approve')->high()
            ->text('-y flag = auto-approve. Skip "Proceed?" but show progress.');

        // DOCUMENTATION IS LAW (from trait - validates against docs, not made-up criteria)
        $this->defineDocumentationIsLawRules();

        // PARENT INHERITANCE
        $this->rule('parent-id-mandatory')->critical()
            ->text('ALL fix-tasks MUST have parent_id = $VECTOR_TASK_ID. No orphans.')
            ->onViolation('ABORT task_create if parent_id wrong.');

        // VALIDATION SCOPE (same as async)
        $this->rule('task-scope-only')->critical()
            ->text('Validate ONLY task.content + documentation requirements. Do NOT expand scope.');

        $this->rule('docs-are-complete-spec')->critical()
            ->text('Documentation (.docs/) = COMPLETE specification. task.content may be brief. ALWAYS read docs if exist. Validate against DOCUMENTATION.')
            ->why('task.content is often summary. Full spec in docs. Validating only task.content misses requirements.')
            ->onViolation('brain docs {keywords} → if docs exist → read → validate against docs.');

        $this->rule('task-complete')->critical()
            ->text('ALL requirements MUST be done. Missing = fix-task.');

        $this->rule('no-garbage')->critical()
            ->text('Detect garbage in task scope: unused imports, dead code, debug statements. Garbage = fix-task.');

        $this->rule('cosmetic-inline')->critical()
            ->text('Cosmetic issues (whitespace, typos, formatting) = fix IMMEDIATELY with Edit. Increment counter. NO task.');

        $this->rule('functional-to-task')->critical()
            ->text('Functional issues = fix-task. Functional: logic bugs, security, architecture violations, missing tests.');

        $this->rule('fix-task-blocks-validated')->critical()
            ->text('Fix-task created → status MUST be "pending". "validated" = ZERO fix-tasks.');

        $this->rule('idempotent')->high()
            ->text('Re-run produces same result. Check existing tasks before creating. Skip duplicates.');

        $this->rule('test-coverage')->high()
            ->text('New code MUST have test coverage >=80%. No coverage = fix-task.');

        $this->rule('slow-test-detection')->high()
            ->text('Slow tests = fix-task. Unit >500ms, integration >2s, any >5s = CRITICAL.');

        // Quality gates
        $qualityCommands = $this->groupVars('QUALITY_COMMAND');
        if (!empty($qualityCommands)) {
            $this->rule('quality-gates-mandatory')->critical()
                ->text('ALL quality commands MUST PASS. Any error OR warning = fix-task.');

            foreach ($qualityCommands as $key => $cmd) {
                $this->rule('quality-' . $key)->critical()
                    ->text("QUALITY GATE [{$key}]: {$cmd}");
            }
        }

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')
            ->goal('Sync validate: load → approve → context → validate → aggregate → create tasks → complete')
            ->example()

            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))
            ->phase(Operator::if('status NOT IN [completed, tested, validated, in_progress]', Operator::abort('Complete via /task:sync first')))
            ->phase(Operator::if('status=in_progress', 'SESSION RECOVERY: check if crashed', Operator::abort('another session active')))
            ->phase(Operator::if('TASK.parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' → context only'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('SUBTASKS'))
            ->phase(Store::as('TASK_PARENT_ID', '$VECTOR_TASK_ID'))

            // 1.5 Set in_progress IMMEDIATELY (all checks passed, work begins NOW)
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Started validation", append_comment: true}'))

            // 2. Approve
            ->phase('Show: Task #{id}, title, status, subtasks count')
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved', 'Ask: "Validate? (yes/no)"'))

            // 3. Context
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{TASK.title}", limit: 5, category: "code-solution"}') . ' → ' . Store::as('MEMORY'))
            ->phase(VectorTaskMcp::call('task_list', '{query: "{TASK.title}", limit: 5}') . ' → ' . Store::as('RELATED'))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' → ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if(Store::get('DOCS_INDEX') . ' found', ReadTool::call('{doc_paths}') . ' → ' . Store::as('DOCUMENTATION') . ' (COMPLETE spec)'))
            ->phase(Operator::if('unknown library/pattern', Context7Mcp::call('query-docs', '{query: "{library}"}') . ' → understand before validating'))

            // 4. Direct validation (TASK SCOPE ONLY)
            ->phase(Store::as('COSMETIC_FIXES', '0'))
            ->phase('4.1 COMPLETION: Extract requirements from DOCUMENTATION (primary) + task.content (secondary) → list ALL requirements → verify each done')
            ->phase(GlobTool::describe('Find task-related files'))
            ->phase(ReadTool::describe('Read files, confirm implementation'))
            ->phase('Detect garbage: unused imports, dead code, debug statements')

            ->phase('4.2 CODE QUALITY: Task scope only')
            ->phase(GrepTool::describe('Search patterns, potential issues'))
            ->phase('Check: logic, security, architecture. Unknown lib → context7.')

            ->phase('4.3 QUALITY GATES')
            ->phase(BashTool::describe('{QUALITY_COMMAND}', 'Run quality gates'))
            ->phase('Gate FAIL = fix-task')

            ->phase('4.4 TESTING: Task scope only')
            ->phase('Check: tests exist (>=80%), pass, edge cases. Slow tests = issue.')

            ->phase('During validation: cosmetic found → Edit → fix → COSMETIC_FIXES++ → continue')

            // 5. Aggregate
            ->phase(Store::as('ISSUES', '{critical, major, minor, missing_requirements}'))
            ->phase(Store::as('FUNCTIONAL_COUNT', 'critical + major + minor + missing'))

            // 6. Create fix-tasks (consolidated 5-8h)
            ->phase(Operator::if('FUNCTIONAL_COUNT = 0', 'Skip to completion'))
            ->phase(VectorTaskMcp::call('task_list', '{query: "fix {TASK.title}", limit: 10}') . ' → check duplicates')
            ->phase(Store::as('TOTAL_ESTIMATE', 'critical*2h + major*1.5h + minor*0.5h + missing*4h'))
            ->phase(Operator::if('TOTAL_ESTIMATE <= 8 AND no duplicate', [
                VectorTaskMcp::call('task_create', '{title: "Validation fixes: #{TASK.id}", content: "{issues}", parent_id: $TASK_PARENT_ID, priority: "{critical>0 ? high : medium}", estimate: {TOTAL_ESTIMATE}, parallel: true, tags: ["validation-fix"]}'),
                Store::as('CREATED_TASKS[]', '{id}'),
            ]))
            ->phase(Operator::if('TOTAL_ESTIMATE > 8', 'Split into 5-8h batches, create multiple tasks'))

            // 7. Complete
            ->phase(Operator::if('CREATED_TASKS.count = 0 AND FUNCTIONAL_COUNT = 0', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated", comment: "Sync validation PASSED", append_comment: true}'),
            ]))
            ->phase(Operator::if('CREATED_TASKS.count > 0', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Validation found issues. Fix-tasks: {count}", append_comment: true}'),
            ]))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Validated #{TASK.id}: {status}. Issues: {counts}. Fix-tasks: {count}.", category: "code-solution"}'))
            ->phase('Report: task, status, issues counts, cosmetic fixes, fix-tasks created');

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('Check task ID')))
            ->phase(Operator::if('task not validatable status', Operator::abort('Complete via /task:sync first')))
            ->phase(Operator::if('validation fails', 'Report partial, store to memory'))
            ->phase(Operator::if('task creation fails', 'Store to memory for manual review'));
    }
}
