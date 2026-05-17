<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

/**
 * Shared subroutine for the 4 intent-based skill proposal commands
 * (/skill:learn, /skill:new, /skill:edit, /skill:deprecate).
 *
 * Centralises the Step 0 triage (5 probes + 9 outcome classifier + branch operators),
 * Steps 4-5 (proposal.json + diff/content build), Step 5.5 (docs_topics suggestion),
 * Step 6 (write pending folder) and Step 7 (report).
 *
 * Backend invariants are identical to the legacy /skill:propose flow:
 * - proposal.json conforms to cli/schema/skill-proposal.schema.json
 * - storage layout: node/Skills/{target}/pending-proposals/{date}-{slug}/
 *   or node/Skills/.new-proposals/{date}-{slug}/ for create-skill
 * - confidence anchors and triage outcomes unchanged
 *
 * Each consumer Include is expected to populate the intent-collection variables
 * ($ACTION, $TARGET_SKILL, $RATIONALE, $EVIDENCE, $CONFIDENCE, optional $CAVEATS,
 * $REPLACEMENT, $DRAFT, $PATCH, $SUGGESTED_DIFF) before calling
 * appendProposalWorkflow() at the end of its handle() method.
 */
trait SkillProposalSharedTrait
{
    /**
     * Append the shared triage + build + write + report workflow to the
     * current Include. Must be called AFTER intent-collection workflows have
     * stored ACTION / TARGET_SKILL / RATIONALE / EVIDENCE / CONFIDENCE.
     */
    protected function appendProposalWorkflow(): void
    {
        $this->defineSharedProposalRules();
        $this->defineTriageWorkflow();
        $this->defineBuildProposalJsonWorkflow();
        $this->defineBuildDiffOrContentWorkflow();
        $this->defineDocsTopicsWorkflow();
        $this->defineWriteFilesWorkflow();
        $this->defineReportWorkflow();
        $this->defineConfidenceAnchorsGuideline();
        $this->defineErrorHandlingGuideline();
    }

    /**
     * Shared iron rules that every proposal-authoring command must obey.
     */
    private function defineSharedProposalRules(): void
    {
        $this->rule('never-write-canonical')->critical()
            ->text('NEVER write directly to node/Skills/{target}/SKILL.md from this command. Output goes ONLY to pending-proposals/{date}-{slug}/ or .new-proposals/{date}-{slug}/.')
            ->why('This command IS part of the diff-review gate. Canonical writes happen exclusively in /skill:apply after explicit user review.')
            ->onViolation('ABORT. Re-route output to the pending folder under the proposal id.');

        $this->rule('schema-conform')->critical()
            ->text('Resulting proposal.json MUST conform to cli/schema/skill-proposal.schema.json. Required fields: id, action, source, evidence (>=1 item), confidence (0.0-1.0), rationale (8-400 chars), created_at (ISO-8601), created_by.')
            ->why('Downstream /skill:apply validates against this schema. Malformed proposals jam the queue.')
            ->onViolation('Re-derive missing fields before writing the file. Do not partially write.');

        $this->rule('evidence-required')->high()
            ->text('evidence array MUST contain at least one item with verifiable {kind, ref}. kind in {task, file, commit, session, memory}.')
            ->why('Proposals without evidence are noise per skill-trigger-policy.')
            ->onViolation('Stop and ask user for at least one evidence pointer (task id, file path, commit, session, or memory id).');

        $this->rule('triage-before-write')->critical()
            ->text('STEP 0 triage MUST complete before any file write. ABORT routes (DUPLICATE, NOISE_OR_TRIVIAL, DUPLICATE_IN_DOCS, BELONGS_IN_DOCS) skip the write entirely. CONFIRM routes (FITS_EXISTING, SPLIT, PREVIOUSLY_REJECTED) require explicit user YES before proceeding to STEP 4.')
            ->why('Skipping triage causes blind self-modification — defeats the diff-review gate by allowing duplicates, noise, docs-duplication, or already-rejected ideas to pollute the skill surface.')
            ->onViolation('Re-run Step 0 triage phases (memory probe, task probe, docs probe, skills scan, rejected scan, classify) before any disk write.');
    }

