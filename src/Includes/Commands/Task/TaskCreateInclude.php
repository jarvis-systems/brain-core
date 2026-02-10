<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Task creation: analyzes description, researches context (memory, codebase, docs), estimates effort, creates well-structured task after approval. NEVER executes.')]
class TaskCreateInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON RULES
        $this->rule('analyze-first')->critical()
            ->text('MUST analyze input thoroughly before creating. Extract: objective, scope, requirements, type (feature/bugfix/refactor/research/docs).');

        $this->rule('research-before-create')->critical()
            ->text('MUST research context: 1) existing tasks (duplicates?), 2) vector memory (prior work), 3) PROJECT DOCUMENTATION (.docs/), 4) codebase (if code-related), 5) context7 (if unknown lib/pattern).');

        // DOCUMENTATION IS LAW
        $this->defineDocumentationIsLawRules();

        $this->rule('docs-define-task-scope')->critical()
            ->text('If documentation exists for task domain → task.content MUST reference docs. Estimate based on FULL spec from docs, not brief description.')
            ->why('Documentation contains complete requirements. Task without doc reference = incomplete context for executor.')
            ->onViolation('Search docs first. If found → include doc reference in task.content and comment.');

        $this->rule('estimate-required')->critical()
            ->text('MUST provide time estimate. 1-8h normal. >8h = recommend /task:decompose after creation.');

        $this->rule('create-only')->critical()
            ->text('This command ONLY creates tasks. NEVER execute after creation. User decides via /task:next or /do.');

        $this->rule('comment-with-context')->high()
            ->text('Initial comment MUST contain: memory IDs, relevant file paths, related task IDs. Preserves research for executor.');

        // Common rule from trait
        $this->defineMandatoryUserApprovalRule();

        $this->rule('fast-path')->high()
            ->text('Simple task (<140 chars, no "architecture/integration/multi-module"): skip heavy research, check duplicates + memory only.');

        $this->rule('auto-approve')->high()
            ->text('-y flag = auto-approve. Skip "Proceed?" but show task spec before creation.');

        // PARALLEL ISOLATION (from trait - strict criteria for parallel: true)
        $this->defineParallelIsolationRules();
        $this->defineParallelIsolationChecklistGuideline();

        // INPUT CAPTURE
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('TASK_DESCRIPTION', '{extracted from RAW_INPUT}'));

        // WORKFLOW
        $this->guideline('workflow')
            ->goal('Create task: parse → research → analyze → formulate → approve → create')
            ->example()

            // 1. Parse
            ->phase('Parse ' . Store::get('TASK_DESCRIPTION') . ' → ' . Store::as('TASK_SCOPE', '{objective, domain, type, requirements}'))
            ->phase(Store::as('IS_SIMPLE', 'description <140 chars AND no architecture/integration/multi-module keywords'))

            // 2. Documentation check (ALWAYS - even for simple tasks)
            ->phase(BashTool::call(BrainCLI::DOCS('{domain} {objective}')) . ' → ' . Store::as('DOCS_INDEX'))
            ->phase(Operator::if(Store::get('DOCS_INDEX') . ' found', [
                ReadTool::call('{doc_paths}') . ' → ' . Store::as('DOCUMENTATION'),
                'DOCUMENTATION = COMPLETE specification. Use for: requirements, estimate, acceptance criteria.',
            ]))

            // 3. Research (conditional depth)
            ->phase(Operator::if(Store::get('IS_SIMPLE'), [
                VectorTaskMcp::call('task_list', '{query: "{objective}", limit: 5}') . ' → check duplicates',
                VectorMemoryMcp::call('search_memories', '{query: "{domain}", limit: 3}'),
            ], [
                // Full research for complex tasks
                TaskTool::agent('explore', 'Search existing tasks for duplicates/related. Objective: {' . Store::get('TASK_SCOPE') . '}. Return: duplicates, potential parent, dependencies.') . ' → ' . Store::as('EXISTING_TASKS'),
                VectorMemoryMcp::call('search_memories', '{query: "{domain} {objective}", limit: 5, category: "code-solution"}') . ' → ' . Store::as('PRIOR_WORK'),
                Operator::if('code-related task', TaskTool::agent('explore', 'Scan codebase for {domain}. Find: files, patterns, dependencies. Return: paths, architecture notes.') . ' → ' . Store::as('CODEBASE_CONTEXT')),
                Operator::if('unknown library/pattern', Context7Mcp::call('query-docs', '{query: "{library}"}') . ' → understand before formulating'),
            ]))
            ->phase(Operator::if('duplicate found', 'STOP. Ask: update existing or create new?'))

            // 4. Analyze (use DOCUMENTATION as primary source for requirements)
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Analyzing: IF DOCS exist → extract full requirements from DOCS. Complexity, estimate (based on DOCS), priority, dependencies, acceptance criteria (from DOCS).",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase(Store::as('ANALYSIS', '{complexity, estimate, priority, dependencies, criteria, doc_requirements}'))

            // 5. Formulate
            ->phase(Store::as('TASK_SPEC', '{
                title: "concise, max 10 words",
                content: "objective, context, acceptance criteria, hints. IF DOCS exist: See documentation: {doc_paths}",
                priority: "critical|high|medium|low",
                estimate: "hours based on DOCUMENTATION (if exists) or description (1-8, >8 needs decompose)",
                parallel: "Apply parallel-isolation-checklist against existing siblings. Default: false. Only true when ALL 5 isolation conditions proven.",
                tags: ["category", "domain"],
                comment: "Docs: {doc_paths or none}. Memory: #IDs. Files: paths. Related: #task_ids."
            }'))

            // 6. Approve
            ->phase('Show: Title, Priority, Estimate, Tags, Content preview, Doc reference (if any)')
            ->phase(Operator::if('estimate > 8', 'WARN: >8h, recommend /task:decompose after creation'))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved', 'Ask: "Create? (yes/no/modify)"'))

            // 7. Create
            ->phase(VectorTaskMcp::call('task_create', '{title, content, priority, tags, estimate, parallel, comment}') . ' → ' . Store::as('CREATED_ID'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Created task #{id}: {title}, {domain}, {estimate}h", category: "tool-usage"}'))
            ->phase(Operator::if('estimate > 8', 'Recommend: /task:decompose ' . Store::get('CREATED_ID')))
            ->phase('STOP. Do NOT execute. Return control to user.');

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('duplicate task found', 'Ask: update existing #ID or create new?'))
            ->phase(Operator::if('research fails', 'Continue with available data, note gaps'))
            ->phase(Operator::if('user rejects', 'Accept modifications, rebuild spec, re-submit'));
    }
}
