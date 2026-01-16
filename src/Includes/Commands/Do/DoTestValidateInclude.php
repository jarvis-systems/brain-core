<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Do;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Text-based test validation with parallel agent orchestration. Accepts text description (example: "test-validate user authentication"). Validates test coverage against documentation requirements, test quality (no bloat, real workflows), test consistency, and completeness. Creates memory entries for gaps. Idempotent. For vector task test validation use /task:test-validate.')]
class DoTestValidateInclude extends IncludeArchetype
{
    use DoCommandCommonTrait;
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // ABSOLUTE FIRST - BLOCKING ENTRY RULE
        $this->defineEntryPointBlockingRule('TEST-VALIDATE');

        // Iron Rules - Zero Tolerance
        $this->defineTestValidationOnlyRule();

        $this->defineTextDescriptionRequiredRule('test-validate', '/task:test-validate');

        $this->rule('real-workflow-tests-only')->critical()
            ->text('Tests MUST cover REAL workflows end-to-end. Reject bloated tests that test implementation details instead of behavior. Quality over quantity.')
            ->why('Bloated tests are maintenance burden, break on refactoring, provide false confidence.')
            ->onViolation('Flag bloated tests for refactoring. Create memory entry to simplify.');

        $this->rule('documentation-requirements-coverage')->critical()
            ->text('EVERY requirement in .docs/ MUST have corresponding test coverage. Missing coverage = immediate memory entry creation.')
            ->why('Documentation defines expected behavior. Untested requirements are unverified.')
            ->onViolation('Create memory entry for each uncovered requirement.');

        $this->defineParallelAgentOrchestrationRule();

        $this->defineIdempotentValidationRule('entries');

        $this->defineVectorMemoryMandatoryRule('ALL test validation results');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->defineInputCaptureWithAutoApproveGuideline();