    /**
     * STEP 0 — Triage proposal intent against five probe sources, classify
     * into one of nine outcomes, and branch (ABORT / CONFIRM / PROCEED).
     */
    private function defineTriageWorkflow(): void
    {
        $this->guideline('workflow-step0-triage')
            ->text('STEP 0 - Triage proposal intent against vector-memory, vector-task, existing skills, rejected history, and project .docs/ BEFORE any write')
            ->example()
            ->phase('triage-memory-probe', Operator::do(
                VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{rationale + keywords from evidence}', 'limit' => 5]),
                Store::as('VECTOR_MEMORY_HITS', 'hits array; on MCP unavailable set [] and emit warning "vector-memory probe skipped"')
            ))
            ->phase('triage-task-probe', Operator::do(
                VectorTaskMcp::callValidatedJson('task_list', ['query' => Store::get('RATIONALE'), 'limit' => 5]),
                Store::as('RELATED_TASKS', 'task list array; on MCP unavailable set [] and emit warning "vector-task probe skipped"')
            ))
            ->phase('triage-skills-scan', Operator::do(
                BashTool::call("find node/Skills -mindepth 2 -maxdepth 2 -name SKILL.md -not -path 'node/Skills/.*'"),
                'Parse YAML frontmatter (name, description) and top-level headings / rule labels from each SKILL.md',
                Store::as('EXISTING_SKILLS', 'array of {skill_id, name, description, rule_labels}')
            ))
            ->phase('triage-rejected-scan', Operator::do(
                BashTool::call("find node/Skills -path '*/history-references/*-rejected-*.md' -not -path 'node/Skills/.*'"),
                'Parse proposal slug + reject reason from frontmatter or first paragraph of each match',
                Store::as('REJECTED_HISTORY', 'array of {skill_id, slug, reason, decided_at}')
            ))
            ->phase('triage-docs-probe', Operator::do(
                BrainCLI::MCP__DOCS_SEARCH(['keywords' => '{rationale + keywords from evidence}', 'limit' => 5]),
                Store::as('DOCS_HITS', 'docs_search hits array of {doc_path, snippet, score}; on MCP unavailable set [] and emit warning "docs_search probe skipped"')
            ))
            ->phase('triage-classify', Operator::do(
                'Evaluate ' . Store::get('VECTOR_MEMORY_HITS') . ' + ' . Store::get('RELATED_TASKS') . ' + ' . Store::get('EXISTING_SKILLS') . ' + ' . Store::get('REJECTED_HISTORY') . ' + ' . Store::get('DOCS_HITS') . ' + intent payload (' . Store::get('ACTION') . ', ' . Store::get('TARGET_SKILL') . ', ' . Store::get('RATIONALE') . ', ' . Store::get('EVIDENCE') . ')',
                Store::as('CLASSIFICATION', 'one of: DUPLICATE {skill, rule_id} | DUPLICATE_IN_DOCS {doc_path, snippet_ref} | FITS_EXISTING {recommended_target} | SPLIT [{action, target, rationale}, ...] | NOISE_OR_TRIVIAL | BELONGS_IN_DOCS | PREVIOUSLY_REJECTED {history_ref, reason} | NEW_DOMAIN | OK_MODIFY')
            ))
            ->phase('triage-branch-duplicate', Operator::if(
                Store::get('CLASSIFICATION') . ' == DUPLICATE',
                Operator::abort('Duplicate of {CLASSIFICATION.skill}/{CLASSIFICATION.rule_id}, no proposal needed')
            ))
            ->phase('triage-branch-noise', Operator::if(
                Store::get('CLASSIFICATION') . ' == NOISE_OR_TRIVIAL',
                Operator::do(
                    VectorMemoryMcp::callValidatedJson('store_memory', [
                        'category' => 'skill-noise-observation',
                        'content' => '{RATIONALE + classifier reasoning + raw evidence}',
                        'tags' => ['skill:triage-noise', 'outcome:not-skill-material', 'action:' . Store::get('ACTION')],
                    ]),
                    Operator::abort('Observation stored to vector-memory, not load-bearing skill material')
                )
            ))
            ->phase('triage-branch-duplicate-in-docs', Operator::if(
                Store::get('CLASSIFICATION') . ' == DUPLICATE_IN_DOCS',
                Operator::do(
                    'Inform user: knowledge already at {CLASSIFICATION.doc_path}. Recommend recording a reference via docs_topics frontmatter or /skill:edit linking to that doc rather than duplicating prose.',
                    Operator::abort('Knowledge duplicates existing .docs/ entry; link via docs_topics or /skill:edit instead')
                )
            ))
            ->phase('triage-branch-belongs-in-docs', Operator::if(
                Store::get('CLASSIFICATION') . ' == BELONGS_IN_DOCS',
                Operator::abort('Input is descriptive material (architectural / runbook). Use /doc:work --create {.docs/path/} to author proper documentation instead of a skill rule.')
            ))
            ->phase('triage-branch-confirm', Operator::if(
                Store::get('CLASSIFICATION') . ' in {FITS_EXISTING, SPLIT, PREVIOUSLY_REJECTED}',
                Operator::do(
                    'Display classification payload (recommended_target / split actions / past reject reason) to user',
                    'Prompt: "Proceed anyway? (yes/no)"',
                    Operator::validate('User response is YES or CONFIRM', Operator::abort('Triage halted: user did not confirm CONFIRM-route classification'))
                )
            ))
            ->phase('triage-branch-proceed', Operator::if(
                Store::get('CLASSIFICATION') . ' in {NEW_DOMAIN, OK_MODIFY}',
                'Proceed silently to STEP 4 (build proposal.json)'
            ));
    }

