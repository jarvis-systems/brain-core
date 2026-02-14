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

        // FAILURE-AWARE VALIDATION (prevent repeating failed solutions)
        $this->rule('failure-history-mandatory')->critical()
            ->text('BEFORE validation: search memory category "debugging" for KNOWN FAILURES. DO NOT create fix-tasks with solutions that already failed.')
            ->why('Repeating failed solutions wastes time.')
            ->onViolation('Search debugging memories FIRST. Block known-failed approaches in fix-task creation.');
        $this->rule('sibling-task-check')->high()
            ->text('BEFORE validation: fetch sibling tasks (same parent_id, status=completed/stopped). Extract what was tried and failed from comments.')
            ->why('Previous attempts contain valuable failure context.');
        $this->rule('no-repeat-failures')->critical()
            ->text('BEFORE creating fix-task: verify proposed solution is NOT in known failures. Match found → research alternative or escalate.')
            ->why('Creating fix-task with known-failed solution = guaranteed waste.');

        // CODEBASE CONSISTENCY (from trait - verify code follows existing patterns)
        $this->defineCodebasePatternReuseRule();

        // IMPACT & QUALITY (from trait - verify changes don't break consumers, catch AI code issues)
        $this->defineImpactRadiusAnalysisRule();
        $this->defineLogicEdgeCaseVerificationRule();
        $this->defineCodeHallucinationPreventionRule();
        $this->defineCleanupAfterChangesRule();

        // TEST SCOPING (from trait - scoped tests for subtasks, full suite for root)
        $this->defineTestScopingRule();

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

        // PARALLEL ISOLATION (from trait - strict criteria when creating fix-tasks)
        $this->defineParallelIsolationRules();

        // Quality gates
        $qualityCommands = $this->groupVars('QUALITY_COMMAND');
        $testGateCmd = $qualityCommands['TEST'] ?? '';
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

            // 1.3 SUBTASKS CHECK
            ->phase(Store::as('HAS_FIX_SUBTASKS', Store::get('SUBTASKS') . ' contains ANY subtask with tag "validation-fix"'))

            // 1.3a FIX-TASKS PRESENT → MANDATORY FULL RE-VALIDATION
            ->phase(Operator::if(
                Store::get('SUBTASKS') . ' not empty AND ALL subtasks status = "validated" AND ' . Store::get('HAS_FIX_SUBTASKS'),
                [
                    'FIX-TASKS COMPLETED: Previous validation created fix-tasks, all now done.',
                    'MANDATORY FULL RE-VALIDATION: fixes may have introduced new issues, original validation may have missed gaps.',
                    'Proceed to FULL VALIDATION below — validate ENTIRE task scope from scratch.',
                ]
            ))

            // 1.3b INTERMEDIATE PARENT (has parent_id) with all decomposition subtasks validated → aggregation fast-path
            ->phase(Operator::if(
                Store::get('SUBTASKS') . ' not empty AND ALL subtasks status = "validated" AND NOT ' . Store::get('HAS_FIX_SUBTASKS') . ' AND ' . Store::get('TASK') . '.parent_id (NOT root)',
                [
                    'AGGREGATION-ONLY MODE: Intermediate parent, all decomposition subtasks validated.',
                    'Read subtask comments → extract validation results (test counts, issues found, fixes applied)',
                    'Parse parent task.content → list ALL parent requirements',
                    'Cross-reference: does each parent requirement map to at least one validated subtask?',
                    Operator::if(
                        'all parent requirements covered by subtask results',
                        [
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated", comment: "Aggregation validation: all {N} subtasks validated. Requirements covered: {list}.", append_comment: true}'),
                            Operator::output('task, subtask results summary, all requirements covered, status=validated'),
                            Operator::skip('full validation — decomposition subtasks already did the work'),
                        ]
                    ),
                    Operator::if(
                        'gaps found: some parent requirements NOT covered by any subtask',
                        [
                            Store::as('UNCOVERED_REQUIREMENTS', '[requirements not mapped to any subtask]'),
                            'Proceed to FULL VALIDATION below, but scope to UNCOVERED_REQUIREMENTS only',
                        ]
                    ),
                ]
            ))

            // 1.3c ROOT TASK (no parent_id) → ALWAYS full validation, no fast-path
            ->phase(Operator::if(
                Store::get('SUBTASKS') . ' not empty AND ALL subtasks status = "validated" AND NOT ' . Store::get('HAS_FIX_SUBTASKS') . ' AND NOT ' . Store::get('TASK') . '.parent_id (ROOT task)',
                [
                    'ROOT TASK — FINAL CHECKPOINT: All subtasks validated individually, but this is the LAST safety net.',
                    'Subtask validators checked isolated scopes. Cross-subtask INTEGRATION was NEVER verified.',
                    'MANDATORY: Proceed to FULL VALIDATION — validate ENTIRE task scope from scratch.',
                    'Focus: integration between subtasks, full test suite, all quality gates, cross-file dependencies.',
                ]
            ))

            // 1.5 Set in_progress IMMEDIATELY (all checks passed, work begins NOW)
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Started validation", append_comment: true}'))

            // 2. Approve
            ->phase('Show: Task #{id}, title, status, subtasks count')
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved', 'Ask: "Validate? (yes/no)"'))

            // 3. Context (including failure history)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{TASK.title}", limit: 5, category: "code-solution"}') . ' → ' . Store::as('MEMORY'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{TASK.title} failed error not working broken", limit: 5}') . ' ' . Store::as('KNOWN_FAILURES') . ' ← what already FAILED')
            ->phase(VectorTaskMcp::call('task_list', '{query: "{TASK.title}", limit: 5}') . ' → ' . Store::as('RELATED'))
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                [
                    VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' → ' . Store::as('SIBLING_TASKS'),
                    'Extract from sibling comments: what was tried, what failed',
                    Store::as('FAILURE_PATTERNS', 'known failed approaches from siblings + debugging memories'),
                ]
            ))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' → ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if(Store::get('DOCS_INDEX') . ' found', ReadTool::call('{doc_paths}') . ' → ' . Store::as('DOCUMENTATION') . ' (COMPLETE spec)'))
            ->phase(Operator::if('unknown library/pattern', Context7Mcp::call('query-docs', '{query: "{library}"}') . ' → understand before validating'))

            // 3.5 Codebase pattern check (verify code follows existing conventions)
            ->phase(GrepTool::describe('Search for similar implementations: analogous class names, method patterns, trait usage'))
            ->phase(Store::as('EXISTING_PATTERNS', '{similar implementations found in codebase}'))

            // 4. Direct validation (TASK SCOPE ONLY)
            ->phase(Store::as('COSMETIC_FIXES', '0'))
            ->phase('4.1 COMPLETION: Extract requirements from DOCUMENTATION (primary) + task.content (secondary) → list ALL requirements → verify each done')
            ->phase(GlobTool::describe('Find task-related files'))
            ->phase(ReadTool::describe('Read files, confirm implementation'))
            ->phase('Detect garbage: unused imports, dead code, debug statements, commented-out blocks')

            ->phase('4.2 CODE QUALITY: Task scope only')
            ->phase(GrepTool::describe('Search patterns, potential issues'))
            ->phase('Check: logic, security, architecture. Unknown lib → context7.')
            ->phase('4.2.1 PATTERN CONSISTENCY: Compare implementation against $EXISTING_PATTERNS. Code should follow established project conventions.')
            ->phase('4.2.2 HALLUCINATION CHECK: Verify ALL method/class/function calls reference REAL code. Read source to confirm methods exist with correct signatures.')
            ->phase('4.2.3 IMPACT RADIUS: For each changed file, Grep who imports/uses/extends it. Verify consumers NOT broken.')
            ->phase('4.2.4 LOGIC EDGE CASES: For each changed function, verify: null/empty handling, boundary values, off-by-one, error paths.')

            ->phase('4.3 QUALITY GATES (non-test)')
            ->phase(BashTool::describe('{non-test QUALITY_COMMAND gates}', 'Run static analysis, linting quality gates (EXCLUDE test gate)'))
            ->phase('Gate FAIL = fix-task')

            ->phase('4.4 TESTING (scoped by hierarchy)')
            ->phase(Operator::if('TASK.parent_id (subtask)', [
                'Find test files: grep tests/ for changed class names, check mirror directory structure',
                GrepTool::describe('Search test directory for imports/uses of changed classes → consumer tests'),
                'Run ONLY found test files by EXPLICIT file path or --filter (e.g., phpunit tests/Unit/FooTest.php, php artisan test --filter=Foo)',
                !empty($testGateCmd)
                    ? "FORBIDDEN: running {$testGateCmd} or any unscoped test command (php artisan test, phpunit without path). This is a FULL SUITE command."
                    : 'FORBIDDEN: running any test command without explicit file path or --filter',
            ]))
            ->phase(Operator::if('NOT TASK.parent_id (root task)', [
                !empty($testGateCmd)
                    ? BashTool::describe($testGateCmd, 'Run FULL test suite via QUALITY GATE [TEST]')
                    : BashTool::describe('{project test command}', 'Detect and run full test suite from project config'),
            ]))
            ->phase('Check: tests exist (>=80%), pass, edge cases. Slow tests = issue.')

            ->phase('4.5 CLEANUP: Scan for unused imports, dead code, orphaned helpers, debug statements')

            ->phase('During validation: cosmetic found → Edit → fix → COSMETIC_FIXES++ → continue')
            ->phase(Operator::if(Store::get('KNOWN_FAILURES') . ' not empty', 'Before creating fix-task: verify proposed fix is NOT in known failures. Match → research alternative.'))

            // 5. Aggregate
            ->phase(Store::as('ISSUES', '{critical, major, minor, missing_requirements}'))
            ->phase(Store::as('FUNCTIONAL_COUNT', 'critical + major + minor + missing'))

            // 6. Create fix-tasks (consolidated 5-8h)
            ->phase(Operator::if('FUNCTIONAL_COUNT = 0', 'Skip to completion'))
            ->phase(VectorTaskMcp::call('task_list', '{query: "fix {TASK.title}", limit: 10}') . ' → check duplicates')
            ->phase(Store::as('TOTAL_ESTIMATE', 'critical*2h + major*1.5h + minor*0.5h + missing*4h'))
            ->phase(Operator::if('TOTAL_ESTIMATE <= 8 AND no duplicate', [
                VectorTaskMcp::call('task_create', '{title: "Validation fixes: #{TASK.id}", content: "{issues}", parent_id: $TASK_PARENT_ID, priority: "{critical>0 ? high : medium}", estimate: {TOTAL_ESTIMATE}, parallel: false, tags: ["validation-fix"]}') . ' ← parallel: false by default. Apply parallel-isolation-checklist against siblings before setting true.',
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
            ->phase(Operator::if('FUNCTIONAL_COUNT > 0', VectorMemoryMcp::call('store_memory', '{content: "Validation #{TASK.id}: {issue_patterns}. Root causes and fix approaches for future reference.", category: "debugging", tags: ["validation-issues"]}') . ' ← ONLY issue patterns, not operational status'))
            ->phase('Report: task, status, issues counts, cosmetic fixes, fix-tasks created');

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('Check task ID')))
            ->phase(Operator::if('task not validatable status', Operator::abort('Complete via /task:sync first')))
            ->phase(Operator::if('validation fails', 'Report partial, store to memory'))
            ->phase(Operator::if('task creation fails', 'Store to memory for manual review'));
    }
}
