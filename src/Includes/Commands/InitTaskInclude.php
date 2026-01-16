<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainNode\Agents\DocumentationMaster;
use BrainNode\Agents\ExploreMaster;
use BrainNode\Agents\VectorMaster;
use BrainNode\Agents\WebResearchMaster;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Aggressive project task initializer with MAXIMUM parallel agent orchestration. Scans every project corner via specialized agents, creates comprehensive epic-level tasks. NEVER executes - only creates.')]
class InitTaskInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // ============================================
        // IRON RULES - AGGRESSIVE ORCHESTRATION
        // ============================================

        $this->rule('parallel-agent-execution')->critical()
            ->text('Launch INDEPENDENT research agents in PARALLEL (multiple Task calls in single response)')
            ->why('Maximizes coverage, reduces total research time, comprehensive analysis')
            ->onViolation('Group independent areas, launch ALL simultaneously');

        $this->rule('every-corner-coverage')->critical()
            ->text('MUST explore EVERY project area: code, tests, config, docs, build, migrations, routes, schemas')
            ->why('First layer tasks define entire project. Missing areas = missing epics = incomplete planning')
            ->onViolation('Add missing areas to parallel exploration batch');

        $this->rule('multi-agent-research')->critical()
            ->text('Use SPECIALIZED agents for each domain: ExploreMaster(code), DocumentationMaster(docs), VectorMaster(memory), WebResearchMaster(external)')
            ->why('Each agent has domain expertise. Single agent cannot comprehensively analyze all areas.')
            ->onViolation('Delegate to appropriate specialized agent');

        $this->rule('create-only-no-execution')->critical()
            ->text('This command ONLY creates root tasks. NEVER execute any task after creation.')
            ->why('Init-task creates strategic foundation. Execution via /task:next or /do')
            ->onViolation('STOP immediately after task creation');

        $this->rule('mandatory-user-approval')->critical()
            ->text('MUST get explicit user YES/APPROVE/CONFIRM before creating ANY tasks')
            ->why('User must validate task breakdown before committing')
            ->onViolation('Present task list and wait for explicit confirmation');

        $this->rule('estimate-required')->critical()
            ->text('MUST provide time estimate (8-40h) for EACH epic')
            ->why('Estimates enable planning and identify tasks needing decomposition')
            ->onViolation('Add estimate before presenting epic');

        $this->rule('exclude-brain-directory')->critical()
            ->text('NEVER analyze ' . Runtime::BRAIN_DIRECTORY . ' - Brain system internals, not project code')
            ->why('Brain config pollutes task list with irrelevant system tasks')
            ->onViolation('Skip ' . Runtime::BRAIN_DIRECTORY . ' in all exploration phases');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('INIT_PARAMS', '{initialization parameters extracted from $RAW_INPUT}'));

        // ============================================
        // PHASE 0: PRE-FLIGHT CHECKS
        // ============================================

        $this->guideline('phase0-preflight')
            ->goal('Check existing state, determine mode')
            ->example()
            ->phase(Operator::output([
                '=== INIT:TASK ACTIVATED ===',
                '',
                '=== PHASE 0: PRE-FLIGHT CHECKS ===',
                'Checking task state...',
            ]))
            ->phase('STEP 1 - Check task state:')
            ->do([
                VectorTaskMcp::call('task_stats', '{}'),
                Store::as('TASK_STATE', '{total, pending, in_progress}'),
            ])
            ->phase('STEP 2 - Decision:')
            ->do([
                Operator::if('$TASK_STATE.total === 0', 'Fresh init → proceed'),
                Operator::if('$TASK_STATE.total > 0', 'Ask: "Tasks exist. (1) Add more, (2) Clear & restart, (3) Abort"'),
            ]);

        // ============================================
        // PHASE 1: STRUCTURE DISCOVERY (Quick Scan)
        // ============================================

        $this->guideline('phase1-structure')
            ->goal('Quick structure scan to identify ALL areas for parallel exploration')
            ->example()
            ->phase(
                ExploreMaster::call(
                    Operator::task([
                        'QUICK STRUCTURE SCAN - identify directories only',
                        'Glob("*") → list root directories and key files',
                        'EXCLUDE: ' . Runtime::BRAIN_DIRECTORY . ', vendor/, node_modules/, .git/',
                        'IDENTIFY: code(src/app), tests, config, docs(.docs), migrations, routes, build, public',
                        'Output JSON: {areas: [{path, type, estimated_files, priority}]}',
                    ]),
                    Operator::output('{areas: [{path, type, estimated_files, priority: critical|high|medium|low}]}'),
                    Store::as('PROJECT_AREAS')
                )
            );

        // ============================================
        // PHASE 2: PARALLEL EXPLORATION BATCH 1 - CODE
        // ============================================

        $this->guideline('phase2-parallel-code')
            ->goal('PARALLEL: Launch code exploration agents simultaneously')
            ->example()
            ->phase('BATCH 1 - Core Code Areas (LAUNCH IN PARALLEL):')
            ->do([
                ExploreMaster::call(
                    Operator::task([
                        'Area: src/ or app/ (MAIN CODE)',
                        'Thoroughness: very thorough',
                        'ANALYZE: directory structure, namespaces, classes, design patterns',
                        'IDENTIFY: entry points, core modules, service layers, models',
                        'EXTRACT: {path|files_count|classes|namespaces|patterns|complexity}',
                        'FOCUS ON: what needs to be built/refactored/improved',
                    ]),
                    Operator::output('{path:"src",files:N,modules:[],patterns:[],tech_debt:[]}'),
                    Store::as('CODE_ANALYSIS')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: tests/ (TEST COVERAGE)',
                        'Thoroughness: medium',
                        'ANALYZE: test structure, frameworks, coverage areas',
                        'IDENTIFY: tested modules, missing coverage, test patterns',
                        'EXTRACT: {path|test_files|framework|covered_modules|gaps}',
                    ]),
                    Operator::output('{path:"tests",files:N,framework:str,coverage_gaps:[]}'),
                    Store::as('TEST_ANALYSIS')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: database/ + migrations/ (DATA LAYER)',
                        'Thoroughness: thorough',
                        'ANALYZE: migrations, seeders, factories, schema',
                        'IDENTIFY: tables, relationships, indexes, pending migrations',
                        'EXTRACT: {migrations_count|tables|relationships|pending_changes}',
                    ]),
                    Operator::output('{migrations:N,tables:[],relationships:[],pending:[]}'),
                    Store::as('DATABASE_ANALYSIS')
                ),
            ])
            ->phase('NOTE: All 3 ExploreMaster agents run SIMULTANEOUSLY');

        // ============================================
        // PHASE 3: PARALLEL EXPLORATION BATCH 2 - CONFIG & ROUTES
        // ============================================

        $this->guideline('phase3-parallel-config')
            ->goal('PARALLEL: Config, routes, and infrastructure analysis')
            ->example()
            ->phase('BATCH 2 - Config & Infrastructure (LAUNCH IN PARALLEL):')
            ->do([
                ExploreMaster::call(
                    Operator::task([
                        'Area: config/ (CONFIGURATION)',
                        'Thoroughness: quick',
                        'ANALYZE: config files, env vars, service bindings',
                        'IDENTIFY: services configured, missing configs, security settings',
                        'EXTRACT: {configs:[names],services:[],env_vars_needed:[]}',
                    ]),
                    Operator::output('{configs:[],services:[],security_gaps:[]}'),
                    Store::as('CONFIG_ANALYSIS')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: routes/ (API SURFACE)',
                        'Thoroughness: thorough',
                        'ANALYZE: route definitions, middleware, controllers',
                        'IDENTIFY: endpoints, auth requirements, API versioning',
                        'EXTRACT: {routes_count|endpoints:[method,path,controller]|middleware:[]}',
                    ]),
                    Operator::output('{routes:N,api_endpoints:[],web_routes:[],middleware:[]}'),
                    Store::as('ROUTES_ANALYSIS')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: build/CI (.github/, docker*, Makefile)',
                        'Thoroughness: quick',
                        'ANALYZE: CI/CD pipelines, Docker setup, build scripts',
                        'IDENTIFY: deployment process, missing CI steps, containerization',
                        'EXTRACT: {ci:bool,docker:bool,pipelines:[],missing:[]}',
                    ]),
                    Operator::output('{ci:bool,docker:bool,deployment_ready:bool,gaps:[]}'),
                    Store::as('BUILD_ANALYSIS')
                ),
            ])
            ->phase('NOTE: All 3 agents run SIMULTANEOUSLY with Batch 1');

        // ============================================
        // PHASE 4: DOCUMENTATION ANALYSIS
        // ============================================

        $this->guideline('phase4-documentation')
            ->goal('Index docs via brain docs, then PARALLEL DocumentationMaster analysis')
            ->example()
            ->phase('STEP 1 - Get documentation index:')
            ->do([
                BashTool::call(BrainCLI::DOCS),
                Store::as('DOCS_INDEX', '[{path, name, description, type}]'),
            ])
            ->phase('STEP 2 - Adaptive batching based on doc count:')
            ->do([
                Operator::if('docs_count <= 3', 'Single DocumentationMaster for all'),
                Operator::if('docs_count 4-8', '2 DocumentationMaster agents in parallel'),
                Operator::if('docs_count > 8', '3+ DocumentationMaster agents in parallel'),
            ])
            ->phase('STEP 3 - PARALLEL DocumentationMaster agents:')
            ->do([
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [README*, CONTRIBUTING*, ARCHITECTURE*]',
                        'Read each doc via Read tool',
                        'EXTRACT: {name|purpose|requirements|constraints|decisions}',
                        'FOCUS ON: project goals, user requirements, acceptance criteria',
                    ]),
                    Operator::output('{docs_analyzed:N,requirements:[],constraints:[]}'),
                    Store::as('DOCS_REQUIREMENTS')
                ),
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [API docs, technical specs, .docs/*.md]',
                        'Read each doc via Read tool',
                        'EXTRACT: {name|endpoints|integrations|dependencies}',
                        'FOCUS ON: technical requirements, API contracts, integrations',
                    ]),
                    Operator::output('{docs_analyzed:N,api_specs:[],integrations:[]}'),
                    Store::as('DOCS_TECHNICAL')
                ),
            ])
            ->phase('STEP 4 - README.md direct read for project overview:')
            ->do([
                ReadTool::call('README.md'),
                Store::as('README_CONTENT', 'project overview, features, setup'),
            ]);

        // ============================================
        // PHASE 5: VECTOR MEMORY DEEP RESEARCH
        // ============================================

        $this->guideline('phase5-vector-research')
            ->goal('VectorMaster for comprehensive prior knowledge extraction')
            ->example()
            ->phase(
                VectorMaster::call(
                    Operator::task([
                        'DEEP MEMORY RESEARCH for project planning',
                        'Multi-probe search strategy:',
                        'Probe 1: "project architecture implementation patterns" (architecture)',
                        'Probe 2: "project requirements features roadmap" (learning)',
                        'Probe 3: "bugs issues problems technical debt" (bug-fix)',
                        'Probe 4: "decisions trade-offs alternatives" (code-solution)',
                        'Probe 5: "project context conventions standards" (project-context)',
                        'EXTRACT: past decisions, known issues, lessons learned, patterns',
                        'OUTPUT: actionable insights for task planning',
                    ]),
                    Operator::output('{memories_found:N,insights:[],warnings:[],recommendations:[]}'),
                    Store::as('PRIOR_KNOWLEDGE')
                )
            )
            ->phase('PARALLEL: Direct memory searches for specific categories:')
            ->do([
                VectorMemoryMcp::call('search_memories', '{query: "project goals objectives success criteria", limit: 5, category: "learning"}'),
                VectorMemoryMcp::call('search_memories', '{query: "technical debt refactoring needed", limit: 5, category: "architecture"}'),
                VectorMemoryMcp::call('search_memories', '{query: "blocked issues dependencies", limit: 5, category: "debugging"}'),
            ]);

        // ============================================
        // PHASE 6: EXTERNAL CONTEXT (if needed)
        // ============================================

        $this->guideline('phase6-external')
            ->goal('WebResearchMaster for external dependencies and APIs')
            ->example()
            ->phase('CONDITIONAL: If project uses external services/APIs:')
            ->do([
                Operator::if(
                    'external services detected in config/routes analysis',
                    WebResearchMaster::call(
                        Operator::task([
                            'Research external dependencies: {detected_services}',
                            'Find: API documentation, rate limits, best practices',
                            'Find: known issues, integration patterns, gotchas',
                            'OUTPUT: integration requirements, constraints, risks',
                        ]),
                        Operator::output('{services_researched:N,requirements:[],risks:[]}'),
                        Store::as('EXTERNAL_CONTEXT')
                    ),
                    Operator::skip('No external dependencies detected')
                ),
            ]);

        // ============================================
        // PHASE 7: SYNTHESIS VIA SEQUENTIAL THINKING
        // ============================================

        $this->guideline('phase7-synthesis')
            ->goal('Synthesize ALL research into comprehensive project context')
            ->example()
            ->phase('COMBINE all stored research:')
            ->do([
                Store::get('CODE_ANALYSIS'),
                Store::get('TEST_ANALYSIS'),
                Store::get('DATABASE_ANALYSIS'),
                Store::get('CONFIG_ANALYSIS'),
                Store::get('ROUTES_ANALYSIS'),
                Store::get('BUILD_ANALYSIS'),
                Store::get('DOCS_REQUIREMENTS'),
                Store::get('DOCS_TECHNICAL'),
                Store::get('README_CONTENT'),
                Store::get('PRIOR_KNOWLEDGE'),
                Store::get('EXTERNAL_CONTEXT'),
            ])
            ->phase('SEQUENTIAL THINKING for strategic decomposition:')
            ->do([
                SequentialThinkingMcp::call('sequentialthinking', '{
                    thought: "Analyzing comprehensive research from 8+ parallel agents. Synthesizing into strategic epics.",
                    thoughtNumber: 1,
                    totalThoughts: 8,
                    nextThoughtNeeded: true
                }'),
            ])
            ->phase('SYNTHESIS STEPS:')
            ->do([
                'Step 1: Extract project scope, primary objectives, success criteria',
                'Step 2: Map functional requirements from docs + code analysis',
                'Step 3: Map non-functional requirements (performance, security, scalability)',
                'Step 4: Identify current state: greenfield / existing / refactor',
                'Step 5: Calculate completion percentage per area',
                'Step 6: Identify major work streams (future epics)',
                'Step 7: Map dependencies between work streams',
                'Step 8: Prioritize: blockers first, then core, then features',
            ])
            ->phase(Store::as('PROJECT_SYNTHESIS', 'comprehensive project understanding'));

        // ============================================
        // PHASE 8: EPIC GENERATION
        // ============================================

        $this->guideline('phase8-epic-generation')
            ->goal('Generate 5-15 strategic epics from synthesis')
            ->example()
            ->phase('EPIC GENERATION RULES:')
            ->do([
                'Target: 5-15 root epics (not too few, not too many)',
                'Each epic: major work stream, 8-40 hours estimate',
                'Epic boundaries: clear scope, deliverables, acceptance criteria',
                'Dependencies: identify inter-epic dependencies',
                'Tags: [epic, {domain}, {stack}, {phase}]',
            ])
            ->phase('EPIC CATEGORIES to consider:')
            ->do([
                'FOUNDATION: setup, infrastructure, CI/CD, database schema',
                'CORE: main features, business logic, models, services',
                'API: endpoints, authentication, authorization, contracts',
                'FRONTEND: UI components, views, assets, interactions',
                'TESTING: unit tests, integration tests, E2E, coverage',
                'SECURITY: auth, validation, encryption, audit',
                'PERFORMANCE: optimization, caching, scaling, monitoring',
                'DOCUMENTATION: API docs, guides, deployment docs',
                'TECH_DEBT: refactoring, upgrades, cleanup, migrations',
            ])
            ->phase(Store::as('EPIC_LIST', '[{title, content, priority, estimate, tags, dependencies}]'));

        // ============================================
        // PHASE 9: USER APPROVAL GATE
        // ============================================

        $this->guideline('phase9-approval')
            ->goal('Present epics for user approval (MANDATORY GATE)')
            ->example()
            ->phase('FORMAT epic list as table:')
            ->do([
                '# | Epic Title | Priority | Estimate | Dependencies | Tags',
                '---|------------|----------|----------|--------------|-----',
                '1 | Foundation Setup | critical | 16h | - | [epic,infra,setup]',
                '2 | Core Models | high | 24h | #1 | [epic,backend,models]',
                '... (all epics)',
            ])
            ->phase('SUMMARY:')
            ->do([
                'Total epics: {count}',
                'Total estimated hours: {sum}',
                'Critical path: {epics with dependencies}',
                'Research agents used: {count} (Explore, Doc, Vector, Web)',
                'Areas analyzed: code, tests, database, config, routes, build, docs, memory',
            ])
            ->phase('PROMPT:')
            ->do([
                'Ask: "Approve epic creation? (yes/no/modify)"',
                Operator::validate('User response is YES, APPROVE, or CONFIRM', 'Wait for explicit approval'),
            ]);

        // ============================================
        // PHASE 10: TASK CREATION
        // ============================================

        $this->guideline('phase10-create')
            ->goal('Create epics in vector task system after approval')
            ->example()
            ->phase('CREATE epics:')
            ->do([
                VectorTaskMcp::call('task_create_bulk', '{tasks: ' . Store::get('EPIC_LIST') . '}'),
                Store::as('CREATED_EPICS', '[task_ids]'),
            ])
            ->phase('VERIFY creation:')
            ->do([
                VectorTaskMcp::call('task_stats', '{}'),
                'Confirm: {count} epics created',
            ]);

        // ============================================
        // PHASE 11: COMPLETION & MEMORY STORAGE
        // ============================================

        $this->guideline('phase11-complete')
            ->goal('Report completion, store insight, STOP')
            ->example()
            ->phase('STORE initialization insight:')
            ->do([
                VectorMemoryMcp::call('store_memory', '{
                    content: "PROJECT_INIT|epics:{count}|hours:{total}|areas:{list}|stack:{tech}|critical_path:{deps}",
                    category: "architecture",
                    tags: ["project-init", "epics", "planning", "init-task"]
                }'),
            ])
            ->phase('REPORT:')
            ->do([
                '═══ INIT-TASK COMPLETE ═══',
                'Epics created: {count}',
                'Total estimate: {hours}h',
                'Agents used: {agent_count} (parallel execution)',
                'Areas covered: code, tests, db, config, routes, build, docs, memory, external',
                '═══════════════════════════',
                '',
                'NEXT STEPS:',
                '  1. /task:decompose {epic_id} - Break down each epic',
                '  2. /task:list - View all tasks',
                '  3. /task:next - Start first task',
            ])
            ->phase('STOP: Do NOT execute any task. Return control to user.');

        // ============================================
        // REFERENCE: FORMATS
        // ============================================

        $this->guideline('epic-format')
            ->text('Required epic structure')
            ->example('title: Concise name (max 10 words)')->key('title')
            ->example('content: Scope, objectives, deliverables, acceptance criteria')->key('content')
            ->example('priority: critical | high | medium | low')->key('priority')
            ->example('estimate: 8-40 hours (will be decomposed)')->key('estimate')
            ->example('tags: [epic, {domain}, {stack}, {phase}]')->key('tags');

        $this->guideline('estimation-guide')
            ->text('Epic estimation guidelines')
            ->example('8-16h: Focused, single domain')->key('small')
            ->example('16-24h: Cross-component, moderate')->key('medium')
            ->example('24-32h: Architectural, integrations')->key('large')
            ->example('32-40h: Foundational, high complexity')->key('xlarge')
            ->example('>40h: Split into multiple epics')->key('split');

        $this->guideline('parallel-pattern')
            ->text('How to execute agents in parallel')
            ->example('WRONG: forEach(areas) → sequential, slow, incomplete')
            ->example('RIGHT: List multiple Task() calls in single response')
            ->example('Brain executes all Task() calls simultaneously')
            ->example('Each agent stores findings, then synthesize all');

        $this->guideline('directive')
            ->text('PARALLEL agents! EVERY corner! MAXIMUM coverage! Dense synthesis! User approval!');
    }
}
