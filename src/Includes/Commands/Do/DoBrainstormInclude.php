<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Do;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Defines the do:brainstorm command protocol for freeform brainstorming sessions. Accepts topic directly as first parameter, facilitates structured ideation with agent delegation for research, documentation reading, and optional task creation. Ideal for exploring ideas before creating formal tasks.')]
class DoBrainstormInclude extends IncludeArchetype
{
    use DoCommandCommonTrait;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->defineEntryPointBlockingRule('BRAINSTORM');

        $this->rule('topic-required')->critical()
            ->text('Brainstorm topic MUST be provided as first argument. If empty, ask user for topic before proceeding.')
            ->why('Cannot brainstorm without a subject. Topic defines the entire session direction.')
            ->onViolation('Ask user: "What topic would you like to brainstorm?"');

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

        $this->rule('task-creation-user-approved')->high()
            ->text('Create vector tasks ONLY when user explicitly requests. Present task proposals, wait for approval.')
            ->why('Task creation is a commitment. User must consent to adding work items.')
            ->onViolation('Ask user before creating any tasks: "Would you like me to create tasks for these ideas?"');

        $this->defineVectorMemoryMandatoryRule('brainstorm topic');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input-capture')
            ->goal('Capture brainstorm topic from command arguments')
            ->example()
            ->phase(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->phase(Store::as('BRAINSTORM_TOPIC', '{brainstorm topic extracted from $RAW_INPUT}'))
            ->phase(Operator::if('$BRAINSTORM_TOPIC is empty OR $RAW_INPUT is empty', [
                Operator::output([
                    '=== DO:BRAINSTORM ===',
                    '',
                    'What topic would you like to brainstorm?',
                ]),
                'WAIT for user to provide topic',
                Store::as('BRAINSTORM_TOPIC', '{user-provided topic}'),
            ]));

        // Phase 0: Context Setup
        $this->guideline('phase0-context-setup')
            ->goal('Initialize brainstorm session, load available agents and documentation, gather memory context')
            ->example()
            ->phase(Operator::output([
                '=== DO:BRAINSTORM ACTIVATED ===',
                '',
                '=== PHASE 0: CONTEXT SETUP ===',
                'Topic: {$BRAINSTORM_TOPIC}',
                'Loading available resources...',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents for potential delegation and specialist invites'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agents with descriptions}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{$BRAINSTORM_TOPIC}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', '{indexed documentation list with descriptions}'))
            ->phase(Operator::if('$DOCS_INDEX has relevant docs', [
                'Select most relevant documents based on topic',
                ReadTool::call('{selected doc paths}'),
                Store::as('DOC_CONTENT', '{documentation content for brainstorm context}'),
            ]))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{$BRAINSTORM_TOPIC}", limit: 5}'))
            ->phase(Store::as('MEMORY_CONTEXT', '{related past solutions and patterns}'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{$BRAINSTORM_TOPIC} best practices architecture", limit: 3, category: "architecture,learning"}'))
            ->phase(Store::as('BEST_PRACTICES', '{relevant patterns}'))
            ->phase('Analyze topic to determine additional research needs:')
            ->phase(Store::as('NEEDS_WEB_RESEARCH', '{true if topic involves external tools, APIs, unfamiliar tech, industry standards}'))
            ->phase(Store::as('NEEDS_CODE_EXPLORATION', '{true if topic requires understanding existing codebase structure}'))
            ->phase(Store::as('RELATED_TASK_ID', '{null or task ID if topic mentions specific task}'))
            ->phase(Operator::output([
                'Available agents: {count} ({list names})',
                'Documentation: {count} relevant docs loaded',
                'Memory context: {summary or none}',
                'Additional research needed: Web={$NEEDS_WEB_RESEARCH}, Code={$NEEDS_CODE_EXPLORATION}',
            ]));

        // Phase 1: Research Delegation (conditional)
        $this->guideline('phase1-research')
            ->goal('Delegate to specialized agents for deep research when needed. Agents already loaded in $AVAILABLE_AGENTS.')
            ->example()
            ->phase(Operator::if('$NEEDS_WEB_RESEARCH === true', [
                Operator::output(['Researching external resources...']),
                'Select agent: prefer @web-research-master if available in $AVAILABLE_AGENTS, otherwise use general agent',
                TaskTool::describe('Task(@{selected-web-agent}, Research: {$BRAINSTORM_TOPIC}. Find best practices, common patterns, potential pitfalls, real-world examples. Store findings to vector memory.)'),
                Store::as('WEB_RESEARCH', '{agent findings}'),
            ]))
            ->phase(Operator::if('$NEEDS_CODE_EXPLORATION === true', [
                Operator::output(['Exploring codebase for context...']),
                'Select agent: find agent specialized for this codebase/domain from $AVAILABLE_AGENTS. If project has dedicated code agent (e.g., laravel-master, react-master), use it. Otherwise use @explore.',
                TaskTool::describe('Task(@{selected-code-agent}, Analyze codebase for: {$BRAINSTORM_TOPIC}. Find relevant files, existing patterns, similar implementations. Store to vector memory.)'),
                Store::as('CODE_CONTEXT', '{agent findings}'),
            ]))
            ->phase(Store::as('RESEARCH_COMPLETE', '{all gathered context merged}'));

        // Phase 2: Brainstorm Session
        $this->guideline('phase2-brainstorm-session')
            ->goal('Facilitate structured ideation with user collaboration')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: BRAINSTORM SESSION ===',
                'Topic: {$BRAINSTORM_TOPIC}',
                '',
                '---',
            ]))
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Analyzing brainstorm topic: {$BRAINSTORM_TOPIC}. Considering: problem space, constraints, stakeholders, success criteria, potential approaches.",
                thoughtNumber: 1,
                totalThoughts: 4,
                nextThoughtNeeded: true
            }'))
            ->phase('Present ideas structured by category:')
            ->phase(Operator::output([
                '## Context',
                '{Relevant findings from memory, research, documentation}',
                '',
                '## Approaches',
                '{List 2-4 potential approaches with brief descriptions}',
                '',
                '## Analysis',
                '',
                '### Approach 1: {name}',
                '- Pros: {advantages}',
                '- Cons: {disadvantages}',
                '- Complexity: {low/medium/high}',
                '- Risk: {low/medium/high}',
                '',
                '### Approach 2: {name}',
                '{same structure...}',
                '',
                '## Recommendation',
                '{Top recommendation with rationale}',
                '',
                '## Open Questions',
                '{Questions that need user input or further exploration}',
            ]));

