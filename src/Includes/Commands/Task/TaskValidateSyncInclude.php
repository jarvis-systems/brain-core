<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\EditTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\GrepTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Direct synchronous vector task validation without agent delegation. Accepts task ID reference (formats: "15", "#15", "task 15"), validates completed tasks against documentation requirements, code consistency, and completeness. Creates follow-up tasks for gaps. Idempotent. Best for: validation requiring direct execution without parallel agents.')]
class TaskValidateSyncInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // ABSOLUTE FIRST - BLOCKING ENTRY RULE
        $this->rule('entry-point-blocking')->critical()
            ->text('ON RECEIVING input: Your FIRST output MUST be "=== TASK:VALIDATE-SYNC ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION. FORBIDDEN first actions: Glob, Grep, Read, Edit, Write, WebSearch, WebFetch, Bash (except brain docs), code generation, file analysis.')
            ->why('Without explicit entry point, Brain skips workflow and executes directly. Entry point forces workflow compliance.')
            ->onViolation('STOP IMMEDIATELY. Delete any tool calls. Output "=== TASK:VALIDATE-SYNC ACTIVATED ===" and restart from Phase 0.');

        // Iron Rules - Zero Tolerance
        $this->rule('validation-only-no-execution')->critical()
            ->text('VALIDATION command validates EXISTING work. NEVER implement, fix, or create functional code directly. Only validate and CREATE TASKS for functional issues found.')
            ->why('Validation is read-only audit. Execution belongs to task:async or task:sync.')
            ->onViolation('Abort any implementation. Create task instead of fixing directly.');

        $this->rule('no-delegation')->critical()
            ->text('SYNC validation executes directly. NO Task() delegation to agents. Use ONLY direct tools: Read, Edit, Write, Glob, Grep, Bash.')
            ->why('Sync mode is for direct execution without agent overhead.')
            ->onViolation('Remove Task() calls. Execute directly.');

        $this->rule('vector-task-id-required')->critical()
            ->text('$RAW_INPUT MUST contain a vector task ID reference. Valid base formats: "15", "#15", "task 15", "task:15", "task-15". Optional flags (-y, --yes) may follow the ID. Examples: "63 -y", "#15 --yes", "task 42 -y". Extract ID first, then check for flags.')
            ->why('This command is exclusively for vector task validation. Text descriptions belong to /do:validate.')
            ->onViolation('STOP. Report: "Invalid task ID. Use /do:validate for text-based validation or provide valid task ID."');

        $this->rule('validatable-status-required')->critical()
            ->text('ONLY tasks with status "completed", "tested", or "validated" can be validated. Pending/in_progress/stopped tasks MUST first be completed via task:async or task:sync.')
            ->why('Validation audits finished work. Incomplete work cannot be validated.')
            ->onViolation('Report: "Task #{id} has status {status}. Complete via /task:async or /task:sync first."');

        $this->rule('auto-approval-flag')->critical()
            ->text('If $RAW_INPUT contains "-y" flag, auto-approve validation scope (skip user confirmation prompt at Phase 1).')
            ->why('Flag -y enables automated/scripted execution without manual approval.')
            ->onViolation('Check for -y flag before waiting for user approval.');

        $this->rule('idempotent-validation')->high()
            ->text('Validation is IDEMPOTENT. Running multiple times produces same result (no duplicate tasks, no repeated fixes).')
            ->why('Allows safe re-runs without side effects.')
            ->onViolation('Check existing tasks before creating. Skip duplicates.');

        $this->rule('session-recovery-via-history')->high()
            ->text('If task status is "in_progress", check status_history. If last entry has "to: null" - previous session crashed mid-execution. Can continue validation WITHOUT changing status. Treat any vector memory findings from crashed session with caution - previous context is lost.')
            ->why('Prevents blocking on crashed sessions. Allows recovery while maintaining awareness that previous session context is incomplete.')
            ->onViolation('Check status_history before blocking. If to:null found, proceed with caution warning.');

        $this->rule('no-direct-fixes-functional')->critical()
            ->text('VALIDATION command NEVER fixes FUNCTIONAL issues directly. Code logic, architecture, functionality issues MUST become tasks.')
            ->why('Traceability and audit trail. Code changes must be tracked via task system.')
            ->onViolation('Create task for the functional issue instead of fixing directly.');

        $this->rule('cosmetic-auto-fix')->critical()
            ->text('COSMETIC issues (whitespace, indentation, extra spaces, trailing spaces, documentation typos, formatting inconsistencies, empty lines) MUST be auto-fixed INLINE when discovered during validation. When you find a cosmetic issue, fix it IMMEDIATELY with Edit tool, increment cosmetic_fixes counter, then continue validation. NO separate phase. NO restart. NO tasks.')
            ->why('Inline fix eliminates separate cosmetic phase. Faster validation, no restarts, no extra phases.')
            ->onViolation('Fix cosmetic issues inline during validation. Report total cosmetic_fixes_applied at end.');

        $this->rule('vector-memory-mandatory')->high()
            ->text('ALL validation results MUST be stored to vector memory. Search memory BEFORE creating duplicate tasks.')
            ->why('Memory prevents duplicate work and provides audit trail.')
            ->onViolation('Store validation summary with findings, fixes, and created tasks.');

        // Phase Execution Sequence - STRICT ORDERING
        $this->rule('phase-sequence-strict')->critical()
            ->text('Phases MUST execute in STRICT sequential order: Phase 0 → Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7. NO phase may start until previous phase is FULLY COMPLETED. Each phase MUST output its header "=== PHASE N: NAME ===" before any actions.')
            ->why('Sequential execution ensures data dependencies are satisfied. Each phase depends on variables stored by previous phases.')
            ->onViolation('STOP. Return to last completed phase. Execute current phase fully before proceeding.');

        $this->rule('no-phase-skip')->critical()
            ->text('FORBIDDEN: Skipping phases. ALL phases 0-7 MUST execute even if a phase has no issues to report. Empty results are valid; skipped phases are VIOLATION.')
            ->why('Phase skipping breaks data flow. Later phases expect variables from earlier phases.')
            ->onViolation('ABORT. Return to first skipped phase. Execute ALL phases in sequence.');

        $this->rule('phase-completion-marker')->high()
            ->text('Each phase MUST end with its output block before next phase begins. Phase N output MUST appear before "=== PHASE N+1 ===" header.')
            ->why('Output markers confirm phase completion. Missing output = incomplete phase.')
            ->onViolation('Complete current phase output before starting next phase.');

        $this->rule('output-status-conditional')->critical()
            ->text('Output status depends on validation outcome: 1) PASSED + no tasks created → "validated", 2) Tasks created for fixes → "pending". Status "validated" means work is COMPLETE and verified.')
            ->why('If fix tasks were created, work is NOT done - task returns to pending queue. Only when validation passes completely (no critical issues, no missing requirements, no tasks created) can status be "validated".')
            ->onViolation('Check CREATED_TASKS.count: if > 0 → set "pending", if === 0 AND passed → set "validated". NEVER set "validated" when fix tasks exist.');

        // CRITICAL: Fix task parent_id assignment
        $this->rule('fix-task-parent-is-validated-task')->critical()
            ->text('Fix tasks MUST have parent_id = VECTOR_TASK_ID (the task being validated NOW). NEVER use VECTOR_TASK.parent_id or PARENT_TASK_CONTEXT. If validating Task B (child of Task A), fix tasks become children of Task B, NOT Task A.')
            ->why('Hierarchical integrity: validation creates subtasks of the validated task. Chain: Task A → Task B (validation fix) → Task C (validation fix of B). Each level is child of its direct parent, not grandparent.')
            ->onViolation('VERIFY parent_id = $TASK_PARENT_ID = $VECTOR_TASK_ID before task_create. If wrong, ABORT and recalculate.');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('VECTOR_TASK_ID', '{numeric ID extracted from $CLEAN_ARGS: "63", "#63", "task 63" → 63}'));

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task using $VECTOR_TASK_ID (already parsed from input), verify validatable status')
            ->example()
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK', '{task object with title, content, status, parent_id, priority, tags}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status NOT IN ["completed", "tested", "validated", "in_progress"]', [
                Operator::output([
                    '=== VALIDATION BLOCKED ===',
                    'Task #$VECTOR_TASK_ID has status: {$VECTOR_TASK.status}',
                    'Only tasks with status completed/tested/validated can be validated.',
                    'Run /task:async or /task:sync $VECTOR_TASK_ID to complete first.',
                ]),
                'ABORT validation',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                Operator::note('Check status_history for session crash indicator'),
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                    Operator::output([
                        '⚠️ SESSION RECOVERY DETECTED',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Continuing validation without status change.',
                        'NOTE: Previous session vector memory findings should be treated with caution.',
                    ]),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== VALIDATION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another session.',
                        'Wait for completion or use /task:async to take over.',
                    ]),
                    'ABORT validation',
                ]),
            ]))
            ->phase(Operator::note('CRITICAL: Set TASK_PARENT_ID to the CURRENTLY validated task ID IMMEDIATELY after loading. This ensures fix tasks become children of the task being validated, NOT grandchildren.'))
            ->phase(Store::as('TASK_PARENT_ID', '{$VECTOR_TASK_ID}'))
            ->phase(Operator::note('TASK_PARENT_ID = $VECTOR_TASK_ID (the task we are validating NOW). Any fix tasks created will be children of THIS task, regardless of whether this task itself has a parent.'))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                Operator::note('Fetching parent task FOR CONTEXT DISPLAY ONLY. This DOES NOT change TASK_PARENT_ID.'),
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK_CONTEXT',
                    '{parent task for display context only - NOT for parent_id assignment}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 50}'))
            ->phase(Store::as('SUBTASKS', '{list of subtasks}'))
            ->phase(Store::as('TASK_DESCRIPTION', '{$VECTOR_TASK.title + $VECTOR_TASK.content}'))
            ->phase(Operator::output([
                '=== TASK:VALIDATE-SYNC ACTIVATED ===',
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent context: {$PARENT_TASK_CONTEXT.title or "none"}',
                'Subtasks: {$SUBTASKS.count}',
                'Fix tasks parent_id will be: $TASK_PARENT_ID (THIS task)',
            ]));

        // Phase 1: Validation Scope Preview + Approval
        $this->guideline('phase1-context-preview')
            ->goal('Present validation scope for approval (sync mode, no agents)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 1: VALIDATION PREVIEW ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'),
                'Get documentation INDEX preview'))
            ->phase(Store::as('DOCS_PREVIEW', 'Documentation files available'))
            ->phase(Operator::output([
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Documentation files: {$DOCS_PREVIEW.count}',
                'Validation mode: SYNC (direct tools, no agents)',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output([
                    '',
                    '⚠️  APPROVAL REQUIRED',
                    '✅ approved/yes - start validation | ❌ no/modifications',
                ]),
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['✅ Auto-approved via -y flag']),
            ]))
            ->phase('After approval (manual or auto) - set task in_progress (validation IS execution)')
            ->phase(VectorTaskMcp::call('task_update',
                '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Sync validation started after approval", append_comment: true}'))
            ->phase(Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} started (sync validation phase)']));

        // Phase 2: Deep Context Gathering (sync)
        $this->guideline('phase2-context-gathering')
            ->goal('Gather deep context via vector memory searches (no agents)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: DEEP CONTEXT GATHERING ===',
            ]))
            ->phase(Operator::if('$IS_SESSION_RECOVERY === true', [
                Operator::note('CAUTION: Session recovery. Memory findings from crashed session should be verified against current codebase.'),
            ]))
            ->phase(VectorMemoryMcp::call('search_memories',
                '{query: "validation context {$TASK_DESCRIPTION}", limit: 5, category: "code-solution,architecture,bug-fix"}'))
            ->phase(Store::as('MEMORY_CONTEXT', '{memory findings for validation}'))
            ->phase(VectorTaskMcp::call('task_list', '{query: "$TASK_DESCRIPTION", limit: 10}'))
            ->phase(Store::as('RELATED_TASKS', 'Related vector tasks'))
            ->phase(Operator::output([
                'Context gathered:',
                '- Memory insights: {$MEMORY_CONTEXT.count}',
                '- Related tasks: {$RELATED_TASKS.count}',
            ]));

        // Phase 3: Documentation Requirements Extraction (sync)
        $this->guideline('phase3-documentation-extraction')
            ->goal('Extract ALL requirements from .docs/ using direct tools')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: DOCUMENTATION REQUIREMENTS ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', 'Documentation file paths'))
            ->phase(Operator::if('$DOCS_INDEX not empty', [
                Operator::forEach('doc in $DOCS_INDEX', [
                    ReadTool::call('{doc.path}'),
                ]),
                Store::as('DOCUMENTATION_REQUIREMENTS', '{structured requirements list extracted from docs}'),
            ]))
            ->phase(Operator::if('$DOCS_INDEX empty', [
                Store::as('DOCUMENTATION_REQUIREMENTS', '[]'),
                Operator::output(['WARNING: No documentation found. Validation will be limited.']),
            ]))
            ->phase(Operator::output([
                'Requirements extracted: {$DOCUMENTATION_REQUIREMENTS.count}',
                '{requirements summary}',
            ]));

        // Phase 4: Direct Validation (sync) with inline cosmetic fixes
        $this->guideline('phase4-direct-validation')
            ->goal('Validate requirements and code consistency using direct tools. FIX COSMETIC ISSUES INLINE.')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 4: DIRECT VALIDATION ===',
            ]))
            ->phase(Store::as('COSMETIC_FIXES_APPLIED', '0'))
            ->phase(Operator::note('COSMETIC FIX RULE: When you find whitespace, indentation, trailing spaces, empty line issues, typos, formatting inconsistencies - FIX THEM IMMEDIATELY with Edit tool. Increment $COSMETIC_FIXES_APPLIED. Continue validation. NO separate phase.'))
            ->phase('Identify relevant files and patterns based on $TASK_DESCRIPTION and $DOCUMENTATION_REQUIREMENTS')
            ->phase(GlobTool::describe('Discover related files using patterns derived from requirements'))
            ->phase(GrepTool::describe('Search for implementation evidence and known patterns'))
            ->phase(ReadTool::describe('Read identified files to confirm implementation and consistency'))
            ->phase('During validation: if cosmetic issue found → Edit tool → fix → $COSMETIC_FIXES_APPLIED++ → continue')
            ->phase(Store::as('VALIDATION_FINDINGS', '{requirements mapping, code issues, tests, docs sync, cosmetic_fixes_applied: $COSMETIC_FIXES_APPLIED}'))
            ->phase(Operator::output([
                'Direct validation completed.',
                'Files reviewed: {count}',
                'Cosmetic fixes applied inline: {$COSMETIC_FIXES_APPLIED}',
                'Findings captured for aggregation.',
            ]));

        // Phase 5: Results Aggregation and Analysis
        $this->guideline('phase5-results-aggregation')
            ->goal('Aggregate all validation results and categorize FUNCTIONAL issues only (cosmetic already fixed inline)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 5: RESULTS AGGREGATION ===',
            ]))
            ->phase('Merge results from direct validation findings')
            ->phase(Store::as('ALL_ISSUES', '{merged FUNCTIONAL issues from validation findings}'))
            ->phase(Store::as('TOTAL_COSMETIC_FIXES', '{$COSMETIC_FIXES_APPLIED from Phase 4}'))
            ->phase('Categorize FUNCTIONAL issues (require tasks):')
            ->phase(Store::as('CRITICAL_ISSUES', '{issues with severity: critical - code logic, security, architecture}'))
            ->phase(Store::as('MAJOR_ISSUES', '{issues with severity: major - functionality, tests, dependencies}'))
            ->phase(Store::as('MINOR_ISSUES', '{issues with severity: minor - code style affecting logic, naming conventions}'))
            ->phase(Store::as('MISSING_REQUIREMENTS', '{requirements not implemented}'))
            ->phase(Operator::note('Cosmetic issues were already fixed inline during Phase 4. No separate cosmetic tracking needed.'))
            ->phase(Store::as('FUNCTIONAL_ISSUES_COUNT', '{$CRITICAL_ISSUES.count + $MAJOR_ISSUES.count + $MINOR_ISSUES.count + $MISSING_REQUIREMENTS.count}'))
            ->phase(Operator::output([
                'Validation results:',
                '- Critical issues: {$CRITICAL_ISSUES.count}',
                '- Major issues: {$MAJOR_ISSUES.count}',
                '- Minor issues: {$MINOR_ISSUES.count}',
                '- Missing requirements: {$MISSING_REQUIREMENTS.count}',
                '- Cosmetic fixes (inline): {$TOTAL_COSMETIC_FIXES}',
                '',
                'Functional issues total: {$FUNCTIONAL_ISSUES_COUNT}',
            ]));

        // Phase 6: Task Creation for FUNCTIONAL Issues Only (Consolidated 5-8h Tasks)
        $this->guideline('phase6-task-creation')
            ->goal('Create consolidated tasks (5-8h each) for FUNCTIONAL issues with comprehensive context (cosmetic issues already fixed inline)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 6: TASK CREATION (CONSOLIDATED) ===',
            ]))
            ->phase(Operator::note('CRITICAL VERIFICATION: Confirm TASK_PARENT_ID before creating any tasks'))
            ->phase(Operator::verify([
                '$TASK_PARENT_ID === $VECTOR_TASK_ID',
                'TASK_PARENT_ID is the ID of the task we are validating (NOT its parent)',
            ]))
            ->phase(Operator::output([
                'Fix tasks will have parent_id: $TASK_PARENT_ID (Task #{$VECTOR_TASK_ID})',
            ]))
            ->phase('Check existing tasks to avoid duplicates')
            ->phase(VectorTaskMcp::call('task_list', '{query: "fix issues $TASK_DESCRIPTION", limit: 20}'))
            ->phase(Store::as('EXISTING_FIX_TASKS', 'Existing fix tasks'))
            ->phase(Operator::note('Phase 6 processes ONLY functional issues. Cosmetic issues were fixed inline in Phase 4.'))
            ->phase(Operator::if('$FUNCTIONAL_ISSUES_COUNT === 0', [
                Operator::output(['No functional issues to create tasks for. Proceeding to Phase 7...']),
                'SKIP to Phase 7',
            ]))
            ->phase('CONSOLIDATION STRATEGY: Group FUNCTIONAL issues into 5-8 hour task batches')
            ->phase(Operator::do([
                'Calculate total estimate for FUNCTIONAL issues only:',
                '- Critical issues: ~2h per issue (investigation + fix + test)',
                '- Major issues: ~1.5h per issue (fix + verify)',
                '- Minor issues: ~0.5h per issue (fix + verify)',
                '- Missing requirements: ~4h per requirement (implement + test)',
                '(Cosmetic issues NOT included - already auto-fixed)',
                Store::as('TOTAL_ESTIMATE', '{sum of FUNCTIONAL issue estimates in hours}'),
            ]))
            ->phase(Operator::if('$TOTAL_ESTIMATE <= 8', [
                'ALL issues fit into ONE consolidated task (5-8h range)',
                Operator::if('($CRITICAL_ISSUES.count + $MAJOR_ISSUES.count + $MINOR_ISSUES.count + $MISSING_REQUIREMENTS.count) > 0 AND NOT exists similar in $EXISTING_FIX_TASKS',
                    [
                        VectorTaskMcp::call('task_create', '{
                        title: "Validation fixes: task #{$VECTOR_TASK_ID}",
                        content: "Consolidated validation findings for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}.\n\nTotal estimate: {$TOTAL_ESTIMATE}h\n\n## Critical Issues ({$CRITICAL_ISSUES.count})\n{FOR each issue: - [{issue.severity}] {issue.description}\n  File: {issue.file}:{issue.line}\n  Type: {issue.type}\n  Suggestion: {issue.suggestion}\n  Memory refs: {issue.memory_refs}\n}\n\n## Major Issues ({$MAJOR_ISSUES.count})\n{FOR each issue: - [{issue.severity}] {issue.description}\n  File: {issue.file}:{issue.line}\n  Type: {issue.type}\n  Suggestion: {issue.suggestion}\n  Memory refs: {issue.memory_refs}\n}\n\n## Minor Issues ({$MINOR_ISSUES.count})\n{FOR each issue: - [{issue.severity}] {issue.description}\n  File: {issue.file}:{issue.line}\n  Type: {issue.type}\n  Suggestion: {issue.suggestion}\n  Memory refs: {issue.memory_refs}\n}\n\n## Missing Requirements ({$MISSING_REQUIREMENTS.count})\n{FOR each req: - {req.description}\n  Acceptance criteria: {req.acceptance_criteria}\n  Related files: {req.related_files}\n  Priority: {req.priority}\n}\n\n## Context References\n- Parent task: #{$VECTOR_TASK_ID}\n- Memory IDs: {$MEMORY_CONTEXT.memory_ids}\n- Related tasks: {$RELATED_TASKS.ids}\n- Documentation: {$DOCS_INDEX.paths}",
                        priority: "{$CRITICAL_ISSUES.count > 0 ? high : medium}",
                        estimate: $TOTAL_ESTIMATE,
                        tags: ["validation-fix", "consolidated"],
                        parent_id: $TASK_PARENT_ID
                    }'),
                        Store::as('CREATED_TASKS[]', '{task_id}'),
                        Operator::output(['Created consolidated task: Validation fixes ({$TOTAL_ESTIMATE}h, {issues_count} issues)']),
                    ]),
            ]))
            ->phase(Operator::if('$TOTAL_ESTIMATE > 8', [
                'Split into multiple 5-8h task batches',
                Store::as('BATCH_SIZE', '6'),
                Store::as('NUM_BATCHES', '{ceil($TOTAL_ESTIMATE / 6)}'),
                'Group issues by priority (critical first) into batches of ~6h each',
                Operator::forEach('batch_index in range(1, $NUM_BATCHES)', [
                    Store::as('BATCH_ISSUES', '{slice of issues for this batch, ~6h worth, priority-ordered}'),
                    Store::as('BATCH_ESTIMATE', '{sum of batch issue estimates}'),
                    Store::as('BATCH_CRITICAL', '{count of critical issues in batch}'),
                    Store::as('BATCH_MAJOR', '{count of major issues in batch}'),
                    Store::as('BATCH_MISSING', '{count of missing requirements in batch}'),
                    Operator::if('NOT exists similar in $EXISTING_FIX_TASKS', [
                        VectorTaskMcp::call('task_create', '{
                            title: "Validation fixes batch {batch_index}/{$NUM_BATCHES}: task #{$VECTOR_TASK_ID}",
                            content: "Validation batch {batch_index} of {$NUM_BATCHES} for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}.\n\nBatch estimate: {$BATCH_ESTIMATE}h\nBatch composition: {$BATCH_CRITICAL} critical, {$BATCH_MAJOR} major, {$BATCH_MISSING} missing reqs\n\n## Issues in this batch\n{FOR each issue in $BATCH_ISSUES:\n### [{issue.severity}] {issue.title}\n- File: {issue.file}:{issue.line}\n- Type: {issue.type}\n- Description: {issue.description}\n- Suggestion: {issue.suggestion}\n- Evidence: {issue.evidence}\n- Memory refs: {issue.memory_refs}\n}\n\n## Full Context References\n- Parent task: #{$VECTOR_TASK_ID}\n- Memory IDs: {$MEMORY_CONTEXT.memory_ids}\n- Related tasks: {$RELATED_TASKS.ids}\n- Documentation: {$DOCS_INDEX.paths}\n- Total batches: {$NUM_BATCHES} ({$TOTAL_ESTIMATE}h total)",
                            priority: "{$BATCH_CRITICAL > 0 ? high : medium}",
                            estimate: $BATCH_ESTIMATE,
                            tags: ["validation-fix", "batch-{batch_index}"],
                            parent_id: $TASK_PARENT_ID
                        }'),
                        Store::as('CREATED_TASKS[]', '{task_id}'),
                        Operator::output(['Created batch {batch_index}/{$NUM_BATCHES}: {$BATCH_ESTIMATE}h ({$BATCH_ISSUES.count} issues)']),
                    ]),
                ]),
            ]))
            ->phase(Operator::output([
                'Tasks created: {$CREATED_TASKS.count} (total estimate: {$TOTAL_ESTIMATE}h)',
            ]));

        // Task Consolidation Rules
        $this->rule('task-size-5-8h')->high()
            ->text('Each created task MUST have estimate between 5-8 hours. Never create tasks < 5h (consolidate) or > 8h (split).')
            ->why('Optimal task size for focused work sessions. Too small = context switching overhead. Too large = hard to track progress.')
            ->onViolation('Merge small issues into consolidated task OR split large task into 5-8h batches.');

        $this->rule('task-comprehensive-context')->critical()
            ->text('Each task MUST include: all file:line references, memory IDs, related task IDs, documentation paths, detailed issue descriptions with suggestions, evidence from validation.')
            ->why('Enables full context restoration without re-exploration. Saves agent time on task pickup.')
            ->onViolation('Add missing context references before creating task.');

        // Phase 7: Validation Completion
        $this->guideline('phase7-completion')
            ->goal('Complete validation, update task status, store summary to memory')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 7: VALIDATION COMPLETE ===',
            ]))
            ->phase(Store::as('VALIDATION_SUMMARY', '{all_issues_count, tasks_created_count, pass_rate}'))
            ->phase(Store::as('VALIDATION_STATUS',
                Operator::if('$CRITICAL_ISSUES.count === 0 AND $MISSING_REQUIREMENTS.count === 0', 'PASSED',
                    'NEEDS_WORK')))
            ->phase(VectorMemoryMcp::call('store_memory',
                '{content: "Validation of task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\n\nStatus: {$VALIDATION_STATUS}\nCritical: {$CRITICAL_ISSUES.count}\nMajor: {$MAJOR_ISSUES.count}\nMinor: {$MINOR_ISSUES.count}\nTasks created: {$CREATED_TASKS.count}\n\nFindings:\n{summary of key findings}", category: "code-solution", tags: ["validation", "audit", "task:validate-sync"]}'))
            ->phase(Operator::if('$VALIDATION_STATUS === "PASSED" AND $CREATED_TASKS.count === 0', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "validated", comment: "Sync validation PASSED. All requirements implemented, no issues found.", append_comment: true}'),
                Operator::output(['✅ Task #{$VECTOR_TASK_ID} marked as VALIDATED']),
            ]))
            ->phase(Operator::if('$CREATED_TASKS.count > 0', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Sync validation found issues. Created {$CREATED_TASKS.count} fix tasks: Critical: {$CRITICAL_ISSUES.count}, Major: {$MAJOR_ISSUES.count}, Minor: {$MINOR_ISSUES.count}, Missing: {$MISSING_REQUIREMENTS.count}. Returning to pending - fix tasks must be completed before re-validation.", append_comment: true}'),
                Operator::output(['⏳ Task #{$VECTOR_TASK_ID} returned to PENDING ({$CREATED_TASKS.count} fix tasks required before re-validation)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== VALIDATION REPORT ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VALIDATION_STATUS}',
                '',
                '| Metric | Count |',
                '|--------|-------|',
                '| Critical issues | {$CRITICAL_ISSUES.count} |',
                '| Major issues | {$MAJOR_ISSUES.count} |',
                '| Minor issues | {$MINOR_ISSUES.count} |',
                '| Missing requirements | {$MISSING_REQUIREMENTS.count} |',
                '| Cosmetic fixes (inline) | {$TOTAL_COSMETIC_FIXES} |',
                '| Tasks created | {$CREATED_TASKS.count} |',
                '',
                '{IF $TOTAL_COSMETIC_FIXES > 0: "✅ Cosmetic issues fixed inline during validation"}',
                '{IF $CREATED_TASKS.count > 0: "Follow-up tasks: {$CREATED_TASKS}"}',
                '',
                'Validation stored to vector memory.',
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Graceful error handling for validation process')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'Abort validation',
            ])
            ->phase()->if('vector task not in validatable status', [
                'Report: "Vector task #{id} status is {status}, not completed/tested/validated"',
                'Suggest: Run /task:async or /task:sync #{id} first',
                'Abort validation',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use /do:validate for text-based validation"',
                'Abort command',
            ])
            ->phase()->if('no documentation found', [
                'Warn: "No documentation in .docs/ for this task"',
                'Continue with limited validation (code-only checks)',
            ])
            ->phase()->if('validation fails', [
                'Log: "Validation failed: {error}"',
                'Report partial validation in summary',
            ])
            ->phase()->if('task creation fails', [
                'Log: "Failed to create task: {error}"',
                'Store issue details to vector memory for manual review',
                'Continue with remaining tasks',
            ]);

        // Constraints and Validation
        $this->guideline('constraints')
            ->text('Validation constraints and limits (sync)')
            ->example()
            ->phase('Max 20 tasks created per validation run')
            ->phase('Validation timeout: 10 minutes total')
            ->phase(Operator::verify([
                'vector_task_loaded = true',
                'validatable_status_verified = true',
                'documentation_checked = true',
                'results_stored_to_memory = true',
                'no_direct_fixes = true',
            ]));

        // Examples
        $this->guideline('example-simple-validation')
            ->scenario('Sync validate completed vector task')
            ->example()
            ->phase('input', '"task 15" or "#15" where task #15 is "Implement user login"')
            ->phase('load', 'task_get(15) → title, content, status: completed')
            ->phase('flow',
                'Task Loading → Context → Docs → Direct Validation → Aggregate → Create Tasks → Complete')
            ->phase('result', 'Validation PASSED → status: validated OR NEEDS_WORK → N fix tasks created');

        $this->guideline('example-with-fixes')
            ->scenario('Sync validation finds issues')
            ->example()
            ->phase('input', '"#28" where task #28 has status: completed')
            ->phase('validation', 'Found: 2 critical, 3 major, 1 missing requirement')
            ->phase('tasks', 'Created 1 consolidated fix task (6h estimate)')
            ->phase('result', 'Task #28 status → pending, 1 fix task created as child');

        $this->guideline('example-rerun')
            ->scenario('Re-run sync validation (idempotent)')
            ->example()
            ->phase('input', '"task 15" (already validated before)')
            ->phase('behavior', 'Skips existing tasks, only creates NEW issues found')
            ->phase('result', 'Same/updated validation report, no duplicate tasks');

        // When to use task:validate vs task:validate-sync
        $this->guideline('validate-vs-validate-sync')
            ->text('When to use /task:validate vs /task:validate-sync')
            ->example()
            ->phase('USE /task:validate',
                'Async validation with parallel agents for large/complex tasks.')
            ->phase('USE /task:validate-sync',
                'Direct sync validation without agents. Best for smaller or isolated tasks.');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | single approval | progress markers | tables for results | Created tasks listed | 📋 task ID references');
    }
}
