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
use BrainNode\Agents\PromptMaster;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('The InitBrain command automates smart distribution of project-specific configuration across Brain.php, Common.php, and Master.php based on project context discovery.')]
class InitBrainInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // =====================================================
        // IRON RULES
        // =====================================================

        $this->rule('temporal-context-first')->critical()
            ->text(['Temporal context MUST be initialized first:', BashTool::call('date +"%Y-%m-%d %H:%M:%S %Z"')])
            ->why('Ensures all research and recommendations reflect current year best practices')
            ->onViolation('Missing temporal context leads to outdated recommendations');

        $this->rule('parallel-research')->critical()
            ->text('Execute independent research tasks in parallel for efficiency')
            ->why('Maximizes throughput and minimizes total execution time')
            ->onViolation('Sequential execution wastes time on independent tasks');

        $this->rule('evidence-based')->critical()
            ->text('All Brain.php guidelines must be backed by discovered project evidence')
            ->why('Prevents generic configurations that do not match project reality')
            ->onViolation('Speculation leads to misaligned Brain behavior');

        $this->rule('vector-memory-storage')->high()
            ->text('Store all significant insights to vector memory with semantic tags')
            ->why('Enables future context retrieval and knowledge accumulation')
            ->onViolation('Knowledge loss and inability to leverage past discoveries');

        $this->rule('preserve-variation')->critical()
            ->text([
                'NEVER modify or replace existing #[Includes()] attributes on Brain.php',
                'Brain already has a Variation (e.g., Scrutinizer) - preserve it',
                'Standard includes from vendor/jarvis-brain/core/src/Includes are OFF LIMITS',
            ])
            ->why('Variations are pre-configured brain personalities with carefully tuned includes')
            ->onViolation('Modifying Variation breaks brain coherence and predefined behavior');

        $this->rule('project-includes-only')->critical()
            ->text([
                'Only analyze and suggest includes from ' . Runtime::NODE_DIRECTORY('Includes/'),
                'FORBIDDEN: vendor/jarvis-brain/core/src/Includes/* modifications',
                'FORBIDDEN: Replacing or adding standard includes to Brain.php',
            ])
            ->why('Standard includes are managed by Variations, not by init process')
            ->onViolation('Standard includes are bundled with Variation - do not duplicate or override');

        $this->rule('smart-distribution')->critical()
            ->text([
                'Distribute project-specific rules across THREE files to avoid duplication:',
                Runtime::NODE_DIRECTORY('Common.php') . ' - Shared by Brain AND all Agents',
                Runtime::NODE_DIRECTORY('Master.php') . ' - Shared by ALL Agents only (NOT Brain)',
                Runtime::NODE_DIRECTORY('Brain.php') . ' - Brain-specific only',
            ])
            ->why('Prevents duplication across components, ensures single source of truth for each rule type')
            ->onViolation('Rule placed in wrong file causes duplication or missing context');

        $this->rule('distribution-categories')->critical()
            ->text([
                'COMMON: Environment (Docker, CI/CD), project tech stack, universal coding standards, shared config',
                'MASTER: Agent execution patterns, tool usage constraints, agent-specific guidelines, task handling',
                'BRAIN: Orchestration rules, delegation strategies, Brain-specific policies, workflow coordination',
            ])
            ->why('Clear categorization ensures each file serves its specific purpose without overlap')
            ->onViolation('Miscategorized rule leads to missing context or unnecessary duplication');

        $this->rule('incremental-enhancement')->critical()
            ->text([
                'ALWAYS analyze existing file content BEFORE enhancement',
                'If file has rules/guidelines - PRESERVE valuable existing, ADD only missing',
                'NEVER blindly overwrite populated files - merge intelligently',
                'Compare discovered patterns with existing config to find gaps',
            ])
            ->why('Preserves manual customizations and avoids losing valuable existing configuration')
            ->onViolation('Valuable existing configuration lost, manual work discarded');

        $this->rule('extract-to-env-variables')->critical()
            ->text([
                'ALL configurable values in generated code MUST use $this->var("KEY", default)',
                'WORKFLOW per file generation (6a, 6b, 7):',
                '  1. READ existing ' . Runtime::BRAIN_DIRECTORY('.env') . ' to get current variables',
                '  2. GENERATE code using $this->var("KEY", default) for configurable values',
                '  3. APPEND new variables to .env with # description and # variants: comments',
                'Variable candidates: thresholds, limits, toggles, versions, paths, model names',
                'Each variable: UPPER_SNAKE_CASE, sensible default, description, variants if applicable',
                'NEVER create empty/dummy variables - only those ACTUALLY USED in generated code',
            ])
            ->why('Centralizes configuration, enables tuning without code changes, prevents magic values')
            ->onViolation('Hardcoded values in code OR unused variables in .env');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('INIT_PARAMS', '{initialization parameters extracted from $RAW_INPUT}'));

        // =====================================================
        // PHASE 1: TEMPORAL CONTEXT INITIALIZATION
        // =====================================================

        $this->guideline('phase1-temporal-context')
            ->goal('Initialize temporal awareness for all subsequent operations')
            ->example()
            ->phase(
                BashTool::describe('date +"%Y-%m-%d"', Store::as('CURRENT_DATE'))
            )
            ->phase(
                BashTool::describe('date +"%Y"', Store::as('CURRENT_YEAR'))
            )
            ->phase(
                BashTool::describe('date +"%Y-%m-%d %H:%M:%S %Z"', Store::as('TIMESTAMP'))
            )
            ->phase(Operator::verify('All temporal variables set'))
            ->note('This ensures all research queries include current year for up-to-date results');

        // =====================================================
        // PHASE 2: PROJECT DISCOVERY (PARALLEL)
        // =====================================================

        $this->guideline('phase2-project-discovery')
            ->goal('Discover project structure, technology stack, and patterns')
            ->example()
            ->note('Execute all discovery tasks in parallel for efficiency')
            ->phase()
            ->name('parallel-discovery-tasks')
            ->do(
                Operator::task([
                    // Task 2.1: Documentation Discovery
                    ExploreMaster::call(
                        Operator::task([
                            'Check if .docs/ directory exists using Glob',
                            'Use Glob("**/.docs/**/*.md") to find documentation files',
                            Operator::if('.docs/ exists', [
                                'Read all .md files from .docs/ directory',
                                'Extract: project goals, requirements, architecture decisions, domain terminology',
                                Store::as('DOCS_CONTENT'),
                            ], [
                                'No .docs/ found',
                                Store::as('DOCS_CONTENT', 'null'),
                            ]),
                        ]),
                        Operator::context('Documentation discovery for project context')
                    ),

                    // Task 2.2: Codebase Structure Analysis
                    ExploreMaster::call(
                        Operator::task([
                            'Analyze project root structure',
                            'Use Glob to find: composer.json, package.json, .env.example, README.md',
                            'Read key dependency files',
                            'Identify project type (Laravel, Node.js, hybrid, etc.)',
                            'Extract technology stack from dependency files',
                            Store::as('PROJECT_TYPE'),
                            Store::as('TECH_STACK', '{languages: [...], frameworks: [...], packages: [...], services: [...]}'),
                        ]),
                        Operator::context('Codebase structure and tech stack analysis')
                    ),

                    // Task 2.3: Architecture Pattern Discovery
                    ExploreMaster::call(
                        Operator::task([
                            'Scan for architectural patterns',
                            'Use Glob to find PHP/JS/TS files in app/ and src/ directories',
                            'Analyze code structure and organization',
                            'Identify: MVC, DDD, CQRS, microservices, monolith, etc.',
                            'Detect design patterns: repositories, services, factories, observers, etc.',
                            'Find coding conventions: naming, structure, organization',
                            Store::as('ARCHITECTURE_PATTERNS', '{architecture_style: "...", design_patterns: [...], conventions: [...]}'),
                        ]),
                        Operator::context('Architecture pattern discovery')
                    ),

                    // Task 2.4: Existing Configuration Analysis (direct Read - known paths)
                    Operator::task([
                        'Read existing configuration files (known paths - no exploration needed):',
                        ReadTool::call(Runtime::NODE_DIRECTORY('Brain.php')),
                        ReadTool::call(Runtime::NODE_DIRECTORY('Common.php')),
                        ReadTool::call(Runtime::NODE_DIRECTORY('Master.php')),
                        'For EACH file analyze handle() method content:',
                        '  - Extract existing $this->rule() definitions (id, severity, text)',
                        '  - Extract existing $this->guideline() definitions (id, phases, examples)',
                        '  - Identify custom logic and project-specific patterns',
                        '  - Mark as POPULATED if handle() has meaningful content beyond skeleton',
                        Store::as('CURRENT_BRAIN_CONFIG', '{includes: [...], rules: [...], guidelines: [...], is_populated: bool}'),
                        Store::as('CURRENT_COMMON_CONFIG', '{rules: [...], guidelines: [...], is_populated: bool}'),
                        Store::as('CURRENT_MASTER_CONFIG', '{rules: [...], guidelines: [...], is_populated: bool}'),
                    ]),
                ])
            )
            ->phase(Operator::verify('All discovery tasks completed'))
            ->phase(Store::as('PROJECT_CONTEXT', 'Merged results from all discovery tasks'));

        // =====================================================
        // PHASE 2.5: ENVIRONMENT DISCOVERY (PARALLEL)
        // =====================================================

        $this->guideline('phase2-5-environment-discovery')
            ->goal('Discover environment configuration, containerization, and infrastructure patterns')
            ->example()
            ->note('Environment rules go to Common.php - shared by Brain AND all Agents')
            ->phase()
            ->name('parallel-environment-tasks')
            ->do(
                Operator::task([
                    // Task 2.5.1: Docker/Container Detection
                    ExploreMaster::call(
                        Operator::task([
                            'Use Glob to find: Dockerfile*, docker-compose*.yml, .dockerignore',
                            'Read Docker configurations if found',
                            'Extract: base images, services, ports, volumes, networks',
                            'Identify: container orchestration patterns (Docker Compose, K8s, etc.)',
                            Store::as('DOCKER_CONFIG', '{has_docker: bool, services: [...], patterns: [...]}'),
                        ]),
                        Operator::context('Docker and containerization discovery')
                    ),

                    // Task 2.5.2: CI/CD Detection
                    ExploreMaster::call(
                        Operator::task([
                            'Use Glob to find: .github/workflows/*.yml, .gitlab-ci.yml, Jenkinsfile, bitbucket-pipelines.yml',
                            'Read CI/CD configurations if found',
                            'Extract: build steps, test runners, deployment targets',
                            'Identify: CI/CD platform and workflow patterns',
                            Store::as('CICD_CONFIG', '{platform: "...", workflows: [...], deployment_targets: [...]}'),
                        ]),
                        Operator::context('CI/CD pipeline discovery')
                    ),

                    // Task 2.5.3: Development Environment Detection
                    ExploreMaster::call(
                        Operator::task([
                            'Use Glob to find: .editorconfig, .prettierrc*, .eslintrc*, phpcs.xml*, phpstan.neon*',
                            'Read linter/formatter configurations if found',
                            'Extract: code style rules, linting rules, analysis levels',
                            'Identify: tooling ecosystem (Prettier, ESLint, PHPStan, etc.)',
                            Store::as('DEV_TOOLS_CONFIG', '{formatters: [...], linters: [...], analyzers: [...]}'),
                        ]),
                        Operator::context('Development tooling discovery')
                    ),

                    // Task 2.5.4: Infrastructure/Services Detection
                    ExploreMaster::call(
                        Operator::task([
                            'Use Glob to find: .env.example, config/*.php, infrastructure/*',
                            'Analyze service connections: databases, caches, queues, storage',
                            'Identify: external service dependencies (AWS, GCP, Redis, Elasticsearch)',
                            'Map infrastructure topology',
                            Store::as('INFRASTRUCTURE_CONFIG', '{services: [...], external_deps: [...], topology: {...}}'),
                        ]),
                        Operator::context('Infrastructure and services discovery')
                    ),
                ])
            )
            ->phase(Operator::verify('Environment discovery completed'))
            ->phase(Store::as('ENVIRONMENT_CONTEXT', 'Merged environment configuration'));

        // =====================================================
        // PHASE 3: DOCUMENTATION DEEP ANALYSIS
        // =====================================================

        $this->guideline('phase3-documentation-analysis')
            ->goal('Deep analysis of project documentation to extract requirements and domain knowledge')
            ->example()
            ->phase(
                Operator::if(Store::get('DOCS_CONTENT') . ' !== null', [
                    DocumentationMaster::call(
                        Operator::input(Store::get('DOCS_CONTENT')),
                        Operator::task([
                            'Analyze all documentation files',
                            'Extract: project goals, requirements, constraints, domain concepts',
                            'Identify: key workflows, business rules, integration points',
                            'Map documentation to Brain configuration needs',
                            'Suggest: custom includes, rules, guidelines based on docs',
                        ]),
                        Operator::output('{goals: [...], requirements: [...], domain_concepts: [...], suggested_config: {...}}'),
                    ),
                    Store::as('DOCS_ANALYSIS'),
                ], [
                    'No documentation found - will rely on codebase analysis only',
                    Store::as('DOCS_ANALYSIS', 'null'),
                ])
            );

        // =====================================================
        // PHASE 3.5: VECTOR MEMORY RESEARCH (Brain direct MCP)
        // =====================================================

        $this->guideline('phase3-5-vector-memory-research')
            ->goal('Search vector memory for project-specific insights via direct MCP calls')
            ->note([
                'Brain uses vector memory MCP tools directly - NO agent delegation needed',
                'Simple tool calls do not require agent orchestration overhead',
                'Multi-probe search: 2-3 focused queries per target file',
            ])
            ->example()
            ->phase('Search vector memory for Common.php insights')
            ->phase(
                Operator::task([
                    VectorMemoryMcp::call('search_memories', '{query: "environment Docker CI/CD containerization rules", limit: 5}'),
                    VectorMemoryMcp::call('search_memories', '{query: "tech stack PHP Node database coding standards", limit: 5}'),
                    VectorMemoryMcp::call('search_memories', '{query: "shared configuration infrastructure patterns", limit: 5}'),
                ])
            )
            ->phase(Store::as('VECTOR_COMMON_INSIGHTS'))
            ->phase('Search vector memory for Master.php insights')
            ->phase(
                Operator::task([
                    VectorMemoryMcp::call('search_memories', '{query: "agent execution patterns tool usage constraints", limit: 5}'),
                    VectorMemoryMcp::call('search_memories', '{query: "task handling decomposition estimation code generation", limit: 5}'),
                    VectorMemoryMcp::call('search_memories', '{query: "test writing conventions quality gates validation", limit: 5}'),
                ])
            )
            ->phase(Store::as('VECTOR_MASTER_INSIGHTS'))
            ->phase('Search vector memory for Brain.php insights')
            ->phase(
                Operator::task([
                    VectorMemoryMcp::call('search_memories', '{query: "orchestration delegation strategies agent selection", limit: 5}'),
                    VectorMemoryMcp::call('search_memories', '{query: "workflow coordination response synthesis validation", limit: 5}'),
                    VectorMemoryMcp::call('search_memories', '{query: "context management memory limits Brain policies", limit: 5}'),
                ])
            )
            ->phase(Store::as('VECTOR_BRAIN_INSIGHTS'))
            ->phase(
                Operator::task([
                    'FILTER vector memory results:',
                    '  - Extract UNIQUE insights not in standard includes',
                    '  - Categorize by target file (Common/Master/Brain)',
                    '  - Reject duplicates and generic knowledge',
                    Store::as('VECTOR_CRITICAL_INSIGHTS', '{common: [...], master: [...], brain: [...]}'),
                ])
            );

        // =====================================================
        // PHASE 4: PROJECT-SPECIFIC INCLUDES ANALYSIS
        // =====================================================

        $this->guideline('phase4-project-includes')
            ->goal('Analyze and suggest PROJECT-SPECIFIC includes only (NOT standard includes)')
            ->note([
                'IMPORTANT: Brain already has a Variation with standard includes configured',
                'This phase focuses ONLY on ' . Runtime::NODE_DIRECTORY('Includes/'),
                'FORBIDDEN: Suggesting or modifying vendor/jarvis-brain/core/src/Includes/*',
                'Brain analyzes ExploreMaster results directly - no additional agent needed',
            ])
            ->example()
            ->phase(
                ExploreMaster::call(
                    Operator::task([
                        'Scan ' . Runtime::NODE_DIRECTORY('Includes/') . ' for existing project includes',
                        'Read each include file to understand its purpose',
                        'Identify gaps in project-specific configuration',
                    ]),
                    Operator::context('Project-specific includes discovery')
                )
            )
            ->phase(Store::as('EXISTING_PROJECT_INCLUDES'))
            ->phase(
                Operator::task([
                    'Brain analyzes EXISTING_PROJECT_INCLUDES directly:',
                    '  - Map discovered includes to project needs from PROJECT_CONTEXT',
                    '  - Identify MISSING project-specific includes based on DOCS_ANALYSIS',
                    '  - DO NOT suggest standard includes from vendor/jarvis-brain/core/src/Includes',
                    '  - Generate list of new project includes to create via brain make:include',
                    Store::as('PROJECT_INCLUDES_RECOMMENDATION', '{existing: [...], suggested_new: [...], rationale: {...}}'),
                ])
            );

        // =====================================================
        // PHASE 5: SMART DISTRIBUTION CATEGORIZATION
        // =====================================================

        $this->guideline('phase5-smart-distribution')
            ->goal('Categorize discovered rules/guidelines into Common, Master, or Brain files')
            ->note([
                'CRITICAL: Each rule MUST go to exactly ONE file to avoid duplication',
                Runtime::NODE_DIRECTORY('Common.php') . ' - Shared by Brain AND all Agents',
                Runtime::NODE_DIRECTORY('Master.php') . ' - Shared by ALL Agents only',
                Runtime::NODE_DIRECTORY('Brain.php') . ' - Brain-specific only',
                'Brain performs categorization directly - simple logic, no agent needed',
            ])
            ->example()
            ->phase(
                Operator::task([
                    'Brain categorizes ALL discovered patterns into target files:',
                    '',
                    'INPUT: PROJECT_CONTEXT, ENVIRONMENT_CONTEXT, DOCS_ANALYSIS, ARCHITECTURE_PATTERNS, VECTOR_CRITICAL_INSIGHTS',
                    '',
                    'COMMON.PHP (Brain + ALL Agents):',
                    '  - Docker/container environment rules (ports, services, networks)',
                    '  - CI/CD pipeline awareness (test commands, build steps)',
                    '  - Project tech stack rules (PHP version, Node version, database type)',
                    '  - Universal coding standards (naming conventions, file structure)',
                    '  - Shared configuration (env vars, paths, external services)',
                    '  - Development tooling rules (linters, formatters, analyzers)',
                    '',
                    'MASTER.PHP (ALL Agents only, NOT Brain):',
                    '  - Agent execution patterns (how agents should approach tasks)',
                    '  - Tool usage constraints (when to use which tools)',
                    '  - Task handling guidelines (decomposition, estimation, status flow)',
                    '  - Code generation patterns (templates, scaffolding)',
                    '  - Test writing conventions (test structure, coverage expectations)',
                    '  - Agent-specific quality gates (validation before completion)',
                    '',
                    'BRAIN.PHP (Brain-specific only):',
                    '  - Orchestration rules (delegation strategies, agent selection)',
                    '  - Brain-specific policies (approval chains, escalation)',
                    '  - Workflow coordination (multi-agent orchestration)',
                    '  - Response synthesis (how to merge agent results)',
                    '  - Brain-level validation (response quality gates)',
                    '',
                    'Generate PHP Builder API code for each category',
                    'Use $this->rule() for constraints, $this->guideline() for patterns',
                    Store::as('DISTRIBUTED_GUIDELINES', '{common: [...], master: [...], brain: [...]}'),
                ])
            );

        // =====================================================
        // PHASE 5A: COMMON.PHP ENHANCEMENT
        // =====================================================

        $this->guideline('phase5a-common-enhancement')
            ->goal('Enhance Common.php with shared project rules for Brain AND all Agents')
            ->note([
                'Common.php is included by BOTH BrainIncludesTrait AND AgentIncludesTrait',
                'Rules here apply universally - avoid agent-specific or brain-specific content',
                'Focus: environment, tech stack, coding standards, shared configuration',
            ])
            ->example()
            ->phase('Read existing Common.php and .env')
            ->phase(
                Operator::task([
                    ReadTool::call(Runtime::NODE_DIRECTORY('Common.php')),
                    Operator::if(Runtime::BRAIN_DIRECTORY('.env') . ' exists', [
                        ReadTool::call(Runtime::BRAIN_DIRECTORY('.env')),
                        Store::as('EXISTING_ENV'),
                    ], [
                        Store::as('EXISTING_ENV', ''),
                    ]),
                ])
            )
            ->phase(Store::as('CURRENT_COMMON_CONFIG'))
            ->phase(
                PromptMaster::call(
                    Operator::input(
                        Store::get('CURRENT_COMMON_CONFIG'),
                        Store::get('DISTRIBUTED_GUIDELINES.common'),
                        Store::get('ENVIRONMENT_CONTEXT'),
                    ),
                    Operator::task([
                        'PRESERVE existing class structure, namespace, and extends IncludeArchetype',
                        Operator::if('CURRENT_COMMON_CONFIG.is_populated', [
                            'MERGE MODE: File has existing content',
                            '  - KEEP all existing rules/guidelines that are still relevant',
                            '  - UPDATE rules if new discovery provides better info (same id, improved text)',
                            '  - ADD only NEW rules/guidelines not already present',
                            '  - REMOVE nothing unless explicitly obsolete',
                            '  - Compare rule IDs to avoid duplicates',
                        ], [
                            'FRESH MODE: File is empty/skeleton - add all discovered rules',
                        ]),
                        'Focus on environment and universal rules:',
                        '  - Docker/container configuration awareness',
                        '  - Tech stack version constraints',
                        '  - Universal coding conventions',
                        '  - Shared infrastructure knowledge',
                        '',
                        'CRITICAL - GENERATE CODE WITH $this->var() IMMEDIATELY:',
                        '  WRONG: ->text("PHP version must be 8.3")',
                        '  RIGHT: ->text(["PHP version must be", $this->var("PHP_VERSION", "8.3")])',
                        '  WRONG: $limit = 100;',
                        '  RIGHT: $limit = $this->var("MAX_LINE_LENGTH", 100);',
                        '',
                        '  For EACH configurable value in generated code:',
                        '    1. USE $this->var("KEY", default) IN THE CODE IMMEDIATELY',
                        '    2. COLLECT to env_vars: {name: "KEY", default: "value", description: "...", variants: "..."}',
                        '',
                        '  Candidates: PHP_VERSION, NODE_VERSION, DATABASE_TYPE, DOCKER_ENABLED',
                        '  Candidates: PHPSTAN_LEVEL, TEST_COVERAGE_MIN, MAX_LINE_LENGTH',
                        '',
                        'Apply prompt engineering: clarity, brevity, token efficiency',
                    ]),
                    Operator::output('{common_php_content: "...", rules_kept: [...], rules_added: [...], rules_updated: [...], env_vars: [{name, default, description, variants}]}'),
                )
            )
            ->phase('Brain receives PromptMaster response with content + env_vars')
            ->phase(Store::as('ENHANCED_COMMON_PHP'))
            ->phase(
                Operator::task([
                    'Write ' . Runtime::NODE_DIRECTORY('Common.php') . ' from ENHANCED_COMMON_PHP.common_php_content',
                    Operator::if('ENHANCED_COMMON_PHP.env_vars not empty', [
                        'APPEND to ' . Runtime::BRAIN_DIRECTORY('.env') . ':',
                        '  # ═══ COMMON ═══ (if not already present)',
                        '  For EACH env_var in ENHANCED_COMMON_PHP.env_vars:',
                        '    IF var.name NOT in EXISTING_ENV:',
                        '      # {var.description}',
                        '      # variants: {var.variants}',
                        '      {var.name}={var.default}',
                    ]),
                ])
            )
            ->phase(
                Operator::note('Common.php written + new env vars appended to .env')
            );

        // =====================================================
        // PHASE 5B: MASTER.PHP ENHANCEMENT
        // =====================================================

        $this->guideline('phase5b-master-enhancement')
            ->goal('Enhance Master.php with agent-specific rules shared by ALL Agents')
            ->note([
                'Master.php is included by AgentIncludesTrait only (NOT Brain)',
                'Rules here apply to all agents but NOT to Brain orchestration',
                'Focus: execution patterns, tool usage, task handling, code generation',
            ])
            ->example()
            ->phase('Read existing Master.php')
            ->phase(
                ReadTool::call(Runtime::NODE_DIRECTORY('Master.php'))
            )
            ->phase(Store::as('CURRENT_MASTER_CONFIG'))
            ->phase(
                PromptMaster::call(
                    Operator::input(
                        Store::get('CURRENT_MASTER_CONFIG'),
                        Store::get('DISTRIBUTED_GUIDELINES.master'),
                        Store::get('ARCHITECTURE_PATTERNS'),
                    ),
                    Operator::task([
                        'PRESERVE existing class structure, namespace, and extends IncludeArchetype',
                        Operator::if('CURRENT_MASTER_CONFIG.is_populated', [
                            'MERGE MODE: File has existing content',
                            '  - KEEP all existing rules/guidelines that are still relevant',
                            '  - UPDATE rules if new discovery provides better info (same id, improved text)',
                            '  - ADD only NEW rules/guidelines not already present',
                            '  - REMOVE nothing unless explicitly obsolete',
                            '  - Compare rule IDs to avoid duplicates',
                        ], [
                            'FRESH MODE: File is empty/skeleton - add all discovered rules',
                        ]),
                        'Focus on agent execution patterns:',
                        '  - How agents should approach project tasks',
                        '  - Tool usage patterns for this project',
                        '  - Code generation conventions',
                        '  - Test writing patterns',
                        '  - Quality gates before task completion',
                        '',
                        'CRITICAL - GENERATE CODE WITH $this->var() IMMEDIATELY:',
                        '  WRONG: ->text("Max task estimate is 8 hours")',
                        '  RIGHT: ->text(["Max task estimate is", $this->var("MAX_TASK_ESTIMATE_HOURS", 8), "hours"])',
                        '  WRONG: $model = "sonnet";',
                        '  RIGHT: $model = $this->var("DEFAULT_AGENT_MODEL", "sonnet");',
                        '',
                        '  For EACH configurable value in generated code:',
                        '    1. USE $this->var("KEY", default) IN THE CODE IMMEDIATELY',
                        '    2. COLLECT to env_vars: {name: "KEY", default: "value", description: "...", variants: "..."}',
                        '',
                        '  Candidates: MAX_TASK_ESTIMATE_HOURS, DEFAULT_AGENT_MODEL, PARALLEL_TASKS',
                        '  Candidates: REQUIRE_TESTS, MIN_COVERAGE, CODE_REVIEW_ENABLED',
                        '',
                        'Apply prompt engineering: clarity, brevity, token efficiency',
                    ]),
                    Operator::output('{master_php_content: "...", rules_kept: [...], rules_added: [...], rules_updated: [...], env_vars: [{name, default, description, variants}]}'),
                )
            )
            ->phase('Brain receives PromptMaster response with content + env_vars')
            ->phase(Store::as('ENHANCED_MASTER_PHP'))
            ->phase(
                Operator::task([
                    'Write ' . Runtime::NODE_DIRECTORY('Master.php') . ' from ENHANCED_MASTER_PHP.master_php_content',
                    Operator::if('ENHANCED_MASTER_PHP.env_vars not empty', [
                        'APPEND to ' . Runtime::BRAIN_DIRECTORY('.env') . ':',
                        '  # ═══ MASTER ═══ (if not already present)',
                        '  For EACH env_var in ENHANCED_MASTER_PHP.env_vars:',
                        '    IF var.name NOT in EXISTING_ENV:',
                        '      # {var.description}',
                        '      # variants: {var.variants}',
                        '      {var.name}={var.default}',
                    ]),
                ])
            )
            ->phase(
                Operator::note('Master.php written + new env vars appended to .env')
            );

        // =====================================================
        // PHASE 6: BRAIN.PHP ENHANCEMENT (PromptMaster)
        // =====================================================

        $this->guideline('phase6-brain-enhancement')
            ->goal('Enhance Brain.php with Brain-specific orchestration rules ONLY')
            ->note([
                'CRITICAL: Preserve ALL existing #[Includes()] attributes - they define the Variation',
                'ONLY add Brain-specific rules (orchestration, delegation, synthesis)',
                'Common rules go to Common.php, agent rules go to Master.php',
            ])
            ->example()
            ->phase('Enhance handle() method with Brain-specific content only')
            ->phase(
                PromptMaster::call(
                    Operator::input(
                        Store::get('CURRENT_BRAIN_CONFIG'),
                        Store::get('PROJECT_INCLUDES_RECOMMENDATION'),
                        Store::get('DISTRIBUTED_GUIDELINES.brain'),
                        Store::get('PROJECT_CONTEXT'),
                    ),
                    Operator::task([
                        'PRESERVE existing #[Includes()] attributes (Variation) - DO NOT MODIFY',
                        'PRESERVE existing class structure and namespace',
                        Operator::if('CURRENT_BRAIN_CONFIG.is_populated', [
                            'MERGE MODE: File has existing handle() content',
                            '  - KEEP all existing rules/guidelines in handle() that are still relevant',
                            '  - UPDATE rules if new discovery provides better info (same id, improved text)',
                            '  - ADD only NEW Brain-specific rules not already present',
                            '  - REMOVE nothing unless explicitly obsolete',
                            '  - Compare rule IDs to avoid duplicates',
                        ], [
                            'FRESH MODE: handle() is empty/skeleton - add all Brain-specific rules',
                        ]),
                        'Focus on Brain-specific rules only (Common/Master rules already distributed):',
                        '  - Orchestration and delegation strategies',
                        '  - Agent selection criteria for this project',
                        '  - Response synthesis patterns',
                        '  - Brain-level validation gates',
                        '',
                        'CRITICAL - GENERATE CODE WITH $this->var() IMMEDIATELY:',
                        '  WRONG: ->text("Default model is sonnet")',
                        '  RIGHT: ->text(["Default model is", $this->var("DEFAULT_MODEL", "sonnet")])',
                        '  WRONG: $depth = 2;',
                        '  RIGHT: $depth = $this->var("MAX_DELEGATION_DEPTH", 2);',
                        '',
                        '  For EACH configurable value in generated code:',
                        '    1. USE $this->var("KEY", default) IN THE CODE IMMEDIATELY',
                        '    2. COLLECT to env_vars: {name: "KEY", default: "value", description: "...", variants: "..."}',
                        '',
                        '  Candidates: DEFAULT_MODEL, MAX_DELEGATION_DEPTH, VALIDATION_THRESHOLD',
                        '  Candidates: ENABLE_PARALLEL_AGENTS, MAX_RETRIES, RESPONSE_MAX_TOKENS',
                        '',
                        'If suggested new project includes, add to #[Includes()] AFTER existing',
                        'Apply prompt engineering: clarity, brevity, token efficiency',
                    ]),
                    Operator::output('{brain_php_content: "...", preserved_variation: "...", rules_kept: [...], rules_added: [...], rules_updated: [...], env_vars: [{name, default, description, variants}]}'),
                )
            )
            ->phase('Brain receives PromptMaster response with content + env_vars')
            ->phase(Store::as('ENHANCED_BRAIN_PHP'))
            ->phase(
                Operator::task([
                    'Write ' . Runtime::NODE_DIRECTORY('Brain.php') . ' from ENHANCED_BRAIN_PHP.brain_php_content',
                    Operator::if('ENHANCED_BRAIN_PHP.env_vars not empty', [
                        'APPEND to ' . Runtime::BRAIN_DIRECTORY('.env') . ':',
                        '  # ═══ BRAIN ═══ (if not already present)',
                        '  For EACH env_var in ENHANCED_BRAIN_PHP.env_vars:',
                        '    IF var.name NOT in EXISTING_ENV:',
                        '      # {var.description}',
                        '      # variants: {var.variants}',
                        '      {var.name}={var.default}',
                    ]),
                ])
            )
            ->phase(
                Operator::note('Brain.php written + new env vars appended to .env')
            );

        // =====================================================
        // PHASE 7: COMPILATION AND VALIDATION
        // =====================================================

        $this->guideline('phase7-compilation')
            ->goal('Validate syntax and compile all enhanced files')
            ->example()
            ->phase('Validate PHP syntax for all modified files')
            ->phase(
                Operator::task([
                    BashTool::describe(
                        'php -l ' . Runtime::NODE_DIRECTORY('Common.php'),
                        'Validate Common.php syntax'
                    ),
                    BashTool::describe(
                        'php -l ' . Runtime::NODE_DIRECTORY('Master.php'),
                        'Validate Master.php syntax'
                    ),
                    BashTool::describe(
                        'php -l ' . Runtime::NODE_DIRECTORY('Brain.php'),
                        'Validate Brain.php syntax'
                    ),
                ])
            )
            ->phase(
                Operator::if('any syntax validation failed', [
                    'Report syntax errors with file:line details',
                    'Provide fix suggestions',
                    Operator::output('Syntax validation failed - review errors above'),
                ])
            )
            ->phase('Compile Brain ecosystem')
            ->phase(
                BashTool::describe(
                    BrainCLI::COMPILE,
                    ['Compile', Runtime::NODE_DIRECTORY('Brain.php'), 'with includes to', Runtime::BRAIN_FILE]
                )
            )
            ->phase(
                Operator::verify([
                    'Compilation succeeded',
                    Runtime::BRAIN_FILE . ' exists',
                    'No compilation errors',
                    'Common.php included via BrainIncludesTrait',
                    'Master.php available for AgentIncludesTrait',
                ])
            )
            ->phase(
                Operator::if('compilation failed', [
                    'Report compilation errors with details',
                    'Provide fix suggestions',
                    Operator::output('Compilation failed - review errors above'),
                ])
            );

        // =====================================================
        // PHASE 8: KNOWLEDGE STORAGE
        // =====================================================

        $this->guideline('phase8-knowledge-storage')
            ->goal('Store all insights to vector memory for future reference')
            ->example()
            ->phase(
                VectorMemoryMcp::call('store_memory', Operator::input(
                    'content: "Brain Initialization - Project: {project_type}, Tech Stack: {tech_stack}, Patterns: {architecture_patterns}, Date: {current_date}"',
                    'category: "architecture"',
                    'tags: ["init-brain", "project-discovery", "configuration"]',
                ))
            )
            ->phase(
                VectorMemoryMcp::call('store_memory', Operator::input(
                    'content: "Environment Discovery - Docker: {has_docker}, CI/CD: {cicd_platform}, Dev Tools: {dev_tools}, Date: {current_date}"',
                    'category: "architecture"',
                    'tags: ["init-brain", "environment", "infrastructure"]',
                ))
            )
            ->phase(
                VectorMemoryMcp::call('store_memory', Operator::input(
                    'content: "Smart Distribution - Common: {common_rules_count} rules, Master: {master_rules_count} rules, Brain: {brain_rules_count} rules, Date: {current_date}"',
                    'category: "architecture"',
                    'tags: ["init-brain", "distribution", "configuration"]',
                ))
            )
            ->phase(
                VectorMemoryMcp::call('store_memory', Operator::input(
                    'content: "Vector Memory Mining - Common: {vector_common_count}, Master: {vector_master_count}, Brain: {vector_brain_count}, Total validated: {vector_total_validated}, Date: {current_date}"',
                    'category: "learning"',
                    'tags: ["init-brain", "vector-mining", "insights"]',
                ))
            );

        // =====================================================
        // PHASE 9: REPORT GENERATION
        // =====================================================

        $this->guideline('phase9-report')
            ->goal('Generate comprehensive initialization report with smart distribution summary')
            ->example()
            ->phase(
                Operator::output([
                    'Brain Ecosystem Initialization Complete',
                    '',
                    '═══════════════════════════════════════════════════════',
                    'SMART DISTRIBUTION SUMMARY',
                    '═══════════════════════════════════════════════════════',
                    '',
                    Runtime::NODE_DIRECTORY('Common.php') . ' (Brain + ALL Agents):',
                    '  Mode: {common_mode}',
                    '  Kept: {common_rules_kept} | Added: {common_rules_added} | Updated: {common_rules_updated}',
                    '  ENV vars: {common_env_count}',
                    '',
                    Runtime::NODE_DIRECTORY('Master.php') . ' (ALL Agents only):',
                    '  Mode: {master_mode}',
                    '  Kept: {master_rules_kept} | Added: {master_rules_added} | Updated: {master_rules_updated}',
                    '  ENV vars: {master_env_count}',
                    '',
                    Runtime::NODE_DIRECTORY('Brain.php') . ' (Brain only):',
                    '  Variation: {existing_variation_name} (PRESERVED)',
                    '  Mode: {brain_mode}',
                    '  Kept: {brain_rules_kept} | Added: {brain_rules_added} | Updated: {brain_rules_updated}',
                    '  ENV vars: {brain_env_count}',
                    '',
                    '═══════════════════════════════════════════════════════',
                    'DISCOVERY RESULTS',
                    '═══════════════════════════════════════════════════════',
                    '',
                    'Project:',
                    '  Type: {project_type}',
                    '  Tech Stack: {tech_stack}',
                    '  Architecture: {architecture_patterns}',
                    '',
                    'Environment:',
                    '  Docker: {has_docker}',
                    '  CI/CD Platform: {cicd_platform}',
                    '  Dev Tools: {dev_tools}',
                    '  Infrastructure: {infrastructure_services}',
                    '',
                    'Documentation:',
                    '  Files Analyzed: {docs_file_count}',
                    '  Domain Concepts: {domain_concepts_count}',
                    '  Requirements: {requirements_count}',
                    '',
                    'Vector Memory Mining:',
                    '  Total Mined: {vector_total_mined}',
                    '  Critical Filtered: {vector_critical_count}',
                    '  Added to Common: {vector_common_count}',
                    '  Added to Master: {vector_master_count}',
                    '  Added to Brain: {vector_brain_count}',
                    '',
                    '═══════════════════════════════════════════════════════',
                    'OUTPUT FILES',
                    '═══════════════════════════════════════════════════════',
                    '',
                    'Source Files:',
                    '  ' . Runtime::NODE_DIRECTORY('Brain.php'),
                    '  ' . Runtime::NODE_DIRECTORY('Common.php'),
                    '  ' . Runtime::NODE_DIRECTORY('Master.php'),
                    '',
                    'Compiled Output:',
                    '  ' . Runtime::BRAIN_FILE,
                    '',
                    'Configuration:',
                    '  ' . Runtime::BRAIN_DIRECTORY('.env'),
                    '  Variables: {env_settings_count} ({env_kept} kept, {env_added} added)',
                    '',
                    '═══════════════════════════════════════════════════════',
                    'VECTOR MEMORY',
                    '═══════════════════════════════════════════════════════',
                    '',
                    '  Insights Stored: {insights_count}',
                    '  Categories: architecture, learning',
                    '  Tags: init-brain, project-discovery, distribution',
                    '',
                    '═══════════════════════════════════════════════════════',
                    'NEXT STEPS',
                    '═══════════════════════════════════════════════════════',
                    '',
                    '  1. Review enhanced files:',
                    '     - Common.php: shared environment/coding rules',
                    '     - Master.php: agent execution patterns',
                    '     - Brain.php: orchestration rules (Variation preserved)',
                    '',
                    '  2. If project includes suggested:',
                    '     brain make:include {name}',
                    '',
                    '  3. Test Brain behavior with sample tasks',
                    '',
                    '  4. After any modifications:',
                    '     brain compile',
                    '',
                    '  5. Consider running:',
                    '     /init-agents for agent generation',
                    '     /init-vector for vector memory population',
                ])
            );

        // =====================================================
        // ERROR RECOVERY
        // =====================================================

        $this->guideline('error-recovery')
            ->text('Comprehensive error handling for all failure scenarios')
            ->example()
            ->phase()->if('no .docs/ found', [
                'Continue with codebase analysis only',
                'Log: Documentation not available',
            ])
            ->phase()->if('tech stack detection fails', [
                'Use manual fallback detection',
                'Analyze file extensions and structure',
            ])
            ->phase()->if('vector memory research fails', [
                'Continue with codebase-only discovery',
                'Log: Vector memory unavailable',
            ])
            ->phase()->if(BrainCLI::LIST_INCLUDES . ' fails', [
                'Use hardcoded standard includes list',
                'Log: Include discovery failed',
            ])
            ->phase()->if('Brain.php generation fails', [
                'Report detailed error with file:line',
                'Provide manual fix guidance',
            ])
            ->phase()->if(BrainCLI::COMPILE . ' fails', [
                'Analyze compilation errors',
                'Provide fix suggestions',
            ])
            ->phase()->if('vector memory storage fails', [
                'Continue without storage',
                'Log: Memory storage unavailable',
            ]);

        // =====================================================
        // QUALITY GATES
        // =====================================================

        $this->guideline('quality-gates')
            ->text('Validation checkpoints throughout initialization')
            ->example('Gate 1: Temporal context initialized (date, year, timestamp)')
            ->example('Gate 2: Project discovery completed with valid tech stack')
            ->example('Gate 3: Environment discovery completed (Docker, CI/CD, Dev Tools)')
            ->example('Gate 4: At least one discovery task succeeded (docs OR codebase)')
            ->example('Gate 5: Smart distribution categorization completed (Common/Master/Brain)')
            ->example('Gate 6: All enhanced files written successfully')
            ->example('Gate 7: All enhanced files pass PHP syntax validation')
            ->example('Gate 8: Compilation completes without errors')
            ->example('Gate 9: Compiled output exists at ' . Runtime::BRAIN_FILE)
            ->example('Gate 10: At least one insight stored to vector memory');

        // =====================================================
        // EXAMPLES
        // =====================================================

        $this->guideline('example-laravel-docker-project')
            ->scenario('Laravel project with Docker, Sail, and comprehensive documentation')
            ->example()
            ->phase('Discovery: Laravel 11, PHP 8.3, MySQL, Redis, Queue, Sanctum')
            ->phase('Environment: Docker (Sail), GitHub Actions CI/CD, PHPStan L8')
            ->phase('Docs: 15 .md files with architecture, requirements, domain logic')
            ->phase('Vector Mining: 12 insights found, 8 validated for distribution')
            ->phase('')
            ->phase('SMART DISTRIBUTION:')
            ->phase('  Common.php: Docker/Sail environment rules, PHP 8.3 type constraints, MySQL conventions')
            ->phase('  Master.php: Service class patterns, repository usage, Pest test conventions')
            ->phase('  Brain.php: Agent delegation for Laravel domains (Auth, Queue, Cache)')
            ->phase('')
            ->phase('Result: All three files enhanced, Scrutinizer Variation preserved')
            ->phase('Insights: 8 architectural insights stored to vector memory');

        $this->guideline('example-node-docker-project')
            ->scenario('Node.js/Express project with Docker and TypeScript')
            ->example()
            ->phase('Discovery: Node.js 20, Express, TypeScript, MongoDB')
            ->phase('Environment: Docker Compose, GitLab CI, ESLint + Prettier')
            ->phase('Docs: None found - codebase analysis only')
            ->phase('Vector Mining: 7 insights found, 5 validated for distribution')
            ->phase('')
            ->phase('SMART DISTRIBUTION:')
            ->phase('  Common.php: Docker network rules, Node 20 constraints, ESLint compliance')
            ->phase('  Master.php: TypeScript type generation, async/await patterns, Jest test structure')
            ->phase('  Brain.php: API route delegation strategy')
            ->phase('')
            ->phase('Result: All three files enhanced, Architect Variation preserved')
            ->phase('Insights: 5 tech stack insights stored');

        $this->guideline('example-hybrid-microservices')
            ->scenario('Hybrid PHP/JavaScript microservices with Kubernetes')
            ->example()
            ->phase('Discovery: Laravel API + React SPA + Docker + Kafka')
            ->phase('Environment: Kubernetes, GitHub Actions, PHPStan + ESLint')
            ->phase('Docs: ADRs, API specs, deployment docs, domain model')
            ->phase('Vector Mining: 18 insights found, 12 validated for distribution')
            ->phase('')
            ->phase('SMART DISTRIBUTION:')
            ->phase('  Common.php: K8s service discovery, cross-service authentication, Kafka topic naming')
            ->phase('  Master.php: Event schema validation, API contract testing, service boundary respect')
            ->phase('  Brain.php: Multi-service orchestration, cross-domain delegation, event saga coordination')
            ->phase('')
            ->phase('Project Includes: Suggested MicroserviceBoundaries.php, EventSchemas.php')
            ->phase('Result: All three files enhanced with microservice awareness')
            ->phase('Insights: 12 cross-cutting concerns stored');

        // =====================================================
        // PERFORMANCE OPTIMIZATION
        // =====================================================

        $this->guideline('performance-optimization')
            ->text('Optimization strategies for efficient initialization')
            ->example()
            ->phase('Parallel Execution: All independent tasks run simultaneously')
            ->phase('Selective Reading: Only read files needed for analysis')
            ->phase('Incremental Storage: Store insights progressively, not at end')
            ->phase('Smart Caching: Leverage vector memory for repeated runs')
            ->phase('Early Validation: Fail fast on critical errors')
            ->phase('Streaming Output: Report progress as phases complete');

        // =====================================================
        // DIRECTIVE
        // =====================================================

        $this->guideline('directive')
            ->text('Core initialization directive')
            ->example('Discover thoroughly! Research current practices! Configure precisely! Validate rigorously! Store knowledge! Report comprehensively!');
    }
}
