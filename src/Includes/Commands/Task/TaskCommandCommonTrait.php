<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Includes\Commands\SharedCommandTrait;
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
    use SharedCommandTrait;

    // =========================================================================
    // INPUT CAPTURE PATTERNS (base + description in InputCaptureTrait)
    // =========================================================================

    /**
     * Define input capture guideline for task commands with VECTOR_TASK_ID.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude,
     *          TaskDecomposeInclude, TaskBrainstormInclude
     */
    protected function defineInputCaptureGuideline(): void
    {
        $this->defineInputCaptureWithCustomGuideline([
            'VECTOR_TASK_ID' => '{numeric ID extracted from $CLEAN_ARGS}',
        ]);
    }

    // =========================================================================
    // STATUS SEMANTICS (TASK LIFECYCLE DEFINITION)
    // =========================================================================

    /**
     * Define task status semantics rule.
     * Establishes clear, unambiguous meaning for each task status.
     * Prevents agents from misusing "stopped" for failures/blocks.
     * Used by: ALL task command includes.
     */
    protected function defineStatusSemanticsRule(): void
    {
        $this->rule('status-semantics')->critical()
            ->text('Task status has STRICT semantics: "pending" = waiting to be worked on (includes failed/blocked tasks returned to queue). "in_progress" = currently being worked on. "completed" = implementation done, ready for validation. "tested" = tests written/passed. "validated" = passed all quality gates. "stopped" = PERMANENTLY CANCELLED — task is NOT needed, will NEVER be executed. ONLY set "stopped" when: user explicitly requests cancellation, OR task is provably unnecessary (duplicate, superseded, irrelevant). NEVER set "stopped" for: failures, blocks, validation issues, tool errors, missing dependencies. For these → set "pending" with detailed blocker in comment.')
            ->why('Agents misuse "stopped" as "failed/blocked" which breaks workflow permanently. A stopped task is removed from pipeline — it will never be picked up again. A pending task with a blocker comment will be retried, either automatically or manually.')
            ->onViolation('If about to set "stopped": verify it is a TRUE cancellation. If task failed or is blocked → set "pending" + comment explaining what happened. "stopped" is irreversible workflow termination.');
    }

    // =========================================================================
    // IRON EXECUTION RULES (UNIVERSAL ACROSS COMMANDS)
    // =========================================================================

    /**
     * Define iron execution rules shared across all task commands.
     * These are universal behavioral constraints that prevent hallucination and verbosity.
     * Each command still defines its own context-specific 'task-get-first' rule.
     * Progress reporting is handled by defineMachineReadableProgressRule() separately.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude, TaskDecomposeInclude.
     */
    protected function defineIronExecutionRules(): void
    {
        $this->rule('no-hallucination')->critical()
            ->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');

        $this->rule('no-verbose')->critical()
            ->text('FORBIDDEN: Wrapping actions in verbose commentary blocks (meta-analysis, synthesis, planning, reflection) before executing. Act FIRST, explain AFTER.');
    }

    // =========================================================================
    // GUARANTEED FINALIZATION (SAFETY NET)
    // =========================================================================

    /**
     * Define guaranteed finalization rule and guideline.
     * Ensures task NEVER remains in_progress after workflow completes.
     * Safety net: if all workflow paths somehow miss explicit status update,
     * this catches it before final output and returns task to pending.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineGuaranteedFinalizationRule(): void
    {
        $this->rule('guaranteed-finalization')->critical()
            ->text('Task MUST NOT remain in_progress after workflow completes. BEFORE emitting RESULT/NEXT output → verify current task status is NOT in_progress. If still in_progress after all workflow phases: set status to "pending" with comment "SAFETY NET: Workflow completed without explicit status update. Returned to pending for retry." This is the ABSOLUTE LAST safety net — every workflow path MUST set explicit status (completed/validated/tested/pending), but if a path is missed, this catches it.')
            ->why('A task stuck in in_progress blocks the entire pipeline. No orchestrator will pick it up, no human will see it as actionable. This safety net ensures workflow bugs are self-healing — worst case pending (retryable), never silent in_progress (invisible).')
            ->onViolation('IMMEDIATELY call task_update(status: pending) with explanation. Then emit RESULT: FAILED.');

        $this->guideline('guaranteed-finalization-check')
            ->goal('Safety net before final output')
            ->example()
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' → check current status')
            ->phase(Operator::if('status = "in_progress"', [
                'SAFETY NET TRIGGERED: workflow completed but status still in_progress.',
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "SAFETY NET: Workflow ended without explicit status update. Returned to pending for retry.", append_comment: true}'),
                Operator::output(['RESULT: FAILED — safety_net_triggered, reason=status_stuck_in_progress']),
            ]))
            ->phase('Proceed to RESULT/NEXT output');
    }

    // =========================================================================
    // AGENT DELEGATION INTEGRITY
    // =========================================================================

    /**
     * Define no-manual-agent-fallback rule.
     * When workflow delegates to validation/execution agents via Task(),
     * Brain MUST NOT perform agent work directly if agents fail.
     * Used by: TaskValidateInclude, TaskTestValidateInclude.
     */
    protected function defineNoManualAgentFallbackRule(): void
    {
        $this->rule('no-manual-agent-fallback')->critical()
            ->text('When workflow delegates to validation/execution agents via Task(), Brain MUST NOT perform agent work directly if agents fail. Brain role = orchestrate + aggregate. Agent role = execute + analyze. If ALL agents fail → set status to "pending", add failure comment with error details, abort. If >=2 of 4 agents succeed → proceed with partial results. NEVER: read files to validate manually, run tests directly, check code quality inline. The ONLY acceptable fallback is retry (max 1) or abort.')
            ->why('Manual fallback violates separation of concerns, produces lower quality validation (single pass vs multi-agent coverage), and masks tool errors that should be investigated. An abort with clear error is better than silent manual degradation.')
            ->onViolation('ABORT. Set status to pending. Report agent failure details. Suggest retry: /task:validate {id} [-y].');
    }

    // =========================================================================
    // ONE TASK PER CYCLE (EXECUTION BOUNDARY)
    // =========================================================================

    /**
     * Define one-task-per-cycle execution boundary rule.
     * Prevents executing multiple tasks in a single session — ensures predictability
     * and gives orchestrator control points between tasks.
     * Parent with children: handle first batch only (parallel group or single sequential).
     * Used by: TaskAsyncInclude, TaskSyncInclude.
     */
    protected function defineOneTaskPerCycleRule(): void
    {
        $this->rule('one-task-per-cycle')->critical()
            ->text('ONE assigned task = ONE execution cycle. After completing task → STOP and return result. NEVER: 1) search for and execute sibling tasks after completion, 2) inline-execute ALL children of parent task in one session. Parent with pending children: handle FIRST BATCH only (first parallel group or single next sequential child), then STOP — orchestrator dispatches remaining children in separate cycles. This applies regardless of how small children appear.')
            ->why('Multi-task sessions are unpredictable: context budget unknown upfront, estimates may be wrong in either direction. Starting task N+1 may exhaust context mid-work = partial results harder to recover than clean start. Orchestrator loses control points between tasks (cannot reprioritize, redirect, stop). Accumulated tool call results from prior tasks bloat context for current task. One task per cycle = one predictable unit = reliable orchestration.')
            ->onViolation('STOP after completing current task/batch. Return RESULT + NEXT. Let orchestrator dispatch next cycle.');
    }

    // =========================================================================
    // MACHINE-READABLE PROGRESS (STATUS/RESULT/NEXT OUTPUT CONTRACT)
    // =========================================================================

    /**
     * Define machine-readable progress format rule.
     * Standardized STATUS/RESULT/NEXT output contract for all executing commands.
     * Ensures parseable, consistent progress reporting across all task execution workflows.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineMachineReadableProgressRule(): void
    {
        $this->rule('machine-readable-progress')->high()
            ->text('ALL progress output MUST follow structured format. DURING EXECUTION: emit "STATUS: [phase_name] description" at each major workflow phase (task loaded, context gathered, agents delegated, validation running, etc.). AT COMPLETION: emit "RESULT: SUCCESS|PARTIAL|FAILED — key=value, key=value, summary" followed by "NEXT: recommended_action_or_command". No free-form progress — only STATUS/RESULT/NEXT lines. Examples: "STATUS: [loading] Task #42 loaded, mode=async, priority=high" | "STATUS: [context] 3 memories found, docs loaded" | "STATUS: [execution] 2 agents delegated" | "RESULT: SUCCESS — files=5, agents=3/3, memory=#123" | "NEXT: /task:validate #42".')
            ->why('Structured format enables UI rendering, orchestrator parsing, progress aggregation, and consistent user experience. Without it, each command reports differently — impossible to parse or automate.')
            ->onViolation('Reformat to STATUS/RESULT/NEXT structure. Replace free-form text with structured lines.');
    }

    /**
     * Define task lifecycle flow rule.
     * Enforces strict lifecycle: execute → validate → next task.
     * Prevents skipping validation after execution.
     * Used by: ALL executing/validating commands (Async, Sync, Validate, ValidateSync, TestValidate).
     */
    protected function defineNextStepFlowRule(): void
    {
        $this->rule('next-step-lifecycle')->critical()
            ->text('NEXT step MUST follow strict task lifecycle. Your scope is THIS task — NEVER suggest actions on sibling tasks outside your lifecycle flow. FORBIDDEN: skipping validation after execution, suggesting execute before current task is validated, acting on sibling tasks with potentially stale state. Consult next-step-lifecycle-flow guideline for exact NEXT command. Workflow completion phases contain reinforcement — follow them.')
            ->why('Each command reliably knows only its own task state. Sibling state may be stale — suggesting actions on siblings causes wrong commands (e.g. suggesting execute for already-validated task).')
            ->onViolation('Apply next-step-lifecycle-flow guideline. When uncertain → suggest re-validate same task.');

        $this->guideline('next-step-lifecycle-flow')
            ->goal('Determine correct NEXT command based on current lifecycle position')
            ->example()
            ->phase('0. Task has "'.self::TAG_ATOMIC.'" tag (decomposer determined non-decomposable):')
            ->phase('   NEXT: /task:sync {task_id} [-y] (or /task:async). Skip decompose — task is atomic.')
            ->phase('1. After /task:sync or /task:async (execution completed):')
            ->phase('   NEXT: /task:validate {same_task_id} [-y] (or /task:validate-sync)')
            ->phase('2. After /task:validate or /task:validate-sync — PASSED (status=validated):')
            ->phase('   a) More pending siblings exist → NEXT: /task:sync {next_pending_sibling_by_order} [-y] (or /task:async)')
            ->phase('   b) No pending siblings + task HAS parent → NEXT: /task:validate {parent_id} [-y] (validate parent, all children done)')
            ->phase('   c) No pending siblings + NO parent (root) → NEXT: all tasks complete')
            ->phase('3. After /task:validate FAILED — fix-tasks created:')
            ->phase('   NEXT: fix-tasks created, re-validate {same_task_id} after all fixes complete')
            ->phase('4. After /task:validate BLOCKED — test failures from parallel sibling (NOT this task):')
            ->phase('   NEXT: /task:validate {same_task_id} [-y] (retry after blocking sibling completes)')
            ->phase('5. After /task:validate FAILED — tool error/crash, no fix-tasks:')
            ->phase('   NEXT: /task:validate {same_task_id} [-y] (retry validation)')
            ->phase('6. After fix-task validated (task has "'.self::TAG_VALIDATION_FIX.'" tag):')
            ->phase('   a) ALL sibling fix-tasks done → NEXT: /task:validate {parent_id} [-y] (re-validate parent)')
            ->phase('   b) More fix-tasks pending → NEXT: /task:sync {next_fix_task_id} [-y] (or /task:async)')
            ->phase('7. After /task:test-validate TDD mode (status was pending):')
            ->phase('   NEXT: /task:sync {same_task_id} [-y] (or /task:async)')
            ->phase('8. After /task:test-validate validation mode (status was completed):')
            ->phase('   NEXT: /task:validate {same_task_id} [-y]');
    }

    // =========================================================================
    // RETRY CIRCUIT BREAKER (PREVENT INFINITE LOOPS)
    // =========================================================================

    /**
     * Define retry circuit breaker rule and guideline.
     * Prevents infinite retry loops in auto-approve mode by tracking attempt count.
     * After MAX_ATTEMPTS (3), adds "stuck" tag and aborts — task needs human investigation.
     * Counter is phase-specific (exec vs validate) to avoid false positives.
     * Counter auto-resets after CIRCUIT BREAKER entry (human removes tag → fresh start).
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude, TaskValidateSyncInclude.
     *
     * @param string $phase 'exec' for executors, 'validate' for validators
     */
    protected function defineRetryCircuitBreakerRule(string $phase = 'exec'): void
    {
        $marker = "ATTEMPT [{$phase}]";

        $this->rule('retry-circuit-breaker')->critical()
            ->text('MAX 3 '.$phase.' attempts per task. Parse task.comment for "'.$marker.':" markers at workflow start (count only markers AFTER last "CIRCUIT BREAKER:" entry — counter auto-resets when human removes "'.self::TAG_STUCK.'" tag and retries). If task has tag "'.self::TAG_STUCK.'" → ABORT immediately (needs human). If '.$phase.' attempt count >= 3 → add tag "'.self::TAG_STUCK.'", store failure summary to memory, ABORT. If count < 3 → proceed, include "'.$marker.': {N+1}/3" in task.comment when setting in_progress.')
            ->why('Without retry limit, auto-approve creates infinite loops: fail → pending → retry → fail. "stopped" = permanently cancelled (wrong semantics). Tag "'.self::TAG_STUCK.'" = circuit breaker: visible via task:list, removable by human to retry. Counter per phase (exec/validate) prevents false positives from normal lifecycle.')
            ->onViolation('Check '.$phase.' attempt counter BEFORE setting in_progress. Tag "'.self::TAG_STUCK.'" = HARD STOP.');

        $this->guideline('retry-circuit-breaker')
            ->goal('Break infinite retry loops by tracking '.$phase.' attempts and tagging stuck tasks')
            ->example()
            ->phase('1. Parse ' . Store::get('TASK') . '.comment: find last "CIRCUIT BREAKER:" entry. Count "'.$marker.':" markers AFTER it (or from start if none). → ' . Store::as('ATTEMPT_COUNT', '{count, default 0}'))
            ->phase('2. ' . Operator::if(Store::get('TASK') . '.tags contains "' . self::TAG_STUCK . '"', Operator::abort('Task is STUCK. Remove "' . self::TAG_STUCK . '" tag to retry.')))
            ->phase('3. ' . Operator::if(Store::get('ATTEMPT_COUNT') . ' >= 3', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, add_tag: "' . self::TAG_STUCK . '", comment: "CIRCUIT BREAKER: 3 '.$phase.' attempts exhausted. Needs human investigation. See failure history above.", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "STUCK: Task #{id} \'{title}\' failed after 3 '.$phase.' attempts. Review task comments for failure details.", category: "' . self::CAT_DEBUGGING . '", tags: ["' . self::MTAG_FAILURE . '"]}'),
                Operator::abort('Circuit breaker → tagged "' . self::TAG_STUCK . '".'),
            ]))
            ->phase('4. ' . Operator::if(Store::get('ATTEMPT_COUNT') . ' < 3', 'Proceed. Include "'.$marker.': {N+1}/3" when setting in_progress.'));
    }

    // =========================================================================
    // VALIDATION CORE RULES (SHARED VALIDATE/VALIDATE-SYNC)
    // =========================================================================

    /**
     * Define core validation rules shared between async and sync validation.
     * Covers: scope, completeness, cosmetic vs functional classification,
     * fix-task policy, test coverage, failure deduplication.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude.
     */
    protected function defineValidationCoreRules(): void
    {
        $this->rule('docs-are-complete-spec')->critical()
            ->text('Documentation (.docs/) = COMPLETE specification. task.content may be brief summary. ALWAYS read and validate against DOCUMENTATION if exists. Missing from docs = not a requirement. In docs but not done = MISSING.')
            ->why('task.content is often summary. Full spec lives in documentation. Validating only task.content misses requirements.')
            ->onViolation('Check DOCS_PATHS. If docs exist → read them → extract full requirements → validate against docs.');

        $this->rule('task-scope-only')->critical()
            ->text('Validate ONLY what task.content + documentation describes. Do NOT expand scope. Task says "add X" = check X exists and works. Task says "fix Y" = check Y is fixed. NOTHING MORE.');

        $this->rule('task-complete')->critical()
            ->text('ALL task requirements MUST be done. Parse task.content → list requirements → verify each. Missing = fix-task.');

        $this->rule('no-garbage')->critical()
            ->text('Garbage code in task scope = fix-task. Detect: unused imports, dead code, debug statements, commented-out blocks.');

        $this->rule('cosmetic-inline')->critical()
            ->text('Cosmetic issues = fix IMMEDIATELY inline. NO task created. Cosmetic: whitespace, typos, formatting, comments, docblocks, naming (non-breaking), import sorting.');

        $this->rule('functional-to-task')->critical()
            ->text('Functional issues = fix-task. Functional: logic bugs, security vulnerabilities, architecture violations, missing tests, broken functionality.');

        $this->rule('test-coverage')->high()
            ->text('No test coverage = fix-task. Critical paths = 100%, other >= 80%.');

        $this->rule('slow-test-detection')->high()
            ->text('Slow tests = fix-task. Unit >500ms, integration >2s, any >5s = CRITICAL.');

        $this->rule('no-repeat-failures')->critical()
            ->text('BEFORE creating fix-task: check if proposed solution matches known failure. If memory says "X does NOT work for Y" — DO NOT create task suggesting X. Research alternative or escalate.')
            ->why('Creating fix-task with known-failed solution = guaranteed failure + wasted effort.')
            ->onViolation('Search memory for proposed fix. Match found in debugging = BLOCK task creation, suggest alternative.');
    }

    // =========================================================================
    // FIX-TASK GATING (VALIDATION CYCLE CONSISTENCY)
    // =========================================================================

    /**
     * Define fix-task gating rules for validation cycle consistency.
     * Canonical re-validation behavior after fix-tasks:
     * - Fix-tasks present + all done → mandatory full re-validation from scratch
     * - Intermediate parent without fix-tasks → aggregation fast-path
     * - Fix-task exists → status NEVER "validated"
     * Used by: TaskValidateInclude, TaskValidateSyncInclude.
     * NOT used by: TaskTestValidateInclude (inline fixes, no fix-task creation).
     */
    protected function defineFixTaskGatingRules(): void
    {
        $this->rule('fix-task-blocks-validated')->critical()
            ->text('Fix-task created → status MUST be "pending", NEVER "validated". "validated" = ZERO fix-tasks. NO EXCEPTIONS.')
            ->why('MCP auto-propagation: when child task starts (status→in_progress), parent auto-reverts to pending. Setting "validated" with pending children is POINTLESS - system will reset it.')
            ->onViolation('ABORT validation. Set status="pending" BEFORE task_create. Never set "validated" if ANY fix-task exists.');

        $this->rule('revalidation-mandatory')->critical()
            ->text('ALL fix-subtasks completed/validated → MANDATORY full re-validation from scratch. No fast-path, no aggregation-only. Fixes may introduce new issues, original validation may have missed gaps.')
            ->why('Fix-tasks modify code that was already validated. Previous validation results are STALE. Only a full re-run catches regressions and new issues introduced by fixes.')
            ->onViolation('Detect fix-subtasks via "validation-fix" tag. If ALL done → proceed to full validation, NEVER skip to "validated".');

        $this->rule('aggregation-only-path')->high()
            ->text('Intermediate parent (has parent_id) with ALL decomposition subtasks validated and NO fix-subtasks → aggregation fast-path. Cross-reference parent requirements vs subtask results. If all covered → "validated" without full re-run. If gaps → scope validation to uncovered requirements only. Root tasks ALWAYS get full validation (cross-subtask integration).')
            ->why('Decomposition subtasks already validated their scope. Re-running full validation duplicates work. Root tasks need cross-subtask integration check that subtask validators never performed.')
            ->onViolation('Check: has parent_id? No fix-subtasks? All subtasks validated? → aggregation. Root task → ALWAYS full validation.');
    }

    // =========================================================================
    // COLLATERAL FAILURE DETECTION (UNRELATED TEST FAILURES)
    // =========================================================================

    /**
     * Define collateral failure detection rule and guideline.
     * Detects test failures OUTSIDE current task scope during validation.
     * Creates max 2 global remediation tasks (no parent_id) to prevent ignoring regressions.
     * Current task validation is NOT affected by collateral failures.
     * Global tasks avoid cascade re-validations (no parent-status propagation).
     * Collateral tasks are explicitly EXEMPT from parent-id-mandatory rule.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude.
     */
    protected function defineCollateralFailureDetectionRule(): void
    {
        $this->rule('collateral-failure-detection')->high()
            ->text('After test execution: separate failing tests into SCOPE (tests for files/modules in task.content or changed by this task) and COLLATERAL (tests for code clearly unrelated to task). Ambiguous = treat as SCOPE (conservative). If COLLATERAL failures exist AND task has ZERO in-scope failures → create max 2 GLOBAL remediation tasks with tag "'.self::TAG_REGRESSION.'" and NO parent_id (EXEMPT from parent-id-mandatory — intentional to prevent cascade re-validations). Current task PASSES quality gates. If task has in-scope failures → fail normally, mention collateral in report but do NOT create remediation tasks (fix own issues first). NOTE: practically triggers only on ROOT task validation (full test suite). Subtasks run scoped tests → no collateral possible.')
            ->why('Ignoring unrelated test failures = hidden regressions accumulate silently. Blocking current task on others\' failures = wrong task punished. Global tasks (no parent) enter normal queue without parent-status propagation → zero cascade re-validations. Max 2 per validation prevents spam. If task turns out unnecessary (already fixed), agent executes it, tests pass, done in one cycle — cheaper than missed regression.')
            ->onViolation('Classify test failures by scope. In-scope = current task problem. Out-of-scope = collateral → global task. Never block validation on collateral failures.');

        $this->guideline('collateral-failure-detection')
            ->goal('Detect unrelated test failures and create global remediation tasks without blocking current validation')
            ->example()
            ->phase('1. After test execution: classify each failing test')
            ->phase('   SCOPE: test file directly tests classes/modules changed by or mentioned in $TASK')
            ->phase('   COLLATERAL: test file tests code clearly unrelated (different module, domain, component)')
            ->phase('   AMBIGUOUS: cannot determine origin → treat as SCOPE (conservative, safe)')
            ->phase('2. '.Operator::if(Store::get('COLLATERAL_FAILURES').' not empty AND '.Store::get('ISSUES').' = 0 (no in-scope failures)', [
                'Group collateral failures by module/area (max 2 groups)',
                Operator::forEach('group in collateral_groups (max 2)', [
                    VectorTaskMcp::call('task_create', '{title: "Fix regression: {module/area}", content: "Test failure(s) detected during validation of task #{$TASK.id} (\'{$TASK.title}\').\n\nThese failures are OUTSIDE that task\'s scope — collateral/regression.\n\nFailing tests:\n- {test_names_with_errors}\n\nError summary:\n{error_details}\n\nDiscovered by: validator during task #{$TASK.id} validation.\nNOT caused by task #{$TASK.id}.", priority: "high", estimate: 2, tags: ["'.self::TAG_REGRESSION.'", "'.self::TAG_BUGFIX.'"]}').' ← NO parent_id (global task, exempt from parent-id-mandatory)',
                ]),
                'Current task: PASSES quality gates (collateral failures are NOT blockers)',
            ]))
            ->phase('3. '.Operator::if(Store::get('COLLATERAL_FAILURES').' not empty AND '.Store::get('ISSUES').' > 0', [
                'Current task FAILS normally (in-scope issues take priority)',
                'Mention collateral failures in validation report for awareness',
                'Do NOT create remediation tasks — fix own issues first, collateral caught on re-validation',
            ]))
            ->phase('4. '.Operator::if('no COLLATERAL failures', 'Normal validation flow — no additional action'));
    }

    // =========================================================================
    // PARENT ID MANDATORY (HIERARCHY INTEGRITY)
    // =========================================================================

    /**
     * Define parent-id-mandatory rule.
     * Ensures all new tasks/subtasks created during a command are children
     * of the current task, maintaining hierarchy integrity.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude,
     *          TaskDecomposeInclude, TaskBrainstormInclude.
     *
     * @param string $variableName Variable name holding the task ID (default: 'VECTOR_TASK_ID')
     */
    protected function defineParentIdMandatoryRule(string $variableName = 'VECTOR_TASK_ID'): void
    {
        $this->rule('parent-id-mandatory')->critical()
            ->text('ALL new tasks/subtasks created MUST have parent_id = $'.$variableName.'. No orphan tasks. No exceptions.')
            ->why('Task hierarchy integrity. Orphan tasks break traceability and workflow.')
            ->onViolation('ABORT task_create if parent_id missing or != $'.$variableName.'.');
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
            ->text('$VECTOR_TASK_ID MUST be a valid vector task ID reference. Valid formats: "15", "#15", "task 15", "task:15", "task-15". If not a valid task ID, abort and suggest '.$alternativeCommand.' for text-based tasks.')
            ->why('This command is exclusively for vector task execution. Text descriptions belong to '.$alternativeCommand.'.')
            ->onViolation('STOP. Report: "Invalid task ID. Use '.$alternativeCommand.' for text-based tasks or provide valid task ID."');
    }

    // =========================================================================
    // COMMENT CONTEXT ANALYSIS (READ ACCUMULATED CONTEXT)
    // =========================================================================

    /**
     * Define comment context analysis rules and extraction guideline.
     * Ensures task.comment is parsed for accumulated inter-session context before execution.
     * Comments contain: memory IDs, file paths, previous results, failures, blockers, decisions.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude, TaskDecomposeInclude, TaskBrainstormInclude.
     */
    protected function defineCommentContextRules(): void
    {
        $this->rule('comment-context-mandatory')->critical()
            ->text('AFTER loading task: parse task.comment for accumulated context. Extract: memory IDs (#NNN), file paths, previous execution results, failure reasons, blockers, decisions made. Store as $COMMENT_CONTEXT. Pass to ALL agents alongside task.content.')
            ->why('Comments accumulate critical inter-session context: what was tried, what failed, what files were touched, what decisions were made. Ignoring comments = blind re-execution without history.')
            ->onViolation('Parse task.comment IMMEDIATELY after task_get. Extract actionable context. Include in agent prompts and planning.');

        $this->guideline('comment-context-extraction')
            ->goal('Extract actionable context from task.comment before any execution or delegation')
            ->example()
            ->phase('Parse $TASK.comment (may be multi-line with \\n\\n separators):')
            ->phase('  1. MEMORY IDs: extract #NNN or memory #NNN patterns → previous knowledge links')
            ->phase('  2. FILE PATHS: extract file paths (src/*, tests/*, app/*, etc.) → files already touched/identified')
            ->phase('  3. EXECUTION HISTORY: entries with "completed", "passed", "started", "Done" → what was already done')
            ->phase('  4. FAILURES: entries with "failed", "error", "stopped", "rolled back" → what went wrong and why')
            ->phase('  5. BLOCKERS: entries with "BLOCKED", "waiting for", "needs" → current impediments')
            ->phase('  6. DECISIONS: entries with "chose", "decided", "approach", "using" → decisions already locked in')
            ->phase('  7. MODE FLAGS: "TDD MODE", "light validation", special execution modes')
            ->phase(Store::as('COMMENT_CONTEXT', '{memory_ids: [], file_paths: [], execution_history: [], failures: [], blockers: [], decisions: [], mode_flags: []}'))
            ->phase('If comment is empty/null → $COMMENT_CONTEXT = {} (proceed without, no error)');
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
            ->phase(Store::as('COMMENT_CONTEXT', '{parsed from $VECTOR_TASK.comment: memory_ids: [#NNN], file_paths: [...], execution_history: [...], failures: [...], blockers: [...], decisions: [], mode_flags: []}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent: {$PARENT_TASK.title or "none"}',
                'Subtasks: {count or "none"}',
                'Comment context: memory_ids={$COMMENT_CONTEXT.memory_ids}, files={$COMMENT_CONTEXT.file_paths}, failures={$COMMENT_CONTEXT.failures}, decisions={$COMMENT_CONTEXT.decisions}',
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
            ->phase('  8. GLOBAL BLACKLIST: If ANY task modifies globally shared files (dependency manifests/locks, .env*, config/**, routes/**, migration directories, CI/CD configs, test/lint/build configs) → that task MUST be parallel: false. Globally shared files are NEVER safe for parallel modification.')
            ->phase('  RESULT: ALL checks pass → parallel: true | ANY check fails → parallel: false')
            ->phase('  DEFAULT: When analysis is uncertain or incomplete → parallel: false (safe default)');
    }

    // =========================================================================
    // TEST SCOPING (SCOPED VS FULL SUITE)
    // =========================================================================

    /**
     * Define test scoping rule.
     * Ensures test execution is scoped to task-related files for subtasks,
     * while root tasks run the full test suite.
     * Prevents wasting time running entire test suite for every small task.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineTestScopingRule(): void
    {
        $this->rule('test-scoping')->critical()
            ->text('Test execution MUST be scoped based on task hierarchy level. SUBTASK (has parent_id): run ONLY tests related to changed files — a) test files that directly test changed classes/modules, b) test files that import/use/depend on changed classes (reverse dependency in test directory). ROOT TASK (no parent_id): run the FULL test suite via quality gate command. NEVER run full test suite for subtasks — it wastes more time than the task itself.')
            ->why('Full test suite for a 1-hour subtask can take longer than the task execution itself. Scoped tests catch 95%+ of regressions at 10% of the cost. Full suite runs at root aggregation level and manually before push.')
            ->onViolation('Check task.parent_id. Has parent → scoped tests only. No parent → full suite allowed.');
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
            ->text('Status icons: ⏳ pending, 🔄 in_progress, ✅ completed, 🧪 tested, ✓ validated, ❌ stopped (cancelled/not needed)')
            ->text('Priority icons: 🔴 critical, 🟠 high, 🟡 medium, 🟢 low')
            ->text('Always prefix status/priority with corresponding emoji')
            ->text('Group tasks by status or parent, use indentation for hierarchy')
            ->text('Show key info inline: ID, title, priority, estimate')
            ->text('Use blank lines between groups for readability');
    }

    // =========================================================================
    // PARALLEL EXECUTION AWARENESS (RUNTIME CONTEXT)
    // =========================================================================

    /**
     * Define parallel execution awareness rules.
     * When a task has parallel: true, the executing agent MUST understand
     * that sibling tasks may be running concurrently on the same codebase.
     * Agent must stay strictly within its task's file scope and never touch
     * files that siblings might be modifying or globally shared files
     * (dependency manifests, .env*, config, routes, migrations, CI/CD, test configs).
     * Complements defineParallelIsolationRules() which covers task CREATION.
     * This method covers task EXECUTION.
     * Used by: TaskSyncInclude, TaskAsyncInclude.
     */
    protected function defineParallelExecutionAwarenessRules(): void
    {
        $this->rule('parallel-execution-awareness')->critical()
            ->text('If $TASK.parallel === true: you are in PARALLEL CONTEXT. Other agents may be executing sibling tasks RIGHT NOW on the same codebase. IMMEDIATELY after loading task: fetch sibling tasks (same parent_id, parallel: true) to understand what they touch. Build $PARALLEL_SIBLINGS context. Stay STRICTLY within your task file scope.')
            ->why('parallel: true means this task was designed to run concurrently with siblings. Without awareness of sibling scopes, agent may accidentally modify shared files, causing conflicts and lost work across parallel sessions.')
            ->onViolation('Fetch siblings with same parent_id. Build parallel context. Restrict to own file scope.');

        $this->rule('parallel-strict-scope')->critical()
            ->text('In PARALLEL CONTEXT: modify ONLY files explicitly described in your task content or directly required by it. If you need to modify a file NOT in your task scope → DO NOT modify it. Record in task comment: "SCOPE EXTENSION NEEDED: {file} — reason: {why}". Let validation or next sequential task handle it.')
            ->why('Parallel sibling may be modifying that same file right now. Touching out-of-scope files = race condition, merge conflict, or overwritten work.')
            ->onViolation('ABORT out-of-scope edit. Add to task comment as scope extension request. Continue with in-scope work only.');

        $this->rule('parallel-shared-files-forbidden')->high()
            ->text('In PARALLEL CONTEXT: globally shared files are FORBIDDEN to edit regardless of sibling scopes. GLOBAL BLACKLIST (identify by project-specific patterns): 1) DEPENDENCY MANIFESTS & LOCKS — package definitions and lock files (composer.json, package.json, Gemfile, go.mod, Cargo.toml, requirements.txt and .lock counterparts), 2) ENVIRONMENT — .env* files, 3) GLOBAL CONFIG — config/**, settings/**, 4) ROUTING — routes/**, 5) SCHEMA/MIGRATIONS — migration directories, schema definitions (database/migrations/**, db/migrate/**), 6) INFRASTRUCTURE/CI — CI/CD pipelines, container configs, build scripts (.github/**, .gitlab-ci.yml, Dockerfile*, docker-compose*, docker/**, Makefile, Jenkinsfile), 7) TEST/LINT/BUILD CONFIG — root-level runner/linter/bundler configs (phpunit.xml*, jest.config*, tsconfig.json, .eslintrc*, vite.config*). Also blacklisted: any service/utility referenced by 2+ sibling tasks. If task REQUIRES blacklisted file → record in comment: "BLOCKED: needs {file} (globally shared). Defer to sequential phase." Complete non-blacklisted work first.')
            ->why('Globally shared files are statically known conflict sources — two agents editing same routes/config/migration simultaneously = one overwrites the other. Unlike sibling-scope files detected at runtime, blacklisted categories are ALWAYS shared regardless of task content. Explicit blacklist removes agent guesswork.')
            ->onViolation('ABORT edit of blacklisted file. Record in task comment: "BLOCKED: needs {file} (globally shared)". Complete remaining in-scope work. Blacklisted file edits handled sequentially after parallel phase.');

        $this->rule('parallel-scope-in-comment')->critical()
            ->text('In PARALLEL CONTEXT: after planning (when actual files known), STORE own scope in task comment via task_update: "PARALLEL SCOPE: [file1.php, file2.php, ...]" with append_comment: true. Siblings read your scope from task comment (already fetched via task_list — ZERO extra MCP calls). Do NOT store scopes in vector memory — scopes are ephemeral structured data, not semantic knowledge.')
            ->why('Task comments are free (come with task_list). Scopes are temporary file lists, not insights. Vector memory is for learnings/patterns, not ephemeral execution state. Comments self-clean when task is deleted.')
            ->onViolation('After planning: task_update with scope in comment. Read sibling scopes from their comments via task_list.');

        $this->rule('parallel-status-interpretation')->high()
            ->text('parallel: true does NOT mean siblings are running RIGHT NOW. It means they CAN run concurrently. Status interpretation: pending = not started, zero threat, ignore for conflict detection. completed = already done, files stable and committed, no active conflict. in_progress = potentially active, the ONLY status that matters for conflict detection. in_progress WITHOUT scope in memory = sibling still planning or just started, NOT a red flag, proceed normally. in_progress WITH scope in memory = REAL concurrent data, cross-reference for conflicts. Do NOT restrict yourself based on pending/completed siblings. Do NOT panic when in_progress sibling has no memory scope.')
            ->why('Without status interpretation, agents overreact: restrict themselves for pending tasks that haven\'t started, fear completed tasks that are done, panic when in_progress siblings lack memory scope. Causes unnecessary self-limitation and blocked work.')
            ->onViolation('Check sibling STATUS before reacting. Only in_progress + registered scope = actionable conflict data. Everything else = awareness only, not restriction.');
    }

    // =========================================================================
    // VALIDATOR PARALLEL COSMETIC DEFERRAL
    // =========================================================================

    /**
     * Define validator-specific parallel cosmetic deferral rule.
     * Validators making inline cosmetic fixes must check if the target file
     * is in an active sibling's scope before editing.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineValidatorParallelCosmeticRule(): void
    {
        $this->rule('validator-parallel-cosmetic-defer')->high()
            ->text('In PARALLEL CONTEXT: before making inline cosmetic fix, check if file is in ACTIVE sibling\'s scope (from $SIBLING_SCOPES). File in active sibling scope → DO NOT fix, record in task comment: "DEFERRED COSMETIC: {file}:{line} — {issue}. Reason: file in active sibling #{id} scope." File NOT in any active scope → safe to fix inline. This applies to ALL inline fixes: whitespace, formatting, typos, import sorting, comment cleanup.')
            ->why('Validator cosmetic fixes (Edit) on files being actively modified by a parallel executor = race condition. Even a whitespace fix overwrites the executor\'s in-memory file content, creating silent data loss or merge conflicts.')
            ->onViolation('Check $SIBLING_SCOPES before Edit. Active sibling owns file → defer cosmetic fix to task comment. Fix will be picked up by next validation pass after sibling completes.');
    }

    // =========================================================================
    // SCOPED GIT CHECKPOINT (MEMORY/ SACRED)
    // =========================================================================

    /**
     * Define scoped git checkpoint rule.
     * Git commits during validation MUST exclude memory/ and scope to task files in parallel context.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineScopedGitCheckpointRule(): void
    {
        $this->rule('scoped-git-checkpoint')->critical()
            ->text('Git checkpoint commits scope depends on context: 1) PARALLEL CONTEXT: "git add {task_file1} {task_file2}" — commit ONLY task-scope files. memory/ excluded implicitly (not in task files). Prevents staging other agents\' uncommitted work and SQLite binary conflicts. 2) NON-PARALLEL context: "git add -A" — full state checkpoint, INCLUDES memory/ for complete project state preservation. 3) If commit fails (pre-commit hook) → LOG and continue, work is still valid.')
            ->why('In parallel context, multiple agents write to memory/ SQLite and codebase concurrently. "git add -A" stages everything: other agents\' half-done work + binary SQLite mid-write = corrupted checkpoint. In non-parallel, "git add -A" is safe and DESIRED — memory/ commit preserves knowledge base alongside code for full revert capability.')
            ->onViolation('Parallel: "git add {specific_files}" (task scope only). Non-parallel: "git add -A" (full checkpoint with memory/).');
    }
}