    /**
     * STEP 4 — Build proposal.json conforming to cli/schema/skill-proposal.schema.json.
     */
    private function defineBuildProposalJsonWorkflow(): void
    {
        $this->guideline('workflow-step4-build-proposal-json')
            ->text('STEP 4 - Build proposal.json conforming to cli/schema/skill-proposal.schema.json')
            ->example()
            ->phase('date', BashTool::call('date +"%Y-%m-%d"') . ' ' . Store::as('CREATED_DATE'))
            ->phase('iso', BashTool::call('date -u +"%Y-%m-%dT%H:%M:%SZ"') . ' ' . Store::as('CREATED_AT'))
            ->phase('slug', Store::as('SLUG', 'kebab-case derived from ' . Store::get('RATIONALE') . ', ASCII, max 32 chars, [a-z0-9-]+, trimmed of leading/trailing dashes'))
            ->phase('id', Store::as('PROPOSAL_ID', Store::get('CREATED_DATE') . '-' . Store::get('SLUG')))
            ->phase('resolve-root', Operator::if(
                Store::get('ACTION') . ' == create-skill',
                Store::as('PROPOSAL_ROOT', 'node/Skills/.new-proposals'),
                Store::as('PROPOSAL_ROOT', 'node/Skills/' . Store::get('TARGET_SKILL') . '/pending-proposals')
            ))
            ->phase('folder', Store::as('PROPOSAL_FOLDER', Store::get('PROPOSAL_ROOT') . '/' . Store::get('PROPOSAL_ID')))
            ->phase('source', Store::as('SOURCE', '{type: "session" | "task" | "manual", ref: session id / task id / short note derived from intent-collection context}'))
            ->phase('created_by', Store::as('CREATED_BY', 'brain when authored autonomously, user when explicit user request, skillable when generated by skillable miner'))
            ->phase('target-field', Operator::if(Store::get('ACTION') . ' == create-skill', 'target_skill = null in JSON', 'target_skill = ' . Store::get('TARGET_SKILL')))
            ->phase('assemble', Store::as('PROPOSAL_JSON', '{ id: ' . Store::get('PROPOSAL_ID') . ', action: ' . Store::get('ACTION') . ', target_skill: see target-field, source: ' . Store::get('SOURCE') . ', evidence: ' . Store::get('EVIDENCE') . ', confidence: ' . Store::get('CONFIDENCE') . ', caveats: ' . Store::get('CAVEATS') . ' (omit if empty), rationale: ' . Store::get('RATIONALE') . ', replacement: ' . Store::get('REPLACEMENT') . ' (only for deprecate-skill when not "none", else omit), created_at: ' . Store::get('CREATED_AT') . ', created_by: ' . Store::get('CREATED_BY') . ' }'))
            ->phase('validate', Operator::validate('PROPOSAL_JSON conforms to cli/schema/skill-proposal.schema.json', Operator::abort('Schema violation, fix payload before writing')));
    }