        // Phase 0: Context Setup and Task ID Detection
        $this->guideline('phase0-context-setup')
            ->goal('Detect task ID patterns and reject, set up test validation context from $RAW_INPUT')
            ->example()
            ->phase(Operator::output([
                '=== DO:TEST-VALIDATE ACTIVATED ===',
                '',
                '=== PHASE 0: CONTEXT SETUP ===',
                'Processing input...',
            ]))
            ->phase(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags (-y, --yes) removed, trimmed}'))
            ->phase('Parse $CLEAN_ARGS for task ID patterns: "N", "#N", "task N", "task:N", "task-N"')
            ->phase(Operator::if('$CLEAN_ARGS matches task ID pattern', [
                Operator::output([
                    '=== DO:TEST-VALIDATE BLOCKED ===',
                    'Detected task ID pattern in arguments: {$RAW_INPUT}',
                    'This command is for TEXT-BASED test validation only.',
                    '',
                    'Use /task:test-validate {task_id} for vector task test validation.',
                ]),
                'ABORT command',
            ]))
            ->phase(Store::as('TASK_DESCRIPTION', '$CLEAN_ARGS'))
            ->phase(Operator::output([
                'Test validation target: {$TASK_DESCRIPTION}',
                'Mode: Text-based (no vector task)',
                '{IF $HAS_AUTO_APPROVE: "Auto-approve: enabled (-y flag)"}',
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
                'Test validation: {$TASK_DESCRIPTION}',
                'Available agents: {$AVAILABLE_AGENTS.count}',
                'Documentation files: {$DOCS_PREVIEW.count}',
                '',
                'Test validation will delegate to agents:',
                '1. VectorMaster - deep memory research for test context',
                '2. DocumentationMaster - testable requirements extraction',
                '3. Selected agents - test discovery + parallel validation (6 aspects)',
                '',
                '⚠️  APPROVAL REQUIRED',
                '✅ approved/yes - start test validation | ❌ no/modifications',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                'AUTO-APPROVED (unattended mode)',
                Operator::output(['Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]));

        // Phase 2: Deep Test Context Gathering via VectorMaster Agent
        $this->guideline('phase2-context-gathering')
            ->goal('Delegate deep test context research to VectorMaster agent')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: DEEP TEST CONTEXT ===',
                'Delegating to VectorMaster for deep memory research...',
            ]))
            ->phase('SELECT vector-master from $AVAILABLE_AGENTS')
            ->phase(Store::as('CONTEXT_AGENT', '{vector-master agent_id}'))
            ->phase(TaskTool::agent('{$CONTEXT_AGENT}', 'DEEP MEMORY RESEARCH for test validation of "{$TASK_DESCRIPTION}": 1) Multi-probe search: past test implementations, test patterns, testing best practices, test failures, coverage gaps 2) Search across categories: code-solution, learning, bug-fix 3) Extract test-specific insights: what worked, what failed, patterns used 4) Return: {test_history: [...], test_patterns: [...], past_failures: [...], quality_standards: [...], key_insights: [...]}. Store consolidated test context.'))
            ->phase(Store::as('TEST_MEMORY_CONTEXT', '{VectorMaster agent results}'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "test $TASK_DESCRIPTION", limit: 10}'))
            ->phase(Store::as('RELATED_TEST_MEMORIES', 'Related test memories'))
            ->phase(Operator::output([
                'Context gathered via {$CONTEXT_AGENT}:',
                '- Test insights: {$TEST_MEMORY_CONTEXT.key_insights.count}',
                '- Related test memories: {$RELATED_TEST_MEMORIES.count}',
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
            ->phase(TaskTool::agent('{$DISCOVERY_AGENT}', 'DEEP RESEARCH - TEST DISCOVERY for "{$TASK_DESCRIPTION}": 1) Search vector memory for past test patterns and locations 2) Scan codebase for test directories (tests/, spec/, __tests__) 3) Find ALL related test files: unit, feature, integration, e2e 4) Analyze test structure and coverage 5) Return: [{test_file, test_type, test_classes, test_methods, related_source_files}]. Store findings to vector memory.'))
            ->phase(Store::as('DISCOVERED_TESTS', '{list of test files with metadata}'))
            ->phase(Operator::output([
                'Tests discovered via {$DISCOVERY_AGENT}: {$DISCOVERED_TESTS.count} files',
                '{test files summary by type}',
            ]));

        // Phase 5: Dynamic Agent Selection and Parallel Test Validation
        $this->guideline('phase5-parallel-validation')
            ->goal('Select best agents from $AVAILABLE_AGENTS and launch parallel test validation')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 5: PARALLEL TEST VALIDATION ===',
            ]))
            ->phase('AGENT SELECTION: Analyze $AVAILABLE_AGENTS descriptions and select BEST agent for each test validation aspect:')
            ->phase(Operator::do([
                'ASPECT 1 - REQUIREMENTS COVERAGE: Select agent for requirements-to-test mapping (vector-master for memory, explore for codebase)',
                'ASPECT 2 - TEST QUALITY: Select agent for code quality analysis (explore for pattern detection)',
                'ASPECT 3 - WORKFLOW COVERAGE: Select agent for workflow analysis (explore for flow tracing)',
                'ASPECT 4 - TEST CONSISTENCY: Select agent for consistency analysis (explore for pattern matching)',
                'ASPECT 5 - TEST ISOLATION: Select agent for isolation analysis (explore for dependency scanning)',
                'ASPECT 6 - TEST EXECUTION: Select agent capable of running tests (explore with bash access)',
            ]))
            ->phase(Store::as('SELECTED_AGENTS', '{aspect: agent_id mapping based on $AVAILABLE_AGENTS}'))
            ->phase(Operator::output([
                'Selected agents for test validation:',
                '{$SELECTED_AGENTS mapping}',
                '',
                'Launching test validation agents in parallel...',
            ]))
            ->phase('PARALLEL BATCH: Launch selected agents simultaneously with DEEP RESEARCH tasks')
            ->phase(Operator::do([
                TaskTool::agent('{$SELECTED_AGENTS.coverage}', 'DEEP RESEARCH - REQUIREMENTS COVERAGE for "{$TASK_DESCRIPTION}": 1) Search vector memory for past requirement-test mappings 2) Compare {$DOCUMENTATION_REQUIREMENTS} against {$DISCOVERED_TESTS} 3) For each requirement verify test exists 4) Return: [{requirement_id, coverage_status: covered|partial|missing, test_file, test_method, gap_description, memory_refs}]. Store findings.'),
                TaskTool::agent('{$SELECTED_AGENTS.quality}', 'DEEP RESEARCH - TEST QUALITY for "{$TASK_DESCRIPTION}": 1) Search memory for test quality standards 2) Analyze {$DISCOVERED_TESTS} for bloat indicators 3) Check: excessive mocking, implementation testing, redundant assertions, copy-paste 4) Return: [{test_file, test_method, bloat_type, severity, suggestion}]. Store findings.'),
                TaskTool::agent('{$SELECTED_AGENTS.workflow}', 'DEEP RESEARCH - WORKFLOW COVERAGE for "{$TASK_DESCRIPTION}": 1) Search memory for workflow patterns 2) Verify {$DISCOVERED_TESTS} cover complete user workflows 3) Check: happy path, error paths, edge cases, boundaries 4) Return: [{workflow, coverage_status, missing_scenarios}]. Store findings.'),
                TaskTool::agent('{$SELECTED_AGENTS.consistency}', 'DEEP RESEARCH - TEST CONSISTENCY for "{$TASK_DESCRIPTION}": 1) Search memory for project test conventions 2) Check {$DISCOVERED_TESTS} for consistency 3) Verify: naming, structure, assertions, fixtures, setup/teardown 4) Return: [{test_file, inconsistency_type, description, suggestion}]. Store findings.'),
                TaskTool::agent('{$SELECTED_AGENTS.isolation}', 'DEEP RESEARCH - TEST ISOLATION for "{$TASK_DESCRIPTION}": 1) Search memory for isolation issues 2) Verify {$DISCOVERED_TESTS} are properly isolated 3) Check: shared state, order dependency, external calls, cleanup 4) Return: [{test_file, isolation_issue, severity, suggestion}]. Store findings.'),
                TaskTool::agent('{$SELECTED_AGENTS.execution}', 'DEEP RESEARCH - TEST EXECUTION for "{$TASK_DESCRIPTION}": 1) Search memory for past test failures 2) Run tests related to task 3) Identify flaky tests 4) Return: [{test_file, execution_status: pass|fail|flaky, error_message, execution_time}]. Store findings.'),
            ]))
            ->phase(Store::as('VALIDATION_BATCH', '{results from all agents}'))
            ->phase(Operator::output([
                'Batch complete: {$SELECTED_AGENTS.count} test validation checks finished',
            ]));

        // Phase 6: Results Aggregation and Analysis
        $this->guideline('phase6-results-aggregation')
            ->goal('Aggregate all test validation results and categorize issues')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 6: RESULTS AGGREGATION ===',
            ]))
            ->phase('Merge results from all validation agents')
            ->phase(Store::as('ALL_TEST_ISSUES', '{merged issues from all agents}'))
            ->phase('Categorize issues:')
            ->phase(Store::as('MISSING_COVERAGE', '{requirements without tests}'))
            ->phase(Store::as('PARTIAL_COVERAGE', '{requirements with incomplete tests}'))
            ->phase(Store::as('BLOATED_TESTS', '{tests flagged for bloat}'))
            ->phase(Store::as('MISSING_WORKFLOWS', '{workflows without end-to-end coverage}'))
            ->phase(Store::as('INCONSISTENT_TESTS', '{tests with consistency issues}'))
            ->phase(Store::as('ISOLATION_ISSUES', '{tests with isolation problems}'))
            ->phase(Store::as('FAILING_TESTS', '{tests that fail or are flaky}'))
            ->phase(Operator::output([
                'Test validation results:',
                '- Missing coverage: {$MISSING_COVERAGE.count} requirements',
                '- Partial coverage: {$PARTIAL_COVERAGE.count} requirements',
                '- Bloated tests: {$BLOATED_TESTS.count} tests',
                '- Missing workflows: {$MISSING_WORKFLOWS.count} workflows',
                '- Inconsistent tests: {$INCONSISTENT_TESTS.count} tests',
                '- Isolation issues: {$ISOLATION_ISSUES.count} tests',
                '- Failing/flaky tests: {$FAILING_TESTS.count} tests',
            ]));

