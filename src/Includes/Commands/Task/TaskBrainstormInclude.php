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

#[Purpose('Collaborative brainstorming anchored to vector task. Loads task, asks user for topic, facilitates ideation with research delegation, optional task modification/subtask creation.')]
class TaskBrainstormInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON RULES
        $this->rule('task-get-first')->critical()
            ->text('FIRST action = mcp__vector-task__task_get. Load task context before anything.');

        $this->rule('topic-prompt-mandatory')->critical()
            ->text('MUST ask user for brainstorm topic after loading task. NEVER assume or invent topic.');

        $this->rule('collaborative-mode')->high()
            ->text('Brainstorm is DIALOGUE. Present ideas → ask feedback → iterate. NOT monologue. User can invite specialist agents.');

        $this->rule('iterative-ideation')->critical()
            ->text('After initial ideas, keep proposing until user says "that\'s all" / "proceed". NEVER skip this loop.');

        $this->rule('research-on-demand')->high()
            ->text('Delegate research ONLY when needed. Unknown tech → context7. Codebase analysis → explore agent. Simple topics → no delegation.');

        $this->rule('modification-user-approved')->high()
            ->text('Modify task or create subtasks ONLY when user explicitly requests. Options: update content, rewrite, append, create subtasks.');

        $this->rule('parent-id-mandatory')->critical()
            ->text('ALL subtasks MUST have parent_id = $VECTOR_TASK_ID. No orphan tasks.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')
            ->goal('Brainstorm: load task → ask topic → gather context → ideate → iterate → actions')
            ->example()

            // 1. Load task
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort('Task not found. Use /do:brainstorm for topic-only.')))
            ->phase(Operator::if('TASK.parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' → ' . Store::as('PARENT')))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' → ' . Store::as('SUBTASKS'))
            ->phase('Show: Task #{id}, title, status, content, parent, subtasks count')
            ->phase('Ask: "What aspect would you like to brainstorm?"')
            ->phase('WAIT for user topic → ' . Store::as('TOPIC'))

            // 2. Context gathering
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{TASK.title} {TOPIC}", limit: 5}') . ' → ' . Store::as('MEMORY'))
            ->phase(BashTool::call(BrainCLI::DOCS('{TOPIC}')) . ' → ' . Store::as('DOCS'))
            ->phase(Operator::if('unknown library/tech in TOPIC', Context7Mcp::call('query-docs', '{query: "{library}"}') . ' → understand first'))
            ->phase(Operator::if('needs codebase analysis', TaskTool::agent('explore', 'Analyze codebase for {TOPIC}. Find: relevant files, patterns, implementations.') . ' → ' . Store::as('CODE_CONTEXT')))
            ->phase(Operator::if('needs external research', TaskTool::agent('web-research-master', 'Research {TOPIC}: best practices, patterns, pitfalls.') . ' → ' . Store::as('WEB_RESEARCH')))

            // 3. Initial ideation
            ->phase('Present structured ideas:')
            ->phase(Operator::do([
                '## Approaches - 2-4 potential approaches',
                '## Pros/Cons - for each approach',
                '## Recommendation - top choice with rationale',
                '## Open Questions - needs user input',
            ]))

            // 4. Iterative loop
            ->phase(Store::as('IDEATION_DONE', 'false'))
            ->phase('Ask: "Your thoughts? More ideas? Say \'proceed\' when done."')
            ->phase(Operator::forEach('WHILE NOT IDEATION_DONE', [
                Operator::if('user says proceed/done', Store::as('IDEATION_DONE', 'true')),
                Operator::if('user shares ideas', 'Build on them, propose 2-3 more, ask again'),
                Operator::if('user wants deep dive', 'Expand specific idea, then ask again'),
            ]))

            // 5. Actions menu
            ->phase('Show options: 1) Invite specialist, 2) Update task, 3) Create subtasks, 4) Research more, 5) End session')
            ->phase('WAIT for user choice')

            // 5a. Invite specialist (optional)
            ->phase(Operator::if('user wants specialist', [
                BashTool::call(BrainCLI::LIST_MASTERS) . ' → show available',
                'WAIT for selection',
                TaskTool::agent('{selected}', 'Specialist perspective on {TOPIC} for task {TASK.title}. Current approaches: {summary}. Provide: alternatives, issues, recommendations.'),
                'Present specialist input, continue brainstorm',
            ]))

            // 5b. Update task (optional)
            ->phase(Operator::if('user wants task update', [
                'Show current vs proposed changes',
                'Options: apply, rewrite, append, cancel',
                Operator::if('confirmed', VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, content: "{new}", comment: "Brainstorm: {TOPIC}", append_comment: true}')),
            ]))

            // 5c. Create subtasks (optional)
            ->phase(Operator::if('user wants subtasks', [
                'List actionable items from brainstorm',
                'Ask: "Create these subtasks? (yes/no/modify)"',
                Operator::if('confirmed', VectorTaskMcp::call('task_create_bulk', '{tasks: [{title, content, parent_id: $VECTOR_TASK_ID, priority, estimate}]}')),
            ]))

            // 6. Complete
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Brainstorm #{TASK.id}: {TOPIC}. Insights: {summary}. Modified: {yes/no}. Subtasks: {count}.", category: "architecture", tags: ["brainstorm"]}'))
            ->phase(Operator::if('task modified OR subtasks created', VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "Brainstorm completed: {TOPIC}", append_comment: true}')))
            ->phase('Report: task, topic, modifications, subtasks created');

        // ERROR HANDLING
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('Use /do:brainstorm for topic-only')))
            ->phase(Operator::if('empty topic', 'Re-prompt: "Please specify aspect to brainstorm"'))
            ->phase(Operator::if('research agent fails', 'Continue with available context, note limitation'))
            ->phase(Operator::if('task creation fails', 'Report which failed, suggest manual creation'));
    }
}
