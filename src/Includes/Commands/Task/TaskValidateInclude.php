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
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Validate completed vector task. 4 parallel agents: Completion, Code Quality, Testing, Security & Performance. Creates fix-tasks for functional issues. Cosmetic fixed inline by agents.')]
class TaskValidateInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('task-get-first')->critical()->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze what to validate.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status, validation results, or issues without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('no-verbose')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action.');
        $this->rule('show-progress')->high()->text('ALWAYS show brief step status and results. User must see what is happening and can interrupt/correct at any moment.');
        $this->rule('auto-approve')->high()->text('-y flag = auto-approve. Skip "Proceed?" questions, but STILL show progress. User sees everything, just no approval prompts.');

        // DOCUMENTATION IS LAW (from trait - validates against docs, not made-up criteria)
        $this->defineDocumentationIsLawRules();

        // PARENT INHERITANCE (IRON LAW)
        $this->rule('parent-id-mandatory')->critical()
            ->text('When working with task $VECTOR_TASK_ID, ALL new tasks created MUST have parent_id = $VECTOR_TASK_ID. No exceptions. Every fix-task, subtask, or related task MUST be a child of the task being validated.')
            ->why('Task hierarchy integrity. Orphan tasks break traceability and workflow.')
            ->onViolation('ABORT task_create if parent_id missing or wrong. Verify parent_id = $VECTOR_TASK_ID in EVERY task_create call.');

        $this->rule('estimate-mandatory')->critical()
            ->text('task_create MUST include estimate (hours). Pessimistic > optimistic. Realistic range, not fantasy.')
            ->onViolation('ABORT. Add estimate. Unsure? Take gut feeling × 1.5.');

        // VALIDATION RULES
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to validate. Task ID given = validate it. JUST EXECUTE.');
        $this->rule('docs-are-complete-spec')->critical()
            ->text('Documentation (.docs/) = COMPLETE specification. task.content may be brief summary. ALWAYS read and validate against DOCUMENTATION if exists. Missing from docs = not a requirement. In docs but not done = MISSING.')
            ->why('task.content is often summary. Full spec lives in documentation. Validating only task.content misses requirements.')
            ->onViolation('Check DOCS_PATHS. If docs exist → read them → extract full requirements → validate against docs.');
        $this->rule('task-scope-only')->critical()->text('Validate ONLY what task.content + documentation describes. Do NOT expand scope. Task says "add X" = check X exists and works. Task says "fix Y" = check Y is fixed. NOTHING MORE.');
        $this->rule('task-complete')->critical()->text('ALL task requirements MUST be done. Parse task.content → list requirements → verify each. Missing = fix-task.');
        $this->rule('no-garbage')->critical()->text('Garbage code in task scope = fix-task.');
        $this->rule('cosmetic-inline')->critical()->text('Cosmetic issues = AGENTS fix inline during validation. NO task created. Cosmetic: whitespace, typos, formatting, comments, docblocks, naming (non-breaking), import sorting.');
        $this->rule('functional-to-task')->critical()->text('Functional issues = fix-task. Functional: logic bugs, security vulnerabilities, architecture violations, missing tests, broken functionality.');
        $this->rule('fix-task-blocks-validated')->critical()
            ->text('Fix-task created → status MUST be "pending", NEVER "validated". "validated" = ZERO fix-tasks. NO EXCEPTIONS.')
            ->why('MCP auto-propagation: when child task starts (status→in_progress), parent auto-reverts to pending. Setting "validated" with pending children is POINTLESS - system will reset it. Any subtask creation = task NOT done = "pending". Period.')
            ->onViolation('ABORT validation. Set status="pending" BEFORE task_create. Never set "validated" if ANY fix-task exists or will be created.');
        $this->rule('parent-readonly')->critical()->text('$PARENT is READ-ONLY. NEVER task_update on parent. Validator scope = $VECTOR_TASK_ID ONLY.');
        $this->rule('test-coverage')->high()->text('No test coverage = fix-task. Critical paths = 100%, other >= 80%.');
        $this->rule('no-breaking-changes')->high()->text('Breaking API/interface changes without documentation = fix-task.');
        $this->rule('slow-test-detection')->high()->text('Slow tests = fix-task. Unit >500ms, integration >2s.');
        $this->rule('flaky-test-detection')->high()->text('Flaky tests = fix-task.');

        // FAILURE-AWARE VALIDATION (CRITICAL - prevents repeating same mistakes)
        $this->rule('failure-history-mandatory')->critical()
            ->text('BEFORE validation: search memory category "debugging" for KNOWN FAILURES related to this task/problem. Pass failures to agents. Agents MUST NOT suggest solutions that already failed.')
            ->why('Repeating failed solutions wastes time. Memory contains "this does NOT work" knowledge.')
            ->onViolation('Search debugging memories. Include KNOWN_FAILURES in agent prompts.');
        $this->rule('sibling-task-check')->high()
            ->text('BEFORE validation: fetch sibling tasks (same parent_id, status=completed/stopped). Analyze their comments for what was tried and failed. Pass context to agents.')
            ->why('Previous attempts on same problem contain valuable "what not to do" information.')
            ->onViolation('task_list with parent_id, extract failure patterns from comments.');
        $this->rule('no-repeat-failures')->critical()
            ->text('BEFORE creating fix-task: check if proposed solution matches known failure. If memory says "X does NOT work for Y" - DO NOT create task suggesting X. Escalate or research alternative.')
            ->why('Creating fix-task with known-failed solution = guaranteed failure + wasted effort.')
            ->onViolation('Search memory for proposed fix. Match found in debugging = BLOCK task creation, suggest alternative or escalate.');

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

            // 2. Context gathering (memory + docs + related tasks + FAILURES)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' ' . Store::as('MEMORY_CONTEXT'))
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

            // 3. Approval (skip if -y)
            ->phase(Operator::if(
                '$HAS_AUTO_APPROVE',
                Operator::skip('approval'),
                'show task info, wait "yes"'
            ))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress"}'))

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
                            VectorTaskMcp::call('task_create', '{title: "Light validation fixes: #ID", content: basic_issues, parent_id: $VECTOR_TASK_ID, tags: ["validation-fix"]}'),
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
            ->phase(Store::as('AGENT_CONTEXT', 'formatted context block with all above data'))
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
7. Fix cosmetic issues inline (whitespace, formatting)

