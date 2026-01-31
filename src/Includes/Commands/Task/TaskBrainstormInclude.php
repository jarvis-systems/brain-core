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
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Defines the task:brainstorm command protocol for collaborative brainstorming sessions anchored to vector tasks. Loads task context, prompts user for discussion topic, then facilitates structured ideation with agent delegation for research, documentation reading, and optional task creation.')]
class TaskBrainstormInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function defineRules(): void
    {
        $this->rule('entry-point-blocking')->critical()
            ->text('ON RECEIVING $RAW_INPUT: Your FIRST output MUST be "=== TASK:BRAINSTORM ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION.')
            ->why('Forces workflow compliance and prevents skipping the structured brainstorm process.')
            ->onViolation('STOP IMMEDIATELY. Output "=== TASK:BRAINSTORM ACTIVATED ===" and restart from Phase 0.');

        $this->rule('topic-prompt-mandatory')->critical()
            ->text('AFTER loading vector task, you MUST ask user for the brainstorm discussion topic. NEVER assume or invent the topic yourself.')
            ->why('User defines the direction of brainstorming. Different topics on same task = different outcomes.')
            ->onViolation('STOP. Ask user: "What aspect of this task would you like to brainstorm?"');

        $this->rule('collaborative-mode')->high()
            ->text('Brainstorm is COLLABORATIVE dialogue. Present ideas, ask for feedback, iterate. NOT one-way monologue. User can invite subagent specialists (other LLMs) to join the brainstorm for alternative perspectives.')
            ->why('Quality brainstorming requires user input and direction throughout the process. Different LLMs provide diverse viewpoints.')
            ->onViolation('Pause after each major idea set. Ask user for direction before continuing.');

        $this->rule('research-on-demand')->high()
            ->text('Delegate to specialized agents ONLY when topic requires external knowledge or codebase analysis. Use brain list:masters to discover available agents and select the most appropriate one for the task (e.g., code specialist for codebase analysis, web-research-master for external research). Read documentation directly via Read tool when brain docs provides paths.')
            ->why('Efficient resource usage - simple topics don\'t need agent delegation overhead. Agent selection must be dynamic based on project configuration.')
            ->onViolation('Evaluate: Is research truly needed? If yes, run brain list:masters, select appropriate agent, delegate. If no, proceed with brainstorming.');

        $this->rule('iterative-ideation-loop')->critical()
            ->text('After presenting initial ideas, you MUST enter iterative ideation loop. Keep proposing new ideas and asking for user input until user explicitly says "that\'s all", "let\'s continue", "proceed", or similar confirmation. NEVER skip this loop or proceed automatically.')
            ->why('Quality brainstorming requires exhaustive exploration. Users often have more ideas after seeing initial proposals. Premature closure misses valuable input.')
            ->onViolation('STOP. Ask user: "Do you have more ideas to share, or shall I propose more? Say \'that\'s all, let\'s continue\' when ready to proceed."');

        $this->rule('task-modification-user-approved')->high()
            ->text('Modify the brainstormed task (update title, content, priority, estimate) OR create subtasks ONLY when user explicitly requests or approves. Options include: 1) Update current task content/description, 2) Rewrite task completely, 3) Add details to existing content, 4) Create subtasks, 5) Any combination.')
            ->why('Task modification is a commitment. User must consent to changing the task they are brainstorming on.')
            ->onViolation('Ask user: "Would you like to update this task, create subtasks, or both?"');

        // PARENT INHERITANCE (IRON LAW)
        $this->rule('parent-id-mandatory')->critical()
            ->text('When working with task $VECTOR_TASK_ID, ALL new subtasks created MUST have parent_id = $VECTOR_TASK_ID. IRON LAW: Subtasks are ALWAYS children of the brainstormed task, NEVER orphans. No exceptions.')
            ->why('Task hierarchy integrity. Orphan tasks break traceability and workflow.')
            ->onViolation('ABORT task_create if parent_id missing or != $VECTOR_TASK_ID. Verify parent_id in EVERY task_create call.');

        $this->defineVectorMemoryMandatoryRule();
        $this->defineVectorTaskIdRequiredRule('/do:brainstorm');
    }

    protected function defineGuidelines(): void
    {
        $this->defineInputCaptureGuideline();

        // Phase 0: Vector Task Loading
        $this->guideline('phase0-task-loading')
            ->goal('Load vector task and prepare brainstorm context')
            ->example()
            ->phase(Operator::output([
                '=== TASK:BRAINSTORM ACTIVATED ===',
                '',
                '=== PHASE 0: LOADING TASK ===',
            ]))
            ->phase('Use pre-captured: $RAW_INPUT, $CLEAN_ARGS, $VECTOR_TASK_ID')
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK', '{task object}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'ABORT command',
            ]))
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK', '{parent task context}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 20}'))
            ->phase(Store::as('SUBTASKS', '{existing subtasks}'))
            ->phase(Operator::output([
                '',
                '=== TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Content: {$VECTOR_TASK.content}',
                'Parent: {$PARENT_TASK.title or "none"}',
                'Subtasks: {count or "none"}',
                '',
                '---',
                '',
                'What aspect of this task would you like to brainstorm?',
                'Examples: implementation approach, architecture design, edge cases, optimization strategies, testing approach, etc.',
            ]))
            ->phase('WAIT for user to provide brainstorm topic');

        // Phase 1: Topic Capture & Context Gathering
        $this->guideline('phase1-topic-context')
            ->goal('Capture user topic, load available agents and documentation, gather context from memory')
            ->example()
            ->phase(Store::as('BRAINSTORM_TOPIC', '{user-provided topic}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 1: CONTEXT GATHERING ===',
                'Topic: {$BRAINSTORM_TOPIC}',
                'Loading available resources...',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents for potential delegation'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agents with descriptions - for research delegation and specialist invites}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{$VECTOR_TASK.title}, {$BRAINSTORM_TOPIC}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', '{indexed documentation list with descriptions}'))
            ->phase(Operator::if('$DOCS_INDEX has relevant docs', [
                'Select most relevant documents based on topic and task',
                ReadTool::call('{selected doc paths}'),
                Store::as('DOC_CONTENT', '{documentation content for brainstorm context}'),
            ]))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{$VECTOR_TASK.title} {$BRAINSTORM_TOPIC}", limit: 5}'))
            ->phase(Store::as('MEMORY_CONTEXT', '{related past solutions and patterns}'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{$BRAINSTORM_TOPIC} best practices", limit: 3, category: "architecture,learning"}'))
            ->phase(Store::as('BEST_PRACTICES', '{relevant patterns}'))
            ->phase('Determine if additional research is needed:')
            ->phase(Store::as('NEEDS_WEB_RESEARCH', '{true if topic involves external tools, APIs, unfamiliar tech}'))
            ->phase(Store::as('NEEDS_CODE_EXPLORATION', '{true if topic requires understanding existing codebase}'))
            ->phase(Operator::output([
                'Available agents: {count} ({list names})',
                'Documentation: {count} relevant docs loaded',
                'Memory context: {found or none}',
                'Additional research needed: Web={$NEEDS_WEB_RESEARCH}, Code={$NEEDS_CODE_EXPLORATION}',
            ]));

        // Phase 2: Research Delegation (conditional)
        $this->guideline('phase2-research')
            ->goal('Delegate to specialized agents for deep research when needed. Agents already loaded in $AVAILABLE_AGENTS.')
            ->example()
            ->phase(Operator::if('$NEEDS_WEB_RESEARCH === true', [
                Operator::output(['Researching external resources...']),
                'Select agent: prefer @web-research-master if available in $AVAILABLE_AGENTS, otherwise use general agent',
                TaskTool::describe('Task(@{selected-web-agent}, Research: {$BRAINSTORM_TOPIC} for {$VECTOR_TASK.title}. Find best practices, common patterns, potential pitfalls. Store findings to vector memory.)'),
                Store::as('WEB_RESEARCH', '{agent findings}'),
            ]))
            ->phase(Operator::if('$NEEDS_CODE_EXPLORATION === true', [
                Operator::output(['Exploring codebase for context...']),
                'Select agent: find agent specialized for this codebase/domain from $AVAILABLE_AGENTS. If project has dedicated code agent (e.g., laravel-master, react-master), use it. Otherwise use @explore.',
                TaskTool::describe('Task(@{selected-code-agent}, Analyze codebase for: {$BRAINSTORM_TOPIC} related to {$VECTOR_TASK.title}. Find relevant files, patterns, existing implementations. Store to vector memory.)'),
                Store::as('CODE_CONTEXT', '{agent findings}'),
            ]))
            ->phase(Store::as('RESEARCH_COMPLETE', '{all gathered context}'));

        // Phase 3: Brainstorm Session
        $this->guideline('phase3-brainstorm-session')
            ->goal('Facilitate structured ideation with user collaboration')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: BRAINSTORM SESSION ===',
                'Task: {$VECTOR_TASK.title}',
                'Topic: {$BRAINSTORM_TOPIC}',
                '',
                '---',
                '',
            ]))
            ->phase('Present ideas structured by category:')
            ->phase(Operator::output([
                '## Approaches',
                '{List 2-4 potential approaches based on context}',
                '',
                '## Pros/Cons Analysis',
                '{For each approach: advantages, disadvantages, complexity, risk}',
                '',
                '## Recommendations',
                '{Top recommendation with rationale}',
                '',
                '## Open Questions',
                '{Questions that need user input or further research}',
            ]));

        // Phase 3A: Iterative Idea Generation Loop
        $this->guideline('phase3a-iterative-ideation')
            ->goal('Continuously generate and refine ideas until user confirms completion. Keep proposing new angles until user says to proceed.')
            ->example()
            ->phase(Store::as('IDEATION_COMPLETE', 'false'))
            ->phase(Operator::output([
                '',
                '---',
                '',
                'These are my initial ideas based on the context gathered.',
                '',
                'Do you have any thoughts, additions, or alternative ideas to share?',
                'I can also propose more ideas from different angles.',
                '',
                '**Reply with your ideas, or say "that\'s all, let\'s continue" to proceed.**',
            ]))
            ->phase('WAIT for user response')
            ->phase(Operator::forEach('WHILE $IDEATION_COMPLETE === false', [
                Operator::if('user says "that\'s all" OR "let\'s continue" OR "proceed" OR similar confirmation', [
                    Store::as('IDEATION_COMPLETE', 'true'),
                    Operator::output(['Great! Moving forward with collected ideas...']),
                ]),
                Operator::if('user provides new ideas OR asks for more', [
                    Store::as('USER_IDEAS', '{append user ideas to collection}'),
                    'Generate 2-3 MORE ideas inspired by user input or from new angle:',
                    Operator::output([
                        '',
                        '## Additional Ideas',
                        '{New approaches inspired by user input or unexplored angles}',
                        '',
                        '## Building on Your Input',
                        '{How user ideas could be extended or combined}',
                        '',
                        '---',
                        '',
                        'Any more thoughts? Or shall we proceed? ("that\'s all, let\'s continue")',
                    ]),
                    'WAIT for user response',
                ]),
                Operator::if('user asks to explore specific idea deeper', [
                    'Expand on requested idea with more detail',
                    Operator::output([
                        '',
                        '## Deep Dive: {idea}',
                        '{Detailed analysis, implementation considerations, edge cases}',
                        '',
                        '---',
                        '',
                        'More ideas to add? Or ready to proceed?',
                    ]),
                    'WAIT for user response',
                ]),
            ]))
            ->phase(Store::as('ALL_IDEAS', '{merged: initial ideas + user ideas + additional generated ideas}'));

        // Phase 3B: Transition to Actions
        $this->guideline('phase3b-action-selection')
            ->goal('After ideation complete, present action options')
            ->example()
            ->phase(Operator::output([
                '',
                '=== IDEATION COMPLETE ===',
                '',
                '## Collected Ideas Summary',
                '{Summary of all ideas discussed}',
                '',
                '---',
                '',
                'What would you like to do next?',
                '1. Invite a specialist agent for alternative perspective',
                '2. Update this task based on insights',
                '3. Create subtasks from brainstorm outcomes',
                '4. Research a specific aspect further',
                '5. End session and save insights',
            ]))
            ->phase('WAIT for user to select action');

        // Phase 3C: Invite Specialist Agent (optional, user-triggered)
        $this->guideline('phase3c-invite-specialist')
            ->goal('Invite subagent as additional specialist for alternative perspective (different LLM = different viewpoint)')
            ->example()
            ->phase(Operator::if('user requests specialist agent', [
                Operator::output([
                    '',
                    '=== INVITE SPECIALIST ===',
                    'Available agents from $AVAILABLE_AGENTS:',
                    '{list agent names with descriptions}',
                    '',
                    'Which agent would you like to invite for their perspective on this topic?',
                ]),
                'WAIT for user to select agent',
                Store::as('INVITED_SPECIALIST', '{selected agent id}'),
                Operator::output(['Consulting @{$INVITED_SPECIALIST} for their perspective...']),
                TaskTool::describe('Task(@{$INVITED_SPECIALIST}, You are invited as specialist to brainstorm session.\\n\\nTask: {$VECTOR_TASK.title}\\nTopic: {$BRAINSTORM_TOPIC}\\n\\nCurrent approaches discussed:\\n{approaches_summary}\\n\\nProvide your perspective: alternative approaches, potential issues with discussed approaches, additional considerations, recommendations. Be specific and actionable.)'),
                Store::as('SPECIALIST_INPUT', '{agent perspective}'),
                Operator::output([
                    '',
                    '## Specialist Perspective (@{$INVITED_SPECIALIST})',
                    '{$SPECIALIST_INPUT}',
                    '',
                    '---',
                    '',
                    'Continue brainstorming with this input?',
                ]),
            ]));

        // Phase 4: Task Modification (optional, user-triggered)
        $this->guideline('phase4-task-modification')
            ->goal('Update the brainstormed task based on session insights when requested')
            ->example()
            ->phase(Operator::if('user requests task update', [
                Operator::output([
                    '',
                    '=== PHASE 4A: TASK MODIFICATION ===',
                    'Preparing task update based on brainstorm insights...',
                ]),
                'Compile proposed changes from brainstorm session',
                Store::as('PROPOSED_CHANGES', '{new_title?, new_content?, append_content?, new_priority?, new_estimate?}'),
                Operator::output([
                    'Current task:',
                    '- Title: {$VECTOR_TASK.title}',
                    '- Content: {$VECTOR_TASK.content}',
                    '- Priority: {$VECTOR_TASK.priority} | Estimate: {$VECTOR_TASK.estimate}h',
                    '',
                    'Proposed changes:',
                    '{show proposed changes with diff-style comparison}',
                    '',
                    'Options:',
                    '1. Apply changes as shown',
                    '2. Rewrite task completely (replace all content)',
                    '3. Append insights to existing content',
                    '4. Modify proposed changes first',
                    '5. Cancel task update',
                ]),
                'WAIT for user choice',
                Operator::if('user chooses apply or rewrite', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, title: "{new_title}", content: "{new_content}", priority: "{new_priority}", estimate: {new_estimate}}'),
                    Operator::output(['Task #{$VECTOR_TASK_ID} updated successfully']),
                ]),
                Operator::if('user chooses append', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, content: "{$VECTOR_TASK.content}\\n\\n---\\n\\n## Brainstorm Insights ({$BRAINSTORM_TOPIC})\\n\\n{insights_to_append}"}'),
                    Operator::output(['Insights appended to task #{$VECTOR_TASK_ID}']),
                ]),
            ]));

        // Phase 4B: Subtask Creation (optional, user-triggered)
        $this->guideline('phase4b-subtask-creation')
            ->goal('Convert brainstorm outcomes to actionable subtasks when requested')
            ->example()
            ->phase(Operator::if('user requests subtask creation', [
                Operator::output([
                    '',
                    '=== PHASE 4B: SUBTASK CREATION ===',
                    'Converting brainstorm outcomes to subtasks...',
                ]),
                'Compile actionable items from brainstorm session',
                Store::as('ACTIONABLE_ITEMS', '[{title, description, priority, estimate}, ...]'),
                Operator::output([
                    'Proposed subtasks for #{$VECTOR_TASK_ID}:',
                    '{list actionable items with estimates}',
                    '',
                    'Create these subtasks? (yes/no/modify)',
                ]),
                'WAIT for user confirmation',
                Operator::if('user confirmed', [
                    Operator::forEach('item in $ACTIONABLE_ITEMS', [
                        VectorTaskMcp::call('task_create', '{title: "{item.title}", content: "{item.description}", parent_id: $VECTOR_TASK_ID, priority: "{item.priority}", estimate: {item.estimate}}'),
                    ]),
                    Operator::output(['Created {count} subtasks under #{$VECTOR_TASK_ID}']),
                ]),
            ]));

        // Phase 5: Session Summary & Memory Storage
        $this->guideline('phase5-completion')
            ->goal('Summarize session and store insights to vector memory')
            ->example()
            ->phase(Store::as('SESSION_SUMMARY', '{key decisions, insights, changes made, next steps}'))
            ->phase(Store::as('TASK_MODIFIED', '{true/false}'))
            ->phase(Store::as('SUBTASKS_CREATED', '{count or 0}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Brainstorm: {$VECTOR_TASK.title}\\nTopic: {$BRAINSTORM_TOPIC}\\n\\nKey Insights:\\n{insights}\\n\\nDecisions:\\n{decisions}\\n\\nTask Modified: {$TASK_MODIFIED}\\nSubtasks Created: {$SUBTASKS_CREATED}\\n\\nNext Steps:\\n{next_steps}", category: "architecture", tags: ["brainstorm", "task-{$VECTOR_TASK_ID}"]}'))
            ->phase(Operator::if('$TASK_MODIFIED OR $SUBTASKS_CREATED > 0', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "Brainstorm session completed. Topic: {$BRAINSTORM_TOPIC}. Task modified: {$TASK_MODIFIED}. Subtasks created: {$SUBTASKS_CREATED}.", append_comment: true}'),
            ]))
            ->phase(Operator::output([
                '',
                '=== BRAINSTORM COMPLETE ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Topic: {$BRAINSTORM_TOPIC}',
                'Task modified: {$TASK_MODIFIED}',
                'Subtasks created: {$SUBTASKS_CREATED}',
                'Insights stored to memory',
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Graceful error handling for brainstorm sessions')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Task #{id} not found"',
                'Suggest: Use /do:brainstorm for topic-only brainstorming',
                'ABORT',
            ])
            ->phase()->if('user provides empty topic', [
                'Re-prompt: "Please specify what aspect you want to brainstorm"',
                'WAIT for valid topic',
            ])
            ->phase()->if('research agent fails', [
                'Log failure',
                'Continue with available context',
                'Note limitation to user',
            ])
            ->phase()->if('task creation fails', [
                'Log error',
                'Report to user which tasks failed',
                'Suggest manual creation',
            ]);
    }
}