        // Phase 2A: Iterative Idea Generation Loop
        $this->guideline('phase2a-iterative-ideation')
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

        // Phase 2B: Transition to Actions
        $this->guideline('phase2b-action-selection')
            ->goal('After ideation complete, present action options')
            ->example()
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Synthesizing all collected ideas from brainstorm session. Evaluating: feasibility, priority, dependencies, risks, actionability.",
                thoughtNumber: 1,
                totalThoughts: 3,
                nextThoughtNeeded: true
            }'))
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
                '2. Research a specific aspect further',
                '3. Create tasks from these ideas',
                '4. Wrap up and save insights',
            ]))
            ->phase('WAIT for user to select action');

        // Phase 2C: Invite Specialist Agent (optional, user-triggered)
        $this->guideline('phase2c-invite-specialist')
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
                TaskTool::describe('Task(@{$INVITED_SPECIALIST}, You are invited as specialist to brainstorm session.\\n\\nTopic: {$BRAINSTORM_TOPIC}\\n\\nCurrent approaches discussed:\\n{approaches_summary}\\n\\nProvide your perspective: alternative approaches, potential issues with discussed approaches, additional considerations, recommendations. Be specific and actionable.)'),
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

        // Phase 3: Task Creation (optional, user-triggered)
        $this->guideline('phase3-task-creation')
            ->goal('Convert brainstorm outcomes to actionable vector tasks when requested')
            ->example()
            ->phase(Operator::if('user requests task creation', [
                Operator::output([
                    '',
                    '=== PHASE 3: TASK CREATION ===',
                    'Converting brainstorm outcomes to tasks...',
                ]),
                SequentialThinkingMcp::call('sequentialthinking', '{
                    thought: "Converting brainstorm ideas to tasks. Analyzing: task boundaries, dependencies, optimal order, effort estimation, priority assignment.",
                    thoughtNumber: 1,
                    totalThoughts: 3,
                    nextThoughtNeeded: true
                }'),
                'Compile actionable items from brainstorm session',
                'Analyze independence: items targeting different files/components with no shared state = parallel: true',
                Store::as('ACTIONABLE_ITEMS', '[{title, description, priority, estimate, order, parallel}, ...]'),
                Operator::output([
                    'Proposed tasks:',
                    '',
                    '{For each item:}',
                    '- **{title}** (Priority: {priority}, Est: {estimate}h)',
                    '  {description}',
                    '',
                    '---',
                    '',
                    'Options:',
                    '1. Create as standalone tasks (no parent)',
                    '2. Create under existing task (provide task ID)',
                    '3. Modify task list first',
                    '4. Cancel task creation',
                ]),
                'WAIT for user choice',
                Operator::if('user chooses standalone', [
                    Operator::forEach('item in $ACTIONABLE_ITEMS', [
                        VectorTaskMcp::call('task_create', '{title: "{item.title}", content: "{item.description}", priority: "{item.priority}", estimate: {item.estimate}, order: {item.order}, parallel: {item.parallel}, tags: ["brainstorm"]}'),
                    ]),
                    Store::as('CREATED_TASK_IDS', '[ids...]'),
                    Operator::output(['Created {count} standalone tasks: {ids}']),
                ]),
                Operator::if('user provides parent task ID', [
                    Store::as('PARENT_TASK_ID', '{user-provided ID}'),
                    Operator::forEach('item in $ACTIONABLE_ITEMS', [
                        VectorTaskMcp::call('task_create', '{title: "{item.title}", content: "{item.description}", parent_id: $PARENT_TASK_ID, priority: "{item.priority}", estimate: {item.estimate}, order: {item.order}, parallel: {item.parallel}, tags: ["brainstorm"]}'),
                    ]),
                    Store::as('CREATED_TASK_IDS', '[ids...]'),
                    Operator::output(['Created {count} subtasks under #{$PARENT_TASK_ID}: {ids}']),
                ]),
            ]));

        // Phase 4: Session Summary & Memory Storage
        $this->guideline('phase4-completion')
            ->goal('Summarize session and store insights to vector memory')
            ->example()
            ->phase(Store::as('SESSION_SUMMARY', '{key decisions, insights, approaches discussed, next steps}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Brainstorm Session: {$BRAINSTORM_TOPIC}\\n\\nContext:\\n{context_summary}\\n\\nApproaches Discussed:\\n{approaches}\\n\\nKey Insights:\\n{insights}\\n\\nDecisions Made:\\n{decisions}\\n\\nNext Steps:\\n{next_steps}\\n\\nTasks Created: {task_ids or none}", category: "architecture", tags: ["brainstorm", "{topic_tag}"]}'))
            ->phase(Operator::output([
                '',
                '=== BRAINSTORM COMPLETE ===',
                'Topic: {$BRAINSTORM_TOPIC}',
                'Session insights stored to vector memory',
                'Tasks created: {count or "none"}',
                '',
                'Use /task:brainstorm #{id} to brainstorm specific aspects of created tasks.',
            ]));

        // Error Handling
        $this->defineErrorHandlingGuideline(
            includeAgentErrors: true,
            includeDocErrors: true,
            isValidation: false
        );

        // Additional brainstorm-specific errors
        $this->guideline('error-handling-brainstorm')
            ->text('Brainstorm-specific error handling')
            ->example()
            ->phase()->if('topic is too vague', [
                'Ask for clarification: "Could you be more specific? E.g., architecture for X, implementation of Y"',
                'WAIT for refined topic',
            ])
            ->phase()->if('user provides task ID in topic', [
                'Suggest: "Did you mean /task:brainstorm #{id}? That command loads task context first."',
                'Continue with freeform brainstorm if user confirms',
            ])
            ->phase()->if('no relevant memory/research found', [
                'Proceed with general knowledge',
                'Note to user: "No prior context found. Starting fresh brainstorm."',
            ]);

        // Response Format
        $this->defineResponseFormatGuideline('=== headers | ## sections | structured analysis | options list | collaborative prompts');
    }
}
