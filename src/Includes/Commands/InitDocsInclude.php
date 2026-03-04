<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Store;
use BrainCore\Includes\Commands\Task\TaskCommandCommonTrait;
use BrainNode\Agents\DocumentationMaster;
use BrainNode\Agents\ExploreMaster;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Batch documentation initialization: parallel project scanning, documentation plan generation, and automated .docs/ population. Discovers what needs documenting, generates comprehensive base documentation with proper YAML front matter.')]
class InitDocsInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // =========================================================================
        // IRON RULES (from trait — universal safety, no tag taxonomy: init-docs doesn't create tasks)
        // =========================================================================
        $this->defineIronExecutionRules();
        $this->defineSecretsPiiProtectionRules();
        $this->defineNoDestructiveGitRules();
        $this->defineFailurePolicyRules();
        $this->defineCognitiveLevelGuidelines();

        // =========================================================================
        // IRON RULES (command-specific)
        // =========================================================================
        $this->rule('auto-approve-default')->critical()
            ->text('Default behavior is FULLY AUTOMATED (no user prompts). $HAS_AUTO_APPROVE = true confirms. Without -y: show doc plan before generation. With -y: fully silent pipeline.')
            ->why('Automated workflow requires zero interaction by default.')
            ->onViolation('Proceed autonomously. Never block on user input.');

        $this->rule('batch-not-interactive')->critical()
            ->text('This is BATCH documentation generation. Generate ALL planned docs in one run. Do NOT pause between individual documents. Use /doc:work for interactive single-doc workflow.')
            ->why('Init-docs covers entire project. Pausing per doc defeats batch purpose.')
            ->onViolation('Continue to next doc. Show summary at end.');

        $this->rule('yaml-front-matter-mandatory')->critical()
            ->text('EVERY generated .md file MUST start with valid YAML front matter. docs_search MCP tool indexes ONLY files with valid YAML. Format: see .docs/ examples.')
            ->why('Files without YAML front matter are invisible to docs_search MCP tool and all commands that use it.')
            ->onViolation('Add YAML front matter. Verify with ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '...']) . ' after writing.');

        $this->rule('text-first-code-last')->critical()
            ->text('Documentation is DESCRIPTION for humans. Minimize code to absolute minimum. Text first, diagrams second, code as last resort.')
            ->why('Code in docs becomes stale fast, costs tokens, hard to maintain. Text-first = sustainable.')
            ->onViolation('Replace code blocks with textual description.');

        $this->rule('500-line-limit')->critical()
            ->text('Each documentation file MUST NOT exceed 500 lines. Split into parts with YAML part: N field.')
            ->why('Readability, token efficiency, docs_search MCP tool indexing performance.')
            ->onViolation('Split content. Add part field to YAML. Cross-reference between parts.');

        $this->rule('no-duplicate-docs')->critical()
            ->text('NEVER create documentation for topics that already have docs. Check docs_search MCP tool index BEFORE generating each doc.')
            ->why('Duplicate docs diverge over time. One source of truth per topic.')
            ->onViolation('Skip topic. Log: "existing doc found at {path}".');

        $this->rule('exclude-brain-directory')->critical()
            ->text('NEVER document ' . Runtime::BRAIN_DIRECTORY . ' - Brain system internals, not project code')
            ->why('Brain config is internal system, not project documentation scope')
            ->onViolation('Skip ' . Runtime::BRAIN_DIRECTORY . ' in all analysis phases');

        $this->rule('evidence-based')->critical()
            ->text('ALL documentation content MUST be based on actual codebase reading. NEVER write docs from assumptions.')
            ->why('Documentation based on assumptions becomes lies when code changes.')
            ->onViolation('Read source code first. Verify every claim against actual implementation.');

        // =========================================================================
        // INPUT CAPTURE (from InputCaptureTrait via TaskCommandCommonTrait)
        // =========================================================================
        $this->defineInputCaptureWithCustomGuideline([
            'DOC_SCOPE' => '{optional scope filter from $CLEAN_ARGS: specific area/module to document, or empty for full project}',
        ]);

        // =========================================================================
        // WORKFLOW
        // =========================================================================

        // Phase 1: Pre-flight — check existing docs state
        $this->guideline('phase1-preflight')
            ->goal('Check .docs/ state, determine fresh vs augment mode')
            ->example()
            ->phase(Operator::output([
                '=== INIT:DOCS ACTIVATED ===',
                '',
                '=== PHASE 1: PRE-FLIGHT CHECKS ===',
            ]))
            ->phase('STEP 1 - Check existing documentation:')
            ->do([
                BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']),
                Store::as('EXISTING_DOCS', '[{path, name, description, type}]'),
            ])
            ->phase('STEP 2 - Check vector memory for prior doc insights:')
            ->do([
                VectorMemoryMcp::callValidatedJson('search_memories', ['query' => 'documentation project structure modules', 'limit' => 5, 'category' => self::CAT_PROJECT_CONTEXT]),
                Store::as('PRIOR_DOC_KNOWLEDGE'),
            ])
            ->phase('STEP 3 - Decision:')
            ->do([
                Operator::if('$EXISTING_DOCS empty', 'Fresh init → full project documentation needed'),
                Operator::if('$EXISTING_DOCS not empty', 'Augment mode → identify gaps, skip existing topics'),
            ]);

        // Phase 2: Project Discovery (PARALLEL)
        $this->guideline('phase2-parallel-discovery')
            ->goal('PARALLEL: Scan entire project to identify documentable areas')
            ->example()
            ->phase('BATCH 1 - Core Discovery (LAUNCH IN PARALLEL):')
            ->do([
                ExploreMaster::call(
                    Operator::task([
                        'Area: src/ or app/ (MAIN CODE)',
                        'Thoroughness: very thorough',
                        'ANALYZE: modules, services, models, controllers, key classes',
                        'IDENTIFY: public APIs, entry points, architectural patterns',
                        'EXTRACT: {modules:[{name,path,classes,purpose}], patterns:[], architecture:"..."}',
                        Operator::if('$DOC_SCOPE not empty', 'FOCUS on areas matching $DOC_SCOPE only'),
                    ]),
                    Operator::output('{modules:[],patterns:[],architecture:str,api_surface:[]}'),
                    Store::as('CODE_DISCOVERY')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: config/, routes/, database/',
                        'Thoroughness: medium',
                        'ANALYZE: configuration options, API endpoints, database schema',
                        'IDENTIFY: key configs, route groups, migrations, relationships',
                        'EXTRACT: {configs:[],routes:{api:[],web:[]},database:{tables:[],relationships:[]}}',
                    ]),
                    Operator::output('{configs:[],routes:{},database:{}}'),
                    Store::as('INFRA_DISCOVERY')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: tests/, .github/, docker*',
                        'Thoroughness: quick',
                        'ANALYZE: test coverage areas, CI/CD setup, deployment patterns',
                        'IDENTIFY: test frameworks, deployment targets, build steps',
                        'EXTRACT: {tests:{framework,coverage_areas:[]},ci:{},deployment:{}}',
                    ]),
                    Operator::output('{tests:{},ci:{},deployment:{}}'),
                    Store::as('OPS_DISCOVERY')
                ),
            ])
            ->phase('NOTE: All 3 ExploreMaster agents run SIMULTANEOUSLY');

        // Phase 3: Documentation Plan Generation
        $this->guideline('phase3-doc-plan')
            ->goal('Generate documentation plan from discovery results')
            ->example()
            ->phase('COMBINE all discovery data:')
            ->do([
                Store::get('CODE_DISCOVERY'),
                Store::get('INFRA_DISCOVERY'),
                Store::get('OPS_DISCOVERY'),
                Store::get('EXISTING_DOCS'),
            ])
            ->phase('GENERATE documentation plan:')
            ->do([
                'For EACH documentable area, create doc entry:',
                '  {path: ".docs/{type}/{name}.md", name, description, type, sections_outline, estimated_lines}',
                '',
                'DOC TYPES to consider:',
                '  architecture/ — system overview, component relationships, data flow',
                '  modules/ — per-module documentation (models, services, controllers)',
                '  api/ — API endpoints, request/response formats, authentication',
                '  guides/ — setup guide, deployment guide, development workflow',
                '  concepts/ — domain concepts, business logic, key algorithms',
                '  reference/ — configuration reference, environment variables',
                '',
                'FILTERS:',
                '  - SKIP topics already in $EXISTING_DOCS',
                '  - SKIP if estimated < 20 lines (too trivial)',
                '  - SPLIT if estimated > 500 lines into parts',
                Operator::if('$DOC_SCOPE not empty', 'KEEP only docs matching $DOC_SCOPE'),
            ])
            ->phase(Store::as('DOC_PLAN', '[{path, name, description, type, sections, estimated_lines}]'));

        // Phase 4: User Approval Gate
        $this->guideline('phase4-approval')
            ->goal('Present doc plan for user approval (conditional on $HAS_AUTO_APPROVE)')
            ->example()
            ->phase('FORMAT doc plan as table:')
            ->do([
                '# | Path | Type | ~Lines | Description',
                '---|------|------|--------|------------',
                '1 | .docs/architecture/overview.md | architecture | ~200 | System architecture overview',
                '2 | .docs/modules/auth.md | module | ~150 | Authentication module',
                '... (all planned docs)',
            ])
            ->phase('SUMMARY:')
            ->do([
                'Total docs to generate: {count}',
                'Total estimated lines: {sum}',
                'Existing docs preserved: {existing_count}',
                'Doc types: {type_breakdown}',
            ])
            ->phase(Operator::if('NOT $HAS_AUTO_APPROVE', [
                'Ask: "Approve documentation generation? (yes/no/modify)"',
                Operator::validate('User response is YES, APPROVE, or CONFIRM', 'Wait for explicit approval'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved. Proceeding to generation.'));

        // Phase 5: PARALLEL Documentation Generation
        $this->guideline('phase5-parallel-generation')
            ->goal('Generate documentation via PARALLEL DocumentationMaster agents')
            ->example()
            ->phase('STEP 1 - Adaptive batching based on doc count:')
            ->do([
                Operator::if('docs_count <= 3', 'Single DocumentationMaster for all'),
                Operator::if('docs_count 4-8', '2 DocumentationMaster agents in parallel'),
                Operator::if('docs_count 9-15', '3 DocumentationMaster agents in parallel'),
                Operator::if('docs_count > 15', '4+ DocumentationMaster agents, batched by type'),
            ])
            ->phase('STEP 2 - PARALLEL DocumentationMaster agents (example for 9 docs):')
            ->do([
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [architecture docs from DOC_PLAN]',
                        'For EACH doc in batch:',
                        '  1. Read relevant source files identified in discovery',
                        '  2. Write YAML front matter (name, description, type, date, version)',
                        '  3. Write sections from outline',
                        '  4. Enforce: text-first, 500-line limit, no secrets',
                        '  5. Write to .docs/{type}/{name}.md',
                    ]),
                    Operator::output('{docs_written:N,paths:[],total_lines:N}')
                ),
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [module docs from DOC_PLAN]',
                        'For EACH doc: read source → YAML → sections → write',
                        'Cross-reference to architecture docs where relevant',
                    ]),
                    Operator::output('{docs_written:N,paths:[],total_lines:N}')
                ),
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [api + guide + reference docs from DOC_PLAN]',
                        'For EACH doc: read source → YAML → sections → write',
                    ]),
                    Operator::output('{docs_written:N,paths:[],total_lines:N}')
                ),
            ])
            ->phase('NOTE: Each agent writes files independently, no conflicts (different paths)');

        // Phase 6: Verification
        $this->guideline('phase6-verification')
            ->goal('Verify all generated docs are valid and indexed')
            ->example()
            ->phase('STEP 1 - Verify docs_search MCP tool indexes all new files:')
            ->do([
                BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']),
                Store::as('FINAL_INDEX'),
            ])
            ->phase('STEP 2 - Compare:')
            ->do([
                'Check: every planned doc from DOC_PLAN appears in FINAL_INDEX',
                'Check: every file has valid YAML front matter (name + description present)',
                'Check: no file exceeds 500 lines',
                Operator::if('any doc missing from index', [
                    'Read file → check YAML front matter → fix if malformed',
                    'Re-verify with ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']),
                ]),
            ]);

        // Phase 7: Knowledge Storage & Completion
        $this->guideline('phase7-complete')
            ->goal('Store insight, report completion')
            ->example()
            ->phase('STORE initialization insight:')
            ->do([
                VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'INIT-DOCS|docs:{count}|lines:{total}|types:{type_list}|areas:{area_list}|mode:{fresh|augment}', 'category' => self::CAT_PROJECT_CONTEXT, 'tags' => [self::MTAG_INSIGHT, self::MTAG_PROJECT_WIDE]]),
            ])
            ->phase('REPORT:')
            ->do([
                Operator::output([
                    '═══ INIT-DOCS COMPLETE ═══',
                    'Docs generated: {count}',
                    'Total lines: {total}',
                    'Types: {type_breakdown}',
                    'Agents used: {agent_count} DocumentationMaster (parallel)',
                    'All indexed by docs_search MCP tool: {verified}',
                    '═══════════════════════════',
                    '',
                    'NEXT STEPS:',
                    '  1. Review generated docs in .docs/',
                    '  2. /doc:work {topic} — refine specific documents interactively',
                    '  3. /init-vector — populate vector memory with doc content',
                    '  4. ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '...']) . ' — verify full index',
                ]),
            ]);

        // =========================================================================
        // REFERENCE GUIDELINES
        // =========================================================================

        // Deep cognitive only: doc structure patterns
        if ($this->cognitiveAtLeast('deep')) {
            $this->guideline('doc-structure')
                ->text('Standard documentation structure per type')
                ->example('architecture: Overview → Components → Data Flow → Decisions → Dependencies')->key('architecture')
                ->example('module: Purpose → API → Configuration → Examples → Related')->key('module')
                ->example('api: Authentication → Endpoints → Request/Response → Errors → Rate Limits')->key('api')
                ->example('guide: Prerequisites → Steps → Verification → Troubleshooting')->key('guide')
                ->example('concept: Definition → Why → How → When → Gotchas')->key('concept')
                ->example('reference: Format → Options → Defaults → Examples')->key('reference');
        }

        // Error Recovery (supplements defineFailurePolicyRules from trait)
        $this->guideline('error-recovery')
            ->text('Command-specific error handling (trait provides baseline tool error / MCP failure policy)')
            ->example('no .docs/ directory → create it, proceed')->key('no-dir')
            ->example('agent timeout → skip doc, continue, report in summary')->key('timeout')
            ->example(BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']) . ' unavailable → write files, skip verification')->key('cli-fail')
            ->example('YAML parsing error → fix front matter, retry verification')->key('yaml-fail')
            ->example('file exceeds 500 lines → split into parts automatically')->key('overflow')
            ->example('vector memory unavailable → skip storage, continue')->key('memory-fail');

        // Quality Gates
        $this->guideline('quality-gates')
            ->text('Validation checkpoints')
            ->example('Gate 1: pre-flight docs state checked')
            ->example('Gate 2: all parallel discovery agents returned')
            ->example('Gate 3: doc plan has at least 1 doc to generate')
            ->example('Gate 4: user approval obtained (or auto-approved)')
            ->example('Gate 5: all DocumentationMaster agents completed')
            ->example('Gate 6: every generated file has valid YAML front matter')
            ->example('Gate 7: docs_search MCP tool indexes all new files')
            ->example('Gate 8: no file exceeds 500 lines')
            ->example('Gate 9: completion insight stored to vector memory');

        // Deep cognitive only: example, directive
        if ($this->cognitiveAtLeast('deep')) {
            $this->guideline('example-fresh-laravel')
                ->scenario('Fresh documentation for Laravel project with no .docs/')
                ->example()
                ->phase('1', 'Pre-flight: no .docs/ → fresh init mode')
                ->phase('2', 'Discovery: 3 parallel agents → modules, routes, config')
                ->phase('3', 'Plan: 8 docs (1 architecture, 3 modules, 2 api, 1 guide, 1 reference)')
                ->phase('4', 'Approval: auto-approved (-y flag)')
                ->phase('5', 'Generation: 3 parallel DocumentationMaster → 8 docs written')
                ->phase('6', 'Verification: docs_search MCP tool indexes all 8, YAML valid, <500 lines each')
                ->phase('7', 'Complete: 8 docs, ~1200 lines, 3 agents used');

            $this->guideline('directive')
                ->text('PARALLEL agents! BATCH generation! YAML front matter! docs_search MCP tool verification! Dense memory! No duplicates!');
        }
    }
}
