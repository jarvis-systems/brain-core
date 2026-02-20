<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Doc;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainCore\Includes\Commands\Task\TaskCommandCommonTrait;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Documentation workflow command: discover → understand → propose → write → finalize. Interactive, evidence-based .docs/ management with Brain Docs CLI integration, vector memory, and YAML front matter enforcement.')]
class DocWorkInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // =========================================================================
        // IRON RULES
        // =========================================================================

        // Universal: no-hallucination, no-verbose (from trait)
        $this->defineIronExecutionRules();

        $this->rule('max-interactivity')->critical()
            ->text('When $HAS_AUTO_APPROVE = false: MUST engage user with clarifying questions via AskUserQuestion tool. NEVER assume scope, depth, audience, or structure. Documentation is a COLLABORATIVE process — user defines WHAT, agent researches and writes HOW. When $HAS_AUTO_APPROVE = true: infer scope/depth/audience from $CLEAN_ARGS context. Skip clarifying questions. Proceed autonomously through all phases.')
            ->why('Wrong assumptions about documentation scope = useless output + full rework. Interactive alignment is cheaper than rewrites. But -y flag means user trusts agent to make reasonable decisions autonomously.')
            ->onViolation('If interactive: STOP and ask clarifying question. If auto-approve: infer from input and proceed.');

        $this->rule('discovery-before-creation')->critical()
            ->text('ALWAYS search existing docs via brain docs CLI BEFORE creating new files. Flow: brain docs "{keywords}" → found? → READ existing → UPDATE. Not found? → apply aggressive-docs-search (3+ keyword variations). Still not found → CREATE new. NEVER create duplicate documentation for same topic.')
            ->why('Duplicate docs diverge over time. One source of truth per topic. Updating existing is faster and preserves history.')
            ->onViolation('Run brain docs first. Found → update. Not found after 3+ searches → create new.');

        $this->rule('evidence-based')->critical()
            ->text('ALL documentation content MUST be based on: 1) actual codebase reading (Read tool, Explore agent), 2) vector memory search, 3) existing .docs/ content, 4) verified web research. NEVER write documentation from assumptions or "common knowledge". Every technical claim must be verifiable against source code.')
            ->why('Documentation based on assumptions becomes lies when code changes. Evidence-based = always accurate.')
            ->onViolation('Read source code FIRST. Verify every claim against actual implementation.');

        $this->rule('500-line-limit')->critical()
            ->text('Each documentation file MUST NOT exceed 500 lines. If content exceeds → split into {topic}-part-1.md, {topic}-part-2.md with cross-references and YAML front matter part: N field.')
            ->why('Readability and token efficiency. Long files are unnavigable and expensive to load.')
            ->onViolation('Split content. Add part field to YAML. Add cross-references between parts.');

        $this->rule('yaml-front-matter-mandatory')->critical()
            ->text('EVERY .docs/ file MUST start with valid YAML front matter. brain docs indexes ONLY files with valid YAML. Format: brain docs --help -v.')
            ->why('brain docs CLI parses YAML for index and search. Docs without front matter are undiscoverable.')
            ->onViolation('Add YAML front matter before any markdown content. Verify with brain docs after writing.');

        $this->rule('text-first-code-last')->critical()
            ->text('Documentation is DESCRIPTION for humans. Minimize code to absolute minimum. Include code ONLY when: 1) text explanation would cost more tokens, AND 2) no other representation works. NEVER dump code blocks as documentation. Prefer: textual description > text-based diagram > minimal code snippet.')
            ->why('Code in docs is expensive, hard to read, becomes stale faster than text. Text-first = maintainable, scannable, token-efficient.')
            ->onViolation('Replace code with clear textual description. Keep only essential, minimal code examples.');

        $this->rule('validation-checkpoints')->high()
            ->text('When $HAS_AUTO_APPROVE = false: obtain user approval at 3 checkpoints: 1) structure proposal (before writing), 2) first section draft (validate tone/depth/style), 3) final documentation (before saving). Between checkpoints: proceed autonomously. When $HAS_AUTO_APPROVE = true: skip ALL checkpoints, proceed through all phases to completion. Still SHOW summary of what was done at end.')
            ->why('3 checkpoints = balance between user control and flow. -y flag = user trusts agent to complete full cycle without interruption.')
            ->onViolation('If interactive: pause at checkpoint, AskUserQuestion. If auto-approve: skip, continue to next phase.');

        // Auto-approval mode — consistent with task commands (from trait concept, doc-specific behavior)
        $this->rule('auto-approve-mode')->critical()
            ->text('$HAS_AUTO_APPROVE = true → AUTONOMOUS MODE. Skip ALL checkpoints (structure approval, first section review, final approval). Infer scope/depth/audience from $CLEAN_ARGS. Choose reasonable defaults: depth=detailed, audience=developer, structure=standard for TARGET_TYPE. Proceed through all phases to completion. Write files directly. Show summary at end. $HAS_AUTO_APPROVE = false → INTERACTIVE MODE. Full checkpoint flow, clarifying questions, user drives decisions.')
            ->why('User explicitly chose autonomous mode via -y flag. Documentation command must support pipeline usage (e.g., after task execution, auto-create docs). Every pause breaks automation flow.')
            ->onViolation('If auto-approve: remove the question, use defaults, continue. If interactive: ask and wait.');

        // Secrets protection — docs should never contain secrets from .env or configs (from trait)
        $this->defineSecretsPiiProtectionRules();

        // No destructive git — doc edits must not wreck git state or memory/ (from trait)
        $this->defineNoDestructiveGitRules();

        // Tag taxonomy — predefined tags for memory storage when storing doc insights (from trait)
        $this->defineTagTaxonomyRules();

        // Failure policy — universal tool error / missing info handling (from trait)
        $this->defineFailurePolicyRules();

        // Aggressive docs search — multi-keyword discovery pattern (from trait)
        $this->defineAggressiveDocsSearchGuideline();

        $this->rule('external-docs-via-context7')->high()
            ->text('When documenting features that use external packages/libraries: resolve library via '.Context7Mcp::callJson('resolve-library-id', ['libraryName' => '{package}']).' then query docs via '.Context7Mcp::callJson('query-docs', ['libraryId' => '{resolved_id}', 'query' => '{specific_topic}']).'. Use Context7 for KNOWN packages (composer/npm dependencies). Use web-research-master for broader context or unknown sources. NEVER guess external API behavior — verify against official docs.')
            ->why('Documentation referencing external packages must be accurate. Guessing package behavior = docs become lies on first version bump. Context7 provides indexed, version-aware library docs.')
            ->onViolation('Identify external dependencies from codebase research. Resolve and query via Context7 before writing docs about them.');

        // =========================================================================
        // INPUT CAPTURE (from InputCaptureTrait via TaskCommandCommonTrait)
        // =========================================================================
        $this->defineInputCaptureWithCustomGuideline([
            'DOC_TARGET' => '{documentation target extracted from $CLEAN_ARGS: feature name, module, concept, topic}',
            'TARGET_TYPE' => '{detect from $CLEAN_ARGS prefix or context: feature|module|concept|architecture|guide|api|reference|topic}',
        ]);

        $this->guideline('arguments-format')
            ->text('Input formats: feature:X, module:X, concept:X, architecture:X, guide:X, api:X, topic:X, or plain text description.')
            ->example('feature:focus-mode → TARGET_TYPE=feature, DOC_TARGET=focus-mode')->key('typed')
            ->example('deployment guide → TARGET_TYPE=guide, DOC_TARGET=deployment')->key('plain')
            ->example('how task delegation works → TARGET_TYPE=concept, DOC_TARGET=task delegation')->key('natural');

        // =========================================================================
        // .docs/ FOLDER STRUCTURE
        // =========================================================================
        $this->guideline('docs-folder-structure')
            ->goal('Organize documentation with clear hierarchy within .docs/')
            ->text('Root: .docs/ — all project documentation. Subdirectories by content type:')
            ->example('.docs/features/ — feature descriptions and usage')->key('features')
            ->example('.docs/modules/ — internal module documentation')->key('modules')
            ->example('.docs/concepts/ — conceptual explanations')->key('concepts')
            ->example('.docs/architecture/ — system design and flows')->key('architecture')
            ->example('.docs/guides/ — how-to guides and tutorials')->key('guides')
            ->example('.docs/api/ — API specifications and contracts')->key('api')
            ->example('.docs/tor/ — terms of reference / requirements')->key('tor')
            ->example('.docs/reference/ — reference material and lookups')->key('reference');

        // =========================================================================
        // YAML FRONT MATTER STRUCTURE
        // =========================================================================
        // =========================================================================
        // WORKFLOW
        // =========================================================================
        $this->guideline('workflow')
            ->goal('Documentation workflow: capture → discover → research → propose → write → finalize')
            ->example()

            // Phase 1: Capture input & discover existing docs
            ->phase('=== PHASE 1: CAPTURE & DISCOVER ===')
            ->phase(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->phase(Store::as('HAS_AUTO_APPROVE', '{true if -y or --yes in RAW_INPUT}'))
            ->phase(Store::as('CLEAN_ARGS', '{RAW_INPUT with flags removed}'))
            ->phase(Store::as('DOC_TARGET', '{extract target from CLEAN_ARGS}'))
            ->phase(Store::as('TARGET_TYPE', '{detect type from CLEAN_ARGS}'))

            // 1.1: Discovery via Brain Docs CLI
            ->phase(BashTool::call(BrainCLI::DOCS('{DOC_TARGET keywords}')).' → '.Store::as('EXISTING_DOCS'))
            ->phase(Operator::if(Store::get('EXISTING_DOCS').' is empty', [
                'Apply aggressive-docs-search: 3+ keyword variations (split CamelCase, strip suffixes, domain words)',
                BashTool::call(BrainCLI::DOCS('{variation_1}')),
                BashTool::call(BrainCLI::DOCS('{variation_2}')),
                BashTool::call(BrainCLI::DOCS('{variation_3}')),
            ]))

            // 1.2: Determine mode
            ->phase(Operator::if(Store::get('EXISTING_DOCS').' found', [
                ReadTool::call('{existing doc paths}').' → '.Store::as('CURRENT_CONTENT'),
                Store::as('DOC_MODE', 'update'),
                'Show: "Found existing docs: {paths}. Mode: UPDATE."',
            ]))
            ->phase(Operator::if(Store::get('EXISTING_DOCS').' NOT found after all searches', [
                Store::as('DOC_MODE', 'create'),
                'Show: "No existing docs for {DOC_TARGET}. Mode: CREATE."',
            ]))

            // 1.3: Vector memory context
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '$DOC_TARGET', 'limit' => 5]).' → '.Store::as('MEMORY_CONTEXT'))
            ->phase(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '$DOC_TARGET architecture design', 'limit' => 3]).' → append to '.Store::get('MEMORY_CONTEXT'))

            // 1.4: Scope clarification (interactive or auto-inferred)
            ->phase(Operator::if('NOT $HAS_AUTO_APPROVE', [
                'AskUserQuestion: What aspects to cover? Depth (overview/detailed/reference)? Target audience (developer/user/admin)?',
                Store::as('USER_REQUIREMENTS', '{user answers: aspects, depth, audience, special_requests}'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', [
                Store::as('USER_REQUIREMENTS', '{inferred from $CLEAN_ARGS context: depth=detailed, audience=developer, aspects=all relevant}'),
                'Auto-inferred scope from input. Proceeding autonomously.',
            ]))

            // Phase 2: Research (evidence gathering)
            ->phase('=== PHASE 2: EVIDENCE GATHERING ===')
            ->phase(TaskTool::agent('explore', 'CODEBASE RESEARCH for documenting {$DOC_TARGET}: 1) Find all related source files (classes, traits, interfaces, configs). 2) Identify public API: method signatures, parameters, return types. 3) Find existing tests (test behavior = specification). 4) Check inline comments, PHPDoc, README fragments. 5) Map dependencies and relationships. Return: {source_files: [], public_api: [], config_options: [], test_files: [], inline_docs: [], dependencies: []}.').' → '.Store::as('CODEBASE_RESEARCH'))
            // 2.2: External package docs via Context7 (if dependencies detected)
            ->phase(Operator::if('$CODEBASE_RESEARCH reveals external packages/libraries', [
                'For each significant dependency: resolve library ID',
                Context7Mcp::callJson('resolve-library-id', ['libraryName' => '{package_name}']).' → '.Store::as('LIBRARY_ID'),
                Context7Mcp::callJson('query-docs', ['libraryId' => '$LIBRARY_ID', 'query' => '{relevant_topic}']).' → append to '.Store::get('CODEBASE_RESEARCH'),
            ]))
            // 2.3: Broader context via web research (if needed beyond package docs)
            ->phase(Operator::if('external context needed beyond package docs (architecture patterns, industry practices)', [
                TaskTool::agent('web-research-master', 'Research {$DOC_TARGET} context: best practices, standard approaches, related documentation patterns').' → '.Store::as('EXTERNAL_CONTEXT'),
            ]))

            // Phase 3: Structure proposal (CHECKPOINT 1 — interactive only)
            ->phase('=== PHASE 3: STRUCTURE PROPOSAL ===')
            ->phase(Store::as('DOC_PLAN', '{proposed_path, sections_outline, estimated_lines, split_plan}'))
            ->phase(Operator::if('NOT $HAS_AUTO_APPROVE', [
                'Present to user (CHECKPOINT 1):',
                '  Path: .docs/{TARGET_TYPE}/{doc-name}.md',
                '  Sections: {outline with estimated line counts per section}',
                '  Split plan: {if estimated > 500 lines: part-1 = sections A-C, part-2 = sections D-F}',
                '  Evidence: "{N} source files, {N} tests, {N} memory insights found"',
                '  Mode: {DOC_MODE} (create new / update existing)',
                'AskUserQuestion → WAIT for explicit approval or changes',
                Operator::if('user requests changes', 'Revise plan, re-propose'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Auto-approved structure. Proceeding to writing.'))

            // Phase 4: Writing (CHECKPOINT 2 — interactive only)
            ->phase('=== PHASE 4: WRITING ===')
            ->phase('Write YAML front matter + first major section')
            ->phase(Operator::if('NOT $HAS_AUTO_APPROVE', [
                'Present first section to user (CHECKPOINT 2)',
                'AskUserQuestion → WAIT for feedback on tone, depth, style',
                Operator::if('approved', 'Continue writing remaining sections'),
                Operator::if('changes needed', 'Apply feedback, show revised, then continue'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Continue writing all sections without pause'))
            ->phase('Complete all sections. Enforce at each section:')
            ->phase('  - Evidence-based claims (cite source files)')
            ->phase('  - Text-first, minimal code')
            ->phase('  - Proper heading hierarchy')
            ->phase('  - Cross-references to related docs')
            ->phase('  - Running line count (split if approaching 500)')

            // Phase 5: Finalization (CHECKPOINT 3 — interactive only)
            ->phase('=== PHASE 5: FINALIZATION ===')
            ->phase('Final review checklist:')
            ->phase('  - YAML front matter present and valid')
            ->phase('  - Line count ≤ 500 per file')
            ->phase('  - All cross-references valid')
            ->phase('  - No secrets or PII')
            ->phase('  - Code examples minimal and accurate')
            ->phase(Operator::if(Store::get('DOC_MODE').' === "update"', 'Diff: show what changed vs original'))
            ->phase(Operator::if('NOT $HAS_AUTO_APPROVE', [
                'Present final to user (CHECKPOINT 3)',
                'AskUserQuestion → WAIT for final approval',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'Self-review passed. Writing files.'))
            ->phase(Operator::if('approved OR $HAS_AUTO_APPROVE', [
                'Write files to .docs/',
                BashTool::call(BrainCLI::DOCS('{DOC_TARGET keywords}')).' → verify files indexed by brain docs',
                Operator::if('NOT indexed', 'Check YAML front matter format. Fix and retry.'),
                VectorMemoryMcp::callValidatedJson('store_memory', ['content' => 'Documentation {created|updated}: {DOC_TARGET}. Path: {file_paths}. Sections: {section_names}. Based on: {source_files}.', 'category' => self::CAT_PROJECT_CONTEXT, 'tags' => [self::MTAG_INSIGHT, self::MTAG_REUSABLE]]),
                'RESULT: Documentation {DOC_MODE}d at {paths}. Indexed in brain docs.',
            ]));

        // =========================================================================
        // WRITING STANDARDS
        // =========================================================================
        $this->guideline('writing-standards')
            ->goal('Professional technical writing for .docs/')
            ->text('Language: clear, concise, jargon-free where possible. Match depth to declared audience.')
            ->text('Structure: logical heading hierarchy (# → ## → ###). Each section = one concept. No orphan sections.')
            ->text('Code examples: MINIMAL. Only when text would cost more tokens or be less clear. Always: language tag, context comment, what it demonstrates.')
            ->text('Diagrams: text-based (ASCII, Mermaid) when visual adds value. Never images (not indexable, not diffable).')
            ->text('Cross-references: relative paths ([See X](../concepts/x.md)). Verify targets exist.')
            ->text('Consistency: follow existing .docs/ style if any docs already exist. Match tone, format, heading style.');

        // =========================================================================
        // FILE NAMING
        // =========================================================================
        $this->guideline('file-naming')
            ->text('Lowercase, hyphens, descriptive, no spaces.')
            ->example('Single file: feature-name.md')->key('single')
            ->example('Multi-part: feature-name-part-1.md, feature-name-part-2.md')->key('multi')
            ->example('Topic split: feature-name-overview.md, feature-name-api.md')->key('topic');

        // =========================================================================
        // ERROR HANDLING
        // =========================================================================
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('brain docs CLI unavailable', 'Fallback: Glob(".docs/**/*.md") + Read YAML headers manually'))
            ->phase(Operator::if('no source code found for topic', 'AskUserQuestion: is this conceptual-only or should match code? Conceptual → proceed with user input. Code-based → verify topic name and search again.'))
            ->phase(Operator::if('user rejects structure at checkpoint', 'Revise based on specific feedback. Re-propose. Max 2 revisions, then AskUserQuestion for precise direction.'))
            ->phase(Operator::if('content exceeds 500 lines mid-writing', 'STOP writing. Propose split plan. Get approval. Split and continue.'))
            ->phase(Operator::if('existing doc found but format is wrong', 'Propose migration: fix YAML front matter, restructure to standards. AskUserQuestion before changing.'));
    }
}
