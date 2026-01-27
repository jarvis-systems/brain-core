<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands;

use BrainCore\Archetypes\AgentArchetype;
use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Agents\AgentMaster;
use BrainNode\Agents\ExploreMaster;
use BrainNode\Agents\WebResearchMaster;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('The InitAgents command initializes the project with agents based on industry best practices.')]
class InitAgentsInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // Iron Rules
        $this->rule('mandatory-agentmaster-delegation')->critical()
            ->text('Brain MUST delegate ALL agent generation to AgentMaster. FORBIDDEN: Brain creating agents directly.')
            ->why('Separation of orchestration (Brain) and execution (AgentMaster). Brain is NOT an executor.')
            ->onViolation('ABORT. Delegate to AgentMaster immediately. Brain orchestrates, never executes.');

        $this->rule('project-agents-only')->critical()
            ->text([
                'ONLY create PROJECT-SPECIFIC agents using Master variation',
                'FORBIDDEN: Creating or modifying SYSTEM agents (AgentMaster, PromptMaster, CommitMaster, etc.)',
                'FORBIDDEN: Modifying agents from vendor/jarvis-brain/core/src/',
                'System agents use SystemMaster variation and are OFF LIMITS',
            ])
            ->why('System agents are pre-configured via SystemMaster variation. Project agents use Master variation.')
            ->onViolation('Skip system agent. Only create project-specific agents.');

        $this->rule('preserve-system-agents')->critical()
            ->text([
                'System agents (ending with Master) are managed by SystemMaster variation',
                'Examples: AgentMaster, PromptMaster, CommitMaster, WebResearchMaster, ExploreMaster, DocumentationMaster, VectorMaster, ScriptMaster',
                'These agents are specialized for Brain orchestration - NEVER regenerate or modify them',
            ])
            ->why('System agents have carefully tuned configurations for Brain ecosystem')
            ->onViolation('ABORT modification. System agents are immutable.');

        $this->rule('parallel-agentmaster-execution')->critical()
            ->text('Run up to 5 AgentMaster instances in PARALLEL. Each AgentMaster can generate 1-3 agents per batch.')
            ->why('Maximizes throughput. Sequential generation wastes time. Parallel = 5x faster.')
            ->onViolation('Batch agents into groups of 3, delegate to 5 AgentMasters concurrently.');

        $this->rule('no-interactive-questions')->critical()
            ->text('NO interactive questions. Fully automated gap analysis and generation.')
            ->why('Automated workflow requires zero user prompts')
            ->onViolation('Execute fully automated');

        $this->rule('brain-make-master-only')->critical()
            ->text(['AgentMaster MUST use', BashTool::call(BrainCLI::MAKE_MASTER), 'for creation - NOT Write() or Edit()'])
            ->why('Ensures proper PHP archetype structure and compilation compatibility')
            ->onViolation('Reject manually created agents');

        $this->rule('no-regeneration')->critical()
            ->text('Skip existing agents. Idempotent operation.')
            ->why('Safe for repeated execution')
            ->onViolation('Skip and continue');

        $this->rule('delegates-web-research')->high()
            ->text('Delegate web research to WebResearchMaster. Brain NEVER executes WebSearch.')
            ->why('Maintains delegation hierarchy')
            ->onViolation('Delegate to WebResearchMaster');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('INIT_PARAMS', '{initialization parameters extracted from $RAW_INPUT}'));

        // Phase 0: Arguments Processing
        $this->guideline('phase0-arguments-processing')
            ->goal('Process optional user arguments to narrow search scope and improve targeting')
            ->example()
            ->phase(Operator::output([
                '=== INIT:AGENTS ACTIVATED ===',
                '',
                '=== PHASE 0: ARGUMENTS PROCESSING ===',
                'Processing input...',
            ]))
            ->phase(Store::as('TARGET_DOMAIN', '{extract domain hint from $RAW_INPUT if provided}'))
            ->phase('Parse $RAW_INPUT for specific domain/technology/agent hints')
            ->phase(Operator::if('$RAW_INPUT provided', [
                'Extract: target_domain from $TARGET_DOMAIN (e.g., "Laravel", "React", "API"), target_technology, specific_agents',
                Store::as('SEARCH_FILTER', '{domain: $TARGET_DOMAIN, tech: ..., agents: [...], keywords: [...]}'),
                'Set search_mode = "targeted"',
                'Log: "Targeted mode: focusing on {domain}/{tech}"'
            ]))
            ->phase(Operator::if('$RAW_INPUT empty', [
                'Set search_mode = "discovery"',
                'Use full project analysis workflow',
                'Log: "Discovery mode: full project analysis"'
            ]))
            ->phase('Store search mode for use in subsequent phases');

        // Phase 1: Get Temporal Context + Parallel Web Search
        $this->guideline('phase1-temporal-context-and-web-cache')
            ->goal('Get current date/year for temporal context AND check vector memory cache for recent patterns')
            ->example()
            ->phase(BashTool::describe('date +"%Y-%m-%d"', Store::as('CURRENT_DATE')))
            ->phase(BashTool::describe('date +"%Y"', Store::as('CURRENT_YEAR')))
            ->phase('PARALLEL: Check vector memory cache while temporal context loads')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "multi-agent architecture patterns", category: "learning", limit: 3}'))
            ->phase(Operator::if('cache_hit AND cache_age < 30 days', [
                Store::as('CACHED_PATTERNS', 'Cached industry patterns from vector memory'),
                Store::as('CACHE_VALID', 'true'),
                Store::as('CACHE_AGE', '{days}'),
                'Log: "Using cached patterns (age: {days} days)"'
            ]))
            ->phase(Operator::if('no_cache OR cache_old', [
                Store::as('CACHE_VALID', 'false'),
                'Log: "Fresh web search required"'
            ]));

        // Phase 1.5: Web Search - Best Practices (DELEGATED TO WebResearchMaster)
        $this->guideline('phase1.5-web-search-best-practices')
            ->goal('Delegate industry best practices research to WebResearchMaster with cache awareness')
            ->note('Delegated to WebResearchMaster for industry research')
            ->example()
            ->phase(Operator::if('search_mode === "discovery"', [
                WebResearchMaster::call(
                    Operator::input(
                        Store::get('CURRENT_YEAR'),
                        Store::get('CACHED_PATTERNS'),
                        Store::get('CACHE_VALID'),
                        Store::get('CACHE_AGE'),
                    ),
                    Operator::task(
                        Operator::if('cache_valid === true', 'Use cached patterns, skip web search'),
                        Operator::if('cache_valid === false', [
                            'Research: multi-agent system architecture best practices {year}',
                            'Research: AI agent orchestration patterns {year}',
                            'Research: domain-driven agent design principles {year}',
                            'Synthesize findings into unified patterns',
                        ]),
                    ),
                    Operator::output('{architecture: [...], orchestration: [...], domain_design: [...], sources: [...], cache_used: true|false}'),
                    Store::as('INDUSTRY_PATTERNS')
                ),
                Operator::if('fresh research performed', 'Store results in vector memory'),
                VectorMemoryMcp::call('store_memory', '{content: $INDUSTRY_PATTERNS, category: "learning", tags: ["agent-patterns", "best-practices", "{CURRENT_YEAR}"]}')
            ]))
            ->phase(Operator::if('search_mode === "targeted"', [
                'Use $CACHED_PATTERNS from phase 1 if available',
                'Log: "Targeted mode - using cached patterns, skipping general research"'
            ]));

        // Phase 2: Inventory Existing Agents (separating system vs project)
        $this->guideline('phase2-inventory-agents')
            ->goal('List all existing agents and separate SYSTEM agents from PROJECT agents')
            ->note([
                'SYSTEM agents (use SystemMaster variation): AgentMaster, PromptMaster, CommitMaster, WebResearchMaster, ExploreMaster, DocumentationMaster, VectorMaster, ScriptMaster',
                'PROJECT agents (use Master variation): All other agents created for project-specific needs',
                'System agents are OFF LIMITS - only inventory, never modify or regenerate',
            ])
            ->example()
            ->phase([BashTool::call(BrainCLI::LIST_MASTERS), 'Parse output'])
            ->phase(Store::as('ALL_AGENTS', '[{id, name, description}, ...]'))
            ->phase('Filter system agents (names ending with "Master" AND in system list)')
            ->phase(Store::as('SYSTEM_AGENTS', '[AgentMaster, PromptMaster, CommitMaster, WebResearchMaster, ExploreMaster, DocumentationMaster, VectorMaster, ScriptMaster, ...]'))
            ->phase('Filter project agents (all others)')
            ->phase(Store::as('PROJECT_AGENTS', '[...project-specific agents...]'))
            ->phase(['Agents located in', Runtime::NODE_DIRECTORY('Agents/*.php')])
            ->phase('Count: system_agents = count($SYSTEM_AGENTS), project_agents = count($PROJECT_AGENTS)')
            ->phase('Log: "System agents (protected): {system_count}, Project agents (manageable): {project_count}"');

        // Phase 3: Extract Project Stack (DELEGATED - ENHANCED with search filter)
        $this->guideline('phase3-read-project-stack')
            ->goal('Extract project technology stack with optional filtering based on $TARGET_DOMAIN')
            ->example(
                ExploreMaster::call(Operator::task([
                    Operator::if('search_mode === "targeted"', [
                        'Priority 1: Focus exploration on $SEARCH_FILTER.domain and $SEARCH_FILTER.tech',
                        'Priority 2: Validate against project documentation (.docs/, CLAUDE.md)',
                        'Priority 3: Extract related technologies and dependencies'
                    ]),
                    Operator::if('search_mode === "discovery"', [
                        'Priority 1: Explore .docs/ directory if exists. Find all *.md files.',
                        'Priority 2: Extract: technologies, frameworks, services, domain requirements',
                        'Priority 3: Explore project files in ./ for tech stack (composer.json, package.json, etc.)'
                    ]),
                    Store::as('PROJECT_STACK', '{technologies: [...], frameworks: [...], services: [...], domain_requirements: [...], primary_stack: "...", confidence: 0-1}'),
                ]))
            );

        // Phase 3.5: Stack-Specific Web Search (DELEGATED TO WebResearchMaster)
        $this->guideline('phase3.5-stack-specific-search')
            ->goal('Delegate technology-specific research to WebResearchMaster based on discovered stack')
            ->note('Delegated to WebResearchMaster for technology-specific patterns')
            ->example()
            ->phase('Extract primary technologies from $PROJECT_STACK (max 3 most important)')
            ->phase(
                WebResearchMaster::call(
                    Operator::input(
                        Store::get('PROJECT_STACK.technologies'),
                        Store::get('CURRENT_YEAR'),
                        Store::get('SEARCH_FILTER'),
                        Store::get('SEARCH_MODE'),
                    ),
                    Operator::task(
                        'Extract top 3 most important technologies from stack',
                        Operator::if('search_mode === "targeted"', 'Focus on ' . Store::get('SEARCH_FILTER') . ' tech'),
                        Operator::forEach('technology in top_3_technologies', [
                            Operator::if('technology is major framework/language', [
                                'Research: {technology} specialized agents best practices {year}',
                                'Research: {technology} multi-agent architecture examples {year}',
                                'Extract: common patterns, agent types, use cases',
                            ]),
                        ]),
                        'Synthesize per-technology patterns',
                    ),
                    Operator::output('{tech_patterns: {Laravel: [...], React: [...]}, tech_examples: {...}}'),
                    Store::as('TECH_PATTERNS')
                )
            )
            ->phase('Cache technology patterns in vector memory')
            ->phase(VectorMemoryMcp::call('store_memory', '{content: $TECH_PATTERNS, category: "learning", tags: ["tech-patterns", $PROJECT_STACK.primary_stack, "{CURRENT_YEAR}"]}'))
            ->phase(Operator::if('search_mode === "targeted"', [
                'Log: "Found {count} patterns for {$SEARCH_FILTER.tech}"',
                'Boost relevance score for matching patterns'
            ]));

        // Phase 4: Gap Analysis (PROJECT AGENTS ONLY)
        $this->guideline('phase4-gap-analysis')
            ->goal('Identify missing PROJECT-SPECIFIC agents (NOT system agents)')
            ->note([
                'Gap analysis compares against PROJECT_AGENTS only, not SYSTEM_AGENTS',
                'FORBIDDEN: Suggesting system agents (AgentMaster, PromptMaster, etc.) as missing',
                'New agents use Master variation, NOT SystemMaster',
                'AgentMaster analyzes gaps using already collected data - no additional web search needed',
            ])
            ->example()
            ->phase('AgentMaster performs gap analysis using cached patterns')
            ->phase(
                AgentMaster::call(
                    Operator::input(
                        Store::get('PROJECT_AGENTS'),
                        Store::get('SYSTEM_AGENTS'),
                        Store::get('PROJECT_STACK'),
                        Store::get('INDUSTRY_PATTERNS'),
                        Store::get('TECH_PATTERNS'),
                        Store::get('SEARCH_FILTER'),
                    ),
                    Operator::task(
                        'Analyze domain expertise needed based on Project requirements',
                        'Compare with existing PROJECT agents (NOT system agents)',
                        'EXCLUDE any agent that overlaps with SYSTEM_AGENTS functionality',
                        'Cross-validate against INDUSTRY_PATTERNS and TECH_PATTERNS (already cached)',
                        'EXCLUDE: system agent types (orchestration, delegation, memory management)',
                        'INCLUDE: domain-specific agents (API, Cache, Auth, Payment, etc.)',
                        'Assign confidence score (0-1) to each missing agent recommendation',
                        'Prioritize critical gaps with high industry alignment',
                        'All new agents will use Master variation (NOT SystemMaster)',
                        Operator::if('search_mode === "targeted"', 'Focus on $SEARCH_FILTER domains only'),
                    ),
                    Operator::output('{covered_domains: [...], missing_agents: [{name: \'AgentName\', purpose: \'...\', capabilities: [...], confidence: 0-1, industry_alignment: 0-1, priority: "critical|high|medium", variation: "Master"}], industry_coverage_score: 0-1}'),
                    Operator::note('Focus on PROJECT agent gaps with high confidence and industry alignment'),
                )
            )
            ->phase(Store::as('GAP_ANALYSIS'))
            ->phase('Filter: confidence >= 0.6, industry_alignment >= 0.6, priority != "low"')
            ->phase('Validate: NO agent names match SYSTEM_AGENTS list')
            ->phase('Sort by: priority DESC, confidence DESC, industry_alignment DESC');

        // Phase 4.5: Validate Against Industry Standards (REMOVED - integrated into phase4)
        // This validation is now part of the AgentMaster delegation in phase4
        // AgentMaster can use WebSearchTool internally as part of its workflow

        // Phase 5: PARALLEL Project Agent Generation (5 AgentMasters, 1-3 agents each)
        $this->guideline('phase5-parallel-generation')
            ->goal('Create missing PROJECT agents via PARALLEL AgentMaster delegation. Max 5 concurrent AgentMasters.')
            ->note([
                'Created agents are PROJECT agents using Master variation',
                'Created files must be valid PHP archetypes extending ' . AgentArchetype::class,
                'FORBIDDEN: Creating any agent that matches SYSTEM_AGENTS list',
            ])
            ->example()
            ->phase('Step 1: Filter and batch agents')
            ->phase([
                'Remove: existing agents, confidence < 0.6, priority === "low"',
                'Remove: any agent matching SYSTEM_AGENTS names (AgentMaster, PromptMaster, etc.)',
                'Keep: confidence >= 0.6 AND (priority === "critical" OR priority === "high")',
                Store::as('FILTERED_AGENTS', '[...filtered project agents only...]'),
            ])
            ->phase('Step 2: Batch into groups of 3 (max 5 batches = 15 agents)')
            ->phase([
                'batch_1 = agents[0:3], batch_2 = agents[3:6], ...',
                Store::as('AGENT_BATCHES', '[[batch1], [batch2], [batch3], [batch4], [batch5]]'),
            ])
            ->phase('Step 3: Pre-create all agent files via brain make:master')
            ->phase(Operator::forEach('agent in $FILTERED_AGENTS', [
                BashTool::describe(BrainCLI::MAKE_MASTER('{agent.name}'), ['Creates', Runtime::NODE_DIRECTORY('Agents/{agent.name}.php')]),
            ]))
            ->phase('Step 4: PARALLEL delegation to 5 AgentMasters')
            ->phase([
                'CRITICAL: Launch ALL 5 Task() calls in SINGLE message block',
                'Each AgentMaster receives batch of 1-3 PROJECT agents to configure',
                'AgentMasters work CONCURRENTLY, not sequentially',
                'All agents use Master variation (for project agents)',
            ])
            ->phase(Operator::forEach('batch in $AGENT_BATCHES (PARALLEL)', [
                AgentMaster::call(
                    Operator::input(
                        'batch_agents = [{name, purpose, capabilities, confidence, variation: "Master"}, ...]',
                        Store::get('INDUSTRY_PATTERNS'),
                        Store::get('TECH_PATTERNS'),
                        Store::get('PROJECT_AGENTS'),
                    ),
                    Operator::task(
                        'FOREACH agent in batch_agents:',
                        '  1. Read created file: .brain/node/Agents/{agent.name}.php',
                        '  2. Update #[Purpose()] with detailed domain expertise',
                        '  3. Add #[Includes(Master::class)] for project agent variation',
                        '  4. Add project-specific includes from ' . Runtime::NODE_DIRECTORY('Includes/'),
                        '  5. Define rules + guidelines in handle()',
                        '  6. Use PHP API only (Runtime::, Operator::, Store::)',
                        'Return: {completed: [...], failed: [...]}',
                    ),
                    Operator::output('{batch_id, completed: [names], failed: [names], errors: [...]}'),
                ),
            ]))
            ->phase('Step 5: Aggregate results from all AgentMasters')
            ->phase([
                'Merge: all completed PROJECT agents from 5 AgentMaster responses',
                'Collect: all failures for error report',
                Store::as('GENERATION_SUMMARY', '{generated: [...], failed: [...], total: N, variation: "Master"}'),
            ]);

        // Phase 6: Compile Agents
        $this->guideline('phase6-compile')
            ->goal('Compile all agents to', Runtime::AGENTS_FOLDER)
            ->example()
            ->phase(BashTool::describe(BrainCLI::COMPILE, ['Compiles', Runtime::NODE_DIRECTORY('Agents/*.php'), 'to', Runtime::AGENTS_FOLDER]))
            ->phase(Operator::verify(
                Operator::check(Runtime::AGENTS_FOLDER, 'for new agent files'),
                'Compilation completed without errors'
            ))
            ->phase('Log: "Compilation complete. New agents available in {AGENTS_FOLDER}"');

        // Phase 7: Report with Confidence Metrics
        $this->guideline('phase7-report-enhanced')
            ->goal('Report generation results with confidence scores, industry alignment, and caching status')
            ->example()
            ->phase(Operator::if('agents_generated > 0', [
                'Calculate: avg_confidence = average(generated_agents.confidence)',
                'Calculate: avg_industry_alignment = average(generated_agents.industry_alignment)',
                VectorMemoryMcp::call('store_memory', '{content: "Init Gap Analysis: mode={search_mode}, technologies={$PROJECT_STACK.technologies}, agents_generated={agents_count}, avg_confidence={avg_confidence}, avg_industry_alignment={avg_industry_alignment}, coverage=improved, date={$CURRENT_DATE}", category: "architecture", tags: ["init", "gap-analysis", "agents", "{CURRENT_YEAR}"]}'),
                Operator::output('Generation summary with agent details, confidence scores, and industry alignment metrics'),
            ]))
            ->phase(Operator::if('agents_generated === 0', [
                VectorMemoryMcp::call('store_memory', '{content: "Init Gap Analysis: mode={search_mode}, result=full_coverage, agents={agents_count}, date={$CURRENT_DATE}", category: "architecture", tags: ["init", "full-coverage", "{CURRENT_YEAR}"]}'),
                Operator::output('Full coverage confirmation with existing agent list and industry coverage score'),
            ]))
            ->phase('Include cache performance metrics: {cache_hits}, {web_searches_performed}');

        // Response Format (unified)
        $this->guideline('response-format')
            ->text('Response structure')
            ->example('Header: Init Gap Analysis Complete | Mode: {search_mode}')
            ->example('System Agents (protected): {system_count} | Variation: SystemMaster')
            ->example('Project Agents Generated: {count} | Variation: Master')
            ->example('Quality: confidence={avg}, alignment={avg}')
            ->example('Performance: cache_hits={n}, parallel_batches={n}')
            ->phase(['Created:', Runtime::NODE_DIRECTORY('Agents/'),'| Compiled:', Runtime::AGENTS_FOLDER])
            ->phase(['Ready via:', TaskTool::agent('{name}', '...')]);

        // Error Recovery (compressed)
        $this->guideline('error-recovery')
            ->text('Graceful degradation')
            ->example('no .docs/ → Brain context only, continue')
            ->example('agent exists → skip, log preserved')
            ->example('system agent suggested → skip, log "system agent protected"')
            ->example('make:master fails → skip agent, continue')
            ->example('compile fails → report errors, list failed')
            ->example('AgentMaster fails → skip batch, continue')
            ->example('web timeout → use cached, mark partial')
            ->example('no internet → local only, -0.2 confidence')
            ->example('memory unavailable → skip cache, continue');

        // Quality Gates (compressed)
        $this->guideline('quality-gates')
            ->text('Validation checkpoints')
            ->example('Gate 1-3: temporal context, cache check, list:masters')
            ->example('Gate 4: system vs project agents separated')
            ->example('Gate 5-6: web delegation, gap analysis output')
            ->example('Gate 7: NO system agents in GAP_ANALYSIS.missing_agents')
            ->example('Gate 8-9: confidence >= 0.6, alignment >= 0.6')
            ->example('Gate 10-11: make:master success, compile success')
            ->example(['Gate 12:', Runtime::AGENTS_FOLDER, 'populated with project agents']);

        // Example: Parallel batch generation (PROJECT AGENTS ONLY)
        $this->guideline('example-parallel-batch')
            ->scenario('System: 8 protected (SystemMaster) | Project: 10 discovered → 4 batches → 4 parallel AgentMasters')
            ->example()
            ->phase('inventory', 'System agents (OFF LIMITS): AgentMaster, PromptMaster, CommitMaster, ExploreMaster, WebResearchMaster, DocumentationMaster, VectorMaster, ScriptMaster')
            ->phase('gap', '10 missing PROJECT agents: API, Cache, Queue, Auth, Payment, Report, Search, Export, Import, Sync')
            ->phase('variation', 'All new agents use Master variation (NOT SystemMaster)')
            ->phase('batch', 'batch_1=[API,Cache,Queue], batch_2=[Auth,Payment,Report], batch_3=[Search,Export], batch_4=[Import,Sync]')
            ->phase('parallel', 'Launch 4 Task(@agent-agent-master) in SINGLE message')
            ->phase('result', 'All 10 PROJECT agents created with Master variation in ~1 AgentMaster cycle');

        // Directive
        $this->guideline('directive')
            ->text('PROJECT agents ONLY! Master variation! NEVER touch system agents! DELEGATE to AgentMaster! PARALLEL batches! brain make:master! Compile!');
    }
}
