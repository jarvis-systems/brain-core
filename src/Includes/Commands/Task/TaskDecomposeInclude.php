<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Task decomposition into subtasks. 2 parallel agents research (code + memory), plans logical execution order, creates subtasks. NEVER executes - only creates.')]
class TaskDecomposeInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // =========================================================================
        // IRON EXECUTION RULES (execute immediately, no verbose)
        // =========================================================================

        $this->defineStatusSemanticsRule();
        $this->rule('task-get-first')->critical()
            ->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze how to decompose.');
        $this->defineIronExecutionRules();

        $this->rule('understand-to-decompose')->critical()
            ->text('MUST understand task INTENT to decompose properly. Analyze: what are logical boundaries? what depends on what? Unknown library/pattern → context7 first.');

        // AUTO-APPROVE & WORKFLOW ATOMICITY (from trait)
        $this->defineAutoApprovalRules();

        // DOCUMENTATION IS LAW (from trait - prevents stupid questions)
        $this->defineDocumentationIsLawRules();

        // CODEBASE PATTERN REUSE (from trait - consistency in decomposition)
        $this->defineCodebasePatternReuseRule();

        // IMPACT RADIUS (from trait - helps scope subtasks by blast radius)
        $this->defineImpactRadiusAnalysisRule();

        // PERFORMANCE AWARENESS (from trait - identify performance-critical subtasks)
        $this->definePerformanceAwarenessRule();

        // COMMENT CONTEXT (from trait - read accumulated context from task.comment)
        $this->defineCommentContextRules();

        $this->rule('docs-define-structure')->critical()
            ->text('Documentation defines STRUCTURE for decomposition. If docs describe modules/components/phases → decompose ACCORDING TO DOCS. Code exploration is SECONDARY.')
            ->why('Docs contain planned architecture. Code may be incomplete WIP. Decomposing by code misses planned structure.')
            ->onViolation('Read docs FIRST. Decompose per documented structure. Code exploration fills gaps.');

        // =========================================================================
        // DECOMPOSITION-SPECIFIC RULES
        // =========================================================================

        $this->rule('always-decompose')->critical()
            ->text('If this command was called — you MUST decompose. NEVER refuse, NEVER say "decomposition not recommended", NEVER exit without creating subtasks. Even small/atomic tasks have logical steps — find them and create subtasks. Automated workflows depend on subtask creation to proceed. Refusing = infinite retry loop.')
            ->why('Automated orchestration calls decompose based on estimate threshold. Exit without subtasks = automation retries endlessly. Your job is to decompose, not to judge whether decomposition is needed.')
            ->onViolation('Find logical steps in ANY task and create subtasks. 1.5h task with 3 steps = 3 subtasks of 30min.');

        $this->rule('create-only')->critical()
            ->text('This command ONLY creates subtasks. NEVER execute any subtask after creation.')
            ->why('Decomposition and execution are separate concerns. User decides what to execute next.')
            ->onViolation('STOP immediately after subtask creation. Return control to user.');

        $this->defineParentIdMandatoryRule('TASK_ID');

        // Common rule from trait
        $this->defineMandatoryUserApprovalRule();

        $this->rule('order-mandatory')->critical()
            ->text('EVERY subtask MUST have unique order (1,2,3,4) AND explicit parallel flag. Independent subtasks that CAN run concurrently = parallel: true. Dependent subtasks = parallel: false.')
            ->why('Order defines strict sequence. Parallel flag enables executor to run independent tasks concurrently without re-analyzing dependencies.')
            ->onViolation('Set order (unique) + parallel (bool) in EVERY task_create call. Never omit either.');

        $this->rule('sequence-analysis')->critical()
            ->text('When creating 2+ subtasks: STOP and THINK about optimal sequence. Use SequentialThinking to analyze dependencies before setting order and parallel flags.')
            ->why('Wrong sequence wastes time. Wrong parallel marking causes race conditions.')
            ->onViolation('Use SequentialThinking to analyze dependencies. Set order + parallel before creation.');

        // PARALLEL ISOLATION (from trait - strict criteria for parallel: true)
        $this->defineParallelIsolationRules();
        $this->defineParallelIsolationChecklistGuideline();

        // TAG TAXONOMY (from trait - predefined tags for tasks and memory)
        $this->defineTagTaxonomyRules();

        $this->rule('file-scope-in-content')->critical()
            ->text('When creating subtasks: task content MUST include explicit file scope: "FILES: [file1.php, file2.php, ...]" from file_manifest. For parallel: true subtasks ALSO include: "PARALLEL: this task may execute concurrently with siblings. Stay within listed file scope." Without explicit files, executors guess scope and parallel conflict detection fails.')
            ->why('Parallel execution awareness reads file scopes from task content and comments. Decompose is the ONLY place where planned file scope is known before execution. If not included in content, the entire parallel safety chain starts blind.')
            ->onViolation('Add "FILES: [...]" to content of EVERY subtask. Add "PARALLEL: ..." note for every parallel: true subtask.');

        $this->rule('logical-order')->high()
            ->text('Subtasks MUST be in logical execution order. Dependencies first, dependents after.')
            ->why('Prevents blocked work. User can execute subtasks sequentially without dependency issues.')
            ->onViolation('Reorder subtasks. Use SequentialThinking for complex dependencies.');

        $this->rule('no-test-quality-subtasks')->critical()
            ->text('FORBIDDEN: Creating subtasks for "Write tests", "Add test coverage", "Run quality gates", "Code quality checks", "Verify implementation", or similar. These are ALREADY handled automatically: 1) Executors (sync/async) write tests during implementation (>=80% coverage, edge cases). 2) Validators run ALL quality gates and check coverage. Decompose ONLY into functional work units.')
            ->why('Each executor writes tests inline. Each validator runs quality gates. Separate test/quality subtasks are always redundant — executor sees them and says "already done", wasting tokens and time.')
            ->onViolation('Remove test/quality/verification subtasks from plan. Tests are part of EACH implementation subtask, not a separate subtask.');

        $this->rule('exclude-brain-directory')->high()
            ->text('NEVER analyze '.Runtime::BRAIN_DIRECTORY.' when decomposing code tasks.')
            ->why('Brain system internals are not project code.')
            ->onViolation('Skip '.Runtime::BRAIN_DIRECTORY.' in all exploration.');

        // =========================================================================
        // INPUT CAPTURE
        // =========================================================================

        $this->defineInputCaptureWithTaskIdGuideline();

        // =========================================================================
        // WORKFLOW (single unified flow)
        // =========================================================================

        $this->guideline('workflow')
            ->goal('Decompose task into subtasks: load → research → plan → approve → create')
            ->example()

            // Stage 1: Load
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $TASK_ID}') . ' → ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found')))

            // Extract comment context (accumulated inter-session history)
            ->phase(Store::as('COMMENT_CONTEXT', '{parsed from $TASK.comment: memory_ids: [#NNN], file_paths: [...], execution_history: [...], failures: [...], blockers: [...], decisions: [], mode_flags: []}'))

            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID, limit: 50}') . ' → ' . Store::as('EXISTING_SUBTASKS'))
            ->phase(Operator::if('EXISTING_SUBTASKS.count > 0 AND NOT $HAS_AUTO_APPROVE', 'Ask: "(1) Add more, (2) Replace all, (3) Abort"'))

            // Mark in_progress while decomposing (orchestrator owns status, not agents)
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $TASK_ID, status: "in_progress", comment: "Started decomposition", append_comment: true}'))

            // Stage 2: Documentation (PRIMARY source for structure)
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords from task}')) . ' → ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if(Store::get('DOCS_INDEX') . ' found', [
                ReadTool::call('{doc_paths}') . ' → ' . Store::as('DOCUMENTATION'),
                'DOCUMENTATION defines decomposition structure: modules, components, phases, dependencies',
            ]))

            // Stage 3: Research (parallel - code exploration is SECONDARY)
            ->phase(Operator::if('unknown library/pattern in task', Context7Mcp::call('query-docs', '{query: "{library/pattern}"}') . ' → understand before decomposing'))
            ->phase(Operator::parallel([
                TaskTool::agent('explore', '
ABSOLUTE PROHIBITION — READ-ONLY AGENT:
× NEVER call mcp__vector-task__task_update or any vector-task write tool
× You are a READ-ONLY researcher — report findings via JSON output ONLY
× Task status is managed EXCLUSIVELY by the orchestrator, NOT by you

DECOMPOSE RESEARCH for task #{$TASK.id}.

COMMENT CONTEXT (previous sessions): {$COMMENT_CONTEXT}
- Use memory IDs to fetch prior findings. Respect decisions already made. Avoid approaches that already failed.

DOCUMENTATION PROVIDED (if exists): {$DOCUMENTATION}
- If docs define structure → USE IT as primary decomposition source
- Code exploration fills gaps and validates feasibility

FIND: files, components, dependencies, split boundaries, SIMILAR existing implementations, REVERSE DEPENDENCIES (who imports/uses target files), performance-critical paths.
EXCLUDE: ' . Runtime::BRAIN_DIRECTORY . '.

CRITICAL: If DOCUMENTATION defines modules/components/phases → subtasks MUST align with documented structure.
Code may be incomplete - docs define PLANNED architecture.

FORBIDDEN SUBTASKS: Do NOT recommend subtasks for "Write tests", "Add test coverage", "Run quality gates", "Verify implementation". Tests and quality gates are handled AUTOMATICALLY by executors and validators for EACH subtask. Decompose ONLY into functional work units.

MANDATORY: You MUST return a split. Never say "no decomposition needed". Find logical steps.

Return: {docs_structure: [], code_structure: [], split: [], conflicts: [], similar_implementations: [], reverse_dependencies: [], performance_hotspots: []}'),
                VectorMemoryMcp::call('search_memories', '{query: "decomposition patterns, similar tasks", limit: 5}') . ' → ' . Store::as('MEMORY_INSIGHTS'),
            ]))
            ->phase(Store::as('CODE_INSIGHTS', '{from explore agent}'))

            // Stage 4: Plan (DOCUMENTATION is PRIMARY)
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Synthesizing: DOCUMENTATION (primary) + CODE_INSIGHTS (secondary) + MEMORY_INSIGHTS. If docs define structure → USE IT. Code fills gaps. Identify: boundaries, dependencies, parallel opportunities, order. For EACH subtask pair: do they share files? Does B need output of A? Same DB tables? If NO to all → both can be parallel: true.",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase('If DOCUMENTATION exists: subtasks MUST align with documented modules/components/phases')
            ->phase('Group by component (per docs), order by dependency, estimate each')
            ->phase('PARALLEL ISOLATION: Apply parallel-isolation-checklist for each subtask pair. Setup/foundation tasks → always parallel: false.')
            ->phase(Store::as('SUBTASK_PLAN', '[{title, content, estimate, priority, order, parallel, file_manifest: [files], doc_reference}]'))
            ->phase('CONTENT ENRICHMENT: For each subtask in PLAN:')
            ->phase('  - Include "FILES: [file_manifest]" in content — executor needs explicit file scope')
            ->phase(Operator::if('subtask.parallel === true', '  - Append to content: "PARALLEL: this task may execute concurrently with sibling tasks. Stay within listed file scope. Other siblings will read your scope from task comment."'))

            // Stage 5: Approve
            ->phase('Show: | Order | Parallel | Subtask | Est | Priority | Doc Ref |')
            ->phase('Visualize parallel groups: sequential tasks = "→", parallel tasks = "⇉"')
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved', 'Ask: "Create {count} subtasks? (yes/no/modify)"'))

            // Stage 6: Create (content MUST contain FILES: [...] and PARALLEL note if parallel: true)
            ->phase(VectorTaskMcp::call('task_create_bulk', '{tasks: [{title, content (with FILES + PARALLEL note), parent_id: $TASK_ID, priority, estimate, order, parallel, tags: ["' . self::TAG_DECOMPOSED . '"]}]}'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID}') . ' → verify')

            // Return to pending — decomposition done, task awaits execution
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $TASK_ID, status: "pending", comment: "Decomposed into {count} subtasks. Ready for execution.", append_comment: true}'))
            ->phase('STOP: Do NOT execute. Return control to user.');

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list')))
            ->phase(Operator::if('agent fails', 'Continue with available data'))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild, re-submit'));
    }
}