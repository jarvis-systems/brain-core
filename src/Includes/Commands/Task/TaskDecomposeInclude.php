<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainNode\Agents\DocumentationMaster;
use BrainNode\Agents\ExploreMaster;
use BrainNode\Agents\VectorMaster;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Aggressive task decomposition with MAXIMUM parallel agent orchestration. Deep multi-agent research, comprehensive codebase analysis, creates optimal subtasks meeting 5-8h golden rule. NEVER executes - only creates.')]
class TaskDecomposeInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // ============================================
        // IRON RULES - AGGRESSIVE DECOMPOSITION
        // ============================================

        $this->rule('golden-rule-estimate')->critical()
            ->text('Each subtask estimate MUST be <= 5-8 hours. This is the CORE PURPOSE.')
            ->why('Tasks >8h are too large for effective tracking, estimation accuracy, and focus')
            ->onViolation('Decompose further until ALL subtasks meet 5-8h. Flag for recursive /task:decompose.');

        $this->rule('max-subtasks-limit')->critical()
            ->text('Maximum 10 subtasks per parent task. NEVER create more than 10 direct children.')
            ->why('Too many subtasks indicate insufficient grouping. Cognitive overload, tracking nightmare.')
            ->onViolation('Group related work into larger subtasks (each 5-8h), mark them [needs-decomposition] for recursive /task:decompose.');

        $this->rule('parallel-agent-execution')->critical()
            ->text('Launch independent research agents in parallel ONLY for complex tasks. SIMPLE tasks may run at most one agent per domain to keep latency low.')
            ->why('Balances coverage with overhead; parallel agents add value when the parent task is complex enough to justify them.')
            ->onViolation('If a simple task was marked complex, re-evaluate scope. For complex tasks, group agents thoughtfully and launch simultaneously.');

        $this->rule('multi-agent-research')->critical()
            ->text('Use specialized agents: ExploreMaster(code), DocumentationMaster(docs), VectorMaster(memory) when complexity demands it. SIMPLE tasks may rely on a single VectorMaster lookup.')
            ->why('Each agent adds context but also cost. Only the necessary mix should run for non-trivial decompositions.')
            ->onViolation('Delegate to targeted agents based on actual task complexity. Avoid blanket multi-agent blasts for simple work.');

        $this->rule('create-only-no-execution')->critical()
            ->text('This command ONLY creates subtasks. NEVER execute any subtask after creation.')
            ->why('Decomposition and execution are separate concerns. User decides via /task:next')
            ->onViolation('STOP immediately after subtask creation');

        // Common rule from trait
        $this->defineMandatoryUserApprovalRule();

        $this->rule('fetch-parent-first')->critical()
            ->text('MUST fetch parent task via task_get BEFORE any research')
            ->why('Cannot decompose without full understanding of parent scope')
            ->onViolation('Execute task_get first, analyze completely');

        $this->rule('correct-parent-id')->critical()
            ->text('MUST set parent_id = task_id for ALL created subtasks')
            ->why('Hierarchy integrity requires correct parent-child relationships')
            ->onViolation('Verify parent_id in every task_create');

        $this->rule('exclude-brain-directory')->critical()
            ->text('NEVER analyze ' . Runtime::BRAIN_DIRECTORY . ' when decomposing code tasks')
            ->why('Brain system internals are not project code')
            ->onViolation('Skip ' . Runtime::BRAIN_DIRECTORY . ' in all exploration');

        $this->rule('simple-decomposition-heuristic')->high()
            ->text('Detect simple parent tasks (low estimate + simple complexity) so heavy parallel research can be skipped.')
            ->why('Keeps latency low on trivial decompositions while running full workflows for complex requests.')
            ->onViolation('If a simple task actually needs deep insight, treat it as complex and rerun the workflow.');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('TASK_ID', '{numeric task ID extracted from $RAW_INPUT}'));

        // ============================================
        // PHASE 0: PARSE AND VALIDATE
        // ============================================

        $this->guideline('phase0-parse')
            ->goal('Validate captured input from $RAW_INPUT')
            ->example()
            ->phase(Operator::output([
                '=== TASK:DECOMPOSE ACTIVATED ===',
                '',
                '=== PHASE 0: INPUT VALIDATION ===',
                'Processing task #{$TASK_ID}...',
            ]))
            ->phase(Store::as('HAS_Y_FLAG', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->phase('STEP 1 - Validate:')
            ->do([
                Operator::validate('$TASK_ID is numeric', 'Request valid task_id from user'),
            ]);

        // ============================================
        // PHASE 1: FETCH PARENT TASK
        // ============================================

        $this->guideline('phase1-fetch')
            ->goal('Fetch and fully understand parent task')
            ->example()
            ->phase('STEP 1 - Fetch parent task:')
            ->do([
                VectorTaskMcp::call('task_get', '{task_id: $TASK_ID}'),
                Operator::validate('Task exists and has content', 'Report: Task not found'),
                Store::as('PARENT_TASK', '{id, title, content, priority, tags, status, estimate}'),
            ])
            ->phase('STEP 2 - Check existing subtasks:')
            ->do([
                VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID, limit: 50}'),
                Operator::if(
                    'existing subtasks > 0',
                    'Ask: "Task has {count} subtasks. (1) Add more, (2) Replace all, (3) Abort"'
                ),
                Store::as('EXISTING_SUBTASKS', '[{id, title, status}]'),
            ])
            ->phase('STEP 3 - Analyze parent task type:')
            ->do([
                'Determine task type: code | architecture | documentation | testing | infrastructure',
                'Identify domain: backend | frontend | database | api | devops',
                'Assess complexity: simple | moderate | complex | very-complex',
                Store::as('TASK_TYPE', '{type, domain, complexity}'),
                Store::as('SIMPLE_DECOMPOSITION', '{true if $TASK_TYPE.complexity === "simple" AND $PARENT_TASK.estimate <= 4}'),
                Operator::note('SIMPLE_DECOMPOSITION = {$SIMPLE_DECOMPOSITION}')
            ]);

        // ============================================
        // PHASE 2: PARALLEL RESEARCH BATCH 1 - MEMORY + DOCS
        // ============================================

        $this->guideline('phase2-parallel-research')
            ->goal('PARALLEL: Deep memory research + documentation analysis when complexity demands it')
            ->example()
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === false', 'BATCH 1 - Memory & Docs (LAUNCH IN PARALLEL):'))
            ->do([
                Operator::if('$SIMPLE_DECOMPOSITION === false', [
                    VectorMaster::call(
                        Operator::task([
                            'DEEP MEMORY RESEARCH for decomposition of: $PARENT_TASK.title',
                            'Multi-probe search strategy:',
                            'Probe 1: "task decomposition {domain} patterns strategies" (tool-usage)',
                            'Probe 2: "$PARENT_TASK.title implementation breakdown structure" (architecture)',
                            'Probe 3: "{domain} subtask estimation accuracy lessons" (learning)',
                            'Probe 4: "similar task decomposition mistakes pitfalls" (bug-fix)',
                            'Probe 5: "{domain} code structure component boundaries" (code-solution)',
                            'EXTRACT: decomposition patterns, common structures, past estimates, warnings',
                            'OUTPUT: actionable decomposition insights',
                        ]),
                        Operator::output('{memories_found:N,patterns:[],estimates_accuracy:[],warnings:[]}'),
                        Store::as('MEMORY_INSIGHTS')
                    ),
                    DocumentationMaster::call(
                        Operator::task([
                            'DOCUMENTATION RESEARCH for task: $PARENT_TASK.title',
                            'Search brain docs for: {domain}, {related_concepts}',
                            'Find: API specs, architecture docs, implementation guides',
                            'EXTRACT: requirements, constraints, patterns, dependencies',
                            'OUTPUT: documentation-based decomposition guidance',
                        ]),
                        Operator::output('{docs_found:N,requirements:[],patterns:[],constraints:[]}'),
                        Store::as('DOC_INSIGHTS')
                    ),
                ]),
                Operator::if('$SIMPLE_DECOMPOSITION === true', [
                    Operator::output(['Simple task detected; running lightweight memory check.']),
                    VectorMaster::call(
                        Operator::task([
                            'LIGHT MEMORY CHECK for: $PARENT_TASK.title',
                            'Probe 1: "$PARENT_TASK.title" (context summary)',
                            'Probe 2: "{domain} decomposition hints"',
                            'EXTRACT: key patterns, estimates, warnings',
                            'OUTPUT: condensed memory summary',
                        ]),
                        Operator::output('{memories_found:N,patterns:[],warnings:[]}'),
                        Store::as('MEMORY_INSIGHTS')
                    ),
                    Store::as('DOC_INSIGHTS', '{docs_found:0,requirements:[],patterns:[],constraints:[]}'),
                    Operator::output(['Documentation research skipped for simple decomposition.']),
                ]),
            ])
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === false', 'NOTE: VectorMaster + DocumentationMaster run SIMULTANEOUSLY'))
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === true', 'NOTE: Simple decomposition skipped docs research.'));

        // ============================================
        // PHASE 3: PARALLEL CODEBASE EXPLORATION (if code task)
        // ============================================

        $this->guideline('phase3-parallel-code')
            ->goal('PARALLEL: Multi-aspect codebase analysis for code tasks')
            ->example()
            ->phase(Operator::if('$TASK_TYPE.type === "code"', 'CONDITIONAL: If $TASK_TYPE.type === "code":'))
            ->phase(Operator::if('$TASK_TYPE.type === "code" AND $SIMPLE_DECOMPOSITION === false', 'BATCH 2 - Codebase Analysis (LAUNCH IN PARALLEL):'))
            ->do([
                Operator::if('$TASK_TYPE.type === "code" AND $SIMPLE_DECOMPOSITION === false', ExploreMaster::call(
                    Operator::task([
                        'COMPONENT ANALYSIS for: $PARENT_TASK.title',
                        'Thoroughness: very thorough',
                        'ANALYZE: affected files, classes, methods, namespaces',
                        'IDENTIFY: component boundaries, natural split points',
                        'EXTRACT: {files:[],classes:[],methods:[],boundaries:[]}',
                        'FOCUS ON: where code changes will be needed',
                    ]),
                    Operator::output('{files:N,components:[],boundaries:[],split_points:[]}'),
                    Store::as('CODE_COMPONENTS')
                )),
                Operator::if('$TASK_TYPE.type === "code" AND $SIMPLE_DECOMPOSITION === false', ExploreMaster::call(
                    Operator::task([
                        'DEPENDENCY ANALYSIS for: $PARENT_TASK.title',
                        'Thoroughness: thorough',
                        'ANALYZE: imports, dependencies, coupling between modules',
                        'IDENTIFY: dependency chains, circular deps, external deps',
                        'EXTRACT: {internal_deps:[],external_deps:[],coupling_level:str}',
                        'FOCUS ON: what must be changed together vs independently',
                    ]),
                    Operator::output('{dependencies:[],coupling:str,independent_areas:[]}'),
                    Store::as('CODE_DEPENDENCIES')
                )),
                Operator::if('$TASK_TYPE.type === "code" AND $SIMPLE_DECOMPOSITION === false', ExploreMaster::call(
                    Operator::task([
                        'TEST ANALYSIS for: $PARENT_TASK.title',
                        'Thoroughness: medium',
                        'ANALYZE: existing tests, test patterns, coverage gaps',
                        'IDENTIFY: what tests need updating/creating',
                        'EXTRACT: {existing_tests:[],patterns:[],gaps:[]}',
                        'FOCUS ON: test requirements for subtasks',
                    ]),
                    Operator::output('{tests:N,coverage_gaps:[],test_requirements:[]}'),
                    Store::as('CODE_TESTS')
                )),
            ])
            ->phase(Operator::if('$TASK_TYPE.type === "code" AND $SIMPLE_DECOMPOSITION === false', 'NOTE: All 3 ExploreMaster agents run SIMULTANEOUSLY'))
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === true', 'NOTE: Simple task skipped code exploration.'));

        // ============================================
        // PHASE 4: ADDITIONAL PARALLEL RESEARCH
        // ============================================

        $this->guideline('phase4-parallel-additional')
            ->goal('PARALLEL: Additional targeted research based on task type when needed')
            ->example()
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === false', 'BATCH 3 - Additional Research (LAUNCH IN PARALLEL):'))
            ->do([
                Operator::if('$SIMPLE_DECOMPOSITION === false', ExploreMaster::call(
                    Operator::task([
                        'COMPLEXITY ASSESSMENT for: $PARENT_TASK.title',
                        'Thoroughness: quick',
                        'ANALYZE: cyclomatic complexity, lines of code, nesting depth',
                        'IDENTIFY: complex hotspots, refactoring candidates',
                        'EXTRACT: {complexity_score:N,hotspots:[],risk_areas:[]}',
                    ]),
                    Operator::output('{complexity:str,hotspots:[],risk_level:str}'),
                    Store::as('COMPLEXITY_ANALYSIS')
                )),
                Operator::if(
                    '($TASK_TYPE.domain === "api" OR $TASK_TYPE.domain === "backend") AND $SIMPLE_DECOMPOSITION === false',
                    ExploreMaster::call(
                        Operator::task([
                            'API/ROUTE ANALYSIS for: $PARENT_TASK.title',
                            'Thoroughness: medium',
                            'ANALYZE: affected routes, controllers, middleware',
                            'IDENTIFY: API contract changes, breaking changes',
                            'EXTRACT: {routes:[],controllers:[],breaking_changes:[]}',
                        ]),
                        Operator::output('{routes:N,changes:[],breaking:bool}'),
                        Store::as('API_ANALYSIS')
                    )
                ),
            ])
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === false', 'PARALLEL memory searches for specific aspects:'))
            ->do([
                Operator::if('$SIMPLE_DECOMPOSITION === false', VectorMemoryMcp::call('search_memories', '{query: "$PARENT_TASK.domain estimation accuracy", limit: 3, category: "learning"}')),
                Operator::if('$SIMPLE_DECOMPOSITION === false', VectorMemoryMcp::call('search_memories', '{query: "$PARENT_TASK.title similar implementation", limit: 3, category: "code-solution"}')),
                Operator::if('$SIMPLE_DECOMPOSITION === false', VectorMemoryMcp::call('search_memories', '{query: "$PARENT_TASK.domain common mistakes", limit: 3, category: "bug-fix"}')),
            ]);

        // ============================================
        // PHASE 5: SYNTHESIS AND DECOMPOSITION PLANNING
        // ============================================

        $this->guideline('phase5-synthesis')
            ->goal('Synthesize ALL research into decomposition plan')
            ->example()
            ->phase('COMBINE all stored research:')
            ->do([
                Store::get('PARENT_TASK'),
                Store::get('EXISTING_SUBTASKS'),
                Store::get('TASK_TYPE'),
                Store::get('MEMORY_INSIGHTS'),
                Store::get('DOC_INSIGHTS'),
                Operator::if('$SIMPLE_DECOMPOSITION === false', Store::get('CODE_COMPONENTS')),
                Operator::if('$SIMPLE_DECOMPOSITION === false', Store::get('CODE_DEPENDENCIES')),
                Operator::if('$SIMPLE_DECOMPOSITION === false', Store::get('CODE_TESTS')),
                Operator::if('$SIMPLE_DECOMPOSITION === false', Store::get('COMPLEXITY_ANALYSIS')),
                Operator::if('$SIMPLE_DECOMPOSITION === false', Store::get('API_ANALYSIS')),
            ])
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === false', 'SEQUENTIAL THINKING for decomposition strategy:'))
            ->do([
                Operator::if('$SIMPLE_DECOMPOSITION === false', SequentialThinkingMcp::call('sequentialthinking', '{
                    thought: "Analyzing comprehensive research from 5+ parallel agents for optimal decomposition. Parent: $PARENT_TASK.title. Golden rule: <=5-8h per subtask.",
                    thoughtNumber: 1,
                    totalThoughts: 6,
                    nextThoughtNeeded: true
                }')),
            ])
            ->phase(Operator::if('$SIMPLE_DECOMPOSITION === true', 'Simple decomposition: skipping SequentialThinking (already lightweight).'))
            ->phase('DECOMPOSITION ANALYSIS:')
            ->do([
                'Step 1: Identify natural task boundaries from CODE_COMPONENTS',
                'Step 2: Map dependencies between potential subtasks from CODE_DEPENDENCIES',
                'Step 3: Group related changes (files that change together)',
                'Step 4: Estimate effort per group (MUST be <=5-8h)',
                'Step 5: Determine optimal execution order',
                'Step 6: Flag any subtask >8h for recursive decomposition',
                'Step 7: COUNT subtasks - if >10, GROUP into larger chunks (5-8h each) with [needs-decomposition] tag',
            ])
            ->phase(Store::as('DECOMPOSITION_PLAN', '[{title, scope, files, estimate, dependencies, order}]'));

        // ============================================
        // PHASE 6: SUBTASK SPECIFICATION
        // ============================================

        $this->guideline('phase6-specification')
            ->goal('Create detailed subtask specifications')
            ->example()
            ->phase('For EACH subtask in DECOMPOSITION_PLAN:')
            ->do([
                Operator::forEach(
                    'subtask in $DECOMPOSITION_PLAN',
                    [
                        'title: Concise, action-oriented (max 6 words)',
                        'content: Scope, requirements, acceptance criteria, affected files',
                        'estimate: hours (MUST be <=5-8h)',
                        'priority: inherit from parent or adjust',
                        'tags: inherit parent + subtask-specific + [decomposed]',
                        Operator::if('estimate > 8', 'Add tag [needs-decomposition], FLAG for recursive'),
                    ]
                ),
            ])
            ->phase('DECOMPOSITION STRATEGIES to apply:')
            ->do([
                'LAYERED: Split by layer (API → service → repository → tests)',
                'FEATURE: Split by feature (auth → validation → core → UI)',
                'PHASE: Split by phase (research → implement → test → document)',
                'DEPENDENCY: Independent first, dependent after',
                'RISK: High-risk isolated for focused testing',
            ])
            ->phase(Store::as('SUBTASK_SPECS', '[{title, content, estimate, priority, tags, needs_decomposition}]'));

        // ============================================
        // PHASE 7: USER APPROVAL GATE
        // ============================================

        $this->guideline('phase7-approval')
            ->goal('Present subtasks for user approval (MANDATORY GATE)')
            ->example()
            ->phase('DISPLAY decomposition summary:')
            ->do([
                '═══ DECOMPOSITION SUMMARY ═══',
                'Parent Task: $PARENT_TASK.title (ID: $TASK_ID)',
                'Parent Estimate: $PARENT_TASK.estimate',
                'Existing Subtasks: {count}',
                '═══════════════════════════════',
            ])
            ->phase('FORMAT subtask list as table:')
            ->do([
                '# | Subtask Title | Estimate | Priority | Dependencies | Files | Flags',
                '--|--------------|----------|----------|--------------|-------|------',
                '1 | Setup base structure | 4h | high | - | 3 | -',
                '2 | Implement core logic | 6h | high | #1 | 5 | -',
                '3 | Add validation | 8h | medium | #2 | 4 | [!] NEEDS DECOMPOSE',
                '... (all subtasks)',
            ])
            ->phase('RESEARCH SUMMARY:')
            ->do([
                'Agents used: {count} (VectorMaster, DocMaster, 3x ExploreMaster)',
                'Memory insights: {count} patterns found',
                'Components analyzed: {files} files, {classes} classes',
                'Dependencies mapped: {count} relationships',
                'Total estimate: {sum}h (parent was: {parent_estimate}h)',
            ])
            ->phase('PROMPT:')
            ->do([
                'Ask: "Create {count} subtasks? (yes/no/modify)"',
                Operator::validate('User response is YES, APPROVE, CONFIRM', 'Wait for explicit approval'),
            ]);

        // ============================================
        // PHASE 8: SUBTASK CREATION
        // ============================================

        $this->guideline('phase8-create')
            ->goal('Create subtasks in vector task system after approval')
            ->example()
            ->phase('CREATE subtasks via bulk:')
            ->do([
                VectorTaskMcp::call('task_create_bulk', '{tasks: $SUBTASK_SPECS.map(s => ({
                    title: s.title,
                    content: s.content,
                    parent_id: $TASK_ID,
                    priority: s.priority,
                    tags: s.tags
                }))}'),
                Store::as('CREATED_SUBTASKS', '[{id, title, estimate}]'),
            ])
            ->phase('VERIFY creation:')
            ->do([
                VectorTaskMcp::call('task_list', '{parent_id: $TASK_ID}'),
                'Confirm: {count} subtasks created',
            ]);

        // ============================================
        // PHASE 9: COMPLETION AND MEMORY STORAGE
        // ============================================

        $this->guideline('phase9-complete')
            ->goal('Report completion, store insight, STOP')
            ->example()
            ->phase('STORE decomposition insight:')
            ->do([
                VectorMemoryMcp::call('store_memory', '{
                    content: "DECOMPOSED|$PARENT_TASK.title|subtasks:{count}|strategy:{approach}|estimates:{breakdown}|components:{from CODE_COMPONENTS}",
                    category: "tool-usage",
                    tags: ["task-decomposition", "$TASK_TYPE.domain", "workflow-pattern"]
                }'),
            ])
            ->phase('REPORT:')
            ->do([
                '═══ DECOMPOSITION COMPLETE ═══',
                'Created: {count} subtasks for task #{$TASK_ID}',
                'Total estimate: {sum}h',
                'Agents used: {agent_count} (parallel execution)',
                '═══════════════════════════════',
            ])
            ->phase('RECURSIVE DECOMPOSITION (if any):')
            ->do([
                Operator::if(
                    'any subtask.needs_decomposition',
                    [
                        '[!] SUBTASKS NEED FURTHER DECOMPOSITION:',
                        Operator::forEach(
                            'subtask in $CREATED_SUBTASKS where needs_decomposition',
                            '  - /task:decompose {subtask.id} (estimate: {subtask.estimate}h)'
                        ),
                    ]
                ),
            ])
            ->phase('NEXT STEPS:')
            ->do([
                '  1. /task:decompose {id} - for subtasks >8h',
                '  2. /task:list --parent=$TASK_ID - view hierarchy',
                '  3. /task:next - start first subtask',
            ])
            ->phase('STOP: Do NOT execute any subtask. Return control to user.');

        // ============================================
        // REFERENCE: FORMATS
        // ============================================

        $this->guideline('subtask-format')
            ->text('Required subtask structure')
            ->example('Max 6 words, action-oriented')->key('title')
            ->example('Scope, requirements, acceptance criteria, files')->key('content')
            ->example('MUST be <=5-8h (GOLDEN RULE)')->key('estimate')
            ->example('Inherit or adjust: critical|high|medium|low')->key('priority')
            ->example('Inherit parent + subtask-specific + [decomposed]')->key('tags');

        $this->guideline('estimation-guide')
            ->text('Subtask estimation (GOLDEN RULE: <=5-8h)')
            ->example('1-2h: Config, single file, simple edit')->key('xs')
            ->example('2-4h: Small feature, multi-file, simple tests')->key('s')
            ->example('4-6h: Moderate feature, refactoring')->key('m')
            ->example('6-8h: Complex feature, architectural piece')->key('l')
            ->example('>8h: VIOLATION - decompose further!')->key('violation');

        $this->guideline('grouping-strategy')
            ->text('When initial decomposition yields >10 subtasks, GROUP into larger chunks')
            ->example()
            ->phase('TRIGGER: count($DECOMPOSITION_PLAN) > 10')
            ->do([
                'Step 1: Identify logical clusters (by feature, layer, or dependency chain)',
                'Step 2: Merge related subtasks into parent chunks (5-8h each)',
                'Step 3: Each chunk gets [needs-decomposition] tag',
                'Step 4: Final count MUST be ≤10 subtasks',
                'Step 5: Recommend /task:decompose for each chunk after creation',
            ])
            ->phase('EXAMPLE:')
            ->do([
                '15 subtasks → group into 6-8 chunks:',
                '  - "API Layer" (auth + validation + routes) → 7h [needs-decomposition]',
                '  - "Service Layer" (logic + handlers) → 8h [needs-decomposition]',
                '  - "Data Layer" (models + migrations + seeders) → 6h [needs-decomposition]',
                '  - "Testing" (unit + integration) → 7h [needs-decomposition]',
            ]);

        $this->guideline('parallel-pattern')
            ->text('Parallel agent execution pattern')
            ->example('WRONG: Sequential agent calls → slow, incomplete')
            ->example('RIGHT: Multiple Task() calls in single response')
            ->example('All agents run SIMULTANEOUSLY')
            ->example('Synthesize ALL results before decomposition');

        $this->guideline('directive')
            ->text('PARALLEL agents! DEEP research! 5-8h GOLDEN RULE! User approval! STOP after create!');
    }
}