        // Phase 7: Memory Storage for Test Gaps
        $this->guideline('phase7-memory-storage')
            ->goal('Store test gap findings to vector memory for future reference')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 7: MEMORY STORAGE ===',
            ]))
            ->phase('Check existing memory entries to avoid duplicates')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "test gaps $TASK_DESCRIPTION", limit: 10}'))
            ->phase(Store::as('EXISTING_TEST_MEMORIES', 'Existing test gap memories'))
            ->phase(Operator::if('$ALL_TEST_ISSUES.count > 0', [
                VectorMemoryMcp::call('store_memory', '{content: "Test validation gaps for {$TASK_DESCRIPTION}:\\n\\n## Missing Coverage ({$MISSING_COVERAGE.count})\\n{FOR each req: - {req.description} | Type: {req.expected_test_type} | Scenarios: {req.testable_scenarios}}\\n\\n## Failing Tests ({$FAILING_TESTS.count})\\n{FOR each test: - {test.test_file}:{test.test_method} | Error: {test.error_message}}\\n\\n## Bloated Tests ({$BLOATED_TESTS.count})\\n{FOR each test: - {test.test_file}:{test.test_method} | Bloat: {test.bloat_type} | Suggestion: {test.suggestion}}\\n\\n## Missing Workflows ({$MISSING_WORKFLOWS.count})\\n{FOR each wf: - {wf.workflow} | Missing: {wf.missing_scenarios}}\\n\\n## Isolation Issues ({$ISOLATION_ISSUES.count})\\n{FOR each test: - {test.test_file} | Issue: {test.isolation_issue}}\\n\\n## Context\\n- Memory IDs: {$TEST_MEMORY_CONTEXT.memory_ids}\\n- Documentation: {$DOCS_INDEX.paths}", category: "code-solution", tags: ["test-validation", "test-gaps", "do:test-validate"]}'),
                Store::as('STORED_MEMORY_ID', '{memory_id}'),
                Operator::output(['Stored test gaps to memory #{$STORED_MEMORY_ID}']),
            ]))
            ->phase(Operator::output([
                'Memory entries created: {$ALL_TEST_ISSUES.count > 0 ? 1 : 0}',
            ]));

        // Phase 8: Test Validation Completion
        $this->guideline('phase8-completion')
            ->goal('Complete test validation and store summary to memory')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 8: TEST VALIDATION COMPLETE ===',
            ]))
            ->phase(Store::as('COVERAGE_RATE', '{covered_requirements / total_requirements * 100}%'))
            ->phase(Store::as('TEST_HEALTH_SCORE', '{100 - (bloat_count + isolation_count + failing_count) / total_tests * 100}%'))
            ->phase(Store::as('VALIDATION_STATUS', Operator::if('$MISSING_COVERAGE.count === 0 AND $FAILING_TESTS.count === 0', 'PASSED', 'NEEDS_WORK')))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Test validation of {$TASK_DESCRIPTION}\\n\\nStatus: {$VALIDATION_STATUS}\\nCoverage rate: {$COVERAGE_RATE}\\nTest health: {$TEST_HEALTH_SCORE}\\n\\nMissing coverage: {$MISSING_COVERAGE.count}\\nFailing tests: {$FAILING_TESTS.count}\\nBloated tests: {$BLOATED_TESTS.count}\\n\\nKey findings: {summary}", category: "code-solution", tags: ["test-validation", "audit", "do:test-validate"]}'))
            ->phase(Operator::output([
                '',
                '=== TEST VALIDATION REPORT ===',
                'Target: {$TASK_DESCRIPTION}',
                'Status: {$VALIDATION_STATUS}',
                '',
                '| Metric | Value |',
                '|--------|-------|',
                '| Requirements coverage | {$COVERAGE_RATE} |',
                '| Test health score | {$TEST_HEALTH_SCORE} |',
                '| Total tests | {$DISCOVERED_TESTS.count} |',
                '| Passing tests | {passing_count} |',
                '| Failing/flaky tests | {$FAILING_TESTS.count} |',
                '',
                '| Issue Type | Count |',
                '|------------|-------|',
                '| Missing coverage | {$MISSING_COVERAGE.count} |',
                '| Partial coverage | {$PARTIAL_COVERAGE.count} |',
                '| Bloated tests | {$BLOATED_TESTS.count} |',
                '| Missing workflows | {$MISSING_WORKFLOWS.count} |',
                '| Isolation issues | {$ISOLATION_ISSUES.count} |',
                '',
                'Test validation stored to vector memory.',
                '',
                'Next steps:',
                '- Use /do:async to implement missing tests',
                '- Or create vector tasks with /task:create for systematic tracking',
            ]));

        // Error Handling
        $this->defineErrorHandlingGuideline(
            includeAgentErrors: true,
            includeDocErrors: true,
            isValidation: true
        );

        // Additional test-specific error handling
        $this->guideline('error-handling-test-specific')
            ->text('Additional error handling for test validation')
            ->example()
            ->phase()->if('no tests found', [
                'Report: "No tests found for {$TASK_DESCRIPTION}"',
                'Store to memory: "Write initial tests for {$TASK_DESCRIPTION}"',
                'Continue with documentation requirements analysis',
            ])
            ->phase()->if('test execution fails', [
                'Log: "Test execution failed: {error}"',
                'Mark tests as "execution_unknown"',
                'Continue with static analysis',
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
            ->phase('Test execution timeout: 5 minutes total')
            ->phase('Bloat threshold: >50% bloated = critical warning')
            ->phase(Operator::verify([
                'text_description_validated = true',
                'parallel_agents_used = true',
                'documentation_checked = true',
                'tests_executed = true',
                'results_stored_to_memory = true',
            ]));

        // Examples
        $this->guideline('example-text-validation')
            ->scenario('Test validate work by description')
            ->example()
            ->phase('input', '"test-validate user authentication"')
            ->phase('flow', 'Context setup → Memory research → Docs → Test Discovery → Parallel Validation → Aggregate → Store → Report')
            ->phase('result', 'Test validation report with coverage metrics, issues stored to memory');

        $this->guideline('example-rerun')
            ->scenario('Re-run test validation (idempotent)')
            ->example()
            ->phase('input', '"test-validate user authentication" (already validated before)')
            ->phase('behavior', 'Checks existing memory entries, skips duplicates')
            ->phase('result', 'Same/updated validation report, no duplicate memory entries');

        // When to use do:test-validate vs task:test-validate
        $this->defineCommandSelectionGuideline(
            '/do:test-validate',
            '/task:test-validate',
            'Validate tests by text description ("test-validate user authentication"). Best for: ad-hoc test validation, exploratory validation, no existing vector task.',
            'Validate tests for specific vector task by ID (15, #15, task 15). Best for: systematic task-based workflow, hierarchical task management, fix task creation as children.'
        );

        // Response Format
        $this->defineResponseFormatGuideline('=== headers | Parallel: agent batch indicators | Tables: coverage metrics + issue counts | Coverage % | Health score | Memory storage confirmation');
    }
}
