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
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Async vector task execution via agent delegation. Brain orchestrates with critical thinking, agents execute. Researches when ambiguous, adapts examples. Parallel when independent.')]
class TaskAsyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('task-get-first')->critical()->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze what to delegate.');
        $this->rule('no-hallucination')->critical()->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');
        $this->rule('no-verbose')->critical()->text('FORBIDDEN: <meta>, <synthesis>, <plan>, <analysis> tags. No long explanations before action.');
        $this->rule('show-progress')->high()->text('ALWAYS show brief step status and results. User must see what is happening and can interrupt/correct at any moment.');

        // DOCUMENTATION IS LAW (from trait - prevents stupid questions)
        $this->defineDocumentationIsLawRules();

        // CRITICAL THINKING FOR DELEGATION
        $this->rule('smart-delegation')->critical()->text('Brain must understand task INTENT before delegating. Agents execute, but Brain decides WHAT to delegate and HOW to split work.');
        $this->rule('research-triggers')->critical()->text('Research BEFORE delegation when ANY: 1) content <50 chars, 2) contains "example/like/similar/e.g./такий як", 3) no file paths AND no class/function names, 4) references unknown library/pattern, 5) contradicts existing code, 6) multiple valid interpretations, 7) task asks "how to" without specifics.');
        $this->rule('research-flow')->high()->text('Research order: 1) context7 for library docs, 2) web-research-master for patterns. -y flag: auto-select best approach for delegation. No -y: present options to user.');

        // FAILURE-AWARE DELEGATION (CRITICAL - prevents repeating same mistakes)
        $this->rule('failure-history-mandatory')->critical()
            ->text('BEFORE delegation: search memory category "debugging" for KNOWN FAILURES related to this task/problem. Pass failures to agents. Agents MUST NOT attempt solutions that already failed.')
            ->why('Repeating failed solutions wastes time. Memory contains "this does NOT work" knowledge.')
            ->onViolation('Search debugging memories FIRST. Include KNOWN_FAILURES in agent prompts.');
        $this->rule('sibling-task-check')->high()
            ->text('BEFORE delegation: fetch sibling tasks (same parent_id, status=completed/stopped). Check comments for what was tried and failed. Pass context to agents.')
            ->why('Previous attempts on same problem contain valuable "what not to do" information.');
        $this->rule('escalate-stuck-problems')->high()
            ->text('If task matches pattern that failed 2+ times (from memory/sibling analysis) → DO NOT delegate same approach. Research alternatives via web-research-master or escalate to user.')
            ->why('Definition of insanity: doing same thing expecting different results.');

        // ASYNC EXECUTION RULES
        $this->rule('never-execute-directly')->critical()->text('Brain NEVER calls Edit/Write/Glob/Grep/Read for implementation. ALL work via Task() to agents.');
        $this->rule('atomic-tasks')->critical()->text('Each agent task: 1-2 files (max 3-5 if same feature). NO broad changes.');
        $this->rule('parallel-when-safe')->high()->text('Parallel: independent tasks, different files, no data flow. Multiple Task() in ONE message.');

        // AUTO-APPROVE MODE (-y flag behavior for delegation)
        $this->rule('auto-approve-autonomy')->high()
            ->text('-y flag = FULL AUTONOMY. Brain delegates ALL work without asking. Auto: select agents, determine parallel vs sequential, handle agent failures, rollback on critical failure.')
            ->why('User explicitly trusts Brain to orchestrate end-to-end. Interruptions defeat async purpose.');
        $this->rule('interactive-mode')->high()
            ->text('NO -y flag = INTERACTIVE. Ask before: major architectural decisions, multiple valid approaches, selecting between incompatible agent strategies, critical failures.')
            ->why('User wants control over significant orchestration decisions.');

        // AGENT INSTRUCTION REQUIREMENTS
        $this->rule('agent-dependency-instruction')->high()
            ->text('Include in agent prompt: "If dependencies needed: detect package manager, install (composer/npm/pip/cargo/go mod). Run audit after install."')
            ->why('Agents handle their own dependency installation autonomously.');
        $this->rule('agent-git-instruction')->high()
            ->text('Include in agent prompt: "Before multi-file changes: check git status. Uncommitted changes: stash first. Rollback on failure."')
            ->why('Agents must protect user work.');
        $this->rule('agent-security-instruction')->critical()
            ->text('Include in agent prompt: "NEVER hardcode secrets. Validate external input. Escape output. Use parameterized queries."')
            ->why('Security rules must propagate to all agents.');
        $this->rule('agent-validation-instruction')->high()
            ->text('Include in agent prompt: "After changes: verify syntax, run linter if configured, run related tests. Fix issues before reporting completion."')
            ->why('Agents must validate their own work.');

        // BRAIN-LEVEL ORCHESTRATION SAFETY
        $this->rule('pre-delegation-git-check')->high()
            ->text('Before ANY delegation: check git status. Uncommitted changes exist: -y = warn and proceed, no -y = ask "Uncommitted changes. Stash before delegating?"')
            ->why('Brain ensures clean state before agents touch files.');
        $this->rule('delegation-context-include')->critical()
            ->text('Every Task() MUST include: 1) clear task description, 2) file scope, 3) memory search hints, 4) security + validation instructions.')
            ->why('Agents need full context to work autonomously.');

        // PARTIAL FAILURE HANDLING (multi-agent)
        $this->rule('agent-failure-isolation')->high()
            ->text('Agent fails: other parallel agents continue. Failed agent work: -y = auto-rollback its files, no -y = ask "Agent X failed. Rollback its changes/Retry/Skip?"');
        $this->rule('critical-agent-failure')->high()
            ->text('Critical agent (blocker for others) fails: -y = abort remaining + rollback all, no -y = ask "Critical task failed. Abort all/Retry/Manual intervention?"');
        $this->rule('partial-success-handling')->medium()
            ->text('N of M agents succeeded: -y = complete with warning listing failed parts, no -y = ask "N/M succeeded. Complete partial/Rollback all/Retry failed?"');

        // RETRY & TIMEOUT FOR AGENTS
        $this->rule('agent-retry-limit')->high()
            ->text('Agent timeout or failure: max 2 retries with same agent. Still fails: try alternative agent if applicable. After all retries: mark subtask failed.');
        $this->rule('agent-timeout')->medium()
            ->text('Agent execution timeout: 300s for implementation, 120s for research, 60s for validation. Timeout exceeded: cancel, retry or skip.');

        // SESSION RECOVERY (async-specific)
        $this->rule('session-recovery-detection')->high()
            ->text('Task status=in_progress: check task.comment for delegation state. Has agent_tasks with pending/running: crashed session. No state OR >1h old: stale session.');
        $this->rule('session-recovery-action')->high()
            ->text('Crashed session: -y = check agent results, continue remaining, no -y = ask "Crashed session. Check agent results/Restart all?" Stale: reset to pending.');

        // SUBTASKS HANDLING (async-specific)
        $this->rule('subtasks-parallel-assessment')->high()
            ->text('Parent task with subtasks: analyze dependencies. Independent subtasks: delegate in parallel. Dependent: delegate in order. -y = auto-decide, no -y = show plan.');
        $this->rule('subtasks-agent-assignment')->medium()
            ->text('Each subtask gets dedicated agent delegation. Track: {subtask_id, agent, status, files_touched}. Update parent progress.');

        // BREAKING CHANGES (via agents)
        $this->rule('breaking-change-detection')->high()
            ->text('Include in agent prompt for refactoring tasks: "Flag breaking changes (API signature, removed exports, changed types). Report in completion summary."');
        $this->rule('breaking-change-action')->high()
            ->text('Agent reports breaking change: -y = accept with deprecation notice, update callers via another agent. No -y = ask "Breaking change reported. Proceed/Modify/Abort?"');

        // FAILURE LEARNING
        $this->rule('failure-memory')->medium()
            ->text('On delegation failure: store to memory with category "debugging". Content: task summary, agent used, failure reason, partial results. Helps future orchestration.');

        // RESULT AGGREGATION
        $this->rule('aggregate-results')->high()
            ->text('After all agents complete: aggregate results. Verify: no conflicts between agent changes, all expected files modified, no orphaned changes.');
        $this->rule('conflict-resolution')->high()
            ->text('Agents modified same file (conflict): -y = merge if possible, prefer later change. No -y = ask "Conflict in {file}. Show diff/Prefer agent A/Prefer agent B?"');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task (ALWAYS FIRST)
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))
            ->phase(Operator::if('status=completed', [
                Operator::if('$HAS_AUTO_APPROVE', Operator::abort('Already completed. Use different task ID.')),
                'ask "Re-execute completed task?"',
            ]))
            ->phase(Operator::if('status=in_progress', [
                'Parse task.comment for delegation_state JSON',
                Operator::if('has agent_tasks with pending/running AND timestamp <1h', [
                    Store::as('IS_CRASHED_SESSION', 'true'),
                    Operator::if('$HAS_AUTO_APPROVE', 'Check agent results, continue remaining delegations'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Crashed session. Check results/Restart all?"'),
                ]),
                Operator::if('no state OR timestamp >1h', [
                    'Stale session detected',
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Stale session reset", append_comment: true}'),
                ]),
            ]))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode → jump to tdd-mode guideline'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' ' . Store::as('PARENT') . ' (READ-ONLY context)'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' ' . Store::as('SUBTASKS'))

            // 1.5 Subtasks check
            ->phase(Operator::if(Store::get('SUBTASKS') . ' has pending items', [
                'Analyze subtask dependencies for parallel/sequential execution',
                Store::as('INDEPENDENT_SUBTASKS', 'subtasks with no blockedBy'),
                Store::as('DEPENDENT_SUBTASKS', 'subtasks with blockedBy'),
                Operator::if('$HAS_AUTO_APPROVE', 'Auto-execute: parallel for independent, sequential for dependent'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Has N subtasks. Execute parallel where possible?"'),
            ]))

            // 2. Fast-path check (simple tasks skip research)
            ->phase(Store::as('IS_SIMPLE', 'task.content >=50 chars AND has specific file/class/function AND no "example/like/similar" AND single clear interpretation'))
            ->phase(Operator::if(Store::get('IS_SIMPLE'), 'SKIP to step 4 (Context gathering)'))

            // 3. Research (ONLY if triggers matched)
            ->phase(Store::as('NEEDS_RESEARCH', 'ANY: content <50 chars, contains "example/like/similar/e.g./такий як/як у", no paths AND no class names, unknown lib/pattern, contradicts code, ambiguous, "how to" without specifics'))
            ->phase(Operator::if(Store::get('NEEDS_RESEARCH'), [
                '3.1: ' . Context7Mcp::call('resolve-library-id', '{libraryName: "{detected_lib}"}') . ' → IF library mentioned',
                '3.2: ' . Context7Mcp::call('query-docs', '{query: "{task question}"}') . ' → get docs',
                '3.3: IF context7 insufficient → ' . TaskTool::agent('web-research-master', 'Research: {task.title}. Find: implementation patterns, best practices.'),
                Store::as('RESEARCH_OPTIONS', '[{option, source, pros, cons}]'),
            ]))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND $HAS_AUTO_APPROVE', 'Auto-select BEST approach for delegation'))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND NOT $HAS_AUTO_APPROVE', 'Present: "Found N approaches: 1)... 2)... Which? (or your variant)"'))

            // 4. Context gathering (memory + local docs + FAILURES)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "code-solution"}') . ' ' . Store::as('MEMORY'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{task.title} {problem keywords} failed error not working broken", limit: 5}') . ' ' . Store::as('KNOWN_FAILURES') . ' ← CRITICAL: what already FAILED (search by failure keywords, not category)')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 3}') . ' ' . Store::as('RELATED'))
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                [
                    VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' ' . Store::as('SIBLING_TASKS'),
                    // CRITICAL: Search vector memory for EACH sibling task to get stored insights/failures
                    Operator::forEach('sibling in ' . Store::get('SIBLING_TASKS'), [
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title}", limit: 3}') . ' → ALL memories for this sibling (failures, solutions, insights)',
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title} failed error not working", limit: 3}') . ' → specifically failure-related memories',
                        'Append results to ' . Store::as('SIBLING_MEMORIES'),
                    ]),
                    'Extract from siblings comments + ' . Store::get('SIBLING_MEMORIES') . ': what was tried, what failed, what worked',
                    Store::as('FAILURE_PATTERNS', 'solutions that were tried and failed (from sibling comments + sibling memories)'),
                ]
            ))
            ->phase(Operator::if(
                Store::get('KNOWN_FAILURES') . ' OR ' . Store::get('FAILURE_PATTERNS') . ' not empty',
                [
                    Store::as('BLOCKED_APPROACHES', Store::get('KNOWN_FAILURES') . ' + ' . Store::get('FAILURE_PATTERNS')),
                    'If planned delegation uses blocked approach → STOP, research alternative or escalate',
                    'Pass BLOCKED_APPROACHES to ALL agents in their prompts',
                ]
            ))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords}')) . ' ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if(Store::get('DOCS_INDEX') . ' found', [
                TaskTool::agent('explore', 'Read docs: {doc.paths}. Return full content.') . ' → ' . Store::as('DOCS_CONTENT'),
                'DOCS_CONTENT = COMPLETE specification. Pass to ALL agents. Documentation > task.content.',
            ]))

            // 4.5 Pre-delegation git check
            ->phase(BashTool::call('git status --porcelain 2>/dev/null || echo "NO_GIT"') . ' ' . Store::as('GIT_STATUS'))
            ->phase(Operator::if(Store::get('GIT_STATUS') . ' has uncommitted changes', [
                Operator::if('$HAS_AUTO_APPROVE', 'WARN: uncommitted changes, proceeding with delegation'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Uncommitted changes. Stash before delegating/Proceed anyway/Abort?"'),
            ]))

            // 5. Plan & Approval
            ->phase('Analyze task INTENT → break into atomic agent subtasks')
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Planning delegation: 1) What is the INTENT? 2) Which agents? 3) Parallel or sequential? 4) File scope per agent? 5) What instructions for security/validation?",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase(Store::as('PLAN', '[{agent, subtask, files, parallel: bool, order, is_critical: bool}]'))
            ->phase('Each agent prompt MUST include: task description, file scope, memory hints, security rules, validation requirements')
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'execute immediately', 'show plan, wait "yes"'))
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Delegating to agents...", append_comment: true}'))
            ->phase(Store::as('DELEGATION_STATE', '{agent_tasks: [], started_at: timestamp}'))

            // 6. Execute via agents with tracking
            ->phase('6.1 PARALLEL: Independent tasks → multiple ' . TaskTool::agent('{agent}', '{subtask + security + validation instructions}') . ' in ONE message')
            ->phase('6.2 SEQUENTIAL: Dependent tasks → one by one, wait for result before next')
            ->phase('Track each delegation: {agent, status, result, files_touched, errors}')
            ->phase(Operator::if('agent fails', [
                'Retry up to 2 times with same agent',
                Operator::if('still fails AND alternative agent exists', 'Try alternative agent'),
                Operator::if('max retries AND is_critical', [
                    Operator::if('$HAS_AUTO_APPROVE', [
                        'Abort remaining delegations',
                        'Request rollback from completed agents',
                        VectorTaskMcp::call('task_update', '{status: "pending", comment: "Critical agent failed: {error}. Rolled back."}'),
                        VectorMemoryMcp::call('store_memory', '{content: "FAILURE: Task #{id}, agent: {name}, error: {msg}", category: "debugging"}'),
                        Operator::abort('Critical agent failed'),
                    ]),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Critical task failed. Abort all/Retry/Manual?"'),
                ]),
                Operator::if('max retries AND NOT is_critical', [
                    'Mark subtask as failed, continue others',
                    'Update delegation_state in task.comment',
                ]),
            ]))
            ->phase('Update task.comment with delegation_state for recovery')

            // 7. Aggregate & validate results
            ->phase('Collect all agent results')
            ->phase('Check for conflicts: multiple agents modified same file')
            ->phase(Operator::if('conflict detected', [
                Operator::if('$HAS_AUTO_APPROVE', 'Merge if possible, prefer later change, WARN'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Conflict in {file}. Show diff/Prefer A/Prefer B?"'),
            ]))
            ->phase('Verify: all expected files modified, no orphaned changes')
            ->phase(Store::as('AGENT_RESULTS', '{succeeded: N, failed: M, files: [...], conflicts: [...]}'))

            // 8. Handle partial success
            ->phase(Operator::if('some agents failed', [
                Operator::if('$HAS_AUTO_APPROVE AND >80% succeeded', [
                    VectorTaskMcp::call('task_update', '{status: "completed", comment: "Partial success: {succeeded}/{total}. Failed: {list}", append_comment: true}'),
                ]),
                Operator::if('$HAS_AUTO_APPROVE AND <=80% succeeded', [
                    VectorTaskMcp::call('task_update', '{status: "pending", comment: "Too many failures: {failed}/{total}", append_comment: true}'),
                    Operator::abort('Too many agent failures'),
                ]),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "N/M succeeded. Complete partial/Rollback all/Retry failed?"'),
            ]))

            // 9. Complete
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Done. Agents: {list}. Files: {files}.", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: delegation strategy, agents used: {list}, learnings: {summary}", category: "code-solution"}'));

        // Agent reference
        $this->guideline('agents')
            ->text('explore = code exploration, file analysis, implementation')
            ->text('web-research-master = external research, best practices')
            ->text('documentation-master = docs research, API documentation')
            ->text('commit-master = git operations, commits')
            ->text('script-master = Laravel scripts, commands')
            ->text('prompt-master = Brain component generation');

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase(Operator::if('task.comment contains "TDD MODE" AND status=tested', 'Execute implementation via agents based on task.content'))
            ->phase('Delegate implementation to agent with TDD context: "Tests exist at {path}. Implement to make tests pass."')
            ->phase('After implementation → ' . TaskTool::agent('explore', 'Run tests. Detect framework (jest, pytest, phpunit, pest, cargo test, go test). Report pass/fail.'))
            ->phase(Operator::if('all tests pass', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "TDD: All tests PASSED", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "TDD success: {feature}, delegation strategy: {summary}", category: "code-solution"}'),
            ]))
            ->phase(Operator::if('tests fail', [
                'Analyze failure from agent report',
                'Delegate fix to same agent with failure context (max 5 iterations)',
                Operator::if('still failing after 5 iterations', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "TDD stuck: {failing_tests}. Need guidance.", append_comment: true}'),
                    Operator::if('$HAS_AUTO_APPROVE', Operator::abort('TDD: Cannot pass tests after 5 iterations')),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Cannot pass tests via agents. Show failures for manual review?"'),
                ]),
            ]));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list or task_create')))
            ->phase(Operator::if('task already completed AND -y', Operator::abort('Already completed')))
            ->phase(Operator::if('task already completed AND no -y', 'ask "Re-execute completed task?"'))
            ->phase(Operator::if('research triggers matched but context7 empty AND web-research empty', [
                Operator::if('$HAS_AUTO_APPROVE', 'Proceed with best-effort delegation based on existing patterns'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'Ask user for clarification with specific questions'),
            ]))
            ->phase(Operator::if('multiple research options, user chose "other"', 'Ask for details, incorporate into delegation plan'))
            ->phase(Operator::if('agent timeout', [
                'Cancel agent, retry up to 2 times',
                Operator::if('still timeout', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Skip with warning, continue other agents'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Agent {name} timed out. Retry/Skip/Abort?"'),
                ]),
            ]))
            ->phase(Operator::if('agent returns invalid result', [
                'Validate: has expected output, files touched, no errors',
                Operator::if('invalid', 'Retry with clearer instructions (max 2)'),
                Operator::if('still invalid', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Mark subtask failed, continue others'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Agent returned invalid result. Show/Retry/Skip?"'),
                ]),
            ]))
            ->phase(Operator::if('conflict between agent results', [
                'Analyze: same file modified differently',
                Operator::if('$HAS_AUTO_APPROVE', 'Attempt merge, prefer later change if conflict'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Conflict in {file}. Show diff/Merge/Prefer A/Prefer B?"'),
            ]))
            ->phase(Operator::if('agent reports breaking change', [
                Operator::if('$HAS_AUTO_APPROVE', [
                    'Accept change',
                    'Delegate update-callers task to another agent',
                    'Add deprecation notice in code comment',
                ]),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Agent reports breaking change: {details}. Proceed/Modify/Abort?"'),
            ]))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild delegation plan, re-present'));

        // Agent instruction template
        $this->guideline('agent-instruction-template')
            ->text('Every Task() delegation MUST include these sections:')
            ->text('1. TASK: Clear description of what to do')
            ->text('2. FILES: Specific file scope (1-2 files, max 3-5 for feature)')
            ->text('3. DOCUMENTATION: "If docs exist: {$DOCS_CONTENT}. Documentation = COMPLETE spec. task.content may be summary. Follow DOCS."')
            ->text('4. BLOCKED APPROACHES: "KNOWN FAILURES (DO NOT USE): {$BLOCKED_APPROACHES}. If your solution matches - find alternative."')
            ->text('5. MEMORY: "Search memory for: {terms}. Check debugging category for failures. Store learnings after."')
            ->text('6. SECURITY: "No hardcoded secrets. Validate input. Escape output. Parameterized queries."')
            ->text('7. VALIDATION: "Verify syntax. Run linter if configured. Run related tests. Fix before completion."')
            ->text('8. GIT: "Check git status. Stash uncommitted. Rollback on failure."')
            ->text('9. DEPS: "If dependencies needed: detect package manager, install, run audit."');
    }
}