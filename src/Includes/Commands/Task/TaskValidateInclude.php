<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Includes;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Validate completed vector task. 4 parallel agents: Completion, Code Quality, Testing, Security & Performance. Conditional 5th research agent for stuck patterns. Creates fix-tasks for functional issues. Cosmetic fixed inline by agents.')]
#[Includes(TaskBaseInclude::class, TaskContextHandoffInclude::class)]
class TaskValidateInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->defineStatusSemanticsRule();
        $this->rule('task-get-first')->critical()->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze what to validate.');
        $this->defineIronExecutionRules();
        $this->defineOneTaskPerCycleRule();
        $this->defineGuaranteedFinalizationRule();
        $this->defineMachineReadableProgressRule();
        $this->defineNextStepFlowRule();
        // AUTO-APPROVE & WORKFLOW ATOMICITY (from trait)
        $this->defineAutoApprovalRules();

        $this->rule('no-direct-test-execution')->critical()
            ->text('Brain NEVER runs tests or quality gates directly via Bash during validation. ALL test execution MUST go through validation agents ONLY. Brain role = orchestrate agents + aggregate results. ZERO exceptions.')
            ->why('Brain running tests directly duplicates agent work, wastes tokens and time, risks timeouts, and bypasses structured validation. Agents already ran these tests.')
            ->onViolation('ABORT direct Bash test call. If tests needed — delegate to Testing agent. If subtasks already validated — trust their results.');

        $this->defineNoManualAgentFallbackRule();

        // DOCUMENTATION IS LAW (from trait - validates against docs, not made-up criteria)

        // SECRETS & PII PROTECTION (from trait - no secret exfiltration via output or storage)

        // CODEBASE CONSISTENCY (from trait - verify code follows existing patterns)
        $this->defineCodebasePatternReuseRule();

        // IMPACT & QUALITY (from trait - verify changes don't break consumers, catch AI code issues)
        $this->defineImpactRadiusAnalysisRule();
        $this->defineLogicEdgeCaseVerificationRule();
        $this->defineCodeHallucinationPreventionRule();
        $this->defineCleanupAfterChangesRule();

        // TEST SCOPING (from trait - scoped tests for subtasks, full suite for root)
        $this->defineTestScopingRule();

        // COMMENT CONTEXT (from trait - read accumulated context from task.comment)
        $this->defineCommentContextRules();

        // TAG TAXONOMY (from trait - predefined tags for tasks and memory)

        // PARENT INHERITANCE (IRON LAW)
        $this->defineParentIdMandatoryRule(allowGlobalRegressionTasks: true);

        if ($this->strictAtLeast('standard')) {
            $this->rule('estimate-mandatory')->critical()
                ->text('task_create MUST include estimate (hours). Pessimistic > optimistic. Realistic range, not fantasy.')
                ->onViolation('ABORT. Add estimate. Unsure? Take gut feeling × 1.5.');
        }

        // VALIDATION RULES (common from trait)
        $this->defineValidationCoreRules();

        // FIX-TASK GATING (from trait - canonical re-validation cycle after fix-tasks)
        $this->defineFixTaskGatingRules();

        // VALIDATION RULES (validate-specific) — standard+
        if ($this->strictAtLeast('standard')) {
            $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to validate. Task ID given = validate it. JUST EXECUTE.');
            $this->rule('parent-readonly')->critical()->text('$PARENT is READ-ONLY. NEVER task_update on parent. Validator scope = $VECTOR_TASK_ID ONLY.');
            $this->rule('no-breaking-changes')->high()->text('Breaking API/interface changes without documentation = fix-task.');
            $this->rule('flaky-test-detection')->high()->text('Flaky tests = fix-task.');
        }

        // FAILURE-AWARE VALIDATION (from trait - prevents repeating same mistakes)
        $this->defineFailureAwarenessRules();

        // STUCK PATTERN ESCALATION (from trait - detect circular failures, research alternatives)
        $this->defineStuckPatternEscalationRule();

        // FAILURE POLICY (from trait - universal tool error / missing docs / ambiguous spec handling)

        // RETRY CIRCUIT BREAKER (from trait - prevents infinite validate retry loops in auto-approve)
        $this->defineRetryCircuitBreakerRule('validate');

        // COLLATERAL FAILURE DETECTION (from trait - create global tasks for unrelated test failures)
        $this->defineCollateralFailureDetectionRule();

        // SECURITY, PERFORMANCE, TYPE SAFETY, DEPENDENCY, TEST QUALITY — strict+
        if ($this->strictAtLeast('strict')) {
            $this->rule('validation-severity-classification')->critical()
                ->text('Create fix-tasks for in-scope security, performance, type-safety, dependency, and test-quality failures. CRITICAL: injection, XSS, hardcoded secrets, data loss/crash risks. MAJOR: auth/authz, sensitive data exposure, N+1, type-safety, dependency vulnerabilities, failing tests, missing critical-path tests. MINOR: complexity/memory warnings, weak assertions, missing edge cases, dependency-license issues.')
                ->onViolation('Classify by highest applicable severity and create a validation-fix task unless the issue is purely cosmetic and safe to fix inline.');
        }

        // DEDUPLICATION & MERGE — standard+
        if ($this->strictAtLeast('standard')) {
            $this->rule('issue-deduplication')->high()
                ->text('Before creating fix-task: deduplicate issues. Same file + same issue type from different agents = ONE fix-task. Merge descriptions. Avoid duplicate work.')
                ->why('Multiple agents may find same issue. Duplicate tasks waste effort.')
                ->onViolation('Compare issues by file path and issue category before task_create.');

            // PARTIAL FAILURE HANDLING
            $this->rule('agent-partial-failure')->high()
                ->text('If agent crashes/times out: retry ONCE. If still fails: continue with remaining agents, mark agent failure in report. 2 of 4 agents = still validate, but note incomplete coverage.')
                ->why('One agent failure should not block entire validation. Partial results > no results.')
                ->onViolation('Log failed agent, include warning in final report, suggest manual review of uncovered area.');
        }

        // VALIDATOR PARALLEL SAFETY (fix-tasks stay sequential; only inline cosmetics need sibling-scope checks)
        $this->defineValidatorParallelCosmeticRule();
        $this->defineScopedGitCheckpointRule();

        // COSMETIC ROLLBACK — standard+
        if ($this->strictAtLeast('standard')) {
            $this->rule('cosmetic-atomic')->medium()
                ->text('Cosmetic fixes by agents MUST be atomic with validation. If validation creates fix-task (functional issues found), cosmetic changes STILL committed. Cosmetic improvements are always safe to keep.')
                ->why('Cosmetic fixes are non-breaking. Discarding them wastes work.');
        }

        // Light validation - skip heavy checks for trivial tasks — strict+
        if ($this->strictAtLeast('strict')) {
            $this->rule('light-validation-tag')->medium()
                ->text('Task with light/trivial/docs-only/cosmetic intent = light validation: skip heavy gates and agents; run only file existence, syntax, and basic format checks. Allowed: docs, typos, comments/docblocks, import sorting, formatting, .gitignore/.editorconfig. FORBIDDEN for logic/API changes, migrations, security code, new features, and bug fixes.')
                ->why('Trivial tasks do not need full validation, but any behavioral change must use full validation.');
        }

        // Quality gates - commands that MUST pass for validation
        $qualityCommands = $this->groupVars('QUALITY_COMMAND');
        $testGateCmd = $qualityCommands['TEST'] ?? '';
        $nonTestGateCmds = array_diff_key($qualityCommands, ['TEST' => true]);
        $nonTestGateList = !empty($nonTestGateCmds)
            ? implode(', ', array_map(static fn($k, $v) => "[{$k}]: {$v}", array_keys($nonTestGateCmds), $nonTestGateCmds))
            : 'none configured';
        $testScopingInstruction = !empty($testGateCmd)
            ? "Project test command (FULL SUITE ONLY): {$testGateCmd}. FOR SUBTASKS: this command runs ALL tests — ABSOLUTELY FORBIDDEN to run it or any equivalent without explicit file path or --filter. FOR ROOT TASKS: run {$testGateCmd}."
            : 'No project test command configured. Detect test runner from project config. FOR SUBTASKS: run by explicit file path or --filter ONLY. FOR ROOT TASKS: run full suite.';

        if (!empty($qualityCommands)) {
            $this->rule('quality-gates-mandatory')->critical()
                ->text('ALL quality commands MUST PASS with ZERO errors AND ZERO warnings. PASS = exit code 0 + no errors + no warnings. Warnings are NOT "acceptable". Any error OR warning = FAIL = create fix-task. Cannot mark validated until ALL gates show clean output.');

            foreach ($qualityCommands as $key => $cmd) {
                $this->rule('quality-' . $key)
                    ->critical()
                    ->text("QUALITY GATE [{$key}]: {$cmd}");
            }
        }

        // INPUT CAPTURE
        $this->defineContextAwareInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::callValidatedJson('task_get', ['task_id' => '$VECTOR_TASK_ID']) . ' ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase($this->automationFlagsPreflightPhase())
            ->phase(Operator::if(
                'status NOT IN [completed, tested, validated, in_progress]',
                Operator::abort('Complete first')
            ))
            ->phase(Operator::if(
                'status=in_progress',
                'SESSION RECOVERY: check if crashed session (no active work)',
                Operator::abort('another session active')
            ))
            ->phase(Store::as('COMMENT_CONTEXT', '{parsed from $TASK.comment: memory_ids: [#NNN], file_paths: [...], execution_history: [...], failures: [...], blockers: [...], decisions: [], mode_flags: []}'))
            ->phase($this->contextHandoffFingerprintPhase())
            ->phase($this->contextHandoffApplyPhase())
            ->phase($this->contextAwareParentLoadPhase(Store::get('TASK') . '.parent_id'))
            ->phase(VectorTaskMcp::callValidatedJson('task_list', ['parent_id' => '$VECTOR_TASK_ID']) . ' ' . Store::as('SUBTASKS'))

            // 1.21 CIRCUIT BREAKER: prevent infinite validation retry loops (check BEFORE validating)
            ->phase(Store::as('ATTEMPT_COUNT', 'count "ATTEMPT [validate]:" markers in $TASK.comment AFTER last "CIRCUIT BREAKER:" entry (default 0)'))
            ->phase(Operator::if(Store::get('TASK') . '.tags contains "' . self::TAG_STUCK . '"', Operator::abort('Task is STUCK. Remove "' . self::TAG_STUCK . '" tag to retry.')))
            ->phase(Operator::if(Store::get('ATTEMPT_COUNT') . ' >= 3', [
                VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'add_tag' => self::TAG_STUCK, 'comment' => 'CIRCUIT BREAKER: 3 validate attempts exhausted. Needs human investigation.', 'append_comment' => true]),
                VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'STUCK: Task #{id} failed 3x validation. See task comments.', 'category' => self::CAT_DEBUGGING, 'tags' => [self::MTAG_FAILURE]]),
                Operator::abort('Circuit breaker → tagged "' . self::TAG_STUCK . '".'),
            ]))

            // 1.25 Parallel execution awareness (validator: check if siblings are active before inline fixes)
            ->phase(Operator::if(Store::get('TASK') . '.parallel === true AND parent_id', [
                VectorTaskMcp::callValidatedJson('task_list', ['parent_id' => '$TASK.parent_id', 'limit' => 20]),
                Store::as('PARALLEL_SIBLINGS', 'filter: parallel=true AND id != $TASK.id → {id, title, status, comment}'),
                Store::as('ACTIVE_SIBLINGS', 'filter PARALLEL_SIBLINGS where status=in_progress'),
                'Extract "PARALLEL SCOPE: [...]" from each ACTIVE_SIBLINGS comment → ' . Store::as('SIBLING_SCOPES', '{sibling_id → [files]}'),
                'LOG: "PARALLEL CONTEXT (validator): {total} siblings ({active} active). Active scopes: {SIBLING_SCOPES or NONE}. Cosmetic fixes on active sibling files will be DEFERRED."',
            ]))

            // 1.3 SUBTASKS CHECK
            ->phase(Store::as('HAS_FIX_SUBTASKS', Store::get('SUBTASKS') . ' contains ANY subtask with tag "validation-fix"'))

            // 1.3a FIX-TASKS PRESENT → MANDATORY FULL RE-VALIDATION (never trust fast-path after fixes)
            ->phase(Operator::if(
                Store::get('SUBTASKS') . ' not empty AND ALL subtasks status = "validated" AND ' . Store::get('HAS_FIX_SUBTASKS'),
                [
                    'FIX-TASKS COMPLETED: Previous validation created fix-tasks, all now done.',
                    'MANDATORY FULL RE-VALIDATION: fixes may have introduced new issues, original validation may have missed gaps.',
                    'Proceed to FULL VALIDATION below — all agents run from scratch on the ENTIRE task scope.',
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
                            VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'validated', 'comment' => 'Aggregation validation: all {N} subtasks validated. Requirements covered: {list}.', 'append_comment' => true]),
                            Operator::output('task, subtask results summary, all requirements covered, status=validated'),
                            Operator::skip('full validation — decomposition subtasks already did the work'),
                        ]
                    ),
                    Operator::if(
                        'gaps found: some parent requirements NOT covered by any subtask',
                        [
                            Store::as('UNCOVERED_REQUIREMENTS', '[requirements not mapped to any subtask]'),
                            'Proceed to FULL VALIDATION below, but scope agents to UNCOVERED_REQUIREMENTS only',
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
                    'MANDATORY: Proceed to FULL VALIDATION — all agents run on ENTIRE task scope.',
                    'Focus: integration between subtasks, full test suite, all quality gates, cross-file dependencies.',
                ]
            ))

            // 1.5 Set in_progress IMMEDIATELY (all checks passed, work begins NOW)
            ->phase(VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'in_progress', 'comment' => 'ATTEMPT [validate]: {$ATTEMPT_COUNT + 1}/3. Started validation.', 'append_comment' => true]))

            // 2. Context gathering (memory + docs + related tasks + FAILURES)
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => 'task.title', 'limit' => 5, 'category' => self::CAT_CODE_SOLUTION]) . ' ' . Store::as('MEMORY_CONTEXT'))
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{task.title} {problem keywords} failed error not working broken', 'limit' => 5]) . ' ' . Store::as('KNOWN_FAILURES') . ' ← CRITICAL: what already FAILED (search by failure keywords, not category)')
            ->phase(VectorTaskMcp::callValidatedJson('task_list', ['query' => 'task.title', 'limit' => 5]) . ' ' . Store::as('RELATED_TASKS'))
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                [
                    VectorTaskMcp::callValidatedJson('task_list', ['parent_id' => '$TASK.parent_id', 'limit' => 20]) . ' ' . Store::as('SIBLING_TASKS') . ' ← previous attempts on same problem',
                    // CRITICAL: Search vector memory for EACH sibling task to get stored insights/failures
                    Operator::forEach('sibling in ' . Store::get('SIBLING_TASKS'), [
                        VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{sibling.title}', 'limit' => 3]) . ' → ALL memories for this sibling (failures, solutions, insights)',
                        VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{sibling.title} failed error not working', 'limit' => 3]) . ' → specifically failure-related memories',
                        'Append results to ' . Store::as('SIBLING_MEMORIES'),
                    ]),
                ]
            ))
            ->phase('Extract from ' . Store::get('SIBLING_TASKS') . ' comments + ' . Store::get('SIBLING_MEMORIES') . ': what was tried, what failed, what worked')
            ->phase(Store::as('FAILURE_PATTERNS', 'solutions that were tried and failed (from sibling comments + sibling memories + debugging memories)'))
            ->phase(BrainCLI::MCP__DOCS_SEARCH(['keywords' => '{keywords from task}']) . ' ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if('unknown library/pattern in task scope', [
                Context7Mcp::callJson('resolve-library-id', ['libraryName' => '{library}', 'query' => '{task question}']) . ' → ' . Store::as('LIBRARY_ID'),
                Context7Mcp::callJson('query-docs', ['libraryId' => '$LIBRARY_ID', 'query' => '{task question}']) . ' → understand API before validating',
            ]))

            // 3. Approval (skip if -y)
            ->phase(Operator::if(
                '$HAS_AUTO_APPROVE',
                Operator::skip('approval'),
                'show task info, wait "yes"'
            ))

            // 3.5 Light validation check - skip heavy validation for trivial tasks
            ->phase(Store::as('IS_LIGHT_VALIDATION', 'task.tags matches light-validation intent (light, trivial, docs-only, minor, cosmetic, etc.)'))
            ->phase(Operator::if(
                Store::get('IS_LIGHT_VALIDATION'),
                [
                    'LIGHT VALIDATION MODE: skip quality gates and agent validation',
                    'Check only: files exist, valid syntax/format, no obvious errors',
                    Operator::if(
                        'basic checks pass',
                        VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'validated', 'comment' => 'Light validation passed (trivial task)', 'append_comment' => true]),
                        [
                            VectorTaskMcp::callValidatedJson('task_create', ['title' => 'Light validation fixes: #ID', 'content' => 'basic_issues with evidence and file paths', 'parent_id' => '$VECTOR_TASK_ID', 'priority' => 'medium', 'estimate' => 1, 'parallel' => false, 'tags' => [self::TAG_VALIDATION_FIX]]),
                            VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending']),
                        ]
                    ),
                    Operator::output('task, light validation result, status'),
                    Operator::skip('full validation'),
                ]
            ))

            // 4. Validate (4 parallel agents) - TASK SCOPE ONLY - FULL VALIDATION PATH
            // CRITICAL: Each agent MUST receive full context to work independently
            ->phase('PREPARE AGENT CONTEXT (extract from stored data):')
            ->phase('  - TASK_ID: ' . Store::get('TASK') . '.id')
            ->phase('  - TASK_TITLE: ' . Store::get('TASK') . '.title')
            ->phase('  - TASK_CONTENT: ' . Store::get('TASK') . '.content (full requirements text)')
            ->phase('  - TASK_FILES: merge file paths from task.content + COMMENT_CONTEXT.file_paths + executor-reported files + git status/diff manifest')
            ->phase('  - PARENT_CONTEXT: ' . Store::get('PARENT') . '.title + .content (if exists) — broader goal')
            ->phase('  - MEMORY_IDS: list memory IDs from ' . Store::get('MEMORY_CONTEXT') . ' (e.g., #1500, #1502)')
            ->phase('  - KNOWN_FAILURES_TEXT: full text from ' . Store::get('KNOWN_FAILURES') . ' — what NOT to suggest')
            ->phase('  - FAILURE_PATTERNS_TEXT: full text from ' . Store::get('FAILURE_PATTERNS') . ' — previous failed attempts')
            ->phase('  - DOCS_PATHS: file paths from ' . Store::get('DOCS_INDEX') . ' (if relevant)')
            ->phase('  - HAS_PARENT: ' . Store::get('TASK') . '.parent_id exists (true = subtask = scoped tests, false = root task = full suite)')
            ->phase('  - COMMENT_CONTEXT: ' . Store::get('COMMENT_CONTEXT') . ' — accumulated inter-session history (memory IDs, files touched, failures, decisions)')
            ->phase('  - AUTO_APPROVE: ' . Store::get('HAS_AUTO_APPROVE') . ' (true = -y mode, agent MUST NOT ask user questions)')
            ->phase('  - SIBLING_SCOPES: ' . Store::get('SIBLING_SCOPES') . ' (if parallel context exists)')
            ->phase(Store::as('AGENT_CONTEXT', 'formatted context block with all above data INCLUDING $COMMENT_CONTEXT and $AUTO_APPROVE'))
            ->phase(Operator::parallel([
                TaskTool::agent('explore', '
SHARED CONTEXT: STORE-GET($AGENT_CONTEXT). Includes task, files, parent, docs, memory, comment context, auto-approve, known failures, failure patterns, sibling scopes. Auto-approve=true → never ask. Known failures/failure patterns → do not suggest.

CRITICAL: DOCUMENTATION = LAW
- task.content may be brief summary
- Documentation (.docs/) = COMPLETE specification
- ALWAYS read docs if DOCS_PATHS provided
- Validate against DOCUMENTATION, not just task.content
- If docs say X but code does Y → MISSING REQUIREMENT
- If code does Z but docs don\'t mention Z → verify if needed or garbage

MISSION: COMPLETION CHECK
1. IF DOCS_PATHS exist → Read ALL documentation files FIRST
2. Extract FULL requirements from: documentation (primary) + task.content (secondary)
3. Create checklist: combine docs requirements + task.content requirements
4. For EACH requirement → verify done in task files
5. Check ONLY files from TASK_FILES list
6. Detect garbage: unused imports, dead code, debug statements, commented code
7. PATTERN CONSISTENCY: Grep for similar classes/methods in codebase — verify implementation follows established project patterns and conventions
8. Fix cosmetic issues inline (whitespace, formatting) — BUT IN PARALLEL CONTEXT: check SIBLING_SCOPES first. File in active sibling scope → DO NOT fix, record as "DEFERRED COSMETIC: {file}:{line} — {issue}"
9. FORBIDDEN: running test commands (phpunit, pest, jest, pytest, composer test, npm test, etc.) — Testing agent handles ALL test execution exclusively
10. PARALLEL CONTEXT: {SIBLING_SCOPES}. If active siblings exist → before ANY Edit, verify file is NOT in their scope. Deferred cosmetics are NOT failures.
11. DOCUMENTATION CHECK: IF task adds NEW feature/module/API → run ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '{keywords}']) . ' to verify .docs/ documentation exists. No docs for new feature = cosmetic issue (executor should have created). Create basic .docs/{feature}.md inline with YAML front matter (name, description, type, date, version) + brief markdown description. If parallel context and doc file could conflict → defer to comment.

Return JSON: {docs_read: [], requirements_from_docs: [], requirements_from_task: [], requirements_checklist: [{requirement, source: "docs|task", status, evidence}], missing_requirements: [{requirement, source, evidence, fix_task_needed}], garbage: [{file, line: null|int, issue, evidence, fix_task_needed}], pattern_violations: [{file, line: null|int, evidence, fix_task_needed}], cosmetic_fixed: [], cosmetic_deferred: [], docs_coverage: {new_features: [], has_docs: bool, docs_created: []}}')
,
                TaskTool::agent('explore', '
SHARED CONTEXT: STORE-GET($AGENT_CONTEXT). Auto-approve=true → never ask. Known failures/failure patterns → do not suggest.

MISSION: CODE QUALITY (static analysis only, NO test execution)
1. Read EACH file from TASK_FILES
2. Check: logic errors, architecture violations, breaking changes
3. Check: type safety (missing types, nullable without null checks)
4. Check: algorithmic complexity (nested loops on data, O(n²))
5. HALLUCINATION CHECK: Verify ALL method/function/class calls reference REAL code. Read source files to confirm methods exist with correct signatures. Flag phantom API calls.
6. IMPACT RADIUS: For each changed file, Grep who imports/uses/extends it. Verify consumers are NOT broken by changes. Changed public signature → all callers must be updated.
7. LOGIC EDGE CASES: For each changed function, verify: what happens with null input? empty collection? boundary values (0, -1, MAX)? error path?
8. Run ONLY these non-test quality gates: ' . $nonTestGateList . '
9. Fix cosmetic issues inline — BUT IN PARALLEL CONTEXT: check SIBLING_SCOPES first. File in active sibling scope → defer to comment.
10. FORBIDDEN: running test commands — Testing agent handles ALL test execution exclusively
11. PARALLEL CONTEXT: {SIBLING_SCOPES}. If active siblings exist → verify file ownership before Edit. Deferred cosmetics are NOT failures.

Return JSON: {files_reviewed: [], logic_issues: [{file, line: null|int, severity, evidence, fix_task_needed}], architecture_issues: [{file, line: null|int, severity, evidence, fix_task_needed}], type_issues: [{file, line: null|int, severity, evidence, fix_task_needed}], complexity_issues: [{file, line: null|int, severity, evidence, fix_task_needed}], hallucinated_calls: [{file, line: null|int, call, evidence, fix_task_needed}], broken_consumers: [{file, line: null|int, consumer, evidence, fix_task_needed}], edge_case_issues: [{file, line: null|int, evidence, fix_task_needed}], static_analysis_result: {}, cosmetic_deferred: []}'),
                TaskTool::agent('explore', '
SHARED CONTEXT: STORE-GET($AGENT_CONTEXT). HAS_PARENT controls test scope. Auto-approve=true → never ask. Known failures/failure patterns → do not suggest.

MISSION: TESTING (EXCLUSIVE test executor — only this agent runs tests)

TEST SCOPING — IRON RULE:
' . $testScopingInstruction . '

=== SUBTASK (HAS_PARENT = true) — SCOPED EXECUTION ===
STEP 1: Find test files related to TASK_FILES:
  - Grep tests/ for TASK_FILES class names and method names
  - Check mirror directory structure (src/Services/Foo.php → tests/Unit/Services/FooTest.php)
STEP 2: Grep test directory for imports/uses of TASK_FILES classes → consumer tests
STEP 3: Run ONLY found files by EXPLICIT file path. Examples:
  - phpunit tests/Unit/Services/FooTest.php
  - php artisan test --filter=FooService
  - jest src/__tests__/foo.test.js
  - pytest tests/test_foo.py
STEP 4: If no direct files found → use --filter with class/method name

ABSOLUTELY FORBIDDEN for subtasks (CRITICAL VIOLATION):
  × ANY test command WITHOUT explicit file path or --filter
  × composer test / npm test / pytest (no args)
  × php artisan test / php artisan test --parallel (no --filter)
  × phpunit / ./vendor/bin/phpunit (no path, no --filter)
  × "running full suite to get summary" or "checking all tests pass"

=== ROOT TASK (HAS_PARENT = false) — FULL SUITE ===
Run project test command for complete coverage.

QUALITY CHECKS (both scoped and root):
1. Tests exist (coverage >=80%, critical paths =100%)
2. Meaningful assertions (not just "no exception thrown")
3. Edge cases covered (null, empty, boundary values)
4. Slow tests (unit >500ms, integration >2s)
5. If suspect flaky → run 2x to confirm

If test approach mentioned in KNOWN_FAILURES → find ALTERNATIVE approach
PARALLEL CONTEXT: {SIBLING_SCOPES}. If active siblings exist → run ONLY tests for THIS task files. Do NOT run tests that touch active sibling files.

Return JSON: {scoped: bool, test_files_found: [], consumer_tests_found: [], coverage: {}, missing_tests: [{file, line: null|int, evidence, fix_task_needed}], failing_tests: [{test, file, evidence, fix_task_needed}], weak_assertions: [{file, line: null|int, evidence, fix_task_needed}], missing_edge_cases: [{file, line: null|int, evidence, fix_task_needed}], slow_tests: [{test, duration_ms, evidence, fix_task_needed}], flaky_tests: [{test, evidence, fix_task_needed}], quality_gate_result: {}}'),
                TaskTool::agent('explore', '
SHARED CONTEXT: STORE-GET($AGENT_CONTEXT). Auto-approve=true → never ask. Known failures/failure patterns → do not suggest.

MISSION: SECURITY & PERFORMANCE
SECURITY (check each file):
1. Injection: SQL (parameterized?), command (escaped?), template
2. XSS: output escaping in HTML/JS context
3. Secrets: grep for password, api_key, token, secret, credential
4. Auth/authz: missing checks, IDOR, privilege escalation
5. Sensitive data: PII in logs, data in error messages

PERFORMANCE (check each file):
1. N+1 queries: loop with DB/API call inside
2. Memory: loading unbounded data, missing pagination
3. If new dependencies added → run audit

CLEANUP (check each file — BUT IN PARALLEL CONTEXT: check SIBLING_SCOPES before fixing):
1. Unused imports/use/require statements
2. Dead code: unreachable after refactoring, orphaned functions/methods
3. Commented-out code blocks (not doc comments)
4. Debug/temporary statements left behind
If file in active sibling scope → DO NOT fix cleanup inline, record as "DEFERRED COSMETIC"

FORBIDDEN: running test commands (phpunit, pest, jest, pytest, composer test, npm test, etc.) — Testing agent handles ALL test execution exclusively
PARALLEL CONTEXT: {SIBLING_SCOPES}. If active siblings exist → verify file ownership before ANY Edit. Deferred cleanups are NOT failures.

Return JSON: {files_reviewed: [], injection: [{file, line: null|int, evidence, fix_task_needed}], xss: [{file, line: null|int, evidence, fix_task_needed}], secrets: [{file, line: null|int, evidence, fix_task_needed}], auth_issues: [{file, line: null|int, evidence, fix_task_needed}], data_exposure: [{file, line: null|int, evidence, fix_task_needed}], n_plus_one: [{file, line: null|int, evidence, fix_task_needed}], memory_issues: [{file, line: null|int, evidence, fix_task_needed}], dependency_vulnerabilities: [{dependency, evidence, fix_task_needed}], dead_code: [{file, line: null|int, evidence, fix_task_needed}], debug_statements: [{file, line: null|int, evidence, fix_task_needed}], cosmetic_deferred: []}'),
            ]))

            // 4.5 AGENT RESULTS GATE — verify sufficient agent coverage before proceeding
            ->phase(Store::as('AGENT_SUCCESS_COUNT', '{count of agents that returned valid JSON results}'))
            ->phase(Operator::if(Store::get('AGENT_SUCCESS_COUNT') . ' = 0', [
                'ALL AGENTS FAILED. Cannot validate without agent results.',
                VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending', 'comment' => 'Validation ABORTED: all 4 agents failed (tool errors). No manual fallback. Retry needed.', 'append_comment' => true]),
                Operator::output(['RESULT: FAILED — agents=0/4, reason=all_agents_failed']),
                Operator::output(['NEXT: /task:validate {$VECTOR_TASK_ID} [-y] (retry after tool issue resolves)']),
                Operator::abort('Zero agent results — cannot validate'),
            ]))
            ->phase(Operator::if(Store::get('AGENT_SUCCESS_COUNT') . ' < 2', [
                'INSUFFICIENT AGENT COVERAGE (' . Store::get('AGENT_SUCCESS_COUNT') . '/4). Minimum 2 required for meaningful validation.',
                VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending', 'comment' => 'Validation ABORTED: only ' . Store::get('AGENT_SUCCESS_COUNT') . '/4 agents succeeded. Insufficient coverage for reliable validation.', 'append_comment' => true]),
                Operator::output(['RESULT: FAILED — agents=' . Store::get('AGENT_SUCCESS_COUNT') . '/4, reason=insufficient_coverage']),
                Operator::output(['NEXT: /task:validate {$VECTOR_TASK_ID} [-y] (retry)']),
                Operator::abort('Insufficient agent coverage'),
            ]))

            // 5. Finalize (IRON LAW: fix-task created = "pending" ALWAYS. MCP will reset status anyway when child starts. NO "validated" with children.)
            ->phase('MERGE RESULTS: Collect all agent JSON outputs. DEDUPLICATE: same file + same issue type = merge into one. CLASSIFY severity:')
            ->phase('  CRITICAL: security issues (injection, XSS, secrets, auth), data loss risk, crashes')
            ->phase('  MAJOR: logic bugs, missing tests for critical paths, N+1 queries, type safety violations, failing tests')
            ->phase('  MINOR: missing edge case tests, complexity warnings, weak assertions, slow tests')
            ->phase('FILTER: Separate results by scope. IN-SCOPE issues → ' . Store::as('ISSUES') . '. OUT-OF-SCOPE test failures → ' . Store::as('COLLATERAL_FAILURES') . ' (test failures only, not code quality opinions).')

            // 5.1 COLLATERAL FAILURE DETECTION: create global tasks for unrelated test failures
            ->phase(Operator::if(
                Store::get('COLLATERAL_FAILURES') . ' not empty AND ' . Store::get('ISSUES') . ' = 0 (no in-scope issues)',
                [
                    'COLLATERAL FAILURES DETECTED: tests failing OUTSIDE task scope.',
                    'Group by module/area (max 2 groups)',
                    Operator::forEach('group in collateral_groups (max 2)', [
                        VectorTaskMcp::callValidatedJson('task_create', ['title' => 'Fix regression: {module/area}', 'content' => 'Test failure(s) outside task #{$TASK.id} scope.\n\nFailing: {test_names}\nError: {summary}\n\nDiscovered during validation of #{$TASK.id}, NOT caused by it.', 'priority' => 'high', 'estimate' => 2, 'tags' => [self::TAG_REGRESSION, self::TAG_BUGFIX]]) . ' ← NO parent_id (global task, exempt from parent-id-mandatory)',
                    ]),
                    'Collateral tasks created. Current task validation NOT affected.',
                ]
            ))
            ->phase(Operator::if(
                Store::get('COLLATERAL_FAILURES') . ' not empty AND ' . Store::get('ISSUES') . ' > 0',
                'Collateral failures noted in report but NOT creating tasks — fix own in-scope issues first.'
            ))

            // 5.5 PRE-FIX-TASK CHECK - verify proposed fixes are NOT in known failures
            ->phase(Operator::if(
                Store::get('ISSUES') . ' not empty',
                [
                    'For EACH proposed fix in issues:',
                    VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{proposed_fix_description} failed not working broken error', 'limit' => 3]) . ' → check if this fix already failed',
                    Operator::if(
                        'memory says this approach FAILED before',
                        [
                            'BLOCK this fix from task creation',
                            'Search for ALTERNATIVE approach: ' . VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{problem} alternative solution', 'limit' => 5, 'category' => self::CAT_CODE_SOLUTION]),
                            Operator::if(
                                'no alternative found',
                                [
                                    'ESCALATE: "Problem {X} has no known working solution. Previous attempts failed: {list}. Needs research or human decision."',
                                    'Add to task comment instead of creating fix-task',
                                ]
                            ),
                        ]
                    ),
                ]
            ))
            ->phase(Store::as('FILTERED_ISSUES', 'issues with known-failed fixes removed, alternatives added where found'))

            // 5.6 STUCK PATTERN ANALYSIS — detect circular failure patterns before creating fix-tasks
            ->phase(Operator::if(Store::get('FILTERED_ISSUES') . ' not empty', [
                'For EACH issue in FILTERED_ISSUES: count appearances of same {file_path + issue_category} in ' . Store::get('FAILURE_PATTERNS') . ' + ' . Store::get('SIBLING_MEMORIES') . ' + ' . Store::get('KNOWN_FAILURES') . '. If count >= 2 → mark as STUCK.',
                Store::as('STUCK_ISSUES', '{issues with circular failure pattern, count >= 2}'),
                Operator::if(Store::get('STUCK_ISSUES') . ' not empty', [
                    'STUCK PATTERN DETECTED: {count} issue(s) in circular failure zones.',
                    Store::as('STUCK_FAILED_APPROACHES', 'For each STUCK issue: collect ALL previously tried approaches from FAILURE_PATTERNS + sibling comments + debugging memories'),

                    // 5.7 CONDITIONAL RESEARCH ESCALATION — sequential agent, runs ONLY when stuck pattern detected
                    TaskTool::agent('web-research-master', '
MISSION: Research ALTERNATIVE SOLUTIONS for stuck validation issues.

Validation of task #{TASK_ID} ("{TASK_TITLE}") found issues matching CIRCULAR FAILURE PATTERNS — same problems were found and "fixed" before, but fixes failed or regressed.

STUCK ISSUES (need alternative approaches):
{STUCK_ISSUES — for each: file, issue category, description, times_failed}

PREVIOUSLY FAILED APPROACHES (DO NOT SUGGEST THESE):
{STUCK_FAILED_APPROACHES — for each: approach description, why it failed, when}

TASK CONTEXT:
- Task content: {TASK_CONTENT}
- Tech stack: extract from project files (composer.json, package.json, etc.)

STEPS:
1. For EACH stuck issue: identify the CORE problem (not symptom)
2. ' . Context7Mcp::callJson('resolve-library-id', ['libraryName' => '{relevant library/framework}', 'query' => '{problem pattern}']) . ' → resolve official docs target
3. ' . Context7Mcp::callJson('query-docs', ['libraryId' => '{resolved_id}', 'query' => '{problem pattern} recommended approach']) . ' → official docs patterns
4. WebSearch("{problem} {framework} best practice alternative solution") → community solutions
5. Cross-reference results against FAILED APPROACHES — eliminate already-tried
6. For each issue: rank remaining alternatives by confidence (high/medium/low)
7. If AUTO-APPROVE: select highest-confidence untried approach automatically

Return JSON: {stuck_issues: [{file, issue, times_failed, failed_approaches: [], research_findings: [{source, approach, confidence, rationale}], recommended: {approach, confidence, rationale}, escalate_to_human: bool}]}'),
                    Store::as('RESEARCH_RESULT', '{research agent output}'),

                    // Inject research into FILTERED_ISSUES for fix-task content enrichment
                    'For EACH stuck issue in RESEARCH_RESULT: enrich matching FILTERED_ISSUES entry:',
                    '  → "STUCK ZONE ({times_failed}x failed): {file}:{issue}"',
                    '  → "Failed approaches: {list}"',
                    '  → "Research findings: {alternatives with sources}"',
                    '  → "RECOMMENDED: {best_untried} (confidence: {level})"',

                    // Escalate issues with no alternative found
                    Operator::if('any stuck issue has escalate_to_human = true', [
                        'ESCALATION: {N} issue(s) have no alternative after research.',
                        VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'comment' => 'STUCK ESCALATION: Issues without alternative after research: {list}. Previously failed: {details}. Needs human decision.', 'append_comment' => true]),
                        VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'STUCK: Task #{TASK.id} — issues without alternative: {list}. All known approaches failed. Needs new strategy.', 'category' => self::CAT_DEBUGGING, 'tags' => [self::MTAG_FAILURE]]),
                        'Remove escalated issues from FILTERED_ISSUES (do NOT create doomed fix-tasks)',
                    ]),
                ]),
            ]))

            ->phase(Operator::if(
                Store::get('FILTERED_ISSUES') . '=0 AND no fix-task needed',
                [
                    VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'validated']),
                    // 5.7 Git checkpoint — commit validated work (parallel = scoped files, non-parallel = full checkpoint with memory/)
                    Operator::if(Store::get('TASK') . '.parallel === true AND ACTIVE_SIBLINGS exist', [
                        BashTool::call('git add {$TASK_FILES}') . ' → PARALLEL: stage ONLY task-scope manifest files (excludes memory/ and sibling work)',
                    ], [
                        BashTool::call('git status --short') . ' → check for unrelated WIP before staging',
                        BashTool::call('git add {$TASK_FILES}') . ' → NON-PARALLEL: stage task-scope manifest files; use full checkpoint ONLY when status shows no unrelated WIP',
                    ]),
                    BashTool::call('git commit -m "Task #$VECTOR_TASK_ID: $TASK_TITLE [validated]"'),
                    Operator::if('commit fails (pre-commit hook)', 'LOG: commit skipped, work is still validated. Continue to report.'),
                ],
                [
                    VectorTaskMcp::callValidatedJson('task_create', ['title' => 'Validation fixes: #ID', 'content' => 'filtered_issues_list with evidence, file paths, severity, and known-failed approaches excluded', 'parent_id' => '$VECTOR_TASK_ID', 'priority' => '{critical>0 ? high : medium}', 'estimate' => '{TOTAL_ESTIMATE}', 'parallel' => false, 'tags' => [self::TAG_VALIDATION_FIX]]) . ' ← parallel: false always for validation fixes unless a later decomposer proves isolation.',
                    VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending']) . ' ← IRON LAW: always "pending" when fix-task created. MCP will reset anyway.',
                ]
            ))

            // 6. Report
            ->phase(Operator::output('task, Critical/Major/Minor counts, cosmetic fixed, status, fix-task ID'))
            ->phase(Operator::if(
                Store::get('FILTERED_ISSUES') . ' not empty',
                VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'Validation #{TASK.id}: {issue_pattern_summary}. Root causes and fix approaches for future reference.', 'category' => self::CAT_DEBUGGING, 'tags' => [self::MTAG_FAILURE]]) . ' ← ONLY issue patterns, not operational status'
            ))

            // SAFETY NET: guaranteed finalization — verify status changed from in_progress
            ->phase(VectorTaskMcp::callValidatedJson('task_get', ['task_id' => '$VECTOR_TASK_ID']) . ' → verify status is NOT in_progress')
            ->phase(Operator::if('task.status = "in_progress"', [
                'SAFETY NET TRIGGERED: workflow completed but status still in_progress.',
                VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending', 'comment' => 'SAFETY NET: Validation workflow ended without explicit status update. Returned to pending.', 'append_comment' => true]),
            ]))

            // NEXT (lifecycle reinforcement — at workflow end for recency)
            ->phase('NEXT STEP — determine from lifecycle position:')
            ->phase(Operator::if('status = "validated"', [
                Operator::if('pending siblings exist (by order field)', 'NEXT: /task:sync {next_pending_sibling_by_order} [-y] (or /task:async)'),
                Operator::if('no pending siblings AND task has parent_id', 'NEXT: /task:validate {parent_id} [-y] (all children done, validate parent)'),
                Operator::if('no pending siblings AND no parent_id (root)', 'NEXT: all tasks complete'),
            ]))
            ->phase(Operator::if('status = "pending" (fix-tasks created)', 'NEXT: fix-tasks created, re-validate {$VECTOR_TASK_ID} after all fixes complete'))
            ->phase(Operator::if('status = "pending" (blocked by parallel sibling)', 'NEXT: /task:validate {$VECTOR_TASK_ID} [-y] — retry after blocking sibling completes'))
            ->phase(Operator::if('status = "pending" (error/crash)', 'NEXT: /task:validate {$VECTOR_TASK_ID} [-y] — retry validation'));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('task status invalid', Operator::abort('Complete first')))
            ->phase(Operator::if('agent fails', [
                'RETRY once with same prompt',
                Operator::if('still fails', [
                    'Mark agent as FAILED. Track: ' . Store::as('FAILED_AGENTS[]', '{agent_name}'),
                    Operator::if(Store::get('AGENT_SUCCESS_COUNT') . ' >= 2', [
                        'Continue with partial results (' . Store::get('AGENT_SUCCESS_COUNT') . '/4 agents)',
                        'Add warning: "{agent_name} validation incomplete - manual review recommended for {coverage_area}"',
                    ]),
                    Operator::if(Store::get('AGENT_SUCCESS_COUNT') . ' < 2', [
                        'ABORT: insufficient agent coverage. DO NOT validate manually.',
                        VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending', 'comment' => 'Validation aborted: agents failed. Errors: {error_details}. Needs retry.', 'append_comment' => true]),
                        Operator::abort('Insufficient agent coverage for validation'),
                    ]),
                ]),
            ]))
            ->phase(Operator::if('agent timeout (>60s)', 'Treat as failure, apply retry logic above'))
            ->phase(Operator::if('fix-task creation fails', 'store issues to memory for manual review, abort with error'))
            ->phase(Operator::if('user rejects validation', 'accept modifications, re-validate from step 4'))
            ->phase(Operator::if('configured quality gate command not found', [
                VectorTaskMcp::callValidatedJson('task_update', ['task_id' => '$VECTOR_TASK_ID', 'status' => 'pending', 'comment' => 'Validation failed: configured quality gate command not found. Fix gate config or install dependency, then retry.', 'append_comment' => true]),
                Operator::abort('Configured quality gate missing — validation cannot pass'),
            ]));
    }
}