    /**
     * STEP 5 — Build the diff (modify/append/deprecate) or full SKILL.md draft (create).
     * Consumer Includes may pre-populate $DRAFT, $PATCH, or $SUGGESTED_DIFF.
     */
    private function defineBuildDiffOrContentWorkflow(): void
    {
        $this->guideline('workflow-step5-build-diff-or-content')
            ->text('STEP 5 - Build the diff file (modify/append/deprecate) or full SKILL.md draft (create). Reuse pre-built $DRAFT / $PATCH / $SUGGESTED_DIFF from intent-collection when available.')
            ->example()
            ->phase('modify', Operator::if(Store::get('ACTION') . ' == modify-skill', Operator::do(
                'Use ' . Store::get('SUGGESTED_DIFF') . ' if intent-collection produced one, else compute unified diff against current node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md',
                'Target file inside proposal: SKILL.md.patch (unified diff, paths relative to project root)'
            )))
            ->phase('append-reference', Operator::if(Store::get('ACTION') . ' == append-reference', Operator::do(
                'Generate new references/{ref-name}.md content',
                'Diff: unified diff that adds the new file plus a bullet under "## References" in SKILL.md when appropriate',
                'Save as SKILL.md.patch'
            )))
            ->phase('create', Operator::if(Store::get('ACTION') . ' == create-skill', Operator::do(
                'Use ' . Store::get('DRAFT') . ' from intent-collection if present, else produce full SKILL.md with YAML front matter (name, description) and body',
                'Save as SKILL.md.new'
            )))
            ->phase('deprecate', Operator::if(Store::get('ACTION') . ' == deprecate-skill', Operator::do(
                'Use ' . Store::get('PATCH') . ' from intent-collection if present, else compute unified diff that adds deprecated: true (and replacement: ' . Store::get('REPLACEMENT') . ' if not "none") to frontmatter',
                'Save as SKILL.md.patch'
            )));
    }

    /**
     * STEP 5.5 — Suggest docs_topics frontmatter keywords from triage docs hits.
     */
    private function defineDocsTopicsWorkflow(): void
    {
        $this->guideline('workflow-step5_5-docs-topics')
            ->text('STEP 5.5 - Optionally suggest docs_topics frontmatter keywords from triage docs hits')
            ->example()
            ->phase('suggest-docs-topics', Operator::if(
                Store::get('ACTION') . ' in {create-skill, modify-skill}',
                Operator::do(
                    'Analyze ' . Store::get('DOCS_HITS') . ' — identify 1-5 topical keywords that capture the docs domains relevant to this skill (NOT hardcoded paths)',
                    'Each keyword: kebab-case, 1-64 chars, no slashes, no file extensions (paths-with-slashes are forbidden — skills must stay portable across projects)',
                    'If relevant keywords exist: append optional docs_topics: [kw1, kw2, ...] line to YAML frontmatter of SKILL.md.new or include it in SKILL.md.patch',
                    'If no relevant docs hits: SKIP this phase — docs_topics field stays absent (the field is optional)',
                    Store::as('DOCS_TOPICS', 'array of strings 1-64 chars each, or null if skipped')
                )
            ))
            ->phase('append-reference-hint', Operator::if(
                Store::get('CLASSIFICATION') . ' == DUPLICATE_IN_DOCS',
                'Inline recommendation: prefer `append-reference` action wiring SKILL.md to {CLASSIFICATION.doc_path} via docs_topics keywords instead of duplicating prose.'
            ));
    }

