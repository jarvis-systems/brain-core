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
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Defines the do:async command protocol for multi-agent orchestration with flexible execution modes, user approval gates, and vector memory integration. Ensures zero distractions, atomic tasks, and strict plan adherence for reliable task execution.')]
class DoAsyncInclude extends IncludeArchetype
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
        $this->defineEntryPointBlockingRule('ASYNC');

        // Universal safety rules
        $this->defineSecretsPiiProtectionRules();
        $this->defineNoDestructiveGitRules();
        $this->defineTagTaxonomyRules();
        $this->defineFailurePolicyRules();
        $this->defineAggressiveDocsSearchGuideline();
        $this->defineDocumentationIsLawRules();

        // Iron Rules - Zero Tolerance
        $this->defineZeroDistractionsRule();

        // Task workflow integration
        $this->defineScopeEscalationRule('async');
        $this->defineDoCircuitBreakerRule('async');
        $this->defineDoFailureAwarenessRule();
        $this->defineDoMachineReadableProgressRule();

        if ($this->strictAtLeast('standard')) {
            $this->rule('approval-gates-mandatory')->critical()
                ->text('User approval REQUIRED at Requirements Analysis gate and Execution Planning gate. NEVER proceed without explicit confirmation. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve all gates (skip waiting for user confirmation).')
                ->why('Maintains user control and prevents unauthorized execution. The -y flag enables unattended/scripted execution.')
                ->onViolation('STOP. Wait for user approval before continuing (unless $HAS_AUTO_APPROVE is true).');
        }

        $this->rule('atomic-tasks-only')->critical()
            ->text('Each agent task MUST be small and focused: maximum 1-2 files per agent invocation. NO large multi-file changes.')
            ->why('Prevents complexity, improves reliability, enables precise tracking')
            ->onViolation('Break task into smaller pieces. Re-plan with atomic steps.');

        $this->rule('no-improvisation')->critical()
            ->text('Execute ONLY approved plan steps. NO improvisation, NO "while we\'re here" additions, NO proactive suggestions during execution.')
            ->why('Maintains plan integrity and predictability')
            ->onViolation('Revert to last approved checkpoint. Resume approved steps only.');

        if ($this->strictAtLeast('standard')) {
            $this->rule('execution-mode-flexible')->high()
                ->text('Execute agents sequentially BY DEFAULT. Allow parallel execution when: 1) tasks are independent (no file/context conflicts), 2) user explicitly requests parallel mode, 3) optimization benefits outweigh tracking complexity.')
                ->why('Balances safety with performance optimization')
                ->onViolation('Validate task independence before parallel execution. Fallback to sequential if conflicts detected.');
        }

        $this->defineVectorMemoryMandatoryRule();

        if ($this->strictAtLeast('standard')) {
            $this->rule('conversation-context-awareness')->high()
                ->text('ALWAYS analyze conversation context BEFORE planning. User may have discussed requirements, constraints, preferences, or decisions in previous messages.')
                ->why('Prevents ignoring critical information already provided by user in conversation')
                ->onViolation('Review conversation history before proceeding with task analysis.');
        }

        $this->rule('full-workflow-mandatory')->critical()
            ->text('ALL requests MUST follow complete workflow: Phase 0 (Context) → Phase 1 (Discovery) → Phase 2 (Requirements + APPROVAL) → Phase 3 (Gathering) → Phase 4 (Planning + APPROVAL) → Phase 5 (Execution via agents) → Phase 6 (Completion). NEVER skip phases. NEVER execute directly without agent delegation.')
            ->why('Workflow ensures quality, user control, and proper orchestration. Skipping phases leads to poor results, missed context, and violated user trust.')
            ->onViolation('STOP. Return to Phase 0. Follow workflow sequentially. Present approval gates. Delegate via Task().');

        $this->rule('never-execute-directly')->critical()
            ->text('Brain NEVER executes implementation tasks directly. For ANY $TASK_DESCRIPTION: MUST delegate to agents via Task(). Brain only: analyzes, plans, presents approvals, delegates, validates results.')
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

        if ($this->strictAtLeast('standard')) {
            $this->rule('one-agent-one-file')->critical()
                ->text('Each programming subtask = separate agent invocation. One agent, one file change. NO multi-file edits in single delegation.')
                ->why('Atomic changes enable precise tracking, easier rollback, clear accountability.')
                ->onViolation('Split into multiple Task() calls. One agent per file modification.');
        }

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->defineInputCaptureWithDescriptionGuideline();

        // Phase 0: Conversation Context Analysis
        $this->guideline('phase0-context-analysis')
            ->goal('Extract task insights from conversation history before planning')
            ->example()
            ->phase(Operator::output([
                '=== DO:ASYNC ACTIVATED ===',
                '',
                '=== PHASE 0: CONTEXT ANALYSIS ===',
                'Task: {$TASK_DESCRIPTION}',
                'Analyzing conversation context...',
            ]))
            ->phase('Analyze conversation context: requirements mentioned, constraints discussed, user preferences, prior decisions, related code/files referenced')
            ->phase(Store::as('CONVERSATION_CONTEXT', '{requirements, constraints, preferences, decisions, references}'))
            ->phase(Operator::if('conversation has relevant context', [
                'Integrate context into task understanding',
                'Note: Use conversation insights throughout all phases',
            ]))
            ->phase(Operator::output([
                'Context: {summary of relevant conversation info}',
            ]));

        // Phase 1: Agent Discovery
        $this->guideline('phase1-agent-discovery')
            ->goal('Discover agents leveraging conversation context + vector memory')
            ->example()
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => 'similar: {$TASK_DESCRIPTION}', 'limit' => 5, 'category' => 'code-solution,architecture']))
            ->phase(Store::as('PAST_SOLUTIONS', 'Past approaches'))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'brain list:masters'))
            ->phase(Store::as('AVAILABLE_AGENTS', 'Agents list'))
            ->phase('Match task to agents: $TASK_DESCRIPTION + $CONVERSATION_CONTEXT + $PAST_SOLUTIONS')
            ->phase(Store::as('RELEVANT_AGENTS', '[{agent, capability, rationale}, ...]'))
            ->phase(Operator::output([
                '=== PHASE 1: AGENT DISCOVERY ===',
                'Agents: {selected} | Context: {conversation insights applied}',
            ]));

        // Phase 2: Requirements Analysis + Approval Gate
        $this->guideline('phase2-requirements-analysis-approval')
            ->goal('Create requirements plan leveraging conversation + memory + GET USER APPROVAL + START TASK')
            ->example()
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => 'patterns: {task_domain}', 'limit' => 5, 'category' => 'learning,architecture']))
            ->phase(Store::as('IMPLEMENTATION_PATTERNS', 'Past patterns'))
            ->phase('Analyze: $TASK_DESCRIPTION + $CONVERSATION_CONTEXT + $PAST_SOLUTIONS + $IMPLEMENTATION_PATTERNS')
            ->phase('Determine needs: scan targets, web research (if non-trivial), docs scan (if architecture-related)')
            ->phase(Store::as('REQUIREMENTS_PLAN', '{scan_targets, web_research, docs_scan, conversation_insights, memory_learnings}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 2: REQUIREMENTS ANALYSIS ===',
                'Context: {conversation insights} | Memory: {key learnings}',
                'Scanning: {targets} | Research: {status} | Docs: {status}',
                '',
                '⚠️  APPROVAL CHECKPOINT #1',
                '✅ approved/yes | ❌ no/modifications',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                'AUTO-APPROVED (unattended mode)',
                Operator::output(['🤖 Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Modify plan → Re-present → WAIT'),
            ]));

        // Phase 3: Material Gathering with Vector Storage
        $this->guideline('phase3-material-gathering')
            ->goal('Collect materials via agents. Brain permitted: brain docs (index only, few tokens). ALL file reading → delegate to agents.')
            ->example()
            ->phase(Operator::forEach('scan_target in $REQUIREMENTS_PLAN.scan_targets', [
                TaskTool::agent('explore', 'Extract context from {scan_target}. Store findings to vector memory.'),
                Store::as('GATHERED_MATERIALS[{target}]', 'Agent-extracted context'),
            ]))
            ->phase(Operator::if('$DOCS_SCAN_NEEDED === true', [
                BashTool::describe(BrainCLI::DOCS('{keywords}'), 'Get documentation INDEX only (Path, Name, Description)'),
                Store::as('DOCS_INDEX', 'Documentation file paths'),
                TaskTool::agent('explore', 'Read and summarize documentation files: {$DOCS_INDEX paths}. Store to vector memory.'),
                Store::as('DOCS_SCAN_FINDINGS', 'Agent-summarized documentation'),
            ]))
            ->phase(Operator::if('$WEB_RESEARCH_NEEDED === true', [
                TaskTool::agent('web-research-master', 'Research best practices for {$TASK_DESCRIPTION}. Store findings to vector memory.'),
                Store::as('WEB_RESEARCH_FINDINGS', 'External knowledge'),
            ]))
            ->phase(Store::as('CONTEXT_PACKAGES', '{agent_name: {context, materials, task_domain}, ...}'))
            ->phase(VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'Context for {$TASK_DESCRIPTION}\n\nMaterials: {summary}', 'category' => self::CAT_CODE_SOLUTION, 'tags' => [self::MTAG_SOLUTION, self::MTAG_REUSABLE]]))
            ->phase(Operator::output([
                '=== PHASE 3: MATERIALS GATHERED ===',
                'Materials: {count} | Docs: {status} | Web: {status}',
                'Context stored to vector memory ✓',
            ]));

        // Phase 4: Execution Planning with Vector Memory + Approval Gate
        $this->guideline('phase4-execution-planning-approval')
            ->goal('Create atomic plan leveraging past execution patterns, analyze dependencies, and GET USER APPROVAL')
            ->example()
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => 'execution approach for {task_type}', 'limit' => 5, 'category' => 'code-solution']))
            ->phase(Store::as('EXECUTION_PATTERNS', 'Past successful execution approaches'))
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Planning agent delegation. Analyzing: task decomposition, agent selection, step dependencies, parallelization opportunities, file scope per step.",
                thoughtNumber: 1,
                totalThoughts: 3,
                nextThoughtNeeded: true
            }'))
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
                '⚠️  APPROVAL CHECKPOINT #2',
                '✅ Type "approved" or "yes" to begin.',
                '❌ Type "no" or provide modifications.',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                'AUTO-APPROVED (unattended mode)',
                Operator::output(['🤖 Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                'WAIT for user approval',
                Operator::verify('User confirmed approval'),
                Operator::if('user rejected', [
                    'Accept modifications → Update plan → Verify atomic + dependencies → Re-present → WAIT',
                ]),
            ]));

        // Phase 5: Flexible Execution (references agent-memory-pattern)
        $this->guideline('phase5-flexible-execution')
            ->goal('Execute plan with optimal mode (sequential OR parallel)')
            ->example()
            ->phase('Initialize: current_step = 1')
            ->phase(Operator::if('$EXECUTION_PLAN.execution_mode === "sequential"', [
                'SEQUENTIAL MODE: Execute steps one-by-one',
                Operator::forEach('step in $EXECUTION_PLAN.steps', [
                    Operator::output(['▶️ Step {N}/{total}: @agent-{step.agent_name} | 📁 {step.file_scope}']),
                    'Delegate via Task() with agent-memory-pattern (BEFORE→DURING→AFTER)',
                    TaskTool::describe('Task(@agent-{name}, {task + memory_search_query + context})'),
                    Store::as('STEP_RESULTS[{N}]', 'Result'),
                    Operator::output(['✅ Step {N} complete']),
                ]),
            ]))
            ->phase(Operator::if('$EXECUTION_PLAN.execution_mode === "parallel"', [
                'PARALLEL MODE: Execute independent steps concurrently',
                Operator::forEach('group in $EXECUTION_PLAN.parallel_groups', [
                    Operator::output(['🚀 Batch {N}: {count} steps']),
                    'Launch ALL steps CONCURRENTLY via multiple Task() calls',
                    'Each task follows agent-memory-pattern',
                    'WAIT for ALL tasks in batch to complete',
                    Store::as('BATCH_RESULTS[{N}]', 'Batch results'),
                    Operator::output(['✅ Batch {N} complete']),
                ]),
            ]))
            ->phase(Operator::if('step fails', ['Store failure to memory', 'Offer: Retry / Skip / Abort']));

        // Phase 6: Completion with Vector Memory Storage
        $this->guideline('phase6-completion-report')
            ->goal('Report results and store comprehensive learnings to vector memory')
            ->example()
            ->phase(Store::as('COMPLETION_SUMMARY', '{completed_steps, files_modified, outcomes, learnings}'))
            ->phase(VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'Completed: {$TASK_DESCRIPTION}\n\nApproach: {summary}\n\nSteps: {outcomes}\n\nLearnings: {insights}\n\nFiles: {list}', 'category' => self::CAT_CODE_SOLUTION, 'tags' => [self::MTAG_SOLUTION, self::MTAG_REUSABLE]]))
            ->phase(Operator::output([
                '',
                '=== EXECUTION COMPLETE ===',
                'Task: {$TASK_DESCRIPTION} | Status: {SUCCESS/PARTIAL/FAILED}',
                '✓ Steps: {completed}/{total} | 📁 Files: {count} | 💾 Learnings stored to memory',
                '{step_outcomes}',
                '',
                'RESULT: {SUCCESS|PARTIAL|FAILED} — steps={completed}/{total}, files={count}',
                'NEXT: /do:validate {$TASK_DESCRIPTION}',
            ]))
            ->phase(Operator::if('partial', [
                'Store partial state → List remaining → Suggest resumption',
            ]));

        // Agent instruction template (15 sections — matches Task workflow depth)
        $this->guideline('agent-instruction-template')
            ->text('Every Task() delegation MUST include these sections:')
            ->text('1. TASK: Clear description of what to do')
            ->text('2. FILES: Specific file scope (1-2 files, max 3-5 for feature)')
            ->text('3. DOCUMENTATION: "If docs exist: {$DOCS_SCAN_FINDINGS}. Documentation = COMPLETE spec. Follow DOCS."')
            ->text('4. BLOCKED APPROACHES: "KNOWN FAILURES (DO NOT USE): {$BLOCKED_APPROACHES}. If your solution matches — find alternative."')
            ->text('5. MEMORY BEFORE: "Search memory for: {terms}. Check debugging category for failures."')
            ->text('6. MEMORY AFTER: "Store learnings: what worked, approach used, key insights. Category: '.self::CAT_CODE_SOLUTION.', tags: ['.self::MTAG_SOLUTION.', '.self::MTAG_REUSABLE.']."')
            ->text('7. SECURITY: "No hardcoded secrets. Validate input. Escape output. Parameterized queries."')
            ->text('8. VALIDATION: "Verify syntax. Run linter if configured. Check logic: null, empty, boundary, off-by-one, error paths. Check performance: N+1, nested loops, unbounded data. Run ONLY related tests (scoped, never full suite). Fix before completion."')
            ->text('9. GIT: "FORBIDDEN: git checkout, git restore, git stash, git reset, git clean. These destroy parallel agents work and memory/ databases. Rollback = Read original content + Write back. Git is READ-ONLY (status, diff, log)."')
            ->text('10. PATTERNS: "BEFORE coding: search codebase for similar implementations. Grep analogous class names, method patterns. Found → follow same approach, reuse helpers. NEVER reinvent existing patterns."')
            ->text('11. IMPACT: "BEFORE editing: Grep who imports/uses/extends target file. Dependents found → ensure changes are compatible. Changing public API → update all callers."')
            ->text('12. HALLUCINATION: "Verify EVERY method/class/function call exists with correct signature. Read source to confirm. NEVER assume API from naming convention."')
            ->text('13. CLEANUP: "After edits: remove unused imports, dead code, orphaned helpers, commented-out blocks."')
            ->text('14. TESTS: "After implementation: check if changed code has tests. NO tests → WRITE them. Insufficient coverage → ADD tests. Target: >=80% coverage, critical paths 100%, meaningful assertions, edge cases (null, empty, boundary). Detect test framework, follow existing test patterns. Run written tests to verify passing."')
            ->text('15. DOCS: "After implementation: IF task adds NEW feature/module/API → run brain docs \"{keywords}\" to check existing docs. NOT found → CREATE .docs/{feature}.md with YAML front matter + markdown body. Documentation = description for humans, text-first, minimize code. IF task CHANGES existing behavior and docs exist → UPDATE relevant docs. Bugfix/refactor → SKIP docs."');

        // Error Recovery
        $this->guideline('error-recovery')
            ->text('Graceful error handling with recovery options')
            ->example()
            ->phase()->if('user rejects plan', [
                'Accept modifications',
                'Rebuild plan',
                'Re-submit for approval',
            ])
            ->phase()->if('no agents available', [
                'Report: "No agents found via brain list:masters"',
                'Suggest: Run /init-agents first',
                'Abort command',
            ])
            ->phase()->if('agent execution fails', [
                'Log: "Step/Agent {N} failed: {error}"',
                'Offer options:',
                '  1. Retry current step',
                '  2. Skip and continue',
                '  3. Abort remaining steps',
                'WAIT for user decision',
            ])
            ->phase()->if('web research timeout', [
                'Log: "Web research timed out - continuing without external knowledge"',
                'Proceed with local context only',
            ])
            ->phase()->if('context gathering fails', [
                'Log: "Failed to gather {context_type}"',
                'Proceed with available context',
                'Warn: "Limited context may affect quality"',
            ])
            ->phase()->if('documentation scan fails', [
                'Log: "brain docs command failed or no documentation found"',
                'Proceed without documentation context',
            ])
            ->phase()->if('memory storage fails', [
                'Log: "Failed to store to memory: {error}"',
                'Report findings in output instead',
                'Continue with report',
            ]);

        // Constraints and Validation
        $this->guideline('constraints-validation')
            ->text('Enforcement of critical constraints throughout execution')
            ->example()
            ->phase('Before Requirements Analysis: Verify $TASK_DESCRIPTION is not empty')
            ->phase('Before Phase 2 → Phase 3 transition: Verify user approval received')
            ->phase('Before Phase 4 → Phase 5 transition: Verify user approval received')
            ->phase('During Execution Planning: Verify each step has ≤ 2 files in scope')
            ->phase('During Execution: Verify dependencies respected (sequential: step order, parallel: no conflicts)')
            ->phase('Throughout: NO unapproved steps allowed')
            ->phase(Operator::verify([
                'approval_checkpoints_passed = 2',
                'all_tasks_atomic = true (≤ 2 files each)',
                'execution_mode = sequential OR parallel (validated)',
                'improvisation_count = 0',
            ]));

        // Examples (3 core scenarios)
        $this->guideline('example-simple')
            ->scenario('Simple single-agent task')
            ->example()
            ->phase('input', '"Fix authentication bug in LoginController.php"')
            ->phase('flow', 'Context → Discovery → Requirements ✓ → Gather → Plan ✓ → Execute (1 step) → Complete');

        $this->guideline('example-sequential')
            ->scenario('Complex multi-agent sequential task')
            ->example()
            ->phase('input', '"Add Laravel rate limiting to API endpoints"')
            ->phase('agents', '@web-research-master, @code-master, @documentation-master')
            ->phase('plan', '4 steps: Middleware → Kernel → Routes → Docs')
            ->phase('execution', 'Sequential: 1→2→3→4 (dependencies between steps)')
            ->phase('result', '4/4 ✓');

        $this->guideline('example-parallel')
            ->scenario('Parallel execution for independent tasks')
            ->example()
            ->phase('input', '"Add validation to UserController, ProductController, OrderController"')
            ->phase('analysis', '3 independent files, no conflicts')
            ->phase('plan', 'Mode: PARALLEL, Batch 1: [Step1, Step2, Step3]')
            ->phase('execution', 'Concurrent: 3 agents simultaneously')
            ->phase('result', '3/3 ✓ (faster than sequential)');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | approval gates | progress | file scope | No filler');
    }
}
