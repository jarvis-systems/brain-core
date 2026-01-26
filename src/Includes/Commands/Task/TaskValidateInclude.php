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

#[Purpose('Quality-first vector task validation. 3 parallel agents validate: Code Quality (completeness, architecture, security, performance, consistency), Testing (coverage, quality, edge cases, consistency), Documentation (sync, API, docblocks, dependencies, consistency). Creates fix-tasks for issues. Cosmetic fixes applied inline.')]
class TaskValidateInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // =========================================================================
        // CORE RULES (9 essential rules)
        // =========================================================================

        $this->rule('always-execute')->critical()
            ->text('ALWAYS execute FULL validation when command is invoked. NEVER skip based on current status, child statuses, or previous validation results.')
            ->why('User invoked command = user wants validation. LLM must not "optimize" by skipping. Re-validation catches regressions and hallucinations.')
            ->onViolation('Execute full validation. Status "validated" means RE-validate, not skip.');

        $this->rule('auto-approve-not-skip')->critical()
            ->text('Flag -y (auto-approve) means "skip approval prompt", NOT "skip validation". Full validation MUST execute.')
            ->why('Auto-approve saves user interaction time, not validation time.')
            ->onViolation('Execute full validation even with -y flag.');

        $this->rule('vector-task-id-required')->critical()
            ->text('Input MUST be valid vector task ID. Formats: "15", "#15", "task 15", "task:15".')
            ->why('This command validates vector tasks only.')
            ->onViolation('STOP. Report: "Use /do:validate for text-based validation."');

        $this->rule('validatable-status')->critical()
            ->text('Only tasks with status "completed", "tested", or "validated" can be validated.')
            ->why('Validation audits finished work.')
            ->onViolation('Report: "Task #{id} has status {status}. Complete via /task:async first."');

        $this->rule('no-direct-fixes')->critical()
            ->text('NEVER fix functional issues directly. Create tasks for: code logic, architecture, security, missing features.')
            ->why('Traceability. All code changes tracked via task system.')
            ->onViolation('Create task instead of fixing.');

        $this->rule('cosmetic-inline-fix')->critical()
            ->text('Cosmetic issues (whitespace, indentation, typos, formatting) MUST be fixed IMMEDIATELY by discovering agent. No tasks for cosmetic.')
            ->why('Cosmetic fixes are trivial. Creating tasks wastes tokens and context.')
            ->onViolation('Agent fixes cosmetic inline, reports count in results.');

        $this->rule('idempotent')->high()
            ->text('Validation is idempotent. Check existing fix-tasks before creating. No duplicates.')
            ->why('Safe re-runs without side effects.')
            ->onViolation('Search existing tasks, skip duplicates.');

        $this->rule('memory-mandatory')->high()
            ->text('Agents MUST search memory BEFORE validation AND store findings AFTER.')
            ->why('Knowledge sharing between agents.')
            ->onViolation('Include memory instructions in agent delegation.');

        $this->rule('fix-task-hierarchy')->high()
            ->text('Fix tasks MUST have parent_id = validated task ID.')
            ->why('Maintains hierarchy: validated task → fix subtasks.')
            ->onViolation('Set parent_id when creating fix tasks.');

        // =========================================================================
        // INPUT CAPTURE
        // =========================================================================

        $this->defineInputCaptureGuideline();

        // =========================================================================
        // STAGE 1: LOAD (Task + Context)
        // =========================================================================

        $this->guideline('stage1-load')
            ->goal('Load vector task, verify status, gather initial context')
            ->example()
            ->phase(Operator::output([
                '=== VALIDATION STARTED ===',
                '',
                '## STAGE 1: LOADING',
            ]))
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('TASK', '{id, title, content, status, parent_id, priority, tags, estimate}'))
            ->phase(Operator::if('$TASK not found', [
                Operator::report('Task #$VECTOR_TASK_ID not found'),
                'ABORT',
            ]))
            ->phase(Operator::if('$TASK.status NOT IN ["completed", "tested", "validated", "in_progress"]', [
                Operator::report('Task has status: $TASK.status. Complete via /task:async first.'),
                'ABORT',
            ]))
            ->phase(Operator::if('$TASK.status === "in_progress"', [
                'Check status_history for crash recovery',
                Operator::if('last_entry.to === null', [
                    Store::as('IS_RECOVERY', 'true'),
                    Operator::output(['⚠️ Session recovery mode. Verifying previous work.']),
                ]),
                Operator::if('last_entry.to !== null', [
                    Operator::report('Task in progress by another session.'),
                    'ABORT',
                ]),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 20}'))
            ->phase(Store::as('SUBTASKS', '{existing subtasks}'))
            ->phase(Operator::if('$TASK.parent_id', [
                VectorTaskMcp::call('task_get', '{task_id: $TASK.parent_id}'),
                Store::as('PARENT', '{parent task context}'),
            ]))
            ->phase(Operator::output([
                'Task: #{$TASK.id} - {$TASK.title}',
                'Status: {$TASK.status} | Priority: {$TASK.priority}',
                'Subtasks: {$SUBTASKS.count} | Parent: {$PARENT.title or "none"}',
            ]));

        // =========================================================================
        // STAGE 2: CONTEXT (Memory + Docs)
        // =========================================================================

        $this->guideline('stage2-context')
            ->goal('Gather requirements from memory and documentation')
            ->example()
            ->phase(Operator::output(['', '## STAGE 2: CONTEXT']))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "$TASK.title $TASK.content", limit: 10}'))
            ->phase(Store::as('MEMORY', '{related implementations, patterns, past validations}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK}'), 'Get documentation index'))
            ->phase(Store::as('DOCS_INDEX', '{documentation file paths}'))
            ->phase(Operator::if('$DOCS_INDEX not empty', [
                'Read documentation files',
                Store::as('REQUIREMENTS', '{extracted requirements, acceptance criteria}'),
            ]))
            ->phase(Operator::if('$DOCS_INDEX empty', [
                Operator::output(['⚠️ No documentation found. Code-only validation.']),
                Store::as('REQUIREMENTS', '[]'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{query: "$TASK.title", limit: 10}'))
            ->phase(Store::as('RELATED_TASKS', '{semantically related tasks}'))
            ->phase(Operator::output([
                'Memory insights: {$MEMORY.count}',
                'Documentation files: {$DOCS_INDEX.count}',
                'Related tasks: {$RELATED_TASKS.count}',
            ]));

        // =========================================================================
        // STAGE 3: APPROVAL
        // =========================================================================

        $this->guideline('stage3-approval')
            ->goal('Present validation scope, get user approval')
            ->example()
            ->phase(Operator::output(['', '## STAGE 3: APPROVAL']))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents'))
            ->phase(Store::as('AGENTS', '{available validation agents}'))
            ->phase(Operator::output([
                'Validation scope:',
                '- Task: #{$TASK.id} - {$TASK.title}',
                '- Requirements: {$REQUIREMENTS.count}',
                '- 3 parallel agents: Code Quality, Testing, Documentation',
                '',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['✅ Auto-approved (-y flag)']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output(['Type "yes" to start validation, "no" to abort.']),
                'WAIT for approval',
                Operator::verify('User approved'),
            ]))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $TASK.id, status: "in_progress", comment: "Validation started", append_comment: true}'));

        // =========================================================================
        // STAGE 4: VALIDATE (3 Parallel Agents)
        // =========================================================================

        $this->guideline('stage4-validate')
            ->goal('Launch 3 parallel validation agents, aggregate results')
            ->example()
            ->phase(Operator::output(['', '## STAGE 4: VALIDATION (3 agents parallel)']))
            ->phase('Launch 3 agents in PARALLEL:')
            ->phase(Operator::do([
                // Agent 1: Code Quality
                TaskTool::agent('explore',
                    'CODE QUALITY VALIDATION for task #{$TASK.id} "{$TASK.title}":

VALIDATE:
1. COMPLETENESS - All requirements from {$REQUIREMENTS} implemented
2. ARCHITECTURE - SOLID principles, correct patterns, no anti-patterns
3. SECURITY - Input validation, SQL injection, XSS, OWASP top 10
4. PERFORMANCE - N+1 queries, memory leaks, complexity issues
5. CODE CONSISTENCY - Naming conventions, style patterns across files

COSMETIC RULE: Whitespace/indentation issues → FIX IMMEDIATELY with Edit tool. Do NOT report as findings.

RETURN: {
  completeness: [{requirement_id, status: implemented|partial|missing, evidence: file:line}],
  architecture: [{issue, severity, file:line, suggestion}],
  security: [{vulnerability, severity, file:line, fix}],
  performance: [{issue, severity, file:line, optimization}],
  consistency: [{pattern_violation, files_affected, suggestion}],
  cosmetic_fixes_applied: N
}

Store findings to vector memory.'),

                // Agent 2: Testing
                TaskTool::agent('explore',
                    'TESTING VALIDATION for task #{$TASK.id} "{$TASK.title}":

VALIDATE:
1. COVERAGE - All code paths have tests, no untested scenarios
2. TEST QUALITY - Meaningful assertions, proper mocks, isolation
3. EDGE CASES - Boundary conditions, null handling, error paths tested
4. ERROR HANDLING - Exceptions caught, fallbacks work, graceful degradation
5. TEST CONSISTENCY - Naming patterns, structure, setup/teardown across test files

COSMETIC RULE: Whitespace/formatting in tests → FIX IMMEDIATELY with Edit tool.

RUN TESTS if possible: Bash("php artisan test" or "vendor/bin/phpunit")

RETURN: {
  coverage: [{file, coverage_status, missing_scenarios}],
  quality: [{test_file, issue, suggestion}],
  edge_cases: [{missing_case, file, severity}],
  error_handling: [{gap, file:line, fix}],
  consistency: [{pattern_violation, test_files, suggestion}],
  test_results: {passed, failed, errors},
  cosmetic_fixes_applied: N
}

Store findings to vector memory.'),

                // Agent 3: Documentation
                TaskTool::agent('explore',
                    'DOCUMENTATION VALIDATION for task #{$TASK.id} "{$TASK.title}":

VALIDATE:
1. DOCS SYNC - Code matches documentation, no stale docs
2. API DOCS - Endpoints documented, params, responses, examples
3. DOCBLOCKS - PHPDoc complete, types correct, descriptions meaningful
4. DEPENDENCIES - Imports clean, composer.json accurate, no unused packages
5. DOCS CONSISTENCY - Style, format, terminology consistent across docs

COSMETIC RULE: Typos, formatting issues in docs → FIX IMMEDIATELY with Edit tool.

RETURN: {
  sync: [{doc_file, sync_status, gaps}],
  api: [{endpoint, documented: bool, missing_info}],
  docblocks: [{file, missing_docs, incomplete_docs}],
  dependencies: [{issue, file, severity}],
  consistency: [{style_issue, files, suggestion}],
  cosmetic_fixes_applied: N
}

Store findings to vector memory.'),
            ]))
            ->phase(Store::as('AGENT_RESULTS', '{results from all 3 agents}'))
            ->phase(Operator::output(['3 validation agents completed']));

        // =========================================================================
        // STAGE 5: FINALIZE (Aggregate + Tasks + Status)
        // =========================================================================

        $this->guideline('stage5-finalize')
            ->goal('Aggregate results, create fix tasks, update status, store to memory')
            ->example()
            ->phase(Operator::output(['', '## STAGE 5: FINALIZE']))
            ->phase('Merge results from all agents:')
            ->phase(Store::as('ALL_ISSUES', '{merged issues from AGENT_RESULTS}'))
            ->phase(Store::as('COSMETIC_FIXED', '{sum of cosmetic_fixes_applied}'))
            ->phase('Categorize by severity:')
            ->phase(Store::as('CRITICAL', '{security, architecture, missing core requirements}'))
            ->phase(Store::as('MAJOR', '{functionality, test coverage, performance}'))
            ->phase(Store::as('MINOR', '{consistency, edge cases, minor gaps}'))
            ->phase(Operator::output([
                'Results:',
                '- Critical: {$CRITICAL.count}',
                '- Major: {$MAJOR.count}',
                '- Minor: {$MINOR.count}',
                '- Cosmetic fixed inline: {$COSMETIC_FIXED}',
            ]))
            ->phase('Check existing fix tasks to avoid duplicates:')
            ->phase(VectorTaskMcp::call('task_list', '{query: "fix $TASK.title", parent_id: $TASK.id, limit: 20}'))
            ->phase(Store::as('EXISTING_FIX_TASKS', '{existing fix tasks}'))
            ->phase(Operator::if('$ALL_ISSUES.count === 0', [
                Operator::output(['✅ No issues found. Validation PASSED.']),
                VectorTaskMcp::call('task_update', '{task_id: $TASK.id, status: "validated", comment: "Validation PASSED. No issues found.", append_comment: true}'),
            ]))
            ->phase(Operator::if('$ALL_ISSUES.count > 0', [
                'Create consolidated fix task (5-8h batch):',
                Operator::if('NOT exists similar in $EXISTING_FIX_TASKS', [
                    VectorTaskMcp::call('task_create', '{
                        title: "Validation fixes: #{$TASK.id}",
                        content: "Fix issues found during validation of task #{$TASK.id}: {$TASK.title}\\n\\n## Critical ({$CRITICAL.count})\\n{list with file:line, description, suggestion}\\n\\n## Major ({$MAJOR.count})\\n{list}\\n\\n## Minor ({$MINOR.count})\\n{list}\\n\\n## Context\\n- Memory IDs: {relevant_ids}\\n- Related tasks: {$RELATED_TASKS.ids}",
                        priority: "{$CRITICAL.count > 0 ? high : medium}",
                        estimate: {calculated_hours},
                        tags: ["validation-fix"],
                        parent_id: $TASK.id
                    }'),
                    Store::as('FIX_TASK_ID', '{created task id}'),
                ]),
                VectorTaskMcp::call('task_update', '{task_id: $TASK.id, status: "pending", comment: "Validation found {$ALL_ISSUES.count} issues. Fix task #{$FIX_TASK_ID} created.", append_comment: true}'),
                Operator::output(['⚠️ Issues found. Task returned to pending. Fix task created.']),
            ]))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Validation #{$TASK.id}: {$TASK.title}\\nResult: {PASSED/NEEDS_WORK}\\nCritical: {$CRITICAL.count}, Major: {$MAJOR.count}, Minor: {$MINOR.count}\\nCosmetic fixed: {$COSMETIC_FIXED}\\nFix task: #{$FIX_TASK_ID or none}", category: "code-solution", tags: ["validation", "quality"]}'))
            ->phase(Operator::output([
                '',
                '=== VALIDATION REPORT ===',
                'Task: #{$TASK.id} - {$TASK.title}',
                '',
                '| Category | Count |',
                '|----------|-------|',
                '| Critical | {$CRITICAL.count} |',
                '| Major | {$MAJOR.count} |',
                '| Minor | {$MINOR.count} |',
                '| Cosmetic (fixed) | {$COSMETIC_FIXED} |',
                '',
                'Status: {validated | pending}',
                '{IF fix task: "Fix task: #{$FIX_TASK_ID}"}',
            ]));

        // =========================================================================
        // ERROR HANDLING
        // =========================================================================

        $this->guideline('error-handling')
            ->text('Graceful error recovery')
            ->example()
            ->phase()->if('task not found', ['Report error', 'Suggest task_list', 'ABORT'])
            ->phase()->if('agent fails', ['Log error', 'Continue with remaining agents', 'Report partial results'])
            ->phase()->if('task creation fails', ['Store to memory for manual review', 'Continue']);
    }
}
