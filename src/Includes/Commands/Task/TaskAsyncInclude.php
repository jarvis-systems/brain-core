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

#[Purpose('Defines the task:async command protocol for executing vector tasks via multi-agent orchestration. Accepts task ID reference (formats: "15", "#15", "task 15"), loads task context, and executes with flexible modes, user approval gates, and vector memory integration.')]
class TaskAsyncInclude extends IncludeArchetype
{
    protected function defineRules(): void
    {
        // ABSOLUTE FIRST - BLOCKING ENTRY RULE
        $this->rule('entry-point-blocking')->critical()
            ->text('ON RECEIVING $RAW_INPUT: Your FIRST output MUST be "=== TASK:ASYNC ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION. FORBIDDEN first actions: Glob, Grep, Read, Edit, Write, WebSearch, WebFetch, Bash (except brain list:masters), code generation, file analysis, problem solving, implementation thinking.')
            ->why('Without explicit entry point, Brain skips workflow and executes directly. Entry point forces workflow compliance.')
            ->onViolation('STOP IMMEDIATELY. Delete any tool calls. Output "=== TASK:ASYNC ACTIVATED ===" and restart from Phase 0.');

        // Iron Rules - Zero Tolerance
        $this->rule('zero-distractions')->critical()
            ->text('ZERO distractions - implement ONLY specified task from vector task content. NO creative additions, NO unapproved features, NO scope creep.')
            ->why('Ensures focused execution and prevents feature drift')
            ->onViolation('Abort immediately. Return to approved plan.');

        $this->rule('approval-gates-mandatory')->critical()
            ->text('User approval REQUIRED at Requirements Analysis gate and Execution Planning gate. NEVER proceed without explicit confirmation. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve all gates (skip user confirmation prompts).')
            ->why('Maintains user control and prevents unauthorized execution. Flag -y enables automated/scripted execution.')
            ->onViolation('STOP. Wait for user approval before continuing (unless $HAS_AUTO_APPROVE is true).');

        $this->rule('atomic-tasks-only')->critical()
            ->text('Each agent task MUST be small and focused: maximum 1-2 files per agent invocation. NO large multi-file changes.')
            ->why('Prevents complexity, improves reliability, enables precise tracking')
            ->onViolation('Break task into smaller pieces. Re-plan with atomic steps.');

        $this->rule('no-improvisation')->critical()
            ->text('Execute ONLY approved plan steps. NO improvisation, NO "while we\'re here" additions, NO proactive suggestions during execution.')
            ->why('Maintains plan integrity and predictability')
            ->onViolation('Revert to last approved checkpoint. Resume approved steps only.');

        $this->rule('execution-mode-flexible')->high()
            ->text('Execute agents sequentially BY DEFAULT. Allow PARALLEL execution when: 1) tasks are simple and independent (no file/context conflicts), 2) each task touches different files, 3) no data flow between tasks. PARALLEL means multiple Task() calls in SINGLE message - NOT run_in_background. Brain waits for ALL parallel agents to complete before proceeding.')
            ->why('Simple independent tasks benefit from parallel execution. Synchronous parallel (not background) ensures Brain receives all results before next phase.')
            ->onViolation('Validate task independence before parallel execution. Fallback to sequential if ANY conflict detected.');

        $this->rule('vector-memory-mandatory')->high()
            ->text('ALL agents MUST search vector memory BEFORE task execution AND store learnings AFTER completion. Vector memory is the primary communication channel between sequential agents.')
            ->why('Enables knowledge sharing between agents, prevents duplicate work, maintains execution continuity across steps')
            ->onViolation('Include explicit vector memory instructions in agent Task() delegation.');

        $this->rule('conversation-context-awareness')->high()
            ->text('ALWAYS analyze conversation context BEFORE planning. User may have discussed requirements, constraints, preferences, or decisions in previous messages.')
            ->why('Prevents ignoring critical information already provided by user in conversation')
            ->onViolation('Review conversation history before proceeding with task analysis.');

        $this->rule('session-recovery-via-history')->high()
            ->text('If task status is "in_progress", check status_history. If last entry has "to: null" - previous session crashed mid-execution. Can RESUME execution WITHOUT changing status (already in_progress). Treat vector memory findings from crashed session with caution - previous context is lost. Execution stage is unknown - may need to verify what was completed.')
            ->why('Prevents blocking on crashed sessions. Allows recovery while maintaining awareness that previous work may be incomplete.')
            ->onViolation('Check status_history before blocking. If to:null found, proceed with recovery mode.');

        $this->rule('vector-task-id-required')->critical()
            ->text('$TASK_ID MUST be a valid vector task ID reference. Valid formats: "15", "#15", "task 15", "task:15", "task-15". If not a valid task ID, abort and suggest /do:async for text-based tasks.')
            ->why('This command is exclusively for vector task execution. Text descriptions belong to /do:async.')
            ->onViolation('STOP. Report: "Invalid task ID. Use /do:async for text-based tasks or provide valid task ID."');

        $this->rule('full-workflow-mandatory')->critical()
            ->text('ALL requests MUST follow complete workflow: Phase 0 (Task Load) → Phase 1 (Discovery) → Phase 2 (Requirements + APPROVAL) → Phase 3 (Gathering) → Phase 4 (Planning + APPROVAL) → Phase 5 (Execution via agents) → Phase 6 (Completion). NEVER skip phases. NEVER execute directly without agent delegation.')
            ->why('Workflow ensures quality, user control, and proper orchestration. Skipping phases leads to poor results, missed context, and violated user trust.')
            ->onViolation('STOP. Return to Phase 0. Follow workflow sequentially. Present approval gates. Delegate via Task().');

        $this->rule('never-execute-directly')->critical()
            ->text('Brain NEVER executes implementation tasks directly. MUST delegate to agents via Task(). Brain only: analyzes, plans, presents approvals, delegates, validates results.')
            ->why('Direct execution violates orchestration model, bypasses agent expertise, wastes Brain tokens on execution instead of coordination.')
            ->onViolation('STOP. Identify required agent from brain list:masters. Delegate via Task(@agent-name, task).');

        // Anti-Improvisation: Strict Tool Prohibition
        $this->rule('no-direct-file-tools')->critical()
            ->text('FORBIDDEN: Brain NEVER calls Glob, Grep, Read, Edit, Write directly. ALL file operations MUST be delegated to agents via Task().')
            ->why('Direct tool calls are expensive, bypass agent expertise, and violate orchestration model. Each file operation costs tokens that agents handle more efficiently.')
            ->onViolation('STOP. Remove direct tool call. Delegate to appropriate agent: ExploreMaster (search/read), code agents (edit/write).');

        $this->rule('orchestration-only')->critical()
            ->text('Brain role is ORCHESTRATION ONLY. Permitted: Task(), vector MCP, brain CLI (docs, list:masters). Everything else → delegate.')
            ->why('Brain is conductor, not musician. Agents execute, Brain coordinates.')
            ->onViolation('Identify task type → Select agent → Delegate via Task().');

        $this->rule('one-agent-one-file')->critical()
            ->text('Each programming subtask = separate agent invocation. One agent, one file change. NO multi-file edits in single delegation.')
            ->why('Atomic changes enable precise tracking, easier rollback, clear accountability.')
            ->onViolation('Split into multiple Task() calls. One agent per file modification.');
    }

