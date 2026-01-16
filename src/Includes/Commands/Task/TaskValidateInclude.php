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

#[Purpose('Comprehensive vector task validation with parallel agent orchestration. Accepts task ID reference (formats: "15", "#15", "task 15"), validates completed tasks against documentation requirements, code consistency, and completeness. Creates follow-up tasks for gaps. Idempotent - can be run multiple times. Best for: validating vector task work quality.')]
class TaskValidateInclude extends IncludeArchetype
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
            ->text('ON RECEIVING input: Your FIRST output MUST be "=== TASK:VALIDATE ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION. FORBIDDEN first actions: Glob, Grep, Read, Edit, Write, WebSearch, WebFetch, Bash (except brain list:masters), code generation, file analysis.')
            ->why('Without explicit entry point, Brain skips workflow and executes directly. Entry point forces workflow compliance.')
            ->onViolation('STOP IMMEDIATELY. Delete any tool calls. Output "=== TASK:VALIDATE ACTIVATED ===" and restart from Phase 0.');

        // Iron Rules - Zero Tolerance
        $this->rule('validation-only-no-execution')->critical()
            ->text('VALIDATION command validates EXISTING work. NEVER implement, fix, or create code directly. Only validate and CREATE TASKS for issues found.')
            ->why('Validation is read-only audit. Execution belongs to task:async.')
            ->onViolation('Abort any implementation. Create task instead of fixing directly.');

        // Common rule from trait
        $this->defineVectorTaskIdRequiredRule('/do:validate');

        $this->rule('validatable-status-required')->critical()
            ->text('ONLY tasks with status "completed", "tested", or "validated" can be validated. Pending/in_progress/stopped tasks MUST first be completed via task:async.')
            ->why('Validation audits finished work. Incomplete work cannot be validated.')
            ->onViolation('Report: "Task #{id} has status {status}. Complete via /task:async first."');

        // Common rule from trait
        $this->defineAutoApprovalFlagRule();

        $this->rule('simple-validation-heuristic')->high()
            ->text('Detect simple tasks early: estimate ≤ 4h AND priority != "critical" AND no architecture/security tags AND subtasks.count ≤ 2. Store as $SIMPLE_VALIDATION flag in Phase 0.')
            ->why('Simple tasks need lighter validation (2-3 agents). Complex tasks need full parallel orchestration (5 agents).')
            ->onViolation('Check estimate, priority, tags, subtasks in Phase 0. Set $SIMPLE_VALIDATION accordingly.');

        $this->rule('parallel-agent-orchestration')->high()
            ->text('Validation phases use parallel agent orchestration SCALED to complexity: SIMPLE = 2-3 agents (completeness + tests), COMPLEX = 5 agents (full coverage).')
            ->why('Parallel validation reduces time. Agent count scales with task complexity.')
            ->onViolation('Check $SIMPLE_VALIDATION flag. Restructure agent batch accordingly.');

        $this->rule('idempotent-validation')->high()
            ->text('Validation is IDEMPOTENT. Running multiple times produces same result (no duplicate tasks, no repeated fixes).')
            ->why('Allows safe re-runs without side effects.')
            ->onViolation('Check existing tasks before creating. Skip duplicates.');

        // Common rule from trait
        $this->defineSessionRecoveryViaHistoryRule();

        $this->rule('no-direct-fixes-functional')->critical()
            ->text('VALIDATION command NEVER fixes FUNCTIONAL issues directly. Code logic, architecture, functionality issues MUST become tasks.')
            ->why('Traceability and audit trail. Code changes must be tracked via task system.')
            ->onViolation('Create task for the functional issue instead of fixing directly.');

        $this->rule('cosmetic-auto-fix')->critical()
            ->text('COSMETIC issues (whitespace, indentation, extra spaces, trailing spaces, documentation typos, formatting inconsistencies, empty lines) MUST be auto-fixed IMMEDIATELY by the agent that discovers them. NO separate phase, NO additional agents, NO tasks. Agent finds problem → Agent fixes it → Agent continues validation.')
            ->why('Cosmetic fixes are trivial. Creating tasks or spawning extra agents for whitespace is wasteful. The discovering agent has full context and can fix instantly.')
            ->onViolation('Agent that found cosmetic issue MUST fix it inline using Edit tool. Report fixed issues in results, not as pending issues.');

        // Common rule from trait
        $this->defineVectorMemoryMandatoryRule();

        // Phase Execution Sequence - STRICT ORDERING
        $this->rule('phase-sequence-strict')->critical()
            ->text('Phases MUST execute in STRICT sequential order: Phase 0 → Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 5.5 → Phase 6 → Phase 7. NO phase may start until previous phase is FULLY COMPLETED. Each phase MUST output its header "=== PHASE N: NAME ===" before any actions. EXCEPTION: Phase 5.5 may trigger RESTART to Phase 0 if only cosmetic issues exist.')
            ->why('Sequential execution ensures data dependencies are satisfied. Each phase depends on variables stored by previous phases.')
            ->onViolation('STOP. Return to last completed phase. Execute current phase fully before proceeding.');

        $this->rule('no-phase-skip')->critical()
            ->text('FORBIDDEN: Skipping phases. ALL phases 0-7 MUST execute even if a phase has no issues to report. Empty results are valid; skipped phases are VIOLATION.')
            ->why('Phase skipping breaks data flow. Later phases expect variables from earlier phases.')
            ->onViolation('ABORT. Return to first skipped phase. Execute ALL phases in sequence.');

        $this->rule('phase-completion-marker')->high()
            ->text('Each phase MUST end with its output block before next phase begins. Phase N output MUST appear before "=== PHASE N+1 ===" header.')
            ->why('Output markers confirm phase completion. Missing output = incomplete phase.')
            ->onViolation('Complete current phase output before starting next phase.');

        $this->rule('no-parallel-phases')->critical()
            ->text('FORBIDDEN: Executing multiple phases simultaneously. Only Phase 4 allows parallel AGENTS within the phase. Phase-level parallelism is NEVER allowed.')
            ->why('Phase parallelism causes race conditions on shared variables.')
            ->onViolation('Serialize phase execution. Wait for phase completion before starting next.');

        $this->rule('output-status-conditional')->critical()
            ->text('Output status depends on validation outcome: 1) PASSED + no tasks created → "validated", 2) Tasks created for fixes → "pending". Status "validated" means work is COMPLETE and verified.')
            ->why('If fix tasks were created, work is NOT done - task returns to pending queue. Only when validation passes completely (no critical issues, no missing requirements, no tasks created) can status be "validated".')
            ->onViolation('Check CREATED_TASKS.count: if > 0 → set "pending", if === 0 AND passed → set "validated". NEVER set "validated" when fix tasks exist.');

        // CRITICAL: Fix task parent_id assignment
        // Common rule from trait
        $this->defineFixTaskParentRule();

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        // Common guideline from trait
        $this->defineInputCaptureGuideline();

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task using $VECTOR_TASK_ID (already parsed from input), verify validatable status')
            ->example()
            ->phase(Operator::output([
                '=== TASK:VALIDATE ACTIVATED ===',
                '',
                '=== PHASE 0: VECTOR TASK LOADING ===',
                'Loading task #{$VECTOR_TASK_ID}...',
            ]))
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK', '{task object with title, content, status, parent_id, priority, tags}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status NOT IN ["completed", "tested", "validated", "in_progress"]', [
                Operator::output([
                    '=== VALIDATION BLOCKED ===',
                    'Task #$VECTOR_TASK_ID has status: {$VECTOR_TASK.status}',
                    'Only tasks with status completed/tested/validated can be validated.',
                    'Run /task:async $VECTOR_TASK_ID to complete first.',
                ]),
                'ABORT validation',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                Operator::note('Check status_history for session crash indicator'),
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                    Operator::output([
                        '⚠️ SESSION RECOVERY DETECTED',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Continuing validation without status change.',
                        'NOTE: Previous session vector memory findings should be treated with caution.',
                    ]),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== VALIDATION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another session.',
                        'Wait for completion or use /task:async to take over.',
                    ]),
                    'ABORT validation',
                ]),
            ]))
            ->phase(Operator::note('CRITICAL: Set TASK_PARENT_ID to the CURRENTLY validated task ID IMMEDIATELY after loading. This ensures fix tasks become children of the task being validated, NOT grandchildren.'))
            ->phase(Store::as('TASK_PARENT_ID', '{$VECTOR_TASK_ID}'))
            ->phase(Operator::note('TASK_PARENT_ID = $VECTOR_TASK_ID (the task we are validating NOW). Any fix tasks created will be children of THIS task, regardless of whether this task itself has a parent.'))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                Operator::note('Fetching parent task FOR CONTEXT DISPLAY ONLY. This DOES NOT change TASK_PARENT_ID.'),
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK_CONTEXT',
                    '{parent task for display context only - NOT for parent_id assignment}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 50}'))
            ->phase(Store::as('SUBTASKS', '{list of subtasks}'))
            ->phase(Store::as('TASK_DESCRIPTION', '{$VECTOR_TASK.title + $VECTOR_TASK.content}'))
            ->phase(Operator::note('Calculate SIMPLE_VALIDATION flag based on task characteristics'))
            ->phase(Store::as('SIMPLE_VALIDATION', '{true IF: $VECTOR_TASK.estimate <= 4 AND $VECTOR_TASK.priority != "critical" AND $VECTOR_TASK.tags NOT contains ["architecture", "security", "breaking-change"] AND $SUBTASKS.count <= 2}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent context: {$PARENT_TASK_CONTEXT.title or "none"}',
                'Subtasks: {$SUBTASKS.count}',
                'Simple validation mode: {$SIMPLE_VALIDATION}',
                'Fix tasks parent_id will be: $TASK_PARENT_ID (THIS task)',
            ]));

        // Phase 1: Agent Discovery and Validation Scope Preview
        $this->guideline('phase1-context-preview')
            ->goal('Discover available agents and present validation scope for approval')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 1: VALIDATION PREVIEW ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents with capabilities'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agent_id: description mapping}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'),
                'Get documentation INDEX preview'))
            ->phase(Store::as('DOCS_PREVIEW', 'Documentation files available'))
            ->phase(Operator::output([
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Available agents: {$AVAILABLE_AGENTS.count}',
                'Documentation files: {$DOCS_PREVIEW.count}',
                '',
                'Validation will delegate to agents:',
                '1. VectorMaster - deep memory research for context',
                '2. DocumentationMaster - requirements extraction',
                '3. Selected agents - parallel validation (5 aspects)',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output([
                    '',
                    '⚠️  APPROVAL REQUIRED',
                    '✅ approved/yes - start validation | ❌ no/modifications',
                ]),
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['✅ Auto-approved via -y flag']),
            ]))
            ->phase('After approval (manual or auto) - set task in_progress (validation IS execution)')
            ->phase(VectorTaskMcp::call('task_update',
                '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Validation started after approval", append_comment: true}'))
            ->phase(Operator::output(['📋 Vector task #{$VECTOR_TASK_ID} started (validation phase)']));

        // Phase 2: Deep Context Gathering via VectorMaster Agent (CONDITIONAL)
        $this->guideline('phase2-context-gathering')
            ->goal('Delegate deep memory research to VectorMaster agent (COMPLEX only) or do lightweight memory check (SIMPLE)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: CONTEXT GATHERING ===',
            ]))
            ->phase(Operator::if('$IS_SESSION_RECOVERY === true', [
                Operator::note('CAUTION: This is a session recovery. Vector memory findings from the crashed session should be treated with skepticism - previous context is incomplete. Verify findings against current codebase state before relying on them.'),
            ]))
            ->phase(Operator::if('$SIMPLE_VALIDATION === true', [
                Operator::note('SIMPLE mode: Lightweight memory check without agent delegation'),
                VectorMemoryMcp::call('search_memories', '{query: "$TASK_DESCRIPTION", limit: 5}'),
                Store::as('MEMORY_CONTEXT', '{direct memory search results}'),
                Operator::output([
                    'Simple mode: Direct memory search',
                    '- Memory insights: {$MEMORY_CONTEXT.count}',
                ]),
            ]))
            ->phase(Operator::if('$SIMPLE_VALIDATION === false', [
                Operator::note('COMPLEX mode: Full agent delegation for deep research'),
                'SELECT vector-master from $AVAILABLE_AGENTS',
                Store::as('CONTEXT_AGENT', '{vector-master agent_id}'),
                TaskTool::agent('{$CONTEXT_AGENT}',
                    'DEEP MEMORY RESEARCH for validation of task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Multi-probe search: implementation patterns, requirements, architecture decisions, past validations, bug fixes 2) Search across categories: code-solution, architecture, learning, bug-fix 3) Extract actionable insights for validation 4) Return: {implementations: [...], requirements: [...], patterns: [...], past_validations: [...], key_insights: [...]}. Store consolidated context.'),
                Store::as('MEMORY_CONTEXT', '{VectorMaster agent results}'),
                Operator::output([
                    'Context gathered via {$CONTEXT_AGENT}:',
                    '- Memory insights: {$MEMORY_CONTEXT.key_insights.count}',
                ]),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{query: "$TASK_DESCRIPTION", limit: 10}'))
            ->phase(Store::as('RELATED_TASKS', 'Related vector tasks'))
            ->phase(Operator::output([
                '- Related tasks: {$RELATED_TASKS.count}',
            ]));

        // Phase 3: Documentation Requirements Extraction (CONDITIONAL)
        $this->guideline('phase3-documentation-extraction')
            ->goal('Extract requirements from .docs/ - via agent (COMPLEX) or direct read (SIMPLE)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: DOCUMENTATION REQUIREMENTS ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from $TASK_DESCRIPTION}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', 'Documentation file paths'))
            ->phase(Operator::if('$DOCS_INDEX empty', [
                Store::as('DOCUMENTATION_REQUIREMENTS', '[]'),
                Operator::output(['WARNING: No documentation found. Validation will be limited.']),
            ]))
            ->phase(Operator::if('$DOCS_INDEX not empty AND $SIMPLE_VALIDATION === true', [
                Operator::note('SIMPLE mode: Direct doc read without agent delegation'),
                'Read documentation files directly using Read tool',
                Store::as('DOCUMENTATION_REQUIREMENTS', '{extracted requirements from direct read}'),
                Operator::output([
                    'Simple mode: Direct documentation read',
                    'Requirements extracted: {$DOCUMENTATION_REQUIREMENTS.count}',
                ]),
            ]))
            ->phase(Operator::if('$DOCS_INDEX not empty AND $SIMPLE_VALIDATION === false', [
                Operator::note('COMPLEX mode: Full agent delegation for thorough extraction'),
                TaskTool::agent('documentation-master',
                    'Extract ALL requirements, acceptance criteria, constraints, and specifications from documentation files: {$DOCS_INDEX paths}. Return structured list: [{requirement_id, description, acceptance_criteria, related_files, priority}]. Store to vector memory.'),
                Store::as('DOCUMENTATION_REQUIREMENTS', '{structured requirements list}'),
                Operator::output([
                    'Requirements extracted via documentation-master: {$DOCUMENTATION_REQUIREMENTS.count}',
                    '{requirements summary}',
                ]),
            ]));

        // Phase 4: Dynamic Agent Selection and Parallel Validation
        $this->guideline('phase4-parallel-validation')
            ->goal('Select best agents from $AVAILABLE_AGENTS and launch parallel validation (scaled to complexity)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 4: PARALLEL VALIDATION ===',
            ]))
            ->phase(Operator::if('$SIMPLE_VALIDATION === true', [
                Operator::note('SIMPLE mode: 2 agents (completeness + tests) - sufficient for low-complexity tasks'),
                'AGENT SELECTION (SIMPLE): Select agents for core validation:',
                Operator::do([
                    'ASPECT 1 - COMPLETENESS: Select agent for requirements verification',
                    'ASPECT 2 - TEST COVERAGE: Select agent for test analysis',
                ]),
                Store::as('SELECTED_AGENTS', '{completeness: agent_id, tests: agent_id}'),
                Operator::output([
                    'Simple validation mode: 2 agents selected',
                    '{$SELECTED_AGENTS mapping}',
                    '',
                    'Launching validation agents in parallel...',
                ]),
                'PARALLEL BATCH (SIMPLE): Launch 2 agents with inline cosmetic fix capability',
                Operator::do([
                    TaskTool::agent('{$SELECTED_AGENTS.completeness}',
                        'COMPLETENESS CHECK: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search vector memory for requirements 2) Scan codebase for implementation evidence 3) Map requirements to code. COSMETIC FIX RULE: If you find whitespace/indentation/formatting issues - FIX THEM IMMEDIATELY using Edit tool, then continue. Do NOT report cosmetic issues as findings. Return: [{requirement_id, status: implemented|partial|missing, evidence: file:line, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.tests}',
                        'TEST COVERAGE CHECK: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Discover related test files 2) Analyze coverage gaps 3) Run tests if possible. COSMETIC FIX RULE: If you find whitespace/indentation/formatting issues in test files - FIX THEM IMMEDIATELY using Edit tool, then continue. Do NOT report cosmetic issues as findings. Return: [{test_file, coverage_status, missing_scenarios, cosmetic_fixes_applied: N}]. Store findings.'),
                ]),
            ]))
            ->phase(Operator::if('$SIMPLE_VALIDATION === false', [
                Operator::note('COMPLEX mode: 5 agents (full coverage) - comprehensive validation for critical/large tasks'),
                'AGENT SELECTION (COMPLEX): Analyze $AVAILABLE_AGENTS descriptions and select BEST agent for each validation aspect:',
                Operator::do([
                    'ASPECT 1 - COMPLETENESS: Select agent best suited for requirements verification (vector-master for memory research, explore for codebase)',
                    'ASPECT 2 - CODE CONSISTENCY: Select agent for code pattern analysis (explore for codebase scanning)',
                    'ASPECT 3 - TEST COVERAGE: Select agent for test analysis (explore for test file discovery)',
                    'ASPECT 4 - DOCUMENTATION SYNC: Select agent for documentation analysis (documentation-master if docs-focused, explore otherwise)',
                    'ASPECT 5 - DEPENDENCIES: Select agent for dependency analysis (explore for import scanning)',
                ]),
                Store::as('SELECTED_AGENTS', '{aspect: agent_id mapping based on $AVAILABLE_AGENTS}'),
                Operator::output([
                    'Complex validation mode: 5 agents selected',
                    '{$SELECTED_AGENTS mapping}',
                    '',
                    'Launching validation agents in parallel...',
                ]),
                'PARALLEL BATCH (COMPLEX): Launch agents with inline cosmetic fix capability',
                Operator::do([
                    TaskTool::agent('{$SELECTED_AGENTS.completeness}',
                        'DEEP RESEARCH - COMPLETENESS: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search vector memory for past implementations and requirements 2) Scan codebase for implementation evidence 3) Map each requirement from {$DOCUMENTATION_REQUIREMENTS} to code. COSMETIC FIX RULE: If you find whitespace/indentation/formatting issues - FIX THEM IMMEDIATELY using Edit tool, then continue. Do NOT report cosmetic issues. Return: [{requirement_id, status: implemented|partial|missing, evidence: file:line, memory_refs: [...], cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.consistency}',
                        'DEEP RESEARCH - CODE CONSISTENCY: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for project coding standards 2) Scan related files for pattern violations 3) Check naming, architecture, style consistency. COSMETIC FIX RULE: If you find whitespace/indentation/formatting issues - FIX THEM IMMEDIATELY using Edit tool, then continue. Only report FUNCTIONAL consistency issues. Return: [{file, issue_type, severity, description, suggestion, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.tests}',
                        'DEEP RESEARCH - TEST COVERAGE: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for test patterns 2) Discover all related test files 3) Analyze coverage gaps 4) Run tests if possible. COSMETIC FIX RULE: If you find whitespace/indentation/formatting issues in tests - FIX THEM IMMEDIATELY using Edit tool, then continue. Return: [{test_file, coverage_status, missing_scenarios, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.docs}',
                        'DEEP RESEARCH - DOCUMENTATION SYNC: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for documentation standards 2) Compare code vs documentation 3) Check docblocks, README, API docs. COSMETIC FIX RULE: If you find typos, formatting issues in docs - FIX THEM IMMEDIATELY using Edit tool, then continue. Only report CONTENT gaps. Return: [{doc_type, sync_status, gaps, cosmetic_fixes_applied: N}]. Store findings.'),
                    TaskTool::agent('{$SELECTED_AGENTS.deps}',
                        'DEEP RESEARCH - DEPENDENCIES: For task #{$VECTOR_TASK_ID} "{$TASK_DESCRIPTION}": 1) Search memory for dependency issues 2) Scan imports and dependencies 3) Check for broken/unused/circular refs. COSMETIC FIX RULE: If you find whitespace/formatting issues in import sections - FIX THEM IMMEDIATELY using Edit tool. Return: [{file, dependency_issue, severity, cosmetic_fixes_applied: N}]. Store findings.'),
                ]),
            ]))
            ->phase(Store::as('VALIDATION_BATCH', '{results from all agents}'))
            ->phase(Operator::output([
                'Batch complete: {$SELECTED_AGENTS.count} validation checks finished',
            ]));

        // Phase 5: Results Aggregation and Analysis
        $this->guideline('phase5-results-aggregation')
            ->goal('Aggregate all validation results - only FUNCTIONAL issues (cosmetic already fixed by agents inline)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 5: RESULTS AGGREGATION ===',
            ]))
            ->phase('Merge results from all validation agents (cosmetic issues already fixed inline)')
            ->phase(Store::as('ALL_ISSUES', '{merged FUNCTIONAL issues from all agents}'))
            ->phase(Store::as('TOTAL_COSMETIC_FIXES', '{sum of cosmetic_fixes_applied from all agents}'))
            ->phase('Categorize FUNCTIONAL issues (require tasks):')
            ->phase(Store::as('CRITICAL_ISSUES', '{issues with severity: critical - code logic, security, architecture}'))
            ->phase(Store::as('MAJOR_ISSUES', '{issues with severity: major - functionality, tests, dependencies}'))
            ->phase(Store::as('MINOR_ISSUES', '{issues with severity: minor - code style affecting logic, naming conventions}'))
            ->phase(Store::as('MISSING_REQUIREMENTS', '{requirements not implemented}'))
            ->phase(Store::as('FUNCTIONAL_ISSUES_COUNT', '{$CRITICAL_ISSUES.count + $MAJOR_ISSUES.count + $MINOR_ISSUES.count + $MISSING_REQUIREMENTS.count}'))
            ->phase(Operator::output([
                'Validation results:',
                '- Critical issues: {$CRITICAL_ISSUES.count}',
                '- Major issues: {$MAJOR_ISSUES.count}',
                '- Minor issues: {$MINOR_ISSUES.count}',
                '- Missing requirements: {$MISSING_REQUIREMENTS.count}',
                '- Cosmetic fixes applied inline: {$TOTAL_COSMETIC_FIXES}',
                '',
                'Functional issues requiring tasks: {$FUNCTIONAL_ISSUES_COUNT}',
            ]));

        // Phase 6: Task Creation for FUNCTIONAL Issues Only (Consolidated 5-8h Tasks)
        $this->guideline('phase6-task-creation')
            ->goal('Create consolidated tasks (5-8h each) for FUNCTIONAL issues with comprehensive context (cosmetic issues already auto-fixed)')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 6: TASK CREATION (CONSOLIDATED) ===',
            ]))
            ->phase(Operator::note('CRITICAL VERIFICATION: Confirm TASK_PARENT_ID before creating any tasks'))
            ->phase(Operator::verify([
                '$TASK_PARENT_ID === $VECTOR_TASK_ID',
                'TASK_PARENT_ID is the ID of the task we are validating (NOT its parent)',
            ]))
            ->phase(Operator::output([
                'Fix tasks will have parent_id: $TASK_PARENT_ID (Task #{$VECTOR_TASK_ID})',
            ]))
            ->phase('Check existing tasks to avoid duplicates')
            ->phase(VectorTaskMcp::call('task_list', '{query: "fix issues $TASK_DESCRIPTION", limit: 20}'))
            ->phase(Store::as('EXISTING_FIX_TASKS', 'Existing fix tasks'))
            ->phase(Operator::note('Phase 6 processes ONLY functional issues. Cosmetic issues were auto-fixed in Phase 5.5'))
            ->phase(Operator::if('$FUNCTIONAL_ISSUES_COUNT === 0', [
                Operator::output(['No functional issues to create tasks for. Proceeding to Phase 7...']),
                'SKIP to Phase 7',
            ]))
            ->phase('CONSOLIDATION STRATEGY: Group FUNCTIONAL issues into 5-8 hour task batches')
            ->phase(Operator::do([
                'Calculate total estimate for FUNCTIONAL issues only:',
                '- Critical issues: ~2h per issue (investigation + fix + test)',
                '- Major issues: ~1.5h per issue (fix + verify)',
                '- Minor issues: ~0.5h per issue (fix + verify)',
                '- Missing requirements: ~4h per requirement (implement + test)',
                '(Cosmetic issues NOT included - already auto-fixed)',
                Store::as('TOTAL_ESTIMATE', '{sum of FUNCTIONAL issue estimates in hours}'),
            ]))
            ->phase(Operator::if('$TOTAL_ESTIMATE <= 8', [
                'ALL issues fit into ONE consolidated task (5-8h range)',
                Operator::if('($CRITICAL_ISSUES.count + $MAJOR_ISSUES.count + $MINOR_ISSUES.count + $MISSING_REQUIREMENTS.count) > 0 AND NOT exists similar in $EXISTING_FIX_TASKS',
                    [
                        VectorTaskMcp::call('task_create', '{
                        title: "Validation fixes: task #{$VECTOR_TASK_ID}",
                        content: "Consolidated validation findings for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}.\\n\\nTotal estimate: {$TOTAL_ESTIMATE}h\\n\\n## Critical Issues ({$CRITICAL_ISSUES.count})\\n{FOR each issue: - [{issue.severity}] {issue.description}\\n  File: {issue.file}:{issue.line}\\n  Type: {issue.type}\\n  Suggestion: {issue.suggestion}\\n  Memory refs: {issue.memory_refs}\\n}\\n\\n## Major Issues ({$MAJOR_ISSUES.count})\\n{FOR each issue: - [{issue.severity}] {issue.description}\\n  File: {issue.file}:{issue.line}\\n  Type: {issue.type}\\n  Suggestion: {issue.suggestion}\\n  Memory refs: {issue.memory_refs}\\n}\\n\\n## Minor Issues ({$MINOR_ISSUES.count})\\n{FOR each issue: - [{issue.severity}] {issue.description}\\n  File: {issue.file}:{issue.line}\\n  Type: {issue.type}\\n  Suggestion: {issue.suggestion}\\n  Memory refs: {issue.memory_refs}\\n}\\n\\n## Missing Requirements ({$MISSING_REQUIREMENTS.count})\\n{FOR each req: - {req.description}\\n  Acceptance criteria: {req.acceptance_criteria}\\n  Related files: {req.related_files}\\n  Priority: {req.priority}\\n}\\n\\n## Context References\\n- Parent task: #{$VECTOR_TASK_ID}\\n- Memory IDs: {$MEMORY_CONTEXT.memory_ids}\\n- Related tasks: {$RELATED_TASKS.ids}\\n- Documentation: {$DOCS_INDEX.paths}\\n- Validation agents used: {$SELECTED_AGENTS}",
                        priority: "{$CRITICAL_ISSUES.count > 0 ? high : medium}",
                        estimate: $TOTAL_ESTIMATE,
                        tags: ["validation-fix", "consolidated"],
                        parent_id: $TASK_PARENT_ID
                    }'),
                        Store::as('CREATED_TASKS[]', '{task_id}'),
                        Operator::output(['Created consolidated task: Validation fixes ({$TOTAL_ESTIMATE}h, {issues_count} issues)']),
                    ]),
            ]))
            ->phase(Operator::if('$TOTAL_ESTIMATE > 8', [
                'Split into multiple 5-8h task batches',
                Store::as('BATCH_SIZE', '6'),
                Store::as('NUM_BATCHES', '{ceil($TOTAL_ESTIMATE / 6)}'),
                'Group issues by priority (critical first) into batches of ~6h each',
                Operator::forEach('batch_index in range(1, $NUM_BATCHES)', [
                    Store::as('BATCH_ISSUES', '{slice of issues for this batch, ~6h worth, priority-ordered}'),
                    Store::as('BATCH_ESTIMATE', '{sum of batch issue estimates}'),
                    Store::as('BATCH_CRITICAL', '{count of critical issues in batch}'),
                    Store::as('BATCH_MAJOR', '{count of major issues in batch}'),
                    Store::as('BATCH_MISSING', '{count of missing requirements in batch}'),
                    Operator::if('NOT exists similar in $EXISTING_FIX_TASKS', [
                        VectorTaskMcp::call('task_create', '{
                            title: "Validation fixes batch {batch_index}/{$NUM_BATCHES}: task #{$VECTOR_TASK_ID}",
                            content: "Validation batch {batch_index} of {$NUM_BATCHES} for task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}.\\n\\nBatch estimate: {$BATCH_ESTIMATE}h\\nBatch composition: {$BATCH_CRITICAL} critical, {$BATCH_MAJOR} major, {$BATCH_MISSING} missing reqs\\n\\n## Issues in this batch\\n{FOR each issue in $BATCH_ISSUES:\\n### [{issue.severity}] {issue.title}\\n- File: {issue.file}:{issue.line}\\n- Type: {issue.type}\\n- Description: {issue.description}\\n- Suggestion: {issue.suggestion}\\n- Evidence: {issue.evidence}\\n- Memory refs: {issue.memory_refs}\\n}\\n\\n## Full Context References\\n- Parent task: #{$VECTOR_TASK_ID}\\n- Memory IDs: {$MEMORY_CONTEXT.memory_ids}\\n- Related tasks: {$RELATED_TASKS.ids}\\n- Documentation: {$DOCS_INDEX.paths}\\n- Total batches: {$NUM_BATCHES} ({$TOTAL_ESTIMATE}h total)\\n- Validation agents: {$SELECTED_AGENTS}",
                            priority: "{$BATCH_CRITICAL > 0 ? high : medium}",
                            estimate: $BATCH_ESTIMATE,
                            tags: ["validation-fix", "batch-{batch_index}"],
                            parent_id: $TASK_PARENT_ID
                        }'),
                        Store::as('CREATED_TASKS[]', '{task_id}'),
                        Operator::output(['Created batch {batch_index}/{$NUM_BATCHES}: {$BATCH_ESTIMATE}h ({$BATCH_ISSUES.count} issues)']),
                    ]),
                ]),
            ]))
            ->phase(Operator::output([
                'Tasks created: {$CREATED_TASKS.count} (total estimate: {$TOTAL_ESTIMATE}h)',
            ]));

        // Task Consolidation Rules
        $this->rule('task-size-5-8h')->high()
            ->text('Each created task MUST have estimate between 5-8 hours. Never create tasks < 5h (consolidate) or > 8h (split).')
            ->why('Optimal task size for focused work sessions. Too small = context switching overhead. Too large = hard to track progress.')
            ->onViolation('Merge small issues into consolidated task OR split large task into 5-8h batches.');

        $this->rule('task-comprehensive-context')->critical()
            ->text('Each task MUST include: all file:line references, memory IDs, related task IDs, documentation paths, detailed issue descriptions with suggestions, evidence from validation.')
            ->why('Enables full context restoration without re-exploration. Saves agent time on task pickup.')
            ->onViolation('Add missing context references before creating task.');

        // Phase 7: Validation Completion
        $this->guideline('phase7-completion')
            ->goal('Complete validation, update task status, store summary to memory')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 7: VALIDATION COMPLETE ===',
            ]))
            ->phase(Store::as('VALIDATION_SUMMARY', '{all_issues_count, tasks_created_count, pass_rate}'))
            ->phase(Store::as('VALIDATION_STATUS',
                Operator::if('$CRITICAL_ISSUES.count === 0 AND $MISSING_REQUIREMENTS.count === 0', 'PASSED',
                    'NEEDS_WORK')))
            ->phase(VectorMemoryMcp::call('store_memory',
                '{content: "Validation of task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nStatus: {$VALIDATION_STATUS}\\nCritical: {$CRITICAL_ISSUES.count}\\nMajor: {$MAJOR_ISSUES.count}\\nMinor: {$MINOR_ISSUES.count}\\nTasks created: {$CREATED_TASKS.count}\\n\\nFindings:\\n{summary of key findings}", category: "code-solution", tags: ["validation", "audit", "task:validate"]}'))
            ->phase(Operator::if('$VALIDATION_STATUS === "PASSED" AND $CREATED_TASKS.count === 0', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "validated", comment: "Validation PASSED. All requirements implemented, no issues found.", append_comment: true}'),
                Operator::output(['✅ Task #{$VECTOR_TASK_ID} marked as VALIDATED']),
            ]))
            ->phase(Operator::if('$CREATED_TASKS.count > 0', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Validation found issues. Created {$CREATED_TASKS.count} fix tasks: Critical: {$CRITICAL_ISSUES.count}, Major: {$MAJOR_ISSUES.count}, Minor: {$MINOR_ISSUES.count}, Missing: {$MISSING_REQUIREMENTS.count}. Returning to pending - fix tasks must be completed before re-validation.", append_comment: true}'),
                Operator::output(['⏳ Task #{$VECTOR_TASK_ID} returned to PENDING ({$CREATED_TASKS.count} fix tasks required before re-validation)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== VALIDATION REPORT ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VALIDATION_STATUS}',
                '',
                '| Metric | Count |',
                '|--------|-------|',
                '| Critical issues | {$CRITICAL_ISSUES.count} |',
                '| Major issues | {$MAJOR_ISSUES.count} |',
                '| Minor issues | {$MINOR_ISSUES.count} |',
                '| Missing requirements | {$MISSING_REQUIREMENTS.count} |',
                '| Cosmetic fixes (inline) | {$TOTAL_COSMETIC_FIXES} |',
                '| Tasks created | {$CREATED_TASKS.count} |',
                '',
                '{IF $TOTAL_COSMETIC_FIXES > 0: "✅ Cosmetic issues fixed inline by validation agents"}',
                '{IF $CREATED_TASKS.count > 0: "Follow-up tasks: {$CREATED_TASKS}"}',
                '',
                'Validation stored to vector memory.',
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Graceful error handling for validation process')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'Abort validation',
            ])
            ->phase()->if('vector task not in validatable status', [
                'Report: "Vector task #{id} status is {status}, not completed/tested/validated"',
                'Suggest: Run /task:async #{id} first',
                'Abort validation',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use /do:validate for text-based validation"',
                'Abort command',
            ])
            ->phase()->if('no documentation found', [
                'Warn: "No documentation in .docs/ for this task"',
                'Continue with limited validation (code-only checks)',
            ])
            ->phase()->if('agent validation fails', [
                'Log: "Validation agent {N} failed: {error}"',
                'Continue with remaining agents',
                'Report partial validation in summary',
            ])
            ->phase()->if('task creation fails', [
                'Log: "Failed to create task: {error}"',
                'Store issue details to vector memory for manual review',
                'Continue with remaining tasks',
            ]);

        // Constraints and Validation
        $this->guideline('constraints')
            ->text('Validation constraints and limits')
            ->example()
            ->phase('Max 6 parallel validation agents per batch')
            ->phase('Max 20 tasks created per validation run')
            ->phase('Validation timeout: 5 minutes per agent')
            ->phase(Operator::verify([
                'vector_task_loaded = true',
                'validatable_status_verified = true',
                'parallel_agents_used = true',
                'documentation_checked = true',
                'results_stored_to_memory = true',
                'no_direct_fixes = true',
            ]));

        // Examples
        $this->guideline('example-simple-validation')
            ->scenario('Validate completed vector task')
            ->example()
            ->phase('input', '"task 15" or "#15" where task #15 is "Implement user login"')
            ->phase('load', 'task_get(15) → title, content, status: completed')
            ->phase('flow',
                'Task Loading → Context → Docs → Parallel Validation (5 agents) → Aggregate → Create Tasks → Complete')
            ->phase('result', 'Validation PASSED → status: validated OR NEEDS_WORK → N fix tasks created');

        $this->guideline('example-with-fixes')
            ->scenario('Validation finds issues')
            ->example()
            ->phase('input', '"#28" where task #28 has status: completed')
            ->phase('validation', 'Found: 2 critical, 3 major, 1 missing requirement')
            ->phase('tasks', 'Created 1 consolidated fix task (6h estimate)')
            ->phase('result', 'Task #28 status → pending, 1 fix task created as child');

        $this->guideline('example-rerun')
            ->scenario('Re-run validation (idempotent)')
            ->example()
            ->phase('input', '"task 15" (already validated before)')
            ->phase('behavior', 'Skips existing tasks, only creates NEW issues found')
            ->phase('result', 'Same/updated validation report, no duplicate tasks');

        // When to use task:validate vs do:validate
        $this->guideline('validate-vs-do-validate')
            ->text('When to use /task:validate vs /do:validate')
            ->example()
            ->phase('USE /task:validate',
                'Validate specific vector task by ID (15, #15, task 15). Best for: systematic task-based workflow, hierarchical task management, fix task creation as children.')
            ->phase('USE /do:validate',
                'Validate work by text description ("validate user authentication"). Best for: ad-hoc validation, exploratory validation, no existing vector task.');

        // Response Format
        $this->guideline('response-format')
            ->text('=== headers | Parallel: agent batch indicators | Tables: validation results | No filler | Created tasks listed | 📋 task ID references');
    }
}
