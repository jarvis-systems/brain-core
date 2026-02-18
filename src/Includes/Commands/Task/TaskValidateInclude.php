<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
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
        $this->defineDocumentationIsLawRules();
        $this->defineNoDestructiveGitRules();

        // SECRETS & PII PROTECTION (from trait - no secret exfiltration via output or storage)
        $this->defineSecretsPiiProtectionRules();

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
        $this->defineTagTaxonomyRules();

        // PARENT INHERITANCE (IRON LAW)
        $this->defineParentIdMandatoryRule();

        $this->rule('estimate-mandatory')->critical()
            ->text('task_create MUST include estimate (hours). Pessimistic > optimistic. Realistic range, not fantasy.')
            ->onViolation('ABORT. Add estimate. Unsure? Take gut feeling × 1.5.');

        // VALIDATION RULES (common from trait)
        $this->defineValidationCoreRules();

        // FIX-TASK GATING (from trait - canonical re-validation cycle after fix-tasks)
        $this->defineFixTaskGatingRules();

        // VALIDATION RULES (validate-specific)
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to validate. Task ID given = validate it. JUST EXECUTE.');
        $this->rule('parent-readonly')->critical()->text('$PARENT is READ-ONLY. NEVER task_update on parent. Validator scope = $VECTOR_TASK_ID ONLY.');
        $this->rule('no-breaking-changes')->high()->text('Breaking API/interface changes without documentation = fix-task.');
        $this->rule('flaky-test-detection')->high()->text('Flaky tests = fix-task.');

        // FAILURE-AWARE VALIDATION (from trait - prevents repeating same mistakes)
        $this->defineFailureAwarenessRules();

        // STUCK PATTERN ESCALATION (from trait - detect circular failures, research alternatives)
        $this->defineStuckPatternEscalationRule();

        // FAILURE POLICY (from trait - universal tool error / missing docs / ambiguous spec handling)
        $this->defineFailurePolicyRules();

        // RETRY CIRCUIT BREAKER (from trait - prevents infinite validate retry loops in auto-approve)
        $this->defineRetryCircuitBreakerRule('validate');

        // COLLATERAL FAILURE DETECTION (from trait - create global tasks for unrelated test failures)
        $this->defineCollateralFailureDetectionRule();

        // SECURITY VALIDATION (severity policy)
        $this->rule('security-injection')->critical()->text('Injection vulnerabilities = fix-task.');
        $this->rule('security-xss')->critical()->text('XSS vulnerabilities = fix-task.');
        $this->rule('security-secrets')->critical()->text('Hardcoded secrets = fix-task.');
        $this->rule('security-auth')->high()->text('Auth/authz issues = fix-task.');
        $this->rule('security-sensitive-data')->high()->text('Sensitive data exposure = fix-task.');

        // PERFORMANCE VALIDATION (severity policy)
        $this->rule('performance-n-plus-one')->high()->text('N+1 query pattern = fix-task.');
        $this->rule('performance-complexity')->medium()->text('Algorithmic complexity issues = fix-task.');
        $this->rule('performance-memory')->medium()->text('Memory issues = fix-task.');

        // TYPE SAFETY (severity policy)
        $this->rule('type-safety')->high()->text('Type safety violations = fix-task.');

        // DEPENDENCY VALIDATION (severity policy)
        $this->rule('dependency-audit')->high()->text('Dependency vulnerabilities = fix-task.');
        $this->rule('dependency-license')->medium()->text('License compatibility issues = fix-task.');

        // TEST QUALITY (severity policy)
        $this->rule('test-quality-assertions')->high()->text('Tests without meaningful assertions = fix-task.');
        $this->rule('test-quality-edge-cases')->high()->text('Missing edge case tests = fix-task.');

        // DEDUPLICATION & MERGE
        $this->rule('issue-deduplication')->high()
            ->text('Before creating fix-task: deduplicate issues. Same file + same issue type from different agents = ONE fix-task. Merge descriptions. Avoid duplicate work.')
            ->why('Multiple agents may find same issue. Duplicate tasks waste effort.')
            ->onViolation('Compare issues by file path and issue category before task_create.');

        // PARTIAL FAILURE HANDLING
        $this->rule('agent-partial-failure')->high()
            ->text('If agent crashes/times out: retry ONCE. If still fails: continue with remaining agents, mark agent failure in report. 2 of 3 agents = still validate, but note incomplete coverage.')
            ->why('One agent failure should not block entire validation. Partial results > no results.')
            ->onViolation('Log failed agent, include warning in final report, suggest manual review of uncovered area.');

        // PARALLEL ISOLATION (from trait - strict criteria when creating fix-tasks)
        $this->defineParallelIsolationRules();

        // PARALLEL EXECUTION AWARENESS (from trait - know sibling tasks when validating parallel task)
        $this->defineParallelExecutionAwarenessRules();
        $this->defineValidatorParallelCosmeticRule();
        $this->defineScopedGitCheckpointRule();

        // COSMETIC ROLLBACK
        $this->rule('cosmetic-atomic')->medium()
            ->text('Cosmetic fixes by agents MUST be atomic with validation. If validation creates fix-task (functional issues found), cosmetic changes STILL committed. Cosmetic improvements are always safe to keep.')
            ->why('Cosmetic fixes are non-breaking. Discarding them wastes work.');

        // Light validation - skip heavy checks for trivial tasks
        $this->rule('light-validation-tag')->medium()
            ->text('Task with "light validation" tag = SKIP heavy checks (quality gates, full test suite, code quality agents). RUN only: syntax check, file exists, basic format validation.')
            ->why('Trivial tasks (docs, typos, comments, config values, formatting) do not need full validation. Explicit tag = conscious decision by task creator.');

        $this->guideline('light-validation-examples')
            ->text('Recognize tags that signal trivial/light validation. Match by INTENT, not exact string.')
            ->example('light-validation, light, trivial, minor, docs-only, documentation, readme, typo, cosmetic, formatting, config-only, skip-tests, no-validation');

        $this->guideline('light-validation-scope')
            ->text('Light validation appropriate for:')
            ->example('Documentation changes (README, CHANGELOG, comments, docblocks)')
            ->example('Typo fixes in text/UI/messages')
            ->example('Config value changes (not logic)')
            ->example('Code formatting, import sorting')
            ->example('Removing dead/unused code')
            ->example('Adding/updating .gitignore, .editorconfig');

        $this->guideline('light-validation-not-for')
            ->text('NEVER light validation for:')
            ->example('Any logic changes (even "simple" ones)')
            ->example('API/interface changes')
            ->example('Database migrations')
            ->example('Security-related code')
            ->example('New features or bug fixes');

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
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase(Operator::if(
                'status NOT IN [completed, tested, validated, in_progress]',
                Operator::abort('Complete first')
            ))
            ->phase(Operator::if(
                'status=in_progress',
                'SESSION RECOVERY: check if crashed session (no active work)',
                Operator::abort('another session active')
            ))
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' ' . Store::as('PARENT') . ' (READ-ONLY context, NEVER modify)'
            ))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' ' . Store::as('SUBTASKS'))

            // 1.2 Extract comment context (accumulated inter-session history)
            ->phase(Store::as('COMMENT_CONTEXT', '{parsed from $TASK.comment: memory_ids: [#NNN], file_paths: [...], execution_history: [...], failures: [...], blockers: [...], decisions: [], mode_flags: []}'))

            // 1.21 CIRCUIT BREAKER: prevent infinite validation retry loops (check BEFORE validating)
            ->phase(Store::as('ATTEMPT_COUNT', 'count "ATTEMPT [validate]:" markers in $TASK.comment AFTER last "CIRCUIT BREAKER:" entry (default 0)'))
            ->phase(Operator::if(Store::get('TASK') . '.tags contains "' . self::TAG_STUCK . '"', Operator::abort('Task is STUCK. Remove "' . self::TAG_STUCK . '" tag to retry.')))
            ->phase(Operator::if(Store::get('ATTEMPT_COUNT') . ' >= 3', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, add_tag: "' . self::TAG_STUCK . '", comment: "CIRCUIT BREAKER: 3 validate attempts exhausted. Needs human investigation.", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "STUCK: Task #{id} failed 3x validation. See task comments.", category: "' . self::CAT_DEBUGGING . '", tags: ["' . self::MTAG_FAILURE . '"]}'),
                Operator::abort('Circuit breaker → tagged "' . self::TAG_STUCK . '".'),
            ]))

            // 1.25 Parallel execution awareness (validator: check if siblings are active before inline fixes)
            ->phase(Operator::if(Store::get('TASK') . '.parallel === true AND parent_id', [
                VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}'),
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
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated", comment: "Aggregation validation: all {N} subtasks validated. Requirements covered: {list}.", append_comment: true}'),
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
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "ATTEMPT [validate]: {$ATTEMPT_COUNT + 1}/3. Started validation.", append_comment: true}'))

            // 2. Context gathering (memory + docs + related tasks + FAILURES)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "' . self::CAT_CODE_SOLUTION . '"}') . ' ' . Store::as('MEMORY_CONTEXT'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{task.title} {problem keywords} failed error not working broken", limit: 5}') . ' ' . Store::as('KNOWN_FAILURES') . ' ← CRITICAL: what already FAILED (search by failure keywords, not category)')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 5}') . ' ' . Store::as('RELATED_TASKS'))
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                [
                    VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' ' . Store::as('SIBLING_TASKS') . ' ← previous attempts on same problem',
                    // CRITICAL: Search vector memory for EACH sibling task to get stored insights/failures
                    Operator::forEach('sibling in ' . Store::get('SIBLING_TASKS'), [
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title}", limit: 3}') . ' → ALL memories for this sibling (failures, solutions, insights)',
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title} failed error not working", limit: 3}') . ' → specifically failure-related memories',
                        'Append results to ' . Store::as('SIBLING_MEMORIES'),
                    ]),
                ]
            ))
            ->phase('Extract from ' . Store::get('SIBLING_TASKS') . ' comments + ' . Store::get('SIBLING_MEMORIES') . ': what was tried, what failed, what worked')
            ->phase(Store::as('FAILURE_PATTERNS', 'solutions that were tried and failed (from sibling comments + sibling memories + debugging memories)'))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if('unknown library/pattern in task scope', Context7Mcp::call('query-docs', '{query: "{library}"}') . ' → understand API before validating'))

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
                        VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated", comment: "Light validation passed (trivial task)", append_comment: true}'),
                        [
                            VectorTaskMcp::call('task_create', '{title: "Light validation fixes: #ID", content: basic_issues, parent_id: $VECTOR_TASK_ID, parallel: false, tags: ["' . self::TAG_VALIDATION_FIX . '"]}'),
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending"}'),
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
            ->phase('  - TASK_FILES: extract file paths mentioned in task.content (src/*, tests/*, etc.)')
            ->phase('  - PARENT_CONTEXT: ' . Store::get('PARENT') . '.title + .content (if exists) — broader goal')
            ->phase('  - MEMORY_IDS: list memory IDs from ' . Store::get('MEMORY_CONTEXT') . ' (e.g., #1500, #1502)')
            ->phase('  - KNOWN_FAILURES_TEXT: full text from ' . Store::get('KNOWN_FAILURES') . ' — what NOT to suggest')
            ->phase('  - FAILURE_PATTERNS_TEXT: full text from ' . Store::get('FAILURE_PATTERNS') . ' — previous failed attempts')
            ->phase('  - DOCS_PATHS: file paths from ' . Store::get('DOCS_INDEX') . ' (if relevant)')
            ->phase('  - HAS_PARENT: ' . Store::get('TASK') . '.parent_id exists (true = subtask = scoped tests, false = root task = full suite)')
            ->phase('  - COMMENT_CONTEXT: ' . Store::get('COMMENT_CONTEXT') . ' — accumulated inter-session history (memory IDs, files touched, failures, decisions)')
            ->phase('  - AUTO_APPROVE: ' . Store::get('HAS_AUTO_APPROVE') . ' (true = -y mode, agent MUST NOT ask user questions)')
            ->phase(Store::as('AGENT_CONTEXT', 'formatted context block with all above data INCLUDING $COMMENT_CONTEXT and $AUTO_APPROVE'))
            ->phase(Operator::parallel([
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}
- Parent goal: {PARENT_CONTEXT}
- Related memories: {MEMORY_IDS}
- Documentation paths: {DOCS_PATHS}
- Comment context: {COMMENT_CONTEXT} (previous sessions: memory IDs, files touched, execution history, failures, decisions)
- Auto-approve: {AUTO_APPROVE}

IF Auto-approve = true: NEVER ask user questions. On ANY ambiguity or decision fork → choose the conservative/non-blocking option automatically. Log the decision, continue without stopping.

KNOWN FAILURES (DO NOT SUGGEST THESE):
{KNOWN_FAILURES_TEXT}

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
11. DOCUMENTATION CHECK: IF task adds NEW feature/module/API → run brain docs "{keywords}" to verify .docs/ documentation exists. No docs for new feature = cosmetic issue (executor should have created). Create basic .docs/{feature}.md inline with YAML front matter (name, description, type, date, version) + brief markdown description. If parallel context and doc file could conflict → defer to comment.

Return JSON: {docs_read: [], requirements_from_docs: [], requirements_from_task: [], requirements_checklist: [{requirement, source: "docs|task", status, evidence}], missing_requirements: [], garbage: [], pattern_violations: [], cosmetic_fixed: [], cosmetic_deferred: [], docs_coverage: {new_features: [], has_docs: bool, docs_created: []}}')
,
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}
- Related memories: {MEMORY_IDS}
- Comment context: {COMMENT_CONTEXT} (previous sessions: memory IDs, files touched, execution history, failures, decisions)
- Auto-approve: {AUTO_APPROVE}

IF Auto-approve = true: NEVER ask user questions. On ANY ambiguity or decision fork → choose the conservative/non-blocking option automatically. Log the decision, continue without stopping.

KNOWN FAILURES (DO NOT SUGGEST THESE):
{KNOWN_FAILURES_TEXT}

PREVIOUS FAILED ATTEMPTS:
{FAILURE_PATTERNS_TEXT}

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

Return JSON: {files_reviewed: [], logic_issues: [], architecture_issues: [], type_issues: [], complexity_issues: [], hallucinated_calls: [], broken_consumers: [], edge_case_issues: [], static_analysis_result: {}, cosmetic_deferred: []}'),
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}
- Has parent: {HAS_PARENT} (true = subtask, false = root task)
- Comment context: {COMMENT_CONTEXT} (previous sessions: memory IDs, files touched, execution history, failures, decisions)
- Auto-approve: {AUTO_APPROVE}

IF Auto-approve = true: NEVER ask user questions. On ANY ambiguity or decision fork → choose the conservative/non-blocking option automatically. Log the decision, continue without stopping.

KNOWN FAILURES (DO NOT SUGGEST THESE):
{KNOWN_FAILURES_TEXT}

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

Return JSON: {scoped: bool, test_files_found: [], consumer_tests_found: [], coverage: {}, missing_tests: [], failing_tests: [], weak_assertions: [], missing_edge_cases: [], slow_tests: [], flaky_tests: [], quality_gate_result: {}}'),
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}
- Comment context: {COMMENT_CONTEXT} (previous sessions: memory IDs, files touched, execution history, failures, decisions)
- Auto-approve: {AUTO_APPROVE}

