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

#[Purpose('Comprehensive vector task test validation with TDD support and parallel agent orchestration. Accepts task ID reference (formats: "15", "#15", "task 15"). TWO MODES: 1) TDD Mode (pending tasks) - writes tests for not-yet-implemented features, sets status "tested" with TDD comment; 2) Post-Implementation Mode (completed/tested/validated tasks) - validates existing test coverage against documentation. Agent fixes gaps inline (no task creation). Idempotent. Best for: TDD workflow and test quality validation.')]
class TaskTestValidateInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // ABSOLUTE FIRST - BLOCKING ENTRY RULE
        $this->rule('entry-point-blocking')->critical()
            ->text('ON RECEIVING input: Your FIRST output MUST be "=== TASK:TEST-VALIDATE ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION. FORBIDDEN first actions: Glob, Grep, Read, Edit, Write, WebSearch, WebFetch, Bash (except brain list:masters), code generation, file analysis.')
            ->why('Without explicit entry point, Brain skips workflow and executes directly. Entry point forces workflow compliance.')
            ->onViolation('STOP IMMEDIATELY. Delete any tool calls. Output "=== TASK:TEST-VALIDATE ACTIVATED ===" and restart from Phase 0.');

        // Iron Rules - Zero Tolerance
        $this->rule('tdd-or-validation-mode')->critical()
            ->text('TWO MODES based on task status: 1) TDD MODE (status=pending): Task not implemented yet. Agent WRITES tests for the feature, sets status "tested", adds TDD comment listing created tests. 2) VALIDATION MODE (status=completed/tested/validated): Agent VALIDATES existing tests, FIXES gaps inline. NEVER create separate tasks - agent fixes issues directly.')
            ->why('TDD mode enables test-first development. Validation mode ensures quality. Both modes fix inline to avoid task overhead.')
            ->onViolation('Check task status. pending → TDD mode (write tests). completed/tested/validated → validation mode (validate & fix inline).');

        // Common rule from trait
        $this->defineVectorTaskIdRequiredRule('/do:test-validate');

        $this->rule('testable-status-required')->critical()
            ->text('Tasks with status "pending", "completed", "tested", or "validated" can be test-validated. PENDING = TDD mode (write tests first). COMPLETED/TESTED/VALIDATED = validation mode. in_progress/stopped tasks MUST first be completed or reset.')
            ->why('Pending enables TDD workflow. Completed+ enables post-implementation validation.')
            ->onViolation('If status=in_progress/stopped: Report "Task #{id} has status {status}. Complete or reset first."');

        $this->rule('output-status-conditional')->critical()
            ->text('Output status depends on mode and outcome: 1) TDD MODE (was pending): Set status "tested", add TDD comment listing created tests. 2) VALIDATION MODE passed: Set status "tested". 3) VALIDATION MODE gaps found: Agent fixes inline, then sets "tested". NEVER create fix tasks - agent handles everything.')
            ->why('TDD mode produces tested status with TDD marker. Validation mode fixes inline. No task creation overhead.')
            ->onViolation('TDD mode → "tested" + TDD comment. Validation mode → fix inline → "tested". NEVER create tasks.');

        $this->rule('no-task-creation')->critical()
            ->text('FORBIDDEN: Creating fix tasks for test gaps. Agent MUST fix all issues inline. For missing tests - WRITE them. For broken tests - FIX them. For bloated tests - REFACTOR them. All in current session.')
            ->why('Task creation for test issues is wasteful overhead. Agent has full context and can fix immediately.')
            ->onViolation('Remove task creation. Fix the issue inline using Edit/Write tools.');

        $this->rule('real-workflow-tests-only')->critical()
            ->text('Tests MUST cover REAL workflows end-to-end. Reject bloated tests that test implementation details instead of behavior. Quality over quantity.')
            ->why('Bloated tests are maintenance burden, break on refactoring, provide false confidence.')
            ->onViolation('Flag bloated tests for refactoring. Create task to simplify.');

        $this->rule('documentation-requirements-coverage')->critical()
            ->text('EVERY requirement in .docs/ MUST have corresponding test coverage. Missing coverage = immediate task creation.')
            ->why('Documentation defines expected behavior. Untested requirements are unverified.')
            ->onViolation('Create task for each uncovered requirement.');

        $this->rule('cosmetic-test-auto-fix')->critical()
            ->text('COSMETIC test issues (whitespace, indentation, extra spaces, trailing spaces, test file formatting, comment typos in tests) MUST be auto-fixed IMMEDIATELY by the agent that discovers them. NO separate phase, NO additional agents, NO tasks. Agent finds problem → Agent fixes it → Agent continues.')
            ->why('Cosmetic fixes are trivial. Creating tasks or spawning extra agents for whitespace in tests is wasteful. The discovering agent has full context.')
            ->onViolation('Agent that found cosmetic test issue MUST fix it inline using Edit tool. Report fixed count in results, not as pending issues.');

        // Common rule from trait
        $this->defineAutoApprovalFlagRule();

        $this->rule('parallel-agent-orchestration')->high()
            ->text('Test validation phases SHOULD scale agent orchestration to task complexity. Complex tasks may launch 5-6 agents in parallel; simple tasks can limit to 1-2 agents.')
            ->why('Parallel validation reduces time and maximizes coverage when complexity justifies it; lighter workloads stay efficient with fewer agents.')
            ->onViolation('Scale agent usage based on scope. Avoid blanket 5-6 agent blasts for simple work.');

        $this->rule('simple-test-validation-heuristic')->high()
            ->text('Detect simple vector tasks (low estimate, non-critical priority) so heavy parallel validation can be skipped.')
            ->why('Tiny tasks benefit from a low-overhead validation path that still checks docs/tests but avoids launching six agents.')
            ->onViolation('Treat mismarked simple tasks as complex before rerunning validation.');

        $this->rule('idempotent-validation')->high()
            ->text('Test validation is IDEMPOTENT. Running multiple times produces same result (no duplicate tasks, no repeated analysis).')
            ->why('Allows safe re-runs without side effects.')
            ->onViolation('Check existing tasks before creating. Skip duplicates.');

        // Common rule from trait
        $this->defineSessionRecoveryViaHistoryRule();

        // Common rule from trait
        $this->defineVectorMemoryMandatoryRule();

        // CRITICAL: Fix task parent_id assignment
        // Common rule from trait
        $this->defineFixTaskParentRule();

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        // Common guideline from trait
        $this->defineInputCaptureGuideline();

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task with full context using pre-parsed $VECTOR_TASK_ID, verify testable status')
            ->example()
            ->phase(Operator::output([
                '=== TASK:TEST-VALIDATE ACTIVATED ===',
                '',
                '=== PHASE 0: VECTOR TASK LOADING ===',
                'Loading task #{$VECTOR_TASK_ID}...',
            ]))
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK', '{task object with title, content, status, parent_id, priority, tags}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status NOT IN ["pending", "completed", "tested", "validated", "in_progress"]', [
                Operator::output([
                    '=== TEST VALIDATION BLOCKED ===',
                    'Task #$VECTOR_TASK_ID has status: {$VECTOR_TASK.status}',
                    'Allowed statuses: pending (TDD mode), completed/tested/validated (validation mode).',
                    'stopped tasks must be reset first.',
                ]),
                'ABORT validation',
            ]))
            ->phase(Store::as('IS_TDD_MODE', '{$VECTOR_TASK.status === "pending"}'))
            ->phase(Operator::if('$IS_TDD_MODE === true', [
                Operator::output([
                    '',
                    '🧪 TDD MODE ACTIVATED',
                    'Task #{$VECTOR_TASK_ID} is pending - will WRITE tests for unimplemented feature.',
                    'After test creation, task will be set to "tested" with TDD comment.',
                ]),
            ]))
            ->phase(Store::as('IS_SESSION_RECOVERY', 'false'))
            ->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                Operator::note('Task is in_progress - check status_history for session crash indicator'),
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Operator::output([
                        '',
                        '⚠️ SESSION RECOVERY DETECTED',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Continuing test validation without status change.',
                        'NOTE: Previous session vector memory findings should be treated with caution - context may be incomplete.',
                        '',
                    ]),
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== TEST VALIDATION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another active session.',
                        'Wait for completion or use /task:async to take over.',
                    ]),
                    'ABORT test validation',
                ]),
            ]))
            ->phase(Operator::note('CRITICAL: Set TASK_PARENT_ID to the CURRENTLY validated task ID IMMEDIATELY after loading.'))
            ->phase(Store::as('TASK_PARENT_ID', '{$VECTOR_TASK_ID}'))
            ->phase(Operator::note('TASK_PARENT_ID = $VECTOR_TASK_ID (the task we are test-validating NOW). Any fix tasks created will be children of THIS task.'))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                Operator::note('Fetching parent task FOR CONTEXT DISPLAY ONLY. This DOES NOT change TASK_PARENT_ID.'),
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK_CONTEXT', '{parent task for display context only - NOT for parent_id assignment}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 50}'))
            ->phase(Store::as('SUBTASKS', '{list of subtasks}'))
            ->phase(Store::as('TASK_DESCRIPTION', '{$VECTOR_TASK.title + $VECTOR_TASK.content}'))
            ->phase(Store::as('SIMPLE_TEST_VALIDATION', '{true if $VECTOR_TASK.estimate <= 4 AND $VECTOR_TASK.priority !== "critical"}'))
            ->phase(Operator::note('SIMPLE_TEST_VALIDATION = {$SIMPLE_TEST_VALIDATION}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent context: {$PARENT_TASK_CONTEXT.title or "none"}',
                'Subtasks: {$SUBTASKS.count}',
                'Fix tasks parent_id will be: $TASK_PARENT_ID (THIS task)',
            ]));

        // Phase 1: Agent Discovery and Test Validation Scope Preview
        $this->guideline('phase1-validation-preview')
            ->goal('Discover available agents and present test validation scope for approval')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 1: TEST VALIDATION PREVIEW ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents with capabilities'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agent_id: description mapping}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'), 'Get documentation INDEX preview'))
            ->phase(Store::as('DOCS_PREVIEW', 'Documentation files available'))
            ->phase(Operator::output([
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Mode: {$IS_TDD_MODE ? "TDD (write tests)" : "Validation (validate & fix)"}',
                'Available agents: {$AVAILABLE_AGENTS.count}',
                'Documentation files: {$DOCS_PREVIEW.count}',
                'Simple validation mode: {$SIMPLE_TEST_VALIDATION}',
                '',
                Operator::if('$IS_TDD_MODE === true', [
                    'TDD workflow will:',
                    '1. Analyze task requirements from docs + memory',
                    '2. WRITE tests for the unimplemented feature',
                    '3. Set status "tested" with TDD comment listing tests',
                    '4. Task can then be executed via /task:async or /task:sync',
                ]),
                Operator::if('$IS_TDD_MODE === false', [
                    'Validation workflow will delegate to agents:',
                    '1. VectorMaster - deep memory research for test context',
                    '2. DocumentationMaster - testable requirements extraction',
                    '3. Selected agents - test discovery + parallel validation',
                    '4. FIX all gaps inline (no task creation)',
                ]),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output([
                    '',
                    '⚠️  APPROVAL REQUIRED',
                    '✅ approved/yes - start test validation | ❌ no/modifications',
                ]),
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['Auto-approval: -y flag detected, skipping confirmation.']),
            ]))
            ->phase('IMMEDIATELY after approval - set task in_progress (test validation IS execution)')
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Test validation started after approval", append_comment: true}'))
            ->phase(Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} started (test validation phase)']));

        // Phase 2: Deep Test Context Gathering via VectorMaster Agent
        $this->guideline('phase2-context-gathering')
            ->goal('Delegate deep test context research to VectorMaster agent')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: DEEP TEST CONTEXT ===',
                'Delegating to VectorMaster for deep memory research...',
            ]))
            ->phase(Operator::if('$IS_SESSION_RECOVERY === true', [
                Operator::note('CAUTION: This is a session recovery. Vector memory findings from the crashed session may be incomplete or stale. Cross-validate memory results with fresh codebase analysis. Do not assume previous session findings are accurate.'),
            ]))
            ->phase('SELECT vector-master from $AVAILABLE_AGENTS')
            ->phase(Store::as('CONTEXT_AGENT', '{vector-master agent_id}'))
            ->phase(TaskTool::agent('{$CONTEXT_AGENT}', 'DEEP MEMORY RESEARCH for test validation of task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Multi-probe search: past test implementations, test patterns, testing best practices, test failures, coverage gaps 2) Search across categories: code-solution, learning, bug-fix 3) Extract test-specific insights: what worked, what failed, patterns used 4) Return: {test_history: [...], test_patterns: [...], past_failures: [...], quality_standards: [...], key_insights: [...]}. Store consolidated test context.'))
            ->phase(Store::as('TEST_MEMORY_CONTEXT', '{VectorMaster agent results}'))
            ->phase(VectorTaskMcp::call('task_list', '{query: "test $TASK_DESCRIPTION", limit: 10}'))
            ->phase(Store::as('RELATED_TEST_TASKS', 'Related test tasks'))
            ->phase(Operator::output([
                'Context gathered via {$CONTEXT_AGENT}:',
                '- Test insights: {$TEST_MEMORY_CONTEXT.key_insights.count}',
                '- Related test tasks: {$RELATED_TEST_TASKS.count}',
            ]));

        // Phase 3: Documentation Requirements Extraction
        $this->guideline('phase3-documentation-extraction')
            ->goal('Extract ALL testable requirements from .docs/ via DocumentationMaster')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: DOCUMENTATION REQUIREMENTS ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', 'Documentation file paths'))
            ->phase(Operator::if('$DOCS_INDEX not empty', [
                TaskTool::agent('documentation-master', 'Extract ALL TESTABLE requirements from documentation files: {$DOCS_INDEX paths}. For each requirement identify: [{requirement_id, description, testable_scenarios: [...], acceptance_criteria, expected_test_type: unit|feature|integration|e2e, priority}]. Focus on BEHAVIOR not implementation. Store to vector memory.'),
                Store::as('DOCUMENTATION_REQUIREMENTS', '{structured testable requirements list}'),
            ]))
            ->phase(Operator::if('$DOCS_INDEX empty', [
                Store::as('DOCUMENTATION_REQUIREMENTS', '[]'),
                Operator::output(['WARNING: No documentation found. Test validation will be limited to existing tests only.']),
            ]))
            ->phase(Operator::output([
                'Testable requirements extracted: {$DOCUMENTATION_REQUIREMENTS.count}',
                '{requirements summary with test types}',
            ]));

        // Phase 4: Test Discovery via Dynamic Agent Selection
        $this->guideline('phase4-test-discovery')
            ->goal('Select best agent from $AVAILABLE_AGENTS and discover all existing tests')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 4: TEST DISCOVERY ===',
            ]))
            ->phase('SELECT AGENT for test discovery from {$AVAILABLE_AGENTS} (prefer explore for codebase scanning)')
            ->phase(Store::as('DISCOVERY_AGENT', '{selected agent_id based on descriptions}'))
            ->phase(TaskTool::agent('{$DISCOVERY_AGENT}', 'DEEP RESEARCH - TEST DISCOVERY for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search vector memory for past test patterns and locations 2) Scan codebase for test directories (tests/, spec/, __tests__) 3) Find ALL related test files: unit, feature, integration, e2e 4) Analyze test structure and coverage 5) Return: [{test_file, test_type, test_classes, test_methods, related_source_files}]. Store findings to vector memory.'))
            ->phase(Store::as('DISCOVERED_TESTS', '{list of test files with metadata}'))
            ->phase(Operator::output([
                'Tests discovered via {$DISCOVERY_AGENT}: {$DISCOVERED_TESTS.count} files',
                '{test files summary by type}',
            ]));

        // Phase 4.5: TDD TEST CREATION (TDD MODE ONLY)
        $this->guideline('phase4.5-tdd-test-creation')
            ->goal('In TDD mode: Write tests for unimplemented feature. Agent creates test files based on requirements.')
            ->example()
            ->phase(Operator::if('$IS_TDD_MODE === true', [
                Operator::output([
                    '',
                    '=== PHASE 4.5: TDD TEST CREATION ===',
                    '🧪 Writing tests for unimplemented feature...',
                ]),
                'SELECT AGENT for test writing from {$AVAILABLE_AGENTS} (prefer agent with Edit/Write capabilities)',
                Store::as('TDD_AGENT', '{selected agent_id for test writing}'),
                TaskTool::agent('{$TDD_AGENT}', 'TDD TEST CREATION for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Analyze {$DOCUMENTATION_REQUIREMENTS} - extract ALL testable scenarios 2) Search vector memory for similar test patterns in this project 3) CREATE test files for EACH requirement: unit tests, feature tests, integration tests as appropriate 4) Follow project test conventions from {$DISCOVERED_TESTS} patterns 5) Tests MUST be designed to FAIL initially (feature not implemented yet) 6) Use descriptive test method names (given_when_then pattern) 7) Include edge cases and error scenarios 8) Return: {created_tests: [{test_file, test_class, test_methods: [...], covered_requirements: [...]}], total_tests_created: N}. Store findings to vector memory.'),
                Store::as('TDD_CREATED_TESTS', '{list of created test files with methods}'),
                Operator::output([
                    'TDD tests created via {$TDD_AGENT}:',
                    '- Test files: {$TDD_CREATED_TESTS.count}',
                    '- Total test methods: {$TDD_CREATED_TESTS.total_tests_created}',
                    '- Requirements covered: {$DOCUMENTATION_REQUIREMENTS.count}',
                ]),
                Store::as('TDD_TESTS_SUMMARY', '{formatted list of created tests with file paths and method names}'),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false', [
                Operator::note('SKIP Phase 4.5 - not in TDD mode'),
            ]));

        // Phase 5: Dynamic Agent Selection and Parallel Test Validation (VALIDATION MODE ONLY)
        $this->guideline('phase5-parallel-validation')
            ->goal('In validation mode: Select best agents and launch parallel test validation. In TDD mode: SKIP (tests just created).')
            ->example()
            ->phase(Operator::if('$IS_TDD_MODE === true', [
                Operator::output([
                    '',
                    '=== PHASE 5: SKIPPED (TDD MODE) ===',
                    'Tests were just created. Skipping validation phase.',
                ]),
                'SKIP to Phase 6 (completion)',
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false', [
                Operator::output([
                    '',
                    '=== PHASE 5: PARALLEL TEST VALIDATION ===',
                ]),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false', [
                'AGENT SELECTION: Analyze $AVAILABLE_AGENTS descriptions and select BEST agent for each test validation aspect:',
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false AND $SIMPLE_TEST_VALIDATION === false', [
                Operator::do([
                    'ASPECT 1 - REQUIREMENTS COVERAGE: Select agent for requirements-to-test mapping (vector-master for memory, explore for codebase)',
                    'ASPECT 2 - TEST QUALITY: Select agent for code quality analysis (explore for pattern detection)',
                    'ASPECT 3 - WORKFLOW COVERAGE: Select agent for workflow analysis (explore for flow tracing)',
                    'ASPECT 4 - TEST CONSISTENCY: Select agent for consistency analysis (explore for pattern matching)',
                    'ASPECT 5 - TEST ISOLATION: Select agent for isolation analysis (explore for dependency scanning)',
                    'ASPECT 6 - TEST EXECUTION: Select agent capable of running tests (explore with bash access)',
                ]),
                Store::as('SELECTED_AGENTS', '{aspect: agent_id mapping based on $AVAILABLE_AGENTS}'),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false AND $SIMPLE_TEST_VALIDATION === true', [
                Operator::output(['Simple validation: reduced agent set (coverage + execution only)']),
                Operator::do([
                    'ASPECT 1 - REQUIREMENTS COVERAGE: Select agent for requirements-to-test mapping',
                    'ASPECT 2 - TEST EXECUTION: Select agent capable of running tests',
                ]),
                Store::as('SELECTED_AGENTS', '{coverage: explore, execution: explore}'),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false', [
                Operator::output([
                    'Selected agents for test validation:',
                    '{$SELECTED_AGENTS mapping}',
                    '',
                    'Launching test validation agents in parallel...',
                ]),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false AND $SIMPLE_TEST_VALIDATION === false', [
                'PARALLEL BATCH: Launch agents with inline FIX capability (no task creation)',
                Operator::do([
                    TaskTool::agent('{$SELECTED_AGENTS.coverage}', 'REQUIREMENTS COVERAGE + FIX for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Compare {$DOCUMENTATION_REQUIREMENTS} against {$DISCOVERED_TESTS} 2) For missing/partial coverage - WRITE the missing tests IMMEDIATELY using Write tool 3) Fix cosmetic issues inline. Return: [{requirement_id, coverage_status: covered|partial|missing, action_taken: none|created|fixed, test_file, test_method, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.quality}', 'TEST QUALITY + FIX for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Analyze {$DISCOVERED_TESTS} for bloat 2) REFACTOR bloated tests IMMEDIATELY using Edit tool 3) Fix cosmetic issues inline. Return: [{test_file, test_method, bloat_type, action_taken: none|refactored, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.workflow}', 'WORKFLOW COVERAGE + FIX for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Verify workflows have end-to-end coverage 2) WRITE missing workflow tests IMMEDIATELY 3) Fix cosmetic issues inline. Return: [{workflow, coverage_status, action_taken: none|created, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.consistency}', 'TEST CONSISTENCY + FIX for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Check test consistency 2) FIX inconsistencies IMMEDIATELY using Edit tool. Return: [{test_file, inconsistency_type, action_taken: none|fixed, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.isolation}', 'TEST ISOLATION + FIX for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Check test isolation 2) FIX isolation issues IMMEDIATELY using Edit tool. Return: [{test_file, isolation_issue, action_taken: none|fixed, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.execution}', 'TEST EXECUTION + FIX for task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Run tests 2) FIX failing tests IMMEDIATELY using Edit tool 3) For flaky tests - add stabilization. Return: [{test_file, execution_status: pass|fail|flaky, action_taken: none|fixed, cosmetic_fixes_applied: N}]. Store findings.'),
                ]),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false AND $SIMPLE_TEST_VALIDATION === true', [
                'SIMPLE BATCH: Launch reduced agent set with inline FIX capability',
                Operator::do([
                    TaskTool::agent('{$SELECTED_AGENTS.coverage}', 'REQUIREMENTS COVERAGE + FIX for task #{$VECTOR_TASK_ID}: Compare requirements against tests. WRITE missing tests IMMEDIATELY. Return: [{requirement_id, coverage_status, action_taken, test_file, cosmetic_fixes_applied: N}].'),
                    TaskTool::agent('{$SELECTED_AGENTS.execution}', 'TEST EXECUTION + FIX for task #{$VECTOR_TASK_ID}: Run tests. FIX failing tests IMMEDIATELY. Return: [{test_file, execution_status, action_taken, cosmetic_fixes_applied: N}].'),
                ]),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false', [
                Store::as('VALIDATION_BATCH', '{results from all agents}'),
                Operator::output([
                    'Batch complete: {$SELECTED_AGENTS.count} test validation + fix agents finished',
                ]),
            ]));

        // Phase 6: Results Aggregation and Completion
        $this->guideline('phase6-completion')
            ->goal('Aggregate results, update task status to "tested", store summary to memory. TDD mode gets TDD comment.')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 6: COMPLETION ===',
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === true', [
                'TDD MODE COMPLETION: Tests created for unimplemented feature',
                Store::as('TDD_COMMENT', 'TDD MODE: Tests created before implementation.\\n\\nCreated tests:\\n{$TDD_TESTS_SUMMARY}\\n\\nRequirements covered: {$DOCUMENTATION_REQUIREMENTS.count}\\nTotal test files: {$TDD_CREATED_TESTS.count}\\nTotal test methods: {$TDD_CREATED_TESTS.total_tests_created}\\n\\nNext: Execute /task:async or /task:sync to implement the feature. Tests will initially FAIL (expected TDD behavior).'),
                VectorMemoryMcp::call('store_memory', '{content: "TDD tests created for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nTests: {$TDD_TESTS_SUMMARY}\\nRequirements covered: {$DOCUMENTATION_REQUIREMENTS.count}", category: "code-solution", tags: ["tdd", "test-creation", "task:test-validate"]}'),
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "tested", comment: "$TDD_COMMENT", append_comment: true}'),
                Operator::output([
                    '✅ TDD MODE COMPLETE',
                    'Task #{$VECTOR_TASK_ID} marked as TESTED (TDD)',
                    '',
                    'Created tests:',
                    '{$TDD_TESTS_SUMMARY}',
                    '',
                    '📋 Next steps:',
                    '1. Run /task:async #{$VECTOR_TASK_ID} or /task:sync #{$VECTOR_TASK_ID} to implement',
                    '2. Tests will FAIL initially (expected TDD behavior)',
                    '3. After implementation, tests should PASS',
                    '4. Run /task:test-validate #{$VECTOR_TASK_ID} again for post-implementation validation',
                ]),
            ]))
            ->phase(Operator::if('$IS_TDD_MODE === false', [
                'VALIDATION MODE COMPLETION: Tests validated and gaps fixed inline',
                'Aggregate results from validation agents',
                Store::as('TESTS_CREATED_BY_AGENTS', '{count of tests created by agents during validation}'),
                Store::as('TESTS_FIXED_BY_AGENTS', '{count of tests fixed by agents during validation}'),
                Store::as('COSMETIC_FIXES', '{sum of cosmetic_fixes_applied from all agents}'),
                Store::as('TOTAL_FIXES', '{$TESTS_CREATED_BY_AGENTS + $TESTS_FIXED_BY_AGENTS + $COSMETIC_FIXES}'),
                VectorMemoryMcp::call('store_memory', '{content: "Test validation of task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nStatus: PASSED (all gaps fixed inline)\\nTests created: {$TESTS_CREATED_BY_AGENTS}\\nTests fixed: {$TESTS_FIXED_BY_AGENTS}\\nCosmetic fixes: {$COSMETIC_FIXES}", category: "code-solution", tags: ["test-validation", "task:test-validate"]}'),
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "tested", comment: "Test validation PASSED. All gaps fixed inline by agents.\\n\\nTests created: {$TESTS_CREATED_BY_AGENTS}\\nTests fixed: {$TESTS_FIXED_BY_AGENTS}\\nCosmetic fixes: {$COSMETIC_FIXES}", append_comment: true}'),
                Operator::output([
                    '✅ VALIDATION MODE COMPLETE',
                    'Task #{$VECTOR_TASK_ID} marked as TESTED',
                    '',
                    '| Metric | Count |',
                    '|--------|-------|',
                    '| Tests created | {$TESTS_CREATED_BY_AGENTS} |',
                    '| Tests fixed | {$TESTS_FIXED_BY_AGENTS} |',
                    '| Cosmetic fixes | {$COSMETIC_FIXES} |',
                    '| Total fixes | {$TOTAL_FIXES} |',
                    '',
                    'All test gaps fixed inline by validation agents.',
                    'Test validation stored to vector memory.',
                ]),
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Graceful error handling for test validation process')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with ' . VectorTaskMcp::method('task_list'),
                'Abort validation',
            ])
            ->phase()->if('vector task not in testable status', [
                'Report: "Vector task #{id} status is {status}"',
                'Allowed: pending (TDD mode), completed/tested/validated (validation mode)',
                'Suggest: Run /task:async #{id} first if in_progress/stopped',
                'Abort validation',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use /do:test-validate for text-based validation"',
                'Abort command',
            ])
            ->phase()->if('no documentation found (TDD mode)', [
                'Warn: "No documentation in .docs/ for TDD test creation"',
                'Continue with task content as requirements source',
                'Note: "Tests will be based on task description only"',
            ])
            ->phase()->if('no documentation found (validation mode)', [
                'Warn: "No documentation in .docs/ for this task"',
                'Continue with test-only validation (existing tests analysis)',
                'Note: "Cannot verify requirements coverage without documentation"',
            ])
            ->phase()->if('no tests found (TDD mode)', [
                'Note: "No existing tests - expected for TDD mode"',
                'Continue with TDD test creation',
            ])
            ->phase()->if('no tests found (validation mode)', [
                'Report: "No tests found for task #{$VECTOR_TASK_ID}"',
                'Agent MUST WRITE initial tests for {$TASK_DESCRIPTION}',
                'Continue with documentation requirements analysis',
            ])
            ->phase()->if('test execution fails', [
                'Log: "Test execution failed: {error}"',
                'Agent MUST FIX the issue if possible',
                'Mark tests as "execution_unknown" if unfixable',
                'Continue with static analysis',
            ])
            ->phase()->if('agent test creation fails (TDD mode)', [
                'Log: "TDD test creation failed: {error}"',
                'Report error to user with details',
                'Suggest manual test creation or retry',
            ])
            ->phase()->if('agent validation fails', [
                'Log: "Validation agent {N} failed: {error}"',
                'Continue with remaining agents',
                'Report partial validation in summary',
            ]);

        // Test Quality Criteria
        $this->guideline('test-quality-criteria')
            ->text('Criteria for evaluating test quality (bloat detection)')
            ->example()
            ->phase('BLOAT INDICATORS (flag for refactoring):')
            ->do([
                'Excessive mocking (>3 mocks per test)',
                'Testing private methods directly',
                'Testing getters/setters without logic',
                'Copy-paste test code (>80% similarity)',
                'Single assertion tests without context',
                'Testing framework internals',
                'Hard-coded magic values without explanation',
                'Test method >50 lines',
                'Setup >30 lines',
            ])
            ->phase('QUALITY INDICATORS (good tests):')
            ->do([
                'Tests behavior, not implementation',
                'Readable test names (given_when_then)',
                'Single responsibility per test',
                'Proper use of fixtures/factories',
                'Edge cases covered',
                'Error paths tested',
                'Fast execution (<100ms per test)',
                'No external dependencies without mocks',
            ]);

        // Constraints and Validation
        $this->guideline('constraints')
            ->text('Test validation constraints and limits')
            ->example()
            ->phase('Max 6 parallel validation agents per batch')
            ->phase('TDD mode: Agent creates tests based on requirements')
            ->phase('Validation mode: Agent fixes issues inline (no task creation)')
            ->phase('Test execution timeout: 5 minutes total')
            ->phase('Bloat threshold: >50% bloated = critical warning')
            ->phase(Operator::verify([
                'vector_task_loaded = true',
                'testable_status_verified = true (pending OR completed/tested/validated)',
                'mode_determined = $IS_TDD_MODE',
                'parallel_agents_used = true (validation mode only)',
                'documentation_checked = true',
                'tests_executed_or_created = true',
                'results_stored_to_memory = true',
                'no_task_creation = true (fixes inline)',
            ]));

        // Examples - TDD Mode
        $this->guideline('example-tdd-mode')
            ->scenario('TDD: Write tests for pending task')
            ->example()
            ->phase('input', '"task 15" where task #15 has status: pending')
            ->phase('mode', 'TDD MODE activated (task not yet implemented)')
            ->phase('flow', 'Task Loading → Context → Docs → Test Discovery → TDD TEST CREATION → Complete')
            ->phase('result', 'Tests created, Task #15 status → tested, TDD comment added')
            ->phase('next', 'Run /task:async #15 or /task:sync #15 to implement. Tests will FAIL initially.');

        // Examples - Validation Mode
        $this->guideline('example-validation-mode')
            ->scenario('Validation: Validate tests for completed task')
            ->example()
            ->phase('input', '"task 15" or "#15" where task #15 has status: completed')
            ->phase('mode', 'VALIDATION MODE activated (task already implemented)')
            ->phase('flow', 'Task Loading → Context → Docs → Test Discovery → Parallel Validation (6 agents) → Inline Fixes → Complete')
            ->phase('result', 'All gaps fixed inline, Task #15 status → tested');

        $this->guideline('example-validation-with-fixes')
            ->scenario('Validation finds and fixes issues inline')
            ->example()
            ->phase('input', '"#28" where task #28 has status: completed')
            ->phase('validation', 'Found: 2 missing coverage, 1 bloated test, 1 failing')
            ->phase('action', 'Agents FIX all issues inline (write missing tests, refactor bloat, fix failing)')
            ->phase('result', 'Task #28 status → tested, all fixes applied directly');

        $this->guideline('example-rerun')
            ->scenario('Re-run test validation (idempotent)')
            ->example()
            ->phase('input', '"task 15" (already test-validated before)')
            ->phase('behavior', 'Checks for new issues, fixes any found inline')
            ->phase('result', 'Same/updated validation report, no duplicate work');

        // TDD Workflow explanation
        $this->guideline('tdd-workflow')
            ->text('Complete TDD workflow with /task:test-validate')
            ->example()
            ->phase('Step 1', 'Create task for new feature: /task:create "Add user notifications"')
            ->phase('Step 2', 'Run TDD test creation: /task:test-validate #15 (status: pending → tested)')
            ->phase('Step 3', 'Implement feature: /task:async #15 or /task:sync #15 (status: tested → allows execution)')
            ->phase('Step 4', 'Tests should PASS after implementation')
            ->phase('Step 5', 'Run post-implementation validation: /task:test-validate #15 (validates & fixes inline)')
            ->phase('Step 6', 'Run full validation: /task:validate #15 (status: tested → validated)');

        // When to use task:test-validate vs do:test-validate
        $this->guideline('test-validate-vs-do-test-validate')
            ->text('When to use /task:test-validate vs /do:test-validate')
            ->example()
            ->phase('USE /task:test-validate', 'Validate/create tests for specific vector task by ID (15, #15, task 15). Supports TDD mode for pending tasks.')
            ->phase('USE /do:test-validate', 'Validate tests by text description ("test-validate user authentication"). For ad-hoc validation without existing vector task.');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | Mode indicator (TDD/Validation) | Tables: coverage metrics + issue counts | Fixes applied inline | 📋 task ID references');
    }
}
