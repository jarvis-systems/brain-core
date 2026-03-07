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
use BrainCore\Includes\Commands\Task\TaskCommandCommonTrait;
use BrainNode\Agents\DocumentationMaster;
use BrainNode\Agents\ExploreMaster;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Vector memory initialization: parallel project scanning, dense knowledge storage, docs_search MCP tool integration. Populates vector memory with project structure, code, docs, and config insights for agent context.')]
class InitVectorInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // =========================================================================
        // IRON RULES (from trait — universal safety, memory tags only: init-vector stores memories)
        // =========================================================================
        $this->defineIronExecutionRules();
        $this->defineMemoryTagTaxonomyRules();
        $this->defineCognitiveLevelGuidelines();

        // =========================================================================
        // IRON RULES (command-specific)
        // =========================================================================
        $this->rule('parallel-execution')->critical()
            ->text('Launch INDEPENDENT areas in PARALLEL (multiple Task calls in single response)')
            ->why('Maximizes throughput, reduces total initialization time')
            ->onViolation('Group independent areas, launch simultaneously');

        $this->rule('brain-docs-then-document-master')->critical()
            ->text('Use ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']) . ' for INDEX, then DocumentationMaster agents to ANALYZE content')
            ->why('docs_search MCP tool = metadata index, DocumentationMaster = content analysis + vector storage')
            ->onViolation(BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']) . ' → group docs → parallel DocumentationMaster agents');

        $this->rule('dense-storage')->critical()
            ->text('Store compact JSON-like format: {key:value} pairs, no verbose prose')
            ->why('Maximizes information density, improves vector search relevance')
            ->onViolation('Reformat to: path|type|files|classes|patterns|deps');

        $this->rule('memory-before-after')->high()
            ->text('search_memories BEFORE exploring, store_memory AFTER')
            ->why('Context continuity between agents')
            ->onViolation('Add mandatory memory operations');

        $this->rule('auto-approve-default')->critical()
            ->text('Default behavior is FULLY AUTOMATED (no user prompts). $HAS_AUTO_APPROVE = true confirms. Without -y: show completion summary. With -y: silent completion.')
            ->why('Batch initialization workflow requires zero interaction by default.')
            ->onViolation('Proceed autonomously. Never block on user input.');

        $this->rule('exclude-brain-directory')->critical()
            ->text('NEVER scan ' . Runtime::BRAIN_DIRECTORY . ' - Brain system internals, not project code')
            ->why('Brain config files pollute vector memory with irrelevant system data')
            ->onViolation('Skip ' . Runtime::BRAIN_DIRECTORY . ' in structure discovery and all exploration phases');

        // =========================================================================
        // INPUT CAPTURE (from InputCaptureTrait via TaskCommandCommonTrait)
        // =========================================================================
        $this->defineInputCaptureWithCustomGuideline([
            'INIT_SCOPE' => '{optional scope filter from $CLEAN_ARGS: specific area/module to scan, or empty for full project}',
        ]);

        // =========================================================================
        // WORKFLOW
        // =========================================================================

        // Phase 1: Memory Status Check
        $this->guideline('phase1-status')
            ->goal('Check memory state, determine fresh vs augment mode')
            ->example()
            ->phase(VectorMemoryMcp::callValidatedJson('get_memory_stats', []))
            ->phase(Store::as('MEM', '{total, categories}'))
            ->phase(Operator::if('$MEM.total === 0', 'Fresh init', 'Augment existing'));

        // Phase 2: Structure Discovery (Quick)
        $this->guideline('phase2-structure')
            ->goal('Quick structure scan to identify areas for parallel exploration')
            ->example()
            ->phase(
                ExploreMaster::call(
                    Operator::task([
                        'QUICK SCAN ONLY - identify directories, not deep analysis',
                        'Glob("*") → list root directories',
                        'EXCLUDE: ' . Runtime::BRAIN_DIRECTORY . ', vendor/, node_modules/, .git/',
                        'Classify: code(src/app), tests, config, docs(.docs), build, deps',
                        Operator::if('$INIT_SCOPE not empty', 'FOCUS on areas matching $INIT_SCOPE only'),
                        'Output JSON: {areas: [{path, type, priority}]}',
                    ]),
                    Operator::output('{areas: [{path, type, priority: high|medium|low}]}'),
                    Store::as('AREAS')
                )
            );

        // Phase 3: PARALLEL Exploration Batches
        $this->guideline('phase3-parallel-code')
            ->goal('PARALLEL: Launch code exploration agents simultaneously')
            ->example()
            ->phase('BATCH 1 - Code Areas (LAUNCH IN PARALLEL):')
            ->do([
                ExploreMaster::call(
                    Operator::task([
                        'Area: src/ or app/',
                        'Thoroughness: very thorough',
                        'BEFORE: search_memories("src code architecture", 3)',
                        'DO: Glob(**/*.php), Grep(class|function|namespace)',
                        'EXTRACT: {path|files|classes|namespaces|patterns|deps}',
                        'AFTER: store_memory(compact_json, "' . self::CAT_ARCHITECTURE . '", ["' . self::MTAG_INSIGHT . '", "' . self::MTAG_PROJECT_WIDE . '"])',
                    ]),
                    Operator::output('{path:"src",files:N,classes:N,key_patterns:[]}')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: tests/',
                        'Thoroughness: medium',
                        'BEFORE: search_memories("tests structure", 3)',
                        'DO: Glob(**/*Test.php), identify test framework',
                        'EXTRACT: {path|test_files|coverage_areas|framework}',
                        'AFTER: store_memory(compact_json, "' . self::CAT_ARCHITECTURE . '", ["' . self::MTAG_INSIGHT . '", "' . self::MTAG_MODULE_SPECIFIC . '"])',
                    ]),
                    Operator::output('{path:"tests",files:N,framework:str}')
                ),
            ])
            ->phase('NOTE: Both agents run SIMULTANEOUSLY via parallel Task calls');

        // Phase 3b: Documentation - Index + Analyze
        $this->guideline('phase3-documentation')
            ->goal('Index .docs/ via ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']) . ', then analyze content via DocumentationMaster agents')
            ->example()
            ->phase('STEP 1 - Get documentation index:')
            ->do([
                BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']),
                Store::as('DOCS_INDEX', '[{path, name, description, type}]'),
            ])
            ->phase('STEP 2 - Adaptive batching based on doc count:')
            ->do([
                Operator::if('docs_count <= 3', 'Single DocumentationMaster for all'),
                Operator::if('docs_count 4-8', '2 DocumentationMaster agents in parallel'),
                Operator::if('docs_count 9-15', '3 DocumentationMaster agents in parallel'),
                Operator::if('docs_count > 15', 'Batch by type (guide, api, concept, etc.)'),
            ])
            ->phase('STEP 3 - PARALLEL DocumentationMaster agents (example for 6 docs):')
            ->do([
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [{path1}, {path2}, {path3}]',
                        'Read each doc via Read tool',
                        'EXTRACT per doc: {name|type|key_concepts|related_to}',
                        'AFTER: store_memory(compact_json, "' . self::CAT_LEARNING . '", ["' . self::MTAG_INSIGHT . '", "' . self::MTAG_REUSABLE . '"])',
                    ]),
                    Operator::output('{docs_analyzed:3,topics:[]}')
                ),
                DocumentationMaster::call(
                    Operator::task([
                        'Docs batch: [{path4}, {path5}, {path6}]',
                        'Read each doc via Read tool',
                        'EXTRACT per doc: {name|type|key_concepts|related_to}',
                        'AFTER: store_memory(compact_json, "' . self::CAT_LEARNING . '", ["' . self::MTAG_INSIGHT . '", "' . self::MTAG_REUSABLE . '"])',
                    ]),
                    Operator::output('{docs_analyzed:3,topics:[]}')
                ),
            ])
            ->phase('NOTE: Each doc → separate memory entry for precise vector search');

        // Phase 3c: Config & Build (PARALLEL)
        $this->guideline('phase3-parallel-config')
            ->goal('PARALLEL: Config and build areas simultaneously')
            ->example()
            ->phase('BATCH 2 - Config/Build (LAUNCH IN PARALLEL):')
            ->do([
                ExploreMaster::call(
                    Operator::task([
                        'Area: config/',
                        'Thoroughness: quick',
                        'DO: Glob(config/*.php), extract key names',
                        'EXTRACT: {configs:[names],env_vars:[],services:[]}',
                        'AFTER: store_memory(compact_json, "' . self::CAT_ARCHITECTURE . '", ["' . self::MTAG_INSIGHT . '"])',
                    ]),
                    Operator::output('{path:"config",configs:[]}')
                ),
                ExploreMaster::call(
                    Operator::task([
                        'Area: build/CI files',
                        'Thoroughness: quick',
                        'DO: Find .github/, docker*, Makefile, composer.json, package.json',
                        'EXTRACT: {ci:bool,docker:bool,deps:{php:[],js:[]}}',
                        'AFTER: store_memory(compact_json, "' . self::CAT_ARCHITECTURE . '", ["' . self::MTAG_INSIGHT . '"])',
                    ]),
                    Operator::output('{ci:bool,docker:bool,deps:{}}')
                ),
            ]);

        // Phase 4: Cross-Area Synthesis
        $this->guideline('phase4-synthesis')
            ->goal('Synthesize all findings into project-wide architecture')
            ->example()
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['keywords' => 'project structure architecture stack patterns', 'limit' => 20, 'category' => self::CAT_ARCHITECTURE]))
            ->phase(Store::as('ALL_FINDINGS'))
            ->phase(
                VectorMemoryMcp::callValidatedJson('store_memory', [
                    'content' => 'INIT-VECTOR SYNTHESIS|PROJECT:{type}|AREAS:{list}|STACK:{tech}|PATTERNS:{arch}|DEPS:{graph}',
                    'category' => self::CAT_ARCHITECTURE,
                    'tags' => [self::MTAG_INSIGHT, self::MTAG_PROJECT_WIDE],
                ])
            );

        // Phase 5: Completion
        $this->guideline('phase5-complete')
            ->goal('Report completion with metrics')
            ->example()
            ->phase(VectorMemoryMcp::callValidatedJson('get_memory_stats', []))
            ->phase(Operator::output([
                '=== INIT-VECTOR COMPLETE ===',
                'Areas: {count} | Memories: {total} | Time: {elapsed}',
                'Parallel batches: 2 | Agents launched: {agent_count}',
                '============================',
            ]));

        // =========================================================================
        // REFERENCE GUIDELINES
        // =========================================================================

        // Deep cognitive only: reference patterns
        if ($this->cognitiveAtLeast('deep')) {
            $this->guideline('storage-format')
                ->text('Compact storage format for maximum vector search relevance')
                ->example('BAD: "The src/ directory contains 150 PHP files organized in MVC pattern..."')->key('verbose')
                ->example('GOOD: "src|150php|MVC|App\\Models,App\\Http|Laravel11|eloquent,routing"')->key('dense')
                ->example('Format: path|files|pattern|namespaces|framework|features')->key('schema');

            $this->guideline('parallel-pattern')
                ->text('How to execute agents in parallel')
                ->example()
                ->phase('WRONG: forEach(areas) → sequential, slow')
                ->phase('RIGHT: List multiple Task() calls in single response')
                ->phase('Brain executes all Task() calls simultaneously')
                ->phase('Each agent works independently, stores to memory')
                ->phase('Wait for all to complete, then synthesize');

            $this->guideline('brain-docs-usage')
                ->text('docs_search MCP tool for INDEX (metadata), then DocumentationMaster agents for CONTENT analysis')
                ->example(BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']))->key('list-all')
                ->example(BrainCLI::MCP__DOCS_SEARCH(['keywords' => 'keyword']))->key('search');
        }

        // Error Handling (supplements defineFailurePolicyRules from trait)
        $this->guideline('error-recovery')
            ->text('Command-specific error handling (trait provides baseline tool error / MCP failure policy)')
            ->example('MCP unavailable → abort, report')->key('memory-fail')
            ->example('Agent timeout → skip area, continue, report in summary')->key('timeout')
            ->example('Empty area → store minimal, proceed')->key('empty');

        // Quality Gates
        $this->guideline('quality-gates')
            ->text('Validation checkpoints')
            ->example('Gate 1: memory status checked (fresh vs augment)')
            ->example('Gate 2: structure discovery completed (AREAS populated)')
            ->example('Gate 3: parallel code exploration returned')
            ->example('Gate 4: documentation indexed and analyzed')
            ->example('Gate 5: config/build exploration returned')
            ->example('Gate 6: synthesis stored to vector memory')
            ->example('Gate 7: get_memory_stats confirms new entries');

        // Deep cognitive only: example, directive
        if ($this->cognitiveAtLeast('deep')) {
            $this->guideline('example-fresh')
                ->scenario('Fresh initialization with 8 docs')
                ->example()
                ->phase('1', 'Memory: 0 entries → fresh init')
                ->phase('2', 'Structure scan: 5 areas (src, tests, config, .docs, build)')
                ->phase('3a', 'PARALLEL: ExploreMaster(src/) + ExploreMaster(tests/) → 2 agents')
                ->phase('3b', BrainCLI::MCP__DOCS_SEARCH(['keywords' => '*']) . ' → 8 docs found → batch into 3+3+2')
                ->phase('3b-parallel', 'PARALLEL: 3x DocumentationMaster agents')
                ->phase('3c', 'PARALLEL: ExploreMaster(config/) + ExploreMaster(build/) → 2 agents')
                ->phase('4', 'Synthesis: search architecture memories → project-wide summary')
                ->phase('5', 'Complete: 15 memories, 7 agents (4 Explore + 3 DocMaster)');

            $this->guideline('directive')
                ->text('PARALLEL agents! docs_search MCP tool → DocumentationMaster! Dense storage! Predefined tags! Fast init!');
        }
    }
}