IF Auto-approve = true: NEVER ask user questions. On ANY ambiguity or decision fork → choose the conservative/non-blocking option automatically. Log the decision, continue without stopping.

KNOWN FAILURES:
{KNOWN_FAILURES_TEXT}

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

Return JSON: {files_reviewed: [], injection: [], xss: [], secrets: [], auth_issues: [], data_exposure: [], n_plus_one: [], memory_issues: [], dependency_vulnerabilities: [], dead_code: [], debug_statements: [], cosmetic_deferred: []}'),
            ]))

            // 4.5 AGENT RESULTS GATE — verify sufficient agent coverage before proceeding
            ->phase(Store::as('AGENT_SUCCESS_COUNT', '{count of agents that returned valid JSON results}'))
            ->phase(Operator::if(Store::get('AGENT_SUCCESS_COUNT') . ' = 0', [
                'ALL AGENTS FAILED. Cannot validate without agent results.',
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Validation ABORTED: all 4 agents failed (tool errors). No manual fallback. Retry needed.", append_comment: true}'),
                Operator::output(['RESULT: FAILED — agents=0/4, reason=all_agents_failed']),
                Operator::output(['NEXT: /task:validate {$VECTOR_TASK_ID} [-y] (retry after tool issue resolves)']),
                Operator::abort('Zero agent results — cannot validate'),
            ]))
            ->phase(Operator::if(Store::get('AGENT_SUCCESS_COUNT') . ' < 2', [
                'INSUFFICIENT AGENT COVERAGE (' . Store::get('AGENT_SUCCESS_COUNT') . '/4). Minimum 2 required for meaningful validation.',
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Validation ABORTED: only ' . Store::get('AGENT_SUCCESS_COUNT') . '/4 agents succeeded. Insufficient coverage for reliable validation.", append_comment: true}'),
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
                        VectorTaskMcp::call('task_create', '{title: "Fix regression: {module/area}", content: "Test failure(s) outside task #{$TASK.id} scope.\n\nFailing: {test_names}\nError: {summary}\n\nDiscovered during validation of #{$TASK.id}, NOT caused by it.", priority: "high", estimate: 2, tags: ["' . self::TAG_REGRESSION . '", "' . self::TAG_BUGFIX . '"]}') . ' ← NO parent_id (global task, exempt from parent-id-mandatory)',
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
                    VectorMemoryMcp::call('search_memories', '{query: "{proposed_fix_description} failed not working broken error", limit: 3}') . ' → check if this fix already failed',
                    Operator::if(
                        'memory says this approach FAILED before',
                        [
                            'BLOCK this fix from task creation',
                            'Search for ALTERNATIVE approach: ' . VectorMemoryMcp::call('search_memories', '{query: "{problem} alternative solution", limit: 5, category: "' . self::CAT_CODE_SOLUTION . '"}'),
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
2. ' . Context7Mcp::call('query-docs', '{query: "{relevant library/framework} {problem pattern}"}') . ' → official docs patterns
3. WebSearch("{problem} {framework} best practice alternative solution") → community solutions
4. Cross-reference results against FAILED APPROACHES — eliminate already-tried
5. For each issue: rank remaining alternatives by confidence (high/medium/low)
6. If AUTO-APPROVE: select highest-confidence untried approach automatically

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
                        VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "STUCK ESCALATION: Issues without alternative after research: {list}. Previously failed: {details}. Needs human decision.", append_comment: true}'),
                        VectorMemoryMcp::call('store_memory', '{content: "STUCK: Task #{TASK.id} — issues without alternative: {list}. All known approaches failed. Needs new strategy.", category: "' . self::CAT_DEBUGGING . '", tags: ["' . self::MTAG_FAILURE . '"]}'),
                        'Remove escalated issues from FILTERED_ISSUES (do NOT create doomed fix-tasks)',
                    ]),
                ]),
            ]))

            ->phase(Operator::if(
                Store::get('FILTERED_ISSUES') . '=0 AND no fix-task needed',
                [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated"}'),
                    // 5.7 Git checkpoint — commit validated work (parallel = scoped files, non-parallel = full checkpoint with memory/)
                    Operator::if(Store::get('TASK') . '.parallel === true AND ACTIVE_SIBLINGS exist', [
                        BashTool::call('git add {$TASK_FILES}') . ' → PARALLEL: stage ONLY task-scope files (excludes memory/ and sibling work)',
                    ], [
                        BashTool::call('git add -A') . ' → NON-PARALLEL: full state checkpoint INCLUDING memory/ for complete revert capability',
                    ]),
                    BashTool::call('git commit -m "Task #$VECTOR_TASK_ID: $TASK_TITLE [validated]"'),
                    Operator::if('commit fails (pre-commit hook)', 'LOG: commit skipped, work is still validated. Continue to report.'),
                ],
                [
                    VectorTaskMcp::call('task_create', '{title: "Validation fixes: #ID", content: filtered_issues_list, parent_id: $VECTOR_TASK_ID, parallel: false, tags: ["' . self::TAG_VALIDATION_FIX . '"]}') . ' ← parallel: false by default. Apply parallel-isolation-checklist against siblings before setting true.',
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending"}') . ' ← IRON LAW: always "pending" when fix-task created. MCP will reset anyway.',
                ]
            ))

            // 6. Report
            ->phase(Operator::output('task, Critical/Major/Minor counts, cosmetic fixed, status, fix-task ID'))
            ->phase(Operator::if(
                Store::get('FILTERED_ISSUES') . ' not empty',
                VectorMemoryMcp::call('store_memory', '{content: "Validation #{TASK.id}: {issue_pattern_summary}. Root causes and fix approaches for future reference.", category: "' . self::CAT_DEBUGGING . '", tags: ["' . self::MTAG_FAILURE . '"]}') . ' ← ONLY issue patterns, not operational status'
            ))

            // SAFETY NET: guaranteed finalization — verify status changed from in_progress
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' → verify status is NOT in_progress')
            ->phase(Operator::if('task.status = "in_progress"', [
                'SAFETY NET TRIGGERED: workflow completed but status still in_progress.',
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "SAFETY NET: Validation workflow ended without explicit status update. Returned to pending.", append_comment: true}'),
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
                        VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Validation aborted: agents failed. Errors: {error_details}. Needs retry.", append_comment: true}'),
                        Operator::abort('Insufficient agent coverage for validation'),
                    ]),
                ]),
            ]))
            ->phase(Operator::if('agent timeout (>60s)', 'Treat as failure, apply retry logic above'))
            ->phase(Operator::if('fix-task creation fails', 'store issues to memory for manual review, abort with error'))
            ->phase(Operator::if('user rejects validation', 'accept modifications, re-validate from step 4'))
            ->phase(Operator::if('quality gate command not found', 'WARN and skip that gate, note in report'));
    }
}
