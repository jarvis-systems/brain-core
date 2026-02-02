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
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Validate completed vector task. 3 parallel agents: Completion, Code Quality, Testing. Creates fix-tasks for functional issues. Cosmetic fixed inline by agents.')]
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

            // 3.5 Pre-validation analysis
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Analyzing task requirements for validation. Parsing task.content to extract: explicit requirements, acceptance criteria, affected files, expected behaviors.",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))

            // 4. Validate (3 parallel agents) - TASK SCOPE ONLY
            ->phase(Operator::parallel([
                TaskTool::agent('explore', 'COMPLETION CHECK: Parse task.content → list requirements → verify each done. Check ONLY task files. Detect garbage (unused imports, dead code). Fix cosmetic inline. Return: missing requirements, garbage.'),
                TaskTool::agent('explore', 'CODE QUALITY: Task scope only. Check: logic, security, architecture, breaking changes. Run quality gates. Unknown lib → context7. Fix cosmetic inline. Return: functional issues.'),
                TaskTool::agent('explore', 'TESTING: Task scope only. Check: tests exist (coverage >=80%), tests pass, edge cases. Slow tests (unit >500ms, integration >2s) = issue. Unknown pattern → context7. Return: missing/failing/slow tests.'),
            ]))

            // 5. Finalize (IRON LAW: fix-task created = "pending" ALWAYS. MCP will reset status anyway when child starts. NO "validated" with children.)
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Merging validation agent results. Analyzing: issue severity, duplicates, false positives, fix priority, task scope compliance.",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase('Merge agent results ' . Store::as('ISSUES') . ' categorize: Critical/Major/Minor')
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
            ->phase(Operator::if('agent fails', 'retry', 'continue with remaining agents'))
            ->phase(Operator::if('fix-task creation fails', 'store to memory for manual review'))
            ->phase(Operator::if('user rejects validation', 'accept modifications, re-validate'));
    }
}
