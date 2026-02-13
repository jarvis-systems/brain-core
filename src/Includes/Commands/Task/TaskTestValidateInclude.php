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

#[Purpose('Test validation with TDD support. TWO MODES: 1) TDD (pending tasks) - writes tests first, sets "tested"; 2) Validation (completed tasks) - validates coverage, fixes gaps inline. No task creation - agents fix directly.')]
class TaskTestValidateInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON RULES
        $this->rule('task-get-first')->critical()
            ->text('FIRST action = mcp__vector-task__task_get. Load task, determine mode (TDD vs Validation).');

        $this->rule('two-modes')->critical()
            ->text('MODE by status: pending = TDD (write tests first). completed/tested/validated = Validation (validate & fix inline).');

        $this->rule('no-task-creation')->critical()
            ->text('FORBIDDEN: Creating fix tasks. Agent MUST fix ALL issues inline. Missing tests = WRITE. Broken = FIX. Bloated = REFACTOR.');

        $this->rule('inline-cosmetic-fix')->critical()
            ->text('Cosmetic issues (whitespace, formatting, typos) = agent fixes IMMEDIATELY inline. No separate phase.');

        $this->rule('real-workflow-tests')->high()
            ->text('Tests MUST cover real workflows. Reject bloated tests that test implementation details.');

        $this->rule('docs-coverage')->high()
            ->text('Every requirement in .docs/ MUST have test coverage. Missing = agent writes test.');

        // Common rules from trait
        $this->defineAutoApprovalFlagRule();
        $this->defineVectorTaskIdRequiredRule('/do:test-validate');

        // DOCUMENTATION IS LAW (from trait - validates against docs, not made-up criteria)
        $this->defineDocumentationIsLawRules();

        // FAILURE-AWARE VALIDATION (prevent repeating failed test approaches)
        $this->rule('failure-history-mandatory')->critical()
            ->text('BEFORE test validation: search memory category "debugging" for KNOWN FAILURES related to this task. Pass to agents. Agents MUST NOT use test approaches that already failed.')
            ->why('Repeating failed test patterns wastes time. Memory contains "this test approach does NOT work" knowledge.')
            ->onViolation('Search debugging memories. Include known failures in agent prompts.');
        $this->rule('sibling-task-check')->high()
            ->text('BEFORE test validation: fetch sibling tasks (same parent_id). Check comments for failed test approaches.')
            ->why('Previous test attempts contain "what not to do" information.');

        // CODEBASE CONSISTENCY (from trait - tests should follow existing test patterns)
        $this->defineCodebasePatternReuseRule();

        // CODE QUALITY (from trait - verify test code correctness)
        $this->defineCodeHallucinationPreventionRule();
        $this->defineCleanupAfterChangesRule();

        // TEST SCOPING (from trait - scoped tests for subtasks, full suite for root)
        $this->defineTestScopingRule();

        $this->rule('scale-agents')->high()
            ->text('Scale agents to complexity. Simple (estimate ≤4h, non-critical): 2 agents. Complex: 3-4 agents max.');

        $this->rule('idempotent')->high()
            ->text('Re-running produces same result. No duplicates, no repeated fixes.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // Quality gate - test command for scoping
        $qualityCommands = $this->groupVars('QUALITY_COMMAND');
        $testGateCmd = $qualityCommands['TEST'] ?? '';
        $testScopingInstruction = !empty($testGateCmd)
            ? "Project test command: {$testGateCmd}. For SUBTASK: extract underlying test runner from this command, run ONLY specific test files via runner with paths/filters. NEVER run {$testGateCmd} directly for subtasks — it runs the FULL suite. For ROOT task: run {$testGateCmd} for full suite."
            : 'No test command configured. Detect test runner from project config (composer.json, package.json, Makefile). For SUBTASK: run specific test files only via runner. For ROOT: run full test suite.';

        // WORKFLOW
        $this->guideline('workflow')
            ->goal('Test validate: load → determine mode → research → TDD/validate → complete')
            ->example()

            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))
            ->phase(Operator::if('status NOT IN [pending, completed, tested, validated, in_progress]', Operator::abort('Invalid status. Complete or reset first.')))
            ->phase(Operator::if('status=in_progress', 'SESSION RECOVERY: check if crashed → continue OR abort'))
            ->phase(Store::as('IS_TDD', 'TASK.status === "pending"'))
            ->phase(Store::as('IS_SIMPLE', 'TASK.estimate ≤4 AND priority !== "critical"'))
            ->phase(Store::as('IS_SUBTASK', 'TASK.parent_id !== null'))
            ->phase(Operator::if('TASK.parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' → context'))

            // 1.5 Set in_progress IMMEDIATELY (all checks passed, work begins NOW)
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Started test validation", append_comment: true}'))

            ->phase('Show: Task #{id}, title, status, mode (TDD/Validation), simple={IS_SIMPLE}')

            // 2. Approval
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved', 'Ask confirmation, WAIT'))

            // 3. Context gathering (including failure history)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{TASK.title} tests", limit: 5}') . ' → ' . Store::as('MEMORY'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{TASK.title} test failed broken not working", limit: 5}') . ' ' . Store::as('KNOWN_FAILURES') . ' ← failed test approaches')
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' → check sibling comments for failed test attempts'
            ))
            ->phase(BashTool::call(BrainCLI::DOCS('{TASK keywords}')) . ' → ' . Store::as('DOCS'))
            ->phase(Operator::if('unknown testing pattern', Context7Mcp::call('query-docs', '{query: "{pattern}"}') . ' → understand first'))

            // 4. TDD MODE (pending tasks)
            ->phase(Operator::if(Store::get('IS_TDD'), [
                TaskTool::agent('explore', 'TDD TEST CREATION for #{TASK.id}: 1) Analyze requirements from docs + task.content, 2) Search memory for test patterns, 3) CREATE test files (unit, feature, integration), 4) Tests MUST fail initially (not implemented). Return: {created_tests: [{file, methods}], total_methods}.'),
                Store::as('TDD_RESULT', '{created tests}'),
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "tested", comment: "TDD MODE: Tests created.\\nFiles: {list}\\nMethods: {count}\\nNext: /task:sync or /task:async to implement.", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "TDD tests for #{TASK.id}: {summary}", category: "code-solution", tags: ["tdd"]}'),
                'Report: TDD complete, tests created, next steps. STOP.',
            ]))

            // 5. VALIDATION MODE (completed tasks)
            ->phase(Operator::if('NOT ' . Store::get('IS_TDD'), [
                // 5a. Test discovery
                TaskTool::agent('explore', 'TEST DISCOVERY for #{TASK.id} (subtask={IS_SUBTASK}): IF subtask → find test files related to task files + consumer tests ONLY. IF root → find all test files. Search: tests/, spec/. Return: [{file, type, methods, scope: "direct|consumer|all"}].') . ' → ' . Store::as('TESTS'),

                // 5b. Parallel validation (scaled to complexity)
                Operator::if(Store::get('IS_SIMPLE'), [
                    // Simple: 2 agents
                    Operator::parallel([
                        TaskTool::agent('explore', 'COVERAGE + FIX: Compare docs requirements vs tests. WRITE missing tests inline. Return: {gaps_fixed, tests_created}.'),
                        TaskTool::agent('explore', 'EXECUTION + FIX for #{TASK.id} (subtask={IS_SUBTASK}): ' . $testScopingInstruction . ' IF subtask → find and run ONLY test files related to task files + consumer tests. IF root → run full test suite. FIX failing inline. Return: {scoped, passed, failed, fixed}.'),
                    ]),
                ], [
                    // Complex: 3 agents
                    Operator::parallel([
                        TaskTool::agent('explore', 'COVERAGE + FIX: Compare docs vs tests. WRITE missing inline. Fix cosmetic. Return: {gaps_fixed, created, cosmetic_fixed}.'),
                        TaskTool::agent('explore', 'QUALITY + FIX: Check bloat (>3 mocks, >50 lines, copy-paste). REFACTOR inline. Return: {bloated_fixed, refactored}.'),
                        TaskTool::agent('explore', 'EXECUTION + FIX for #{TASK.id} (subtask={IS_SUBTASK}): ' . $testScopingInstruction . ' IF subtask → find and run ONLY test files related to task files + consumer tests. IF root → run full test suite. FIX failing/flaky inline. Return: {scoped, passed, failed, fixed}.'),
                    ]),
                ]),
                Store::as('VALIDATION_RESULT', '{aggregated from agents}'),
            ]))

            // 6. Complete (validation mode)
            ->phase(Operator::if('NOT ' . Store::get('IS_TDD'), [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "tested", comment: "Test validation PASSED. Fixes: {summary}", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "Test validation #{TASK.id}: {summary}", category: "code-solution", tags: ["test-validation"]}'),
                'Report: created={N}, fixed={N}, cosmetic={N}. STOP.',
            ]));

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('Check ID with task_list')))
            ->phase(Operator::if('invalid status', Operator::abort('Complete or reset first')))
            ->phase(Operator::if('no docs (TDD)', 'Use task.content as requirements'))
            ->phase(Operator::if('no docs (validation)', 'Validate existing tests only'))
            ->phase(Operator::if('no tests (TDD)', 'Expected - create them'))
            ->phase(Operator::if('no tests (validation)', 'Agent MUST write initial tests'))
            ->phase(Operator::if('agent fails', 'Continue with others, report partial'));

        // TEST QUALITY (inline, not separate guideline)
        $this->rule('bloat-detection')->high()
            ->text('Bloat indicators: >3 mocks, testing private methods, copy-paste >80%, >50 lines, testing getters/setters. Good: behavior not implementation, given_when_then names, <100ms execution.');
    }
}