    /**
     * STEP 6 — Materialize the proposal folder on disk.
     */
    private function defineWriteFilesWorkflow(): void
    {
        $this->guideline('workflow-step6-write-files')
            ->text('STEP 6 - Materialize the proposal folder')
            ->example()
            ->phase('mkdir', BashTool::call('mkdir -p ' . Store::get('PROPOSAL_FOLDER')))
            ->phase('write-json', WriteTool::call(Store::get('PROPOSAL_FOLDER') . '/proposal.json', Store::get('PROPOSAL_JSON')))
            ->phase('write-diff-or-new', Operator::if(
                Store::get('ACTION') . ' == create-skill',
                WriteTool::call(Store::get('PROPOSAL_FOLDER') . '/SKILL.md.new', '{draft SKILL.md content from $DRAFT or freshly built}'),
                WriteTool::call(Store::get('PROPOSAL_FOLDER') . '/SKILL.md.patch', '{unified diff from $PATCH / $SUGGESTED_DIFF or freshly built}')
            ));
    }

    /**
     * STEP 7 — Report proposal id, path, and reviewer next steps.
     */
    private function defineReportWorkflow(): void
    {
        $this->guideline('workflow-step7-report')
            ->text('STEP 7 - Report proposal id, path, and reviewer next steps')
            ->example()
            ->phase('summary', 'Display: Proposal ' . Store::get('PROPOSAL_ID') . ' created at ' . Store::get('PROPOSAL_FOLDER'))
            ->phase('next-list', 'List all pending proposals: /skill:list')
            ->phase('next-review', 'Inspect this proposal: /skill:review ' . Store::get('PROPOSAL_ID'))
            ->phase('next-apply', 'Apply if good: /skill:apply ' . Store::get('PROPOSAL_ID'))
            ->phase('next-reject', 'Reject if not: /skill:reject ' . Store::get('PROPOSAL_ID') . ' --reason "..."');
    }

    /**
     * Confidence calibration anchors (informational).
     */
    private function defineConfidenceAnchorsGuideline(): void
    {
        $this->guideline('confidence-anchors')
            ->text('Confidence calibration anchors when no explicit signal overrides')
            ->example('Single task observation, no repeats')->key('0.4')
            ->example('2-3 repeated observations across sessions')->key('0.7')
            ->example('Explicit user request to record the skill')->key('0.9')
            ->example('Post-correction validated pattern')->key('1.0');
    }

    /**
     * Shared error-handling guideline for all proposal-authoring commands.
     */
    private function defineErrorHandlingGuideline(): void
    {
        $this->guideline('error-handling')
            ->text('Common failure modes and recovery')
            ->example()
            ->phase('missing-evidence', 'Stop and prompt user for at least one evidence pointer (task / file / commit / session / memory id) before continuing')
            ->phase('rationale-too-long', 'Truncate suggestion or ask user to shorten to single sentence (8-400 chars)')
            ->phase('slug-collision', 'Append short hash suffix or ask user for explicit slug override')
            ->phase('schema-violation', 'Print failing JSON Schema field with reason, do NOT write partial files')
            ->phase('mcp-unavailable', 'Skip the affected triage probe, continue with remaining sources, log warning in proposal.json caveats');
    }
}