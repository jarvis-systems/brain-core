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

        // PARENT INHERITANCE (IRON LAW)
        $this->rule('parent-id-mandatory')->critical()
            ->text('When working with task $VECTOR_TASK_ID, ALL new tasks created MUST have parent_id = $VECTOR_TASK_ID. No exceptions. Every fix-task, subtask, or related task MUST be a child of the task being validated.')
            ->why('Task hierarchy integrity. Orphan tasks break traceability and workflow.')
            ->onViolation('ABORT task_create if parent_id missing or wrong. Verify parent_id = $VECTOR_TASK_ID in EVERY task_create call.');

        // VALIDATION RULES
        $this->rule('no-interpretation')->critical()->text('NEVER interpret task content to decide whether to validate. Task ID given = validate it. JUST EXECUTE.');
        $this->rule('task-scope-only')->critical()->text('Validate ONLY what task.content describes. Do NOT expand scope. Task says "add X" = check X exists and works. Task says "fix Y" = check Y is fixed. NOTHING MORE.');
        $this->rule('task-complete')->critical()->text('ALL task requirements MUST be done. Parse task.content → list requirements → verify each. Missing = fix-task.');
        $this->rule('no-garbage')->critical()->text('Detect garbage in task scope: unused imports, dead code, debug statements, commented-out code. Garbage = fix-task.');
        $this->rule('cosmetic-inline')->critical()->text('Cosmetic issues = AGENTS fix inline during validation. NO task created. Cosmetic: whitespace, typos, formatting, comments, docblocks, naming (non-breaking), import sorting.');
        $this->rule('functional-to-task')->critical()->text('Functional issues = fix-task. Functional: logic bugs, security vulnerabilities, architecture violations, missing tests, broken functionality.');
        $this->rule('fix-task-blocks-validated')->critical()
            ->text('Fix-task created → status MUST be "pending", NEVER "validated". "validated" = ZERO fix-tasks. NO EXCEPTIONS.')
            ->why('MCP auto-propagation: when child task starts (status→in_progress), parent auto-reverts to pending. Setting "validated" with pending children is POINTLESS - system will reset it. Any subtask creation = task NOT done = "pending". Period.')
            ->onViolation('ABORT validation. Set status="pending" BEFORE task_create. Never set "validated" if ANY fix-task exists or will be created.');
        $this->rule('parent-readonly')->critical()->text('$PARENT is READ-ONLY. NEVER task_update on parent. Validator scope = $VECTOR_TASK_ID ONLY.');
        $this->rule('test-coverage')->high()->text('New code MUST have test coverage. Critical paths = 100%. Other code >= 80%. No coverage = fix-task.');
        $this->rule('no-breaking-changes')->high()->text('Public API/interface changes = verify backward compatibility OR document breaking change in task comment.');
        $this->rule('slow-test-detection')->high()->text('Slow tests = fix-task. Thresholds: unit >500ms, integration >2s, any >5s = CRITICAL. Causes: missing mocks, real I/O, unoptimized queries.');
        $this->rule('flaky-test-detection')->high()
            ->text('Flaky tests (pass/fail inconsistently) = fix-task. Run test 2-3 times if suspect. Causes: shared state, time-dependent logic, race conditions, external dependencies without mocks.')
            ->why('Flaky tests erode trust in test suite and waste CI resources.');

        // SECURITY VALIDATION (language-agnostic)
        $this->rule('security-injection')->critical()
            ->text('Injection vulnerabilities = fix-task. Check: SQL/NoSQL injection (parameterized queries?), command injection (shell escaping?), template injection, LDAP injection, XPath injection. ANY user input in query/command = suspect.')
            ->why('Injection = #1 OWASP. Exploitable = full system compromise.');
        $this->rule('security-xss')->critical()
            ->text('XSS vulnerabilities = fix-task. Check: output escaping in HTML/JS context, innerHTML usage, dangerouslySetInnerHTML, template literals with user data, URL parameters reflected in page.')
            ->why('XSS enables session hijacking, defacement, malware distribution.');
        $this->rule('security-secrets')->critical()
            ->text('Hardcoded secrets = fix-task. Grep for: password, secret, api_key, token, credential, private_key, AWS_, STRIPE_, DATABASE_URL. Check: .env files not in .gitignore, secrets in logs/comments.')
            ->why('Leaked credentials = immediate breach. No exceptions.');
        $this->rule('security-auth')->high()
            ->text('Auth/authz issues = fix-task. Check: missing authentication on endpoints, broken access control (IDOR), privilege escalation paths, session management (secure cookies, expiration).')
            ->why('Broken auth = unauthorized access to data/functionality.');
        $this->rule('security-sensitive-data')->high()
            ->text('Sensitive data exposure = fix-task. Check: PII in logs, sensitive data in error messages, missing encryption for data at rest/transit, excessive data in API responses.')
            ->why('Data leaks = compliance violations, reputation damage.');

        // PERFORMANCE VALIDATION (language-agnostic)
        $this->rule('performance-n-plus-one')->high()
            ->text('N+1 query pattern = fix-task. Detect: loop with DB/API call inside, lazy loading in iteration, missing eager loading/batching. Check query logs or ORM debug output.')
            ->why('N+1 destroys performance at scale. 100 items = 101 queries.');
        $this->rule('performance-complexity')->medium()
            ->text('Algorithmic complexity issues = fix-task. Nested loops on unbounded data, recursive calls without memoization, O(n²) or worse on large datasets. Check: loops inside loops, repeated searches.')
            ->why('Bad algorithms fail silently until data grows.');
        $this->rule('performance-memory')->medium()
            ->text('Memory issues = fix-task. Loading entire dataset into memory, missing pagination, unbounded caches, large object graphs, missing cleanup/disposal.')
            ->why('Memory leaks cause OOM crashes in production.');

        // TYPE SAFETY (language-agnostic)
        $this->rule('type-safety')->high()
            ->text('Type safety violations = fix-task. Missing type annotations on public API, any/unknown overuse, nullable without null checks, implicit type coercion in comparisons, missing runtime validation at boundaries.')
            ->why('Type errors are runtime bombs. Static typing catches bugs early.');

        // DEPENDENCY VALIDATION (language-agnostic)
        $this->rule('dependency-audit')->high()
            ->text('Dependency vulnerabilities = fix-task. Run package audit tool (npm audit, composer audit, pip-audit, cargo audit, etc.). Known CVEs in dependencies = CRITICAL.')
            ->why('Supply chain attacks via vulnerable dependencies are common.');
        $this->rule('dependency-license')->medium()
            ->text('License compatibility issues = fix-task. New dependencies must have compatible licenses. GPL in proprietary project = problem. Check: SPDX identifiers, license files.')
            ->why('License violations = legal liability.');

        // TEST QUALITY (beyond coverage)
        $this->rule('test-quality-assertions')->high()
            ->text('Tests without meaningful assertions = fix-task. Empty tests, tests that only check "no exception thrown", mocked everything including SUT. Test MUST verify behavior, not just execute code.')
            ->why('High coverage with weak assertions = false confidence.');
        $this->rule('test-quality-edge-cases')->high()
            ->text('Missing edge case tests = fix-task. Check: null/empty inputs, boundary values, error paths, concurrent access, timeout scenarios. Happy path only = incomplete.')
            ->why('Bugs hide in edge cases. Production hits all paths.');

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

            // 2. Context gathering (memory + docs + related tasks)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' ' . Store::as('MEMORY_CONTEXT'))
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 5}') . ' ' . Store::as('RELATED_TASKS'))
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
            ->phase(Operator::parallel([
                TaskTool::agent('explore', 'COMPLETION CHECK: Parse task.content → list requirements → verify each done. Check ONLY task files. Detect garbage (unused imports, dead code, debug statements, commented code). Fix cosmetic inline. Return JSON: {missing_requirements: [], garbage: [], cosmetic_fixed: []}'),
                TaskTool::agent('explore', 'CODE QUALITY: Task scope only. Check: logic errors, architecture violations, breaking changes, type safety (missing types, nullable without checks), algorithmic complexity (nested loops, O(n²)). Run quality gates. Fix cosmetic inline. Return JSON: {logic_issues: [], architecture_issues: [], type_issues: [], complexity_issues: []}'),
                TaskTool::agent('explore', 'TESTING: Task scope only. Check: tests exist (coverage >=80%, critical=100%), tests pass, meaningful assertions (not just "no exception"), edge cases covered (null, empty, boundary), slow tests (unit >500ms, integration >2s), flaky tests (run 2x if suspect). Return JSON: {missing_tests: [], failing_tests: [], weak_assertions: [], missing_edge_cases: [], slow_tests: [], flaky_tests: []}'),
                TaskTool::agent('explore', 'SECURITY & PERFORMANCE: Task scope only. Security: injection (SQL, command, template), XSS (output escaping), hardcoded secrets (grep: password, api_key, token, secret), auth/authz gaps, sensitive data in logs. Performance: N+1 queries (loop+DB call), memory issues (unbounded loading), missing pagination. Dependency audit if new deps added. Return JSON: {injection: [], xss: [], secrets: [], auth_issues: [], data_exposure: [], n_plus_one: [], memory_issues: [], dependency_vulnerabilities: []}'),
            ]))

            // 5. Finalize (IRON LAW: fix-task created = "pending" ALWAYS. MCP will reset status anyway when child starts. NO "validated" with children.)
            ->phase('MERGE RESULTS: Collect all agent JSON outputs. DEDUPLICATE: same file + same issue type = merge into one. CLASSIFY severity:')
            ->phase('  CRITICAL: security issues (injection, XSS, secrets, auth), data loss risk, crashes')
            ->phase('  MAJOR: logic bugs, missing tests for critical paths, N+1 queries, type safety violations, failing tests')
            ->phase('  MINOR: missing edge case tests, complexity warnings, weak assertions, slow tests')
            ->phase('FILTER: Remove false positives (issue outside task scope). Store final list ' . Store::as('ISSUES'))
            ->phase(Operator::if(
                'issues=0 AND no fix-task needed',
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "validated"}'),
                [
                    VectorTaskMcp::call('task_create', '{title: "Validation fixes: #ID", content: issues_list, parent_id: $VECTOR_TASK_ID, tags: ["validation-fix"]}'),
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
