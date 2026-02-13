<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

/**
 * Common patterns extracted from Task command includes.
 * Provides reusable rule and guideline definitions for task-related commands.
 *
 * Usage patterns identified across 9 Task command files:
 * - Input capture (RAW_INPUT, HAS_AUTO_APPROVE, CLEAN_ARGS, VECTOR_TASK_ID)
 * - Vector task loading with session recovery
 * - Auto-approval handling
 * - Completion status updates (SUCCESS/PARTIAL/FAILED)
 * - Error handling
 * - Vector memory mandatory rule
 * - Task consolidation (5-8h batches)
 */
trait TaskCommandCommonTrait
{
    // =========================================================================
    // INPUT CAPTURE PATTERNS
    // =========================================================================

    /**
     * Define input capture guideline for commands with VECTOR_TASK_ID.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineInputCaptureGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('VECTOR_TASK_ID', '{numeric ID extracted from $CLEAN_ARGS}'));
    }

    /**
     * Define input capture guideline for commands with TASK_ID (decompose style).
     * Used by: TaskDecomposeInclude
     */
    protected function defineInputCaptureWithTaskIdGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_Y_FLAG', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('TASK_ID', '{numeric ID extracted from $CLEAN_ARGS}'));
    }

    /**
     * Define input capture guideline for create command.
     * Used by: TaskCreateInclude
     */
    protected function defineInputCaptureWithDescriptionGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_Y_FLAG', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('TASK_DESCRIPTION', '{$RAW_INPUT with -y flag removed}'));
    }

    // =========================================================================
    // DOCUMENTATION IS LAW (CRITICAL FOUNDATION)
    // =========================================================================

    /**
     * Define documentation-is-law rules.
     * These rules ensure agents follow documentation/ТЗ exactly without inventing alternatives.
     * Used by: ALL task execution and validation commands.
     */
    protected function defineDocumentationIsLawRules(): void
    {
        $this->rule('docs-are-law')->critical()
            ->text('Documentation/ТЗ is the SINGLE SOURCE OF TRUTH. If docs exist for task - FOLLOW THEM EXACTLY. No deviations, no "alternatives", no "options" that docs don\'t mention.')
            ->why('User wrote docs for a reason. Asking about non-existent alternatives wastes time and shows you didn\'t read the docs.')
            ->onViolation('Re-read documentation. Execute ONLY what docs specify.');

        $this->rule('no-phantom-options')->critical()
            ->text('FORBIDDEN: Asking "keep as is / rewrite / both?" when docs specify ONE approach. If docs say HOW to do it - do it. Don\'t invent alternatives.')
            ->why('Docs are the holy grail. Phantom options confuse user and delay work.')
            ->onViolation('Check docs again. If docs are clear - execute. If genuinely ambiguous - ask about THAT ambiguity, not made-up options.');

        $this->rule('partial-work-continue')->critical()
            ->text('Partial implementation exists? Read DOCS first, understand FULL spec. Continue from where it stopped ACCORDING TO DOCS. Never ask "keep partial or rewrite" - docs define target state.')
            ->why('Partial work means someone started following docs. Continue following docs, not inventing alternatives.')
            ->onViolation('Read docs → understand target state → implement remaining parts per docs.');

        $this->rule('docs-over-existing-code')->high()
            ->text('Conflict between docs and existing code? DOCS WIN. Existing code may be: WIP, placeholder, wrong, outdated. Docs define WHAT SHOULD BE.')
            ->why('Code is implementation, docs are specification. Spec > current impl.');

        $this->rule('aggressive-docs-search')->critical()
            ->text('NEVER search docs with single exact query. Generate 3-5 keyword variations: 1) split CamelCase (FocusModeTest → "FocusMode", "Focus Mode", "Focus"), 2) remove technical suffixes (Test, Controller, Service, Repository, Command, Handler, Provider), 3) extract domain words, 4) try singular/plural. Search until found OR 3+ variations tried.')
            ->why('Docs may be named differently than code. "FocusModeTest" code → "Focus Mode" doc. Single exact search = missed docs = wrong decisions.')
            ->onViolation('Generate keyword variations. Search each. Only conclude "no docs" after 3+ failed searches.');

        // Include the search guideline
        $this->defineAggressiveDocsSearchGuideline();
    }

    /**
     * Define aggressive documentation search guideline.
     * Ensures multiple search attempts with keyword variations before concluding no docs exist.
     * Used by: ALL task execution and validation commands.
     */
    protected function defineAggressiveDocsSearchGuideline(): void
    {
        $this->guideline('aggressive-docs-search')
            ->goal('Find documentation even if named differently than task/code')
            ->example()
            ->phase('Generate keyword variations from task title/content:')
            ->phase('  1. Original: "FocusModeTest" → search "FocusModeTest"')
            ->phase('  2. Split CamelCase: "FocusModeTest" → search "FocusMode", "Focus Mode"')
            ->phase('  3. Remove suffix: "FocusModeTest" → search "Focus" (remove Mode, Test)')
            ->phase('  4. Domain words: extract meaningful nouns → search each')
            ->phase('  5. Parent context: if task has parent → include parent title keywords')
            ->phase('Common suffixes to STRIP: Test, Tests, Controller, Service, Repository, Command, Handler, Provider, Factory, Manager, Helper, Validator, Processor')
            ->phase('Search ORDER: most specific → most general. STOP when found.')
            ->phase('Minimum 3 search attempts before concluding "no documentation".')
            ->phase('WRONG: brain docs "UserAuthenticationServiceTest" → not found → done')
            ->phase('RIGHT: brain docs "UserAuthenticationServiceTest" → not found → brain docs "UserAuthentication" → not found → brain docs "Authentication" → FOUND!');
    }

    // =========================================================================
    // COMMON RULES
    // =========================================================================

    /**
     * Define vector-task-id-required rule.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $alternativeCommand Command to suggest for text-based tasks (e.g., '/do:async', '/do:validate')
     */
    protected function defineVectorTaskIdRequiredRule(string $alternativeCommand): void
    {
        $this->rule('vector-task-id-required')->critical()
            ->text('$TASK_ID MUST be a valid vector task ID reference. Valid formats: "15", "#15", "task 15", "task:15", "task-15". If not a valid task ID, abort and suggest '.$alternativeCommand.' for text-based tasks.')
            ->why('This command is exclusively for vector task execution. Text descriptions belong to '.$alternativeCommand.'.')
            ->onViolation('STOP. Report: "Invalid task ID. Use '.$alternativeCommand.' for text-based tasks or provide valid task ID."');
    }

    /**
     * Define auto-approval-flag rule.
     * Used by: TaskAsyncInclude, TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineAutoApprovalFlagRule(): void
    {
        $this->rule('auto-approval-flag')->critical()
            ->text('If $HAS_AUTO_APPROVE is true, auto-approve all approval gates. Skip approval checkpoints and proceed directly.')
            ->why('Flag -y enables automated execution without user interaction.')
            ->onViolation('Check $HAS_AUTO_APPROVE before showing approval checkpoint.');
    }

    /**
     * Define approval-gates-mandatory rule (async style - multiple gates).
     * Used by: TaskAsyncInclude
     */
    protected function defineApprovalGatesMandatoryRule(): void
    {
        $this->rule('approval-gates-mandatory')->critical()
            ->text('User approval REQUIRED before execution. DEFAULT: two gates (Requirements and Planning). For SIMPLE_TASK, allow a single combined approval after planning. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve all gates.')
            ->why('Maintains user control while reducing friction for simple tasks. Flag -y enables automated execution.')
            ->onViolation('STOP. Wait for required approval before continuing (unless $HAS_AUTO_APPROVE is true).');
    }

    /**
     * Define single-approval-gate rule (sync style - one gate).
     * Used by: TaskSyncInclude
     */
    protected function defineSingleApprovalGateRule(): void
    {
        $this->rule('single-approval-gate')->critical()
            ->text('User approval REQUIRED before execution. Single approval gate after plan presentation. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve the gate.')
            ->why('Direct execution requires explicit user consent. Flag -y enables automated execution.')
            ->onViolation('STOP. Wait for approval before continuing (unless $HAS_AUTO_APPROVE is true).');
    }

    /**
     * Define mandatory-user-approval rule (create/decompose style).
     * Used by: TaskCreateInclude, TaskDecomposeInclude
     */
    protected function defineMandatoryUserApprovalRule(): void
    {
        $this->rule('mandatory-user-approval')->critical()
            ->text('EVERY operation MUST have explicit user approval BEFORE execution. Present plan → WAIT for approval → Execute. NO auto-execution. EXCEPTION: If $HAS_Y_FLAG is true, auto-approve.')
            ->why('User maintains control. No surprises. Flag -y enables automated execution.')
            ->onViolation('STOP. Wait for explicit user approval (unless $HAS_Y_FLAG is true).');
    }

    /**
     * Define session-recovery-via-history rule.
     * Used by: TaskAsyncInclude, TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineSessionRecoveryViaHistoryRule(): void
    {
        $this->rule('session-recovery-via-history')->high()
            ->text('If task status is "in_progress", check status_history. If last entry has "to: null" - previous session crashed mid-execution. Can RESUME execution WITHOUT changing status (already in_progress). Treat vector memory findings from crashed session with caution - previous context is lost. Execution stage is unknown - may need to verify what was completed.')
            ->why('Prevents blocking on crashed sessions. Allows recovery while maintaining awareness that previous work may be incomplete.')
            ->onViolation('Check status_history before blocking. If to:null found, proceed with recovery mode.');
    }

    /**
     * Define vector-memory-mandatory rule.
     * Used by: TaskAsyncInclude, TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineVectorMemoryMandatoryRule(): void
    {
        $this->rule('vector-memory-mandatory')->high()
            ->text('ALL agents MUST search vector memory BEFORE task execution AND store learnings AFTER completion. Vector memory is the primary communication channel between sequential agents.')
            ->why('Enables knowledge sharing between agents, prevents duplicate work, maintains execution continuity across steps')
            ->onViolation('Include explicit vector memory instructions in agent Task() delegation.');
    }

    /**
     * Define fix-task-parent-is-validated-task rule.
     * Used by: TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineFixTaskParentRule(): void
    {
        $this->rule('fix-task-parent-is-validated-task')->high()
            ->text('ALL fix tasks created during validation MUST have parent_id = $VECTOR_TASK_ID. This maintains hierarchy: validated task → fix subtasks.')
            ->why('Ensures fix tasks are linked to their source validation task for tracking and completion.')
            ->onViolation('Set parent_id: $VECTOR_TASK_ID when creating fix tasks.');
    }

    // =========================================================================
    // PHASE 0: VECTOR TASK LOADING
    // =========================================================================

    /**
     * Define phase0 task loading guideline.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $activationHeader Header text (e.g., '=== TASK:ASYNC ACTIVATED ===')
     * @param array $validStatuses Valid statuses for this command (e.g., ['pending', 'in_progress'] or ['completed', 'tested', 'validated'])
     * @param bool $includeSessionRecovery Whether to include session recovery logic
     */
    protected function definePhase0TaskLoadingGuideline(
        string $activationHeader,
        array $validStatuses = ['pending', 'in_progress'],
        bool $includeSessionRecovery = true
    ): void {
        $guideline = $this->guideline('phase0-task-loading')
            ->goal('Load vector task with full context using pre-captured $VECTOR_TASK_ID')
            ->example()
            ->phase(Operator::output([
                $activationHeader,
                '',
                '=== PHASE 0: VECTOR TASK LOADING ===',
                'Loading task #{$VECTOR_TASK_ID}...',
            ]))
            ->phase('Use pre-captured: $RAW_INPUT, $HAS_AUTO_APPROVE, $CLEAN_ARGS, $VECTOR_TASK_ID')
            ->phase('Validate $VECTOR_TASK_ID: must be numeric, extracted from "15", "#15", "task 15", "task:15", "task-15"')
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK',
                '{task object with title, content, status, parent_id, priority, tags, comment}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]));

        // Add session recovery logic if enabled
        if ($includeSessionRecovery) {
            $guideline->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                'Check status_history for session crash indicator',
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Operator::output([
                        '⚠️ SESSION RECOVERY MODE',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Resuming execution without status change.',
                        'WARNING: Previous execution stage unknown. Will verify what was completed via vector memory.',
                        'NOTE: Memory findings from crashed session should be verified against codebase.',
                    ]),
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== EXECUTION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another session.',
                        'Wait for completion or manually reset status if session crashed without history update.',
                    ]),
                    'ABORT execution',
                ]),
            ]));
        }

        // Add status validation based on valid statuses
        if (in_array('completed', $validStatuses, true)) {
            // For validation commands that work on completed tasks
            $guideline->phase(Operator::if('$VECTOR_TASK.status NOT IN ["'.implode('", "', $validStatuses).'"]', [
                Operator::report('Vector task #$VECTOR_TASK_ID has status: {$VECTOR_TASK.status}'),
                'Only tasks with status ['.implode(', ', $validStatuses).'] can be processed',
                'ABORT command',
            ]));
        } else {
            // For execution commands (async/sync)
            $guideline->phase(Operator::if('$VECTOR_TASK.status === "completed"', [
                Operator::report('Vector task #$VECTOR_TASK_ID already completed'),
                'Ask user: "Re-execute this task? (yes/no)"',
                'WAIT for user decision',
            ]));
        }

        // Parent and subtasks loading
        $guideline
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK', '{parent task for broader context}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 20}'))
            ->phase(Store::as('SUBTASKS', '{child tasks if any}'))
            ->phase(Store::as('TASK_DESCRIPTION', '$VECTOR_TASK.title + $VECTOR_TASK.content'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent: {$PARENT_TASK.title or "none"}',
                'Subtasks: {count or "none"}',
                'Comment: {$VECTOR_TASK.comment or "none"}',
            ]));
    }

    // =========================================================================
    // AUTO-APPROVAL CHECKPOINT
    // =========================================================================

    /**
     * Define auto-approval checkpoint guideline.
     * Used by: TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $checkpointLabel Label for the checkpoint (e.g., 'VALIDATION', 'TEST VALIDATION')
     * @param string $phaseHeader Phase header (e.g., '=== PHASE 1: APPROVAL ===')
     */
    protected function defineAutoApprovalCheckpointGuideline(string $checkpointLabel, string $phaseHeader = '=== PHASE 1: APPROVAL ==='): void
    {
        $this->guideline('phase1-approval')
            ->goal('Check auto-approval flag and handle approval checkpoint')
            ->example()
            ->phase(Operator::output([$phaseHeader]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['Auto-approval enabled (-y flag). Skipping approval checkpoint.']),
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "'.$checkpointLabel.' started (auto-approved)", append_comment: true}'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output([
                    '',
                    'APPROVAL CHECKPOINT',
                    'Task: #{$VECTOR_TASK_ID} - {$VECTOR_TASK.title}',
                    'Action: '.$checkpointLabel,
                    '',
                    'Type "approved" or "yes" to proceed.',
                    'Type "no" to abort.',
                ]),
                'WAIT for user approval',
                Operator::verify('User approved'),
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "'.$checkpointLabel.' started", append_comment: true}'),
            ]));
    }

    // =========================================================================
    // COMPLETION STATUS UPDATE
    // =========================================================================

    /**
     * Define completion status update guideline.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $successStatus Status to set on success (e.g., 'completed', 'validated', 'tested')
     * @param string $processName Name of the process for messages (e.g., 'Execution', 'Validation', 'Test validation')
     */
    protected function defineCompletionStatusUpdateGuideline(string $successStatus, string $processName): void
    {
        $this->guideline('completion-status-update')
            ->goal('Update vector task status based on execution outcome')
            ->example()
            ->phase(Store::as('COMPLETION_SUMMARY', '{completed_steps, files_modified, outcomes, learnings}'))
            ->phase(VectorMemoryMcp::call('store_memory',
                '{content: "Completed task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nApproach: {summary}\\n\\nSteps: {outcomes}\\n\\nLearnings: {insights}\\n\\nFiles: {list}", category: "code-solution", tags: ["task-command", "completed"]}'))
            ->phase(Operator::if('status === SUCCESS', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "'.$successStatus.'", comment: "'.$processName.' completed successfully. Files: {list}. Memory: #{memory_id}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} '.$successStatus]),
            ]))
            ->phase(Operator::if('status === PARTIAL', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, comment: "Partial completion: {completed}/{total} steps. Remaining: {list}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} progress saved (partial)']),
            ]))
            ->phase(Operator::if('status === FAILED', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "stopped", comment: "'.$processName.' failed: {reason}. Completed: {completed}/{total}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} stopped (failed)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== '.strtoupper($processName).' COMPLETE ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {SUCCESS/PARTIAL/FAILED}',
                'Steps: {completed}/{total} | Files: {count} | Learnings stored',
                '{step_outcomes}',
            ]));
    }

    // =========================================================================
    // TASK CONSOLIDATION (5-8h BATCHES)
    // =========================================================================

    /**
     * Define task consolidation guideline.
     * Used by: TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $taskPrefix Prefix for created tasks (e.g., 'FIX|', 'TEST-FIX|')
     */
    protected function defineTaskConsolidationGuideline(string $taskPrefix): void
    {
        $this->guideline('task-consolidation')
            ->goal('Consolidate similar fixes into 5-8 hour batches to reduce task overhead')
            ->example()
            ->phase('Group related fixes by: file proximity, similar issue type, shared context')
            ->phase('Target batch size: 5-8 hours estimated work')
            ->phase('Split if batch > 8 hours into smaller coherent groups')
            ->phase(Operator::if('multiple related fixes found', [
                'Create single consolidated task with all related fixes',
                'Title format: "'.$taskPrefix.' {count} fixes in {area/component}"',
                'Content: List all individual fixes with file references',
                'Set estimate: sum of individual estimates (max 8h per task)',
            ]))
            ->phase(Operator::if('batch > 8 hours', [
                'Split into multiple tasks by logical grouping',
                'Each task 5-8 hours',
                'Maintain coherent scope per task',
            ]));
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    /**
     * Define error handling guideline.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $processName Name of the process (e.g., 'Execution', 'Validation')
     * @param string $alternativeCommand Alternative command to suggest (e.g., '/do:async', '/do:validate')
     */
    protected function defineErrorHandlingGuideline(string $processName, string $alternativeCommand): void
    {
        $this->guideline('error-handling')
            ->text('Graceful error handling with recovery options')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'Abort command',
            ])
            ->phase()->if('vector task already completed', [
                'Report: "Vector task #{id} already has status: completed"',
                'Ask user: "Do you want to re-execute this task?"',
                'WAIT for user decision',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use '.$alternativeCommand.' for text-based task descriptions"',
                'Abort command',
            ])
            ->phase()->if('user rejects plan', [
                'Accept modifications',
                'Rebuild plan',
                'Re-submit for approval',
            ])
            ->phase()->if($processName.' step fails', [
                'Log: "Step {N} failed: {error}"',
                'Update task comment with failure details',
                'Offer options:',
                '  1. Retry current step',
                '  2. Skip and continue',
                '  3. Abort remaining steps',
                'WAIT for user decision',
            ]);
    }

    // =========================================================================
    // STATUS/PRIORITY FILTERS (for list/status commands)
    // =========================================================================

    // =========================================================================
    // PARALLEL ISOLATION (SHARED ACROSS ALL TASK COMMANDS)
    // =========================================================================

    /**
     * Define parallel isolation rules.
     * Strict criteria for marking tasks as parallel: true.
     * Prevents race conditions, file conflicts, and lost changes between concurrent tasks.
     * Used by: ALL task commands that create or execute parallel tasks.
     */
    protected function defineParallelIsolationRules(): void
    {
        $this->rule('parallel-isolation-mandatory')->critical()
            ->text('Before setting parallel: true, ALL isolation conditions MUST be verified: 1) ZERO file overlap — tasks touch completely different files, 2) ZERO import chain — file A does NOT import/use/require anything from file B scope, 3) ZERO shared model/table — tasks do NOT modify same DB table/migration/model, 4) ZERO shared config — tasks do NOT modify same config key/.env variable, 5) ZERO output→input — task B does NOT need result/output of task A. ALL five MUST be TRUE.')
            ->why('Parallel tasks with shared files or dependencies cause race conditions, lost changes, and merge conflicts. LLM agents cannot lock files.')
            ->onViolation('Set parallel: false. When in doubt, sequential is always safe.');

        $this->rule('parallel-file-manifest')->critical()
            ->text('Before marking ANY task parallel: true, EXPLICITLY list ALL files each task will read/write/create. Cross-reference lists. If ANY file appears in 2+ tasks → parallel: false for ALL overlapping tasks. No exceptions.')
            ->why('Implicit file overlap is the #1 cause of parallel task conflicts. Explicit manifest prevents it.')
            ->onViolation('Create file manifest per task. Cross-reference. Overlap found = parallel: false.');

        $this->rule('parallel-conservative-default')->high()
            ->text('Default is parallel: false. Only set parallel: true when ALL isolation conditions are PROVEN. Uncertain about independence = sequential. Cost of wrong parallel (lost work, conflicts) far exceeds cost of wrong sequential (slower execution).')
            ->why('False negative (missing parallelism) = slower. False positive (wrong parallelism) = data loss. Asymmetric risk demands conservative default.')
            ->onViolation('Revert to parallel: false.');

        $this->rule('parallel-transitive-deps')->high()
            ->text('Check transitive dependencies: if task A modifies file X, and file X is imported by file Y, and task B modifies file Y — tasks A and B are NOT independent. Follow import/use/require chains one level deep minimum.')
            ->why('Indirect dependencies through shared modules cause subtle race conditions and inconsistent state.')
            ->onViolation('Trace import chain one level. Any indirect overlap = parallel: false.');
    }

    /**
     * Define parallel isolation checklist guideline.
     * Step-by-step verification procedure for task independence.
     * Used by: TaskDecomposeInclude, TaskCreateInclude, TaskBrainstormInclude (task creation workflows).
     */
    protected function defineParallelIsolationChecklistGuideline(): void
    {
        $this->guideline('parallel-isolation-checklist')
            ->goal('Systematic verification of task independence before setting parallel: true')
            ->example()
            ->phase('For EACH pair of tasks being considered for parallel execution:')
            ->phase('  1. FILE MANIFEST: List ALL files each task will read/write/create')
            ->phase('  2. FILE OVERLAP: Cross-reference manifests → shared file = parallel: false for BOTH')
            ->phase('  3. IMPORT CHAIN: Check if any file in task A imports/uses files from task B scope (and vice versa)')
            ->phase('  4. SHARED MODEL: Check if tasks modify same DB table, model, or migration')
            ->phase('  5. SHARED CONFIG: Check if tasks modify same config key, .env variable, or shared state')
            ->phase('  6. OUTPUT→INPUT: Check if task B needs any result/artifact/output from task A')
            ->phase('  7. TRANSITIVE: Follow imports one level deep — indirect overlap = NOT independent')
            ->phase('  RESULT: ALL checks pass → parallel: true | ANY check fails → parallel: false')
            ->phase('  DEFAULT: When analysis is uncertain or incomplete → parallel: false (safe default)');
    }

    // =========================================================================
    // CODEBASE PATTERN REUSE (CONSISTENCY & DRY)
    // =========================================================================

    /**
     * Define codebase pattern reuse rule.
     * Ensures agents search for similar existing implementations before writing new code.
     * Prevents reinventing the wheel and maintains codebase consistency.
     * Used by: TaskSyncInclude, TaskAsyncInclude, TaskDecomposeInclude, TaskCreateInclude, TaskBrainstormInclude.
     */
    protected function defineCodebasePatternReuseRule(): void
    {
        $this->rule('codebase-pattern-reuse')->critical()
            ->text('BEFORE implementing: search codebase for similar/analogous implementations. Grep for: similar class names, method signatures, trait usage, helper utilities. Found → REUSE approach, follow same patterns, extend existing code. Not found → proceed independently. NEVER reinvent what already exists in the project.')
            ->why('Codebase consistency > personal style. Duplicate implementations create maintenance burden, inconsistency, and confusion. Existing patterns are battle-tested.')
            ->onViolation('STOP. Search codebase for analogous code. Found → study and follow the pattern. Only then proceed.');
    }

    /**
     * Define codebase pattern reuse workflow guideline.
     * Step-by-step search procedure for finding and reusing existing implementations.
     * Used by: TaskSyncInclude, TaskAsyncInclude (as agent instruction).
     */
    protected function defineCodebasePatternReuseGuideline(): void
    {
        $this->guideline('codebase-pattern-reuse')
            ->goal('Find and reuse existing patterns before implementing anything new')
            ->example()
            ->phase('1. IDENTIFY: From task extract: class type, feature domain, architectural pattern')
            ->phase('2. SEARCH SIMILAR: Grep for analogous class names, method names, trait usage')
            ->phase('   Creating new Service → Grep *Service.php → Read → extract pattern')
            ->phase('   Adding validation → Grep existing validation → follow same approach')
            ->phase('   New API endpoint → Find existing endpoints → follow same structure')
            ->phase('3. SEARCH HELPERS: Grep for existing utilities, traits, base classes to reuse')
            ->phase('4. EVALUATE: ' . Store::as('EXISTING_PATTERNS', '{files, approach, utilities, base classes, conventions}'))
            ->phase('5. APPLY: Use $EXISTING_PATTERNS as blueprint. Follow conventions, extend helpers, reuse base classes.')
            ->phase('6. NOT FOUND: Proceed independently. Still follow project conventions from other code.');
    }

    // =========================================================================
    // IMPACT RADIUS ANALYSIS (REVERSE DEPENDENCY)
    // =========================================================================

    /**
     * Define impact radius analysis rule.
     * Ensures proactive reverse dependency check BEFORE editing files.
     * Prevents cascade failures from changing code that others depend on.
     * Used by: TaskSyncInclude, TaskAsyncInclude, TaskDecomposeInclude, TaskCreateInclude.
     */
    protected function defineImpactRadiusAnalysisRule(): void
    {
        $this->rule('impact-radius-analysis')->critical()
            ->text('BEFORE editing any file: check WHO DEPENDS on it. Grep for imports/use/require/extends/implements of target file. Dependents found → plan changes to not break them. Changing public method/function signature → update ALL callers or flag as breaking change.')
            ->why('Changing code without knowing its consumers causes cascade failures. Proactive impact analysis prevents breaking downstream code.')
            ->onViolation('STOP. Grep for reverse dependencies of target file. Assess impact BEFORE editing.');
    }

    /**
     * Define impact radius analysis workflow guideline.
     * Step-by-step procedure for assessing change blast radius.
     * Used by: TaskSyncInclude (inline), TaskAsyncInclude (via agent instructions).
     */
    protected function defineImpactRadiusAnalysisGuideline(): void
    {
        $this->guideline('impact-radius-analysis')
            ->goal('Understand blast radius before making changes')
            ->example()
            ->phase('1. For EACH file in change plan: Grep for imports/use/require/extends/implements referencing it')
            ->phase('2. Map dependents: {file → [consumers]}')
            ->phase('3. Classify: NONE (internal-only change) | LOW (private/unused externally) | MEDIUM (few consumers) | HIGH (widely used)')
            ->phase('4. HIGH impact → review all callers, ensure signature compatibility, include dependents in plan')
            ->phase('5. ' . Store::as('DEPENDENTS_MAP', '{file → [consumers], impact_level}'))
            ->phase('6. Changing interface/trait/abstract/base class → ALL implementors/users MUST be checked');
    }

    // =========================================================================
    // LOGIC & EDGE CASE VERIFICATION
    // =========================================================================

    /**
     * Define logic and edge case verification rule.
     * Ensures explicit logic correctness review after implementation.
     * AI code has 75% more logic bugs - this rule counteracts that.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions).
     */
    protected function defineLogicEdgeCaseVerificationRule(): void
    {
        $this->rule('logic-edge-case-verification')->high()
            ->text('After implementation: explicitly verify logic correctness for each changed function/method. Check: null/empty inputs, boundary values (0, -1, MAX, empty collection), off-by-one errors, error/exception paths, type coercion edge cases, concurrent access if applicable. Ask: "what happens if input is null? empty? maximum?"')
            ->why('AI-generated code has 75% more logic bugs than human code. Syntax and linter pass but logic fails silently. Most missed category in code reviews.')
            ->onViolation('Review each changed function: what happens with null? empty? boundary? error path? Fix before proceeding.');
    }

    // =========================================================================
    // PERFORMANCE AWARENESS
    // =========================================================================

    /**
     * Define performance awareness rule.
     * Prevents common performance anti-patterns during coding.
     * AI code has 8x more performance issues, especially I/O.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions), TaskDecomposeInclude.
     */
    protected function definePerformanceAwarenessRule(): void
    {
        $this->rule('performance-awareness')->high()
            ->text('During implementation: avoid known performance anti-patterns. Check for: nested loops over data (O(n²)), query-per-item patterns (N+1), I/O operations inside loops, loading entire datasets when subset needed, blocking operations where async possible, missing pagination for large collections, unnecessary serialization/deserialization.')
            ->why('AI-generated code has 8x more performance issues than human code, especially I/O patterns. Catching during coding is cheaper than fixing after validation.')
            ->onViolation('Review loops: is there a query/I/O inside? Can it be batched? Is the algorithm optimal for expected data size?');
    }

    // =========================================================================
    // CODE HALLUCINATION PREVENTION
    // =========================================================================

    /**
     * Define code hallucination prevention rule.
     * Ensures generated code references real methods/classes/functions.
     * Different from no-hallucination (tool results) - this is about CODE content.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions).
     */
    protected function defineCodeHallucinationPreventionRule(): void
    {
        $this->rule('code-hallucination-prevention')->critical()
            ->text('Before using any method/function/class in generated code: VERIFY it actually exists with correct signature. Read the source or use Grep to confirm. NEVER assume API exists based on naming convention. Common hallucinations: wrong method names, incorrect parameter order/count, non-existent helper functions, invented framework methods, deprecated APIs used as current.')
            ->why('AI generates plausible-looking code referencing non-existent APIs. Parses and lints OK but fails at runtime. Most dangerous because it looks correct.')
            ->onViolation('Read actual source for EVERY external method/class used. Verify name + parameter signature before writing.');
    }

    // =========================================================================
    // CLEANUP AFTER CHANGES
    // =========================================================================

    /**
     * Define cleanup after changes rule.
     * Ensures dead code and artifacts are removed after edits.
     * AI refactoring often leaves unused imports and orphaned code.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions).
     */
    protected function defineCleanupAfterChangesRule(): void
    {
        $this->rule('cleanup-after-changes')->medium()
            ->text('After all edits: scan changed files for artifacts. Remove: unused imports/use/require statements, unreachable code after refactoring, orphaned helper functions no longer called, commented-out code blocks, stale TODO/FIXME without actionable context.')
            ->why('AI refactoring often leaves dead imports, orphaned functions, commented-out code. Accumulates technical debt and confuses future readers.')
            ->onViolation('Scan changed files for unused imports and unreachable code. Remove confirmed dead code.');
    }

    // =========================================================================
    // STATUS/PRIORITY FILTERS
    // =========================================================================

    /**
     * Define status and priority icon mappings.
     * Used by: TaskListInclude, TaskStatusInclude
     */
    protected function defineStatusPriorityIconsGuideline(): void
    {
        $this->guideline('status-priority-icons')
            ->goal('Format task output with clear visual hierarchy using emojis and readable structure')
            ->text('Status icons: 📝 draft, ⏳ pending, 🔄 in_progress, ✅ completed, 🧪 tested, ✓ validated, ⏸️ stopped, ❌ canceled')
            ->text('Priority icons: 🔴 critical, 🟠 high, 🟡 medium, 🟢 low')
            ->text('Always prefix status/priority with corresponding emoji')
            ->text('Group tasks by status or parent, use indentation for hierarchy')
            ->text('Show key info inline: ID, title, priority, estimate')
            ->text('Use blank lines between groups for readability');
    }
}