    protected function defineGuidelines(): void
    {
        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('VECTOR_TASK_ID', '{numeric ID extracted from $CLEAN_ARGS}'));

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task with full context using pre-captured $VECTOR_TASK_ID')
            ->example()
            ->phase('Use pre-captured: $RAW_INPUT, $HAS_AUTO_APPROVE, $CLEAN_ARGS, $VECTOR_TASK_ID')
            ->phase('Validate $VECTOR_TASK_ID: must be numeric, extracted from "15", "#15", "task 15", "task:15", "task-15"')
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK',
                '{task object with title, content, status, parent_id, priority, tags, comment}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                'Check status_history for session crash indicator',
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Operator::output([
                        '⚠️ SESSION RECOVERY MODE',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Resuming execution without status change.',
                        'WARNING: Previous execution stage unknown. Will verify what was completed via vector memory.',
                        'NOTE: Memory findings from crashed session should be verified against codebase.',
                    ]),
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== EXECUTION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another session.',
                        'Wait for completion or manually reset status if session crashed without history update.',
                    ]),
                    'ABORT execution',
                ]),
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status === "completed"', [
                Operator::report('Vector task #$VECTOR_TASK_ID already completed'),
                'Ask user: "Re-execute this task? (yes/no)"',
                'WAIT for user decision',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK', '{parent task for broader context}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 20}'))
            ->phase(Store::as('SUBTASKS', '{child tasks if any}'))
            ->phase(Store::as('TASK_DESCRIPTION', '$VECTOR_TASK.title + $VECTOR_TASK.content'))
            ->phase(Operator::output([
                '=== TASK:ASYNC ACTIVATED ===',
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent: {$PARENT_TASK.title or "none"}',
                'Subtasks: {count or "none"}',
                'Comment: {$VECTOR_TASK.comment or "none"}',
            ]));

        // Phase 1: Agent Discovery
        $this->guideline('phase1-agent-discovery')
            ->goal('Discover agents leveraging task context + vector memory')
            ->example()
            ->phase(VectorMemoryMcp::call('search_memories',
                '{query: "similar: {$TASK_DESCRIPTION}", limit: 5, category: "code-solution,architecture"}'))
            ->phase(Store::as('PAST_SOLUTIONS', 'Past approaches'))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'brain list:masters'))
            ->phase(Store::as('AVAILABLE_AGENTS', 'Agents list'))
            ->phase('Match task to agents: $TASK_DESCRIPTION + $VECTOR_TASK.tags + $PAST_SOLUTIONS')
            ->phase(Store::as('RELEVANT_AGENTS', '[{agent, capability, rationale}, ...]'))
            ->phase(Operator::output([
                '=== PHASE 1: AGENT DISCOVERY ===',
                'Agents: {selected} | Task tags: {$VECTOR_TASK.tags}',
            ]));

        // Phase 2: Requirements Analysis + Approval Gate
        $this->guideline('phase2-requirements-analysis-approval')
            ->goal('Create requirements plan leveraging task context + memory + GET USER APPROVAL + START TASK')
            ->example()
            ->phase(VectorMemoryMcp::call('search_memories',
                '{query: "patterns: {task_domain}", limit: 5, category: "learning,architecture"}'))
            ->phase(Store::as('IMPLEMENTATION_PATTERNS', 'Past patterns'))
            ->phase('Analyze: $TASK_DESCRIPTION + $VECTOR_TASK.comment + $PAST_SOLUTIONS + $IMPLEMENTATION_PATTERNS')
            ->phase('Determine needs: scan targets, web research (if non-trivial), docs scan (if architecture-related)')
            ->phase(Store::as('REQUIREMENTS_PLAN',
                '{scan_targets, web_research, docs_scan, task_comment_insights, memory_learnings}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 2: REQUIREMENTS ANALYSIS ===',
                'Task comment insights: {from $VECTOR_TASK.comment}',
                'Memory learnings: {key learnings}',
                'Scanning: {targets} | Research: {status} | Docs: {status}',
                '',
                'APPROVAL CHECKPOINT #1',
                'approved/yes | no/modifications',
            ]))
            ->phase('WAIT for user approval')
            ->phase(Operator::verify('User approved'))
            ->phase(Operator::if('rejected', 'Modify plan → Re-present → WAIT'))
            ->phase('IMMEDIATELY after approval - set task in_progress (research IS execution)')
            ->phase(VectorTaskMcp::call('task_update',
                '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Execution started after requirements approval", append_comment: true}'))
            ->phase(Operator::output(['Vector task #{$VECTOR_TASK_ID} started (research phase)']));

        // Phase 3: Material Gathering with Vector Storage
        $this->guideline('phase3-material-gathering')
            ->goal('Collect materials via agents. Brain permitted: brain docs (index only, few tokens). ALL file reading → delegate to agents.')
            ->example()
            ->phase(Operator::if('$IS_SESSION_RECOVERY === true', [
                'SESSION RECOVERY: Verify what was completed in crashed session',
                VectorMemoryMcp::call('search_memories',
                    '{query: "task #{$VECTOR_TASK_ID} progress execution", limit: 5, category: "code-solution,tool-usage"}'),
                Store::as('CRASHED_SESSION_FINDINGS', '{memory findings from crashed session}'),
                'WARNING: Verify findings against actual codebase - crashed session may have partial/incomplete changes',
                TaskTool::agent('explore', 'Verify crashed session changes for task #{$VECTOR_TASK_ID}. Check files mentioned in memory findings: {$CRASHED_SESSION_FINDINGS}. Report: what was completed, what is incomplete, any conflicts.'),
                Store::as('RECOVERY_VERIFICATION', '{verified state from codebase}'),
            ]))
            ->phase(Operator::forEach('scan_target in $REQUIREMENTS_PLAN.scan_targets', [
                TaskTool::agent('explore', 'Extract context from {scan_target}. Store findings to vector memory.'),
                Store::as('GATHERED_MATERIALS[{target}]', 'Agent-extracted context'),
            ]))
            ->phase(Operator::if('$DOCS_SCAN_NEEDED === true', [
                BashTool::describe(BrainCLI::DOCS('{keywords}'),
                    'Get documentation INDEX only (Path, Name, Description)'),
                Store::as('DOCS_INDEX', 'Documentation file paths'),
                TaskTool::agent('explore',
                    'Read and summarize documentation files: {$DOCS_INDEX paths}. Store to vector memory.'),
                Store::as('DOCS_SCAN_FINDINGS', 'Agent-summarized documentation'),
            ]))
            ->phase(Operator::if('$WEB_RESEARCH_NEEDED === true', [
                TaskTool::agent('web-research-master',
                    'Research best practices for {$TASK_DESCRIPTION}. Store findings to vector memory.'),
                Store::as('WEB_RESEARCH_FINDINGS', 'External knowledge'),
            ]))
            ->phase(Store::as('CONTEXT_PACKAGES', '{agent_name: {context, materials, task_domain}, ...}'))
            ->phase(VectorMemoryMcp::call('store_memory',
                '{content: "Context for {$TASK_DESCRIPTION}\\n\\nMaterials: {summary}", category: "tool-usage", tags: ["task-async", "context-gathering"]}'))
            ->phase(Operator::output([
                '=== PHASE 3: MATERIALS GATHERED ===',
                'Materials: {count} | Docs: {status} | Web: {status}',
                'Context stored to vector memory',
            ]));

        // Phase 4: Execution Planning with Vector Memory + Approval Gate
        $this->guideline('phase4-execution-planning-approval')
            ->goal('Create atomic plan leveraging past execution patterns, analyze dependencies, and GET USER APPROVAL')
            ->example()
            ->phase(VectorMemoryMcp::call('search_memories',
                '{query: "execution approach for {task_type}", limit: 5, category: "code-solution"}'))
            ->phase(Store::as('EXECUTION_PATTERNS', 'Past successful execution approaches'))
            ->phase('Create plan: atomic steps (≤2 files each), logical order, informed by $EXECUTION_PATTERNS')
            ->phase('Analyze step dependencies: file conflicts, context dependencies, data flow')
            ->phase('Determine execution mode: sequential (default/safe) OR parallel (independent tasks/user request/optimization)')
            ->phase(Operator::if('parallel possible AND beneficial', [
                'Group independent steps into parallel batches',
                'Validate NO conflicts: 1) File: same file in multiple steps, 2) Context: step B needs output of step A, 3) Resource: same API/DB/external',
                Store::as('EXECUTION_MODE', 'parallel'),
                Store::as('PARALLEL_GROUPS', '[[step1, step2], [step3], ...]'),
            ]))
            ->phase(Operator::if('NOT parallel OR dependencies detected', [
                Store::as('EXECUTION_MODE', 'sequential'),
            ]))
            ->phase(Store::as('EXECUTION_PLAN',
                '{steps: [{step_number, agent_name, task_description, file_scope: [≤2 files], memory_search_query, expected_outcome}, ...], total_steps: N, execution_mode: "sequential|parallel", parallel_groups: [...]}'))
            ->phase(Operator::verify('Each step has ≤ 2 files'))
            ->phase(Operator::verify('Parallel groups have NO conflicts'))
            ->phase(Operator::output([
                '',
                '=== PHASE 4: EXECUTION PLAN ===',
                'Task: {$TASK_DESCRIPTION} | Steps: {N} | Mode: {execution_mode}',
                'Learned from: {$EXECUTION_PATTERNS summary}',
                '',
                '{Step-by-step breakdown with files and memory search queries}',
                '{If parallel: show grouped batches}',
                '',
                'APPROVAL CHECKPOINT #2',
                'Type "approved" or "yes" to begin.',
                'Type "no" or provide modifications.',
            ]))
            ->phase('WAIT for user approval')
            ->phase(Operator::verify('User confirmed approval'))
            ->phase(Operator::if('user rejected', [
                'Accept modifications → Update plan → Verify atomic + dependencies → Re-present → WAIT',
            ]));

        // Phase 5: Flexible Execution (references agent-memory-pattern)
        // CRITICAL ENFORCEMENT: Brain executes ZERO code - ALL work via Task() delegation
        $this->rule('phase5-delegation-only')->critical()
            ->text('In Phase 5, Brain is FORBIDDEN from calling: Edit, Write, Glob, Grep, Read, Bash (except brain CLI). EVERY step MUST be Task() call to agent. Brain role: invoke Task(), wait for result, log outcome. ZERO direct implementation.')
            ->why('Brain is orchestrator, not executor. Direct tool calls bypass agent expertise, waste Brain tokens, violate architecture. Phase 5 is delegation phase, not execution phase.')
            ->onViolation('STOP IMMEDIATELY. Delete pending Edit/Write/Glob/Grep/Read calls. Identify agent from $EXECUTION_PLAN.steps[N].agent_name. Delegate via Task(subagent_type=agent_name, prompt=step_description).');

        $this->guideline('phase5-flexible-execution')
            ->goal('Execute plan via Task() delegation ONLY - Brain calls NO file tools')
            ->example()
            ->phase('CRITICAL: Brain calls ONLY Task() tool in this phase. NO Edit/Write/Glob/Grep/Read.')
            ->phase('NOTE: Task already in_progress since Phase 2 approval')
            ->phase('Initialize: current_step = 1')
            ->phase(Operator::if('$EXECUTION_PLAN.execution_mode === "sequential"', [
                'SEQUENTIAL MODE: Delegate steps one-by-one via Task()',
                Operator::forEach('step in $EXECUTION_PLAN.steps', [
                    Operator::output(['Step {N}/{total}: DELEGATING to @agent-{step.agent_name} | {step.file_scope}']),
                    'MANDATORY: Use Task() tool - NOT Edit/Write/Glob/Grep/Read',
                    TaskTool::agent('{step.agent_name}', 'Execute: {step.task_description}. Files: {step.file_scope}. Memory query: {step.memory_search_query}. Follow agent-memory-pattern: search before, store after.'),
                    'WAIT for agent completion',
                    Store::as('STEP_RESULTS[{N}]', 'Agent result'),
                    Operator::output(['Step {N} delegated and completed by agent']),
                ]),
            ]))
            ->phase(Operator::if('$EXECUTION_PLAN.execution_mode === "parallel"', [
                'PARALLEL MODE: Multiple Task() calls in SINGLE message (NOT run_in_background)',
                'Simple independent tasks → launch agents in parallel for efficiency',
                Operator::forEach('group in $EXECUTION_PLAN.parallel_groups', [
                    Operator::output(['Batch {N}: DELEGATING {count} steps in PARALLEL (single message)']),
                    'MANDATORY: Multiple Task() calls in ONE message block',
                    'FORBIDDEN: run_in_background=true (Brain must wait for all results)',
                    'Each Task() includes: agent_name, task_description, file_scope, memory_search_query',
                    'Brain sends SINGLE message with multiple Task() tool calls',
                    'Brain WAITS for ALL agents to complete synchronously',
                    Store::as('BATCH_RESULTS[{N}]', 'All agent results received together'),
                    Operator::output(['Batch {N}: all {count} agents completed']),
                ]),
            ]))
            ->phase(Operator::if('step fails', ['Store failure to memory', 'Offer: Retry / Skip / Abort']))
            ->phase('POST-EXECUTION CHECK: Verify Brain called ONLY Task() tools. Any Edit/Write/Glob/Grep/Read = VIOLATION.');

        // Phase 6: Completion with Vector Task Update
        $this->guideline('phase6-completion-report')
            ->goal('Report results, update vector task status, store learnings')
            ->example()
            ->phase(Store::as('COMPLETION_SUMMARY', '{completed_steps, files_modified, outcomes, learnings}'))
            ->phase(VectorMemoryMcp::call('store_memory',
                '{content: "Completed task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nApproach: {summary}\\n\\nSteps: {outcomes}\\n\\nLearnings: {insights}\\n\\nFiles: {list}", category: "code-solution", tags: ["task-async", "completed"]}'))
            ->phase(Operator::if('status === SUCCESS', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Execution completed successfully. Files: {list}. Memory: #{memory_id}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} completed']),
            ]))
            ->phase(Operator::if('status === PARTIAL', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, comment: "Partial completion: {completed}/{total} steps. Remaining: {list}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} progress saved (partial)']),
            ]))
            ->phase(Operator::if('status === FAILED', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "stopped", comment: "Execution failed: {reason}. Completed: {completed}/{total}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} stopped (failed)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== EXECUTION COMPLETE ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {SUCCESS/PARTIAL/FAILED}',
                'Steps: {completed}/{total} | Files: {count} | Learnings stored',
                '{step_outcomes}',
            ]))
            ->phase(Operator::if('partial', [
                'Store partial state → List remaining → Suggest resumption',
            ]));

        // Agent Vector Memory Instructions Template
        $this->guideline('agent-memory-instructions')
            ->text('MANDATORY vector memory pattern for ALL agents')
            ->example()
            ->phase('BEFORE TASK:')
            ->do([
                'Execute: mcp__vector-memory__search_memories(query: "{relevant}", limit: 5)',
                'Review: Analyze results for patterns, solutions, learnings',
                'Apply: Incorporate insights into approach',
                'CAUTION: If $IS_SESSION_RECOVERY=true, memory findings may be from crashed session - verify against actual codebase before trusting',
            ])
            ->phase('DURING TASK:')
            ->do([
                'Focus: Execute ONLY assigned task within file scope',
                'Atomic: Respect 1-2 file limit strictly',
            ])
            ->phase('AFTER TASK:')
            ->do([
                'Document: Summarize what was done, how it worked, key insights',
                'Execute: mcp__vector-memory__store_memory(content: "{what+how+insights}", category: "{appropriate}", tags: [...])',
                'Verify: Confirm storage successful',
            ])
            ->phase('CRITICAL: Vector memory is the communication channel between agents. Your learnings enable the next agent!');

        // Error Handling
        $this->guideline('error-handling')
            ->text('Graceful error handling with recovery options')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'Abort command',
            ])
            ->phase()->if('vector task already completed', [
                'Report: "Vector task #{id} already has status: completed"',
                'Ask user: "Do you want to re-execute this task?"',
                'WAIT for user decision',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use /do:async for text-based task descriptions"',
                'Abort command',
            ])
            ->phase()->if('no agents available', [
                'Report: "No agents found via brain list:masters"',
                'Suggest: Run /init-agents first',
                'Abort command',
            ])
            ->phase()->if('user rejects requirements plan', [
                'Accept modifications',
                'Rebuild requirements plan',
                'Re-submit for approval',
            ])
            ->phase()->if('user rejects execution plan', [
                'Accept modifications',
                'Rebuild execution plan',
                'Verify atomic task constraints',
                'Re-submit for approval',
            ])
            ->phase()->if('agent execution fails', [
                'Log: "Step {N} failed: {error}"',
                'Update task comment with failure details',
                'Offer options:',
                '  1. Retry current step',
                '  2. Skip and continue',
                '  3. Abort remaining steps',
                'WAIT for user decision',
            ])
            ->phase()->if('documentation scan fails', [
                'Log: "brain docs command failed or no documentation found"',
                'Proceed without documentation context',
                'Note: "Documentation context unavailable"',
            ])
            ->phase()->if('web research timeout', [
                'Log: "Web research timed out - continuing without external knowledge"',
                'Proceed with local context only',
            ])
            ->phase()->if('context gathering fails', [
                'Log: "Failed to gather {context_type}"',
                'Proceed with available context',
                'Warn: "Limited context may affect quality"',
            ]);

        // Constraints and Validation
        $this->guideline('constraints-validation')
            ->text('Enforcement of critical constraints throughout execution')
            ->example()
            ->phase('Phase 0: Verify $TASK_ID is valid task ID format')
            ->phase('Phase 0: Verify vector task exists and is not completed (unless user confirms re-execute)')
            ->phase('Before Phase 2 → Phase 3 transition: Verify user approval received')
            ->phase('Before Phase 4 → Phase 5 transition: Verify user approval received')
            ->phase('During Execution Planning: Verify each step has ≤ 2 files in scope')
            ->phase('During Execution: Verify dependencies respected (sequential: step order, parallel: no conflicts)')
            ->phase('Throughout: NO unapproved steps allowed')
            ->phase(Operator::verify([
                'task_id_valid = true',
                'vector_task_loaded = true',
                'approval_checkpoints_passed = 2',
                'all_tasks_atomic = true (≤ 2 files each)',
                'execution_mode = sequential OR parallel (validated)',
                'improvisation_count = 0',
            ]));

        // Examples
        $this->guideline('example-simple')
            ->scenario('Simple single-agent task execution')
            ->example()
            ->phase('input', '"task 15" where task #15 is "Fix typo in LoginController"')
            ->phase('load', 'task_get(15) → title, content, status, priority')
            ->phase('flow',
                'Task Load → Discovery → Requirements APPROVE → Gather → Plan APPROVE → Execute (1 step) → task_update(completed) → Complete');

        $this->guideline('example-complex')
            ->scenario('Complex multi-agent sequential execution')
            ->example()
            ->phase('input', '"#42" where task #42 is "Add rate limiting to API" with parent #40 "API Security"')
            ->phase('load', 'task_get(42) → task_get(40 parent) → full context')
            ->phase('agents', '@web-research-master, @code-master, @documentation-master')
            ->phase('plan', '4 steps: Middleware → Kernel → Routes → Docs')
            ->phase('execution', 'Sequential: 1→2→3→4 (dependencies between steps)')
            ->phase('result', 'task_update(42, completed) → 4/4 complete');

        $this->guideline('example-parallel')
            ->scenario('Parallel execution for simple independent subtasks')
            ->example()
            ->phase('input', '"task:28" where task #28 has 3 simple independent subtasks')
            ->phase('analysis', '3 independent files, no conflicts, simple tasks')
            ->phase('plan', 'Mode: PARALLEL, Batch 1: [Step1, Step2, Step3]')
            ->phase('execution', 'Brain sends SINGLE message with 3 Task() calls (NOT run_in_background). Brain waits for all 3 agents.')
            ->phase('result', 'All 3 results received together → task_update(28, completed) → 3/3 (faster than sequential)');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | approval gates | progress markers | file scope | task ID references | No filler');
    }
}