Return JSON: {docs_read: [], requirements_from_docs: [], requirements_from_task: [], requirements_checklist: [{requirement, source: "docs|task", status, evidence}], missing_requirements: [], garbage: [], cosmetic_fixed: []}'),
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}
- Related memories: {MEMORY_IDS}

KNOWN FAILURES (DO NOT SUGGEST THESE):
{KNOWN_FAILURES_TEXT}

PREVIOUS FAILED ATTEMPTS:
{FAILURE_PATTERNS_TEXT}

MISSION: CODE QUALITY
1. Read EACH file from TASK_FILES
2. Check: logic errors, architecture violations, breaking changes
3. Check: type safety (missing types, nullable without null checks)
4. Check: algorithmic complexity (nested loops on data, O(n²))
5. Run quality gates (composer test, composer analyse)
6. Fix cosmetic issues inline

Return JSON: {files_reviewed: [], logic_issues: [], architecture_issues: [], type_issues: [], complexity_issues: [], quality_gate_results: {}}'),
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}

KNOWN FAILURES (DO NOT SUGGEST THESE):
{KNOWN_FAILURES_TEXT}

MISSION: TESTING
1. Find test files for TASK_FILES (tests/*Test.php, tests/**/*Test.php)
2. Check: tests exist (coverage >=80%, critical paths =100%)
3. Run tests, check they pass
4. Check: meaningful assertions (not just "no exception thrown")
5. Check: edge cases covered (null, empty, boundary values)
6. Check: slow tests (unit >500ms, integration >2s)
7. If suspect flaky → run 2x to confirm

If test approach mentioned in KNOWN_FAILURES → find ALTERNATIVE approach

Return JSON: {test_files_found: [], coverage: {}, missing_tests: [], failing_tests: [], weak_assertions: [], missing_edge_cases: [], slow_tests: [], flaky_tests: []}'),
                TaskTool::agent('explore', '
CONTEXT (provided by validator):
- Task ID: {TASK_ID}
- Task title: {TASK_TITLE}
- Task content: {TASK_CONTENT}
- Files to check: {TASK_FILES}

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

Return JSON: {files_reviewed: [], injection: [], xss: [], secrets: [], auth_issues: [], data_exposure: [], n_plus_one: [], memory_issues: [], dependency_vulnerabilities: []}'),
            ]))

            // 5. Finalize (IRON LAW: fix-task created = "pending" ALWAYS. MCP will reset status anyway when child starts. NO "validated" with children.)
            ->phase('MERGE RESULTS: Collect all agent JSON outputs. DEDUPLICATE: same file + same issue type = merge into one. CLASSIFY severity:')
            ->phase('  CRITICAL: security issues (injection, XSS, secrets, auth), data loss risk, crashes')
            ->phase('  MAJOR: logic bugs, missing tests for critical paths, N+1 queries, type safety violations, failing tests')
            ->phase('  MINOR: missing edge case tests, complexity warnings, weak assertions, slow tests')
            ->phase('FILTER: Remove false positives (issue outside task scope). Store final list ' . Store::as('ISSUES'))

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
                            'Search for ALTERNATIVE approach: ' . VectorMemoryMcp::call('search_memories', '{query: "{problem} alternative solution", limit: 5, category: "code-solution"}'),
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

            ->phase(Operator::if(
                Store::get('FILTERED_ISSUES') . '=0 AND no fix-task needed',
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated"}'),
                [
                    VectorTaskMcp::call('task_create', '{title: "Validation fixes: #ID", content: filtered_issues_list, parent_id: $VECTOR_TASK_ID, tags: ["validation-fix"]}'),
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending"}') . ' ← IRON LAW: always "pending" when fix-task created. MCP will reset anyway.',
                ]
            ))

            // 6. Report
            ->phase(Operator::output('task, Critical/Major/Minor counts, cosmetic fixed, status, fix-task ID'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: validation_summary, category: "code-solution"}'));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('task status invalid', Operator::abort('Complete first')))
            ->phase(Operator::if('agent fails', [
                'RETRY once with same prompt',
                Operator::if('still fails', [
                    'Mark agent as FAILED in report',
                    'Continue with remaining agents (partial validation > no validation)',
                    'Add warning: "{agent_name} validation incomplete - manual review recommended for {coverage_area}"',
                ]),
            ]))
            ->phase(Operator::if('agent timeout (>60s)', 'Treat as failure, apply retry logic above'))
            ->phase(Operator::if('fix-task creation fails', 'store issues to memory for manual review, abort with error'))
            ->phase(Operator::if('user rejects validation', 'accept modifications, re-validate from step 4'))
            ->phase(Operator::if('quality gate command not found', 'WARN and skip that gate, note in report'));
    }
}
