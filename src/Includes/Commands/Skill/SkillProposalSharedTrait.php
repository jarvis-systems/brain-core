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
 * Shared subroutine for the 4 intent-based skill commands
 * (/skill:learn, /skill:new, /skill:edit, /skill:deprecate).
 *
 * Centralises the Step 0 triage (5 probes + 9 outcome classifier + branch
 * operators), the optional docs_topics suggestion, the direct write to
 * canonical node/Skills/{target}/SKILL.md, the per-skill history-references
 * audit log entry, the vector-memory positive signal store, and the final
 * report. The intermediate "proposal" stage has been eliminated — user
 * invocation of a /skill:* command IS the explicit consent, triage is the
 * safety gate, and git revert is the rollback path.
 *
 * Each consumer Include is expected to populate the intent-collection
 * variables before calling appendProposalWorkflow() at the end of its
 * handle() method:
 *   $ACTION         create-skill | modify-skill | append-reference | deprecate-skill
 *   $TARGET_SKILL   kebab-case skill id (canonical folder under node/Skills/)
 *   $RATIONALE      single sentence, 8-400 chars
 *   $EVIDENCE       array of {kind: task|file|commit|session|memory, ref: ...} (>=1 item)
 *   $CONFIDENCE     calibrated float 0.0..1.0
 *   $DRAFT          (create-skill) full SKILL.md content with YAML frontmatter
 *   $PATCH          (deprecate-skill / append-reference) unified diff to apply
 *   $SUGGESTED_DIFF (modify-skill) unified diff to apply
 *   $REF_NAME       (append-reference) kebab-case file name (no extension)
 *   $REF_CONTENT    (append-reference) full reference markdown body
 *   $REPLACEMENT    (deprecate-skill) target skill id, or literal "none"
 *   $CAVEATS        optional array of caveats; "[]" default
 */
trait SkillProposalSharedTrait
{
    /**
     * Append the shared triage + direct-write + history-record + report
     * workflow to the current Include. Must be called AFTER intent-collection
     * workflows have stored ACTION / TARGET_SKILL / RATIONALE / EVIDENCE /
     * CONFIDENCE (plus the action-specific payload variables).
     */
    protected function appendProposalWorkflow(): void
    {
        $this->defineSharedSkillRules();
        $this->defineTriageWorkflow();
        $this->defineDocsTopicsWorkflow();
        $this->defineDirectWriteWorkflow();
        $this->defineHistoryRecordWorkflow();
        $this->defineMemoryPositiveSignalWorkflow();
        $this->defineReportWorkflow();
        $this->defineConfidenceAnchorsGuideline();
        $this->defineErrorHandlingGuideline();
    }

    /**
     * Helper: append a uniform "user declined" workflow tail. Consumer
     * Includes invoke this from their abort branches (e.g. user picks
     * "none" / aborts the draft / aborts the diff) to record a negative
     * signal in vector-memory before exiting. The TARGET_SKILL store
     * variable may be null/empty when the user declined before any
     * target was derived — that is acceptable.
     */
    protected function recordDeclineSignal(): void
    {
        $this->guideline('workflow-decline-signal')
            ->text('On user decline (none picked / draft aborted / diff aborted): record a negative signal in vector-memory before exiting')
            ->example()
            ->phase('store-decline', VectorMemoryMcp::callValidatedJson('store_memory', [
                'category' => 'skill-declined',
                'content' => '{RATIONALE if available, else short summary of declined candidate + decline reason}',
                'tags' => ['skill:' . Store::get('TARGET_SKILL'), 'outcome:declined', 'action:' . Store::get('ACTION')],
            ]))
            ->phase('graceful-degradation', Operator::if(
                'MCP vector-memory unavailable',
                'Log warning "vector-memory decline signal not recorded" and continue. Do NOT fail the decline path.'
            ));
    }

    /**
     * Shared iron rules that every direct-write skill command must obey.
     */
    private function defineSharedSkillRules(): void
    {
        $this->rule('triage-before-write')->critical()
            ->text('STEP 0 triage MUST complete before any direct write to node/Skills/{target}/. ABORT routes (DUPLICATE, NOISE_OR_TRIVIAL, DUPLICATE_IN_DOCS, BELONGS_IN_DOCS) terminate the flow. CONFIRM routes (FITS_EXISTING, SPLIT, PREVIOUSLY_REJECTED) require explicit user YES before proceeding to direct write. PROCEED routes (NEW_DOMAIN, OK_MODIFY) go straight to direct write.')
            ->why('Skipping triage causes blind self-modification — duplicates, noise, docs-duplication, or already-rejected ideas pollute the canonical skill surface. Triage IS the safety gate now that the pending-proposals stage is gone.')
            ->onViolation('Re-run Step 0 triage phases (memory probe, task probe, docs probe, skills scan, rejected scan, classify) before any disk write.');

        $this->rule('evidence-required')->high()
            ->text('EVIDENCE array MUST contain at least one item with verifiable {kind, ref}. kind in {task, file, commit, session, memory}.')
            ->why('Skill changes without evidence are noise per skill-trigger-policy. The history record needs verifiable pointers for future audit.')
            ->onViolation('Stop and ask user for at least one evidence pointer (task id, file path, commit, session, or memory id).');

        $this->rule('rationale-shape')->high()
            ->text('RATIONALE MUST be a single declarative sentence, 8-400 chars. Multi-sentence rationales or bullet lists are rejected.')
            ->why('Rationale is rendered verbatim into the history record and into vector-memory tags. Long-form prose belongs in the SKILL.md body or in .docs/.')
            ->onViolation('Truncate suggestion or ask user to shorten to one sentence (8-400 chars) before continuing.');

        $this->rule('frontmatter-required')->critical()
            ->text('After any write, the canonical node/Skills/{target}/SKILL.md MUST contain valid YAML frontmatter with non-empty name and description fields. If write would produce malformed frontmatter, ABORT before disk write.')
            ->why('NativeSkillCollector rejects skills missing these fields — a malformed write breaks brain compile.')
            ->onViolation('ABORT before disk write. If write already happened and the lint failed, instruct user to run git checkout node/Skills/{target}/SKILL.md to restore.');
    }

    /**
     * STEP 0 — Triage skill change intent against five probe sources, classify
     * into one of nine outcomes, and branch (ABORT / CONFIRM / PROCEED).
     */
    private function defineTriageWorkflow(): void
    {
        $this->guideline('workflow-step0-triage')
            ->text('STEP 0 - Triage skill change intent against vector-memory, vector-task, existing skills, rejected history, and project .docs/ BEFORE any write')
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
                BashTool::call("find node/Skills -path '*/history-references/*-declined-*.md' -not -path 'node/Skills/.*'"),
                'Parse decline reason from frontmatter or first paragraph of each match',
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
                Operator::abort('Duplicate of {CLASSIFICATION.skill}/{CLASSIFICATION.rule_id}, no skill change needed')
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
                    'Display classification payload (recommended_target / split actions / past decline reason) to user',
                    'Prompt: "Proceed anyway? (yes/no)"',
                    Operator::validate('User response is YES or CONFIRM', Operator::abort('Triage halted: user did not confirm CONFIRM-route classification'))
                )
            ))
            ->phase('triage-branch-proceed', Operator::if(
                Store::get('CLASSIFICATION') . ' in {NEW_DOMAIN, OK_MODIFY}',
                'Proceed silently to direct write'
            ));
    }

    /**
     * STEP 5.5 — Suggest docs_topics frontmatter keywords from triage docs hits.
     */
    private function defineDocsTopicsWorkflow(): void
    {
        $this->guideline('workflow-step5_5-docs-topics')
            ->text('STEP 5.5 - Optionally derive docs_topics frontmatter keywords from triage docs hits before direct write')
            ->example()
            ->phase('suggest-docs-topics', Operator::if(
                Store::get('ACTION') . ' in {create-skill, modify-skill}',
                Operator::do(
                    'Analyze ' . Store::get('DOCS_HITS') . ' — identify 1-5 topical keywords that capture the docs domains relevant to this skill (NOT hardcoded paths)',
                    'Each keyword: kebab-case, 1-64 chars, no slashes, no file extensions (paths-with-slashes are forbidden — skills must stay portable across projects)',
                    'If relevant keywords exist: embed optional docs_topics: [kw1, kw2, ...] line into YAML frontmatter of $DRAFT (create-skill) or extend the $SUGGESTED_DIFF (modify-skill) to update frontmatter',
                    'If no relevant docs hits: SKIP — docs_topics field stays absent (the field is optional)',
                    Store::as('DOCS_TOPICS', 'array of strings 1-64 chars each, or null if skipped')
                )
            ))
            ->phase('append-reference-hint', Operator::if(
                Store::get('CLASSIFICATION') . ' == DUPLICATE_IN_DOCS',
                'Inline recommendation: prefer `append-reference` action wiring SKILL.md to {CLASSIFICATION.doc_path} via docs_topics keywords instead of duplicating prose.'
            ));
    }

    /**
     * Direct write to canonical node/Skills/{target}/. Dispatches on $ACTION.
     * No pending-proposals/ folder. No proposal.json. No .new-proposals/.
     * Triage gate plus user pick are the only safeguards in front of the
     * canonical write; git revert is the rollback path.
     */
    private function defineDirectWriteWorkflow(): void
    {
        $this->guideline('workflow-direct-write')
            ->text('Direct write to canonical node/Skills/{target}/. Dispatched by $ACTION. Triage MUST have passed; user pick / confirmation MUST have been collected upstream by the consuming command.')
            ->example()
            ->phase('resolve-skill-folder', Store::as('SKILL_FOLDER', 'node/Skills/' . Store::get('TARGET_SKILL')))
            ->phase('resolve-canonical', Store::as('CANONICAL_SKILL', Store::get('SKILL_FOLDER') . '/SKILL.md'))
            ->phase('create-skill-mkdir', Operator::if(
                Store::get('ACTION') . ' == create-skill',
                Operator::do(
                    BashTool::call('mkdir -p ' . Store::get('SKILL_FOLDER')),
                    WriteTool::call(Store::get('CANONICAL_SKILL'), Store::get('DRAFT')),
                    'Verify: YAML frontmatter present, name + description non-empty. If frontmatter malformed, ABORT and instruct user to inspect the freshly written file before any further command.'
                )
            ))
            ->phase('modify-skill-patch', Operator::if(
                Store::get('ACTION') . ' == modify-skill',
                Operator::do(
                    BashTool::call('patch -p0 ' . Store::get('CANONICAL_SKILL') . ' < <(printf "%s" ' . Store::get('SUGGESTED_DIFF') . ')'),
                    'On patch failure: ABORT with clear error. The canonical SKILL.md may be in a partial state — instruct user to run "git checkout -- ' . Store::get('CANONICAL_SKILL') . '" to restore.',
                    'Verify: YAML frontmatter still parses, name + description non-empty after patch'
                )
            ))
            ->phase('append-reference-write', Operator::if(
                Store::get('ACTION') . ' == append-reference',
                Operator::do(
                    BashTool::call('mkdir -p ' . Store::get('SKILL_FOLDER') . '/references'),
                    WriteTool::call(Store::get('SKILL_FOLDER') . '/references/' . Store::get('REF_NAME') . '.md', Store::get('REF_CONTENT')),
                    Operator::if(
                        Store::get('PATCH') . ' is non-empty',
                        Operator::do(
                            BashTool::call('patch -p0 ' . Store::get('CANONICAL_SKILL') . ' < <(printf "%s" ' . Store::get('PATCH') . ')'),
                            'On patch failure: ABORT with clear error. References file is already on disk; instruct user to either keep the new reference and reapply the SKILL.md link manually, or remove the new reference file with rm.'
                        )
                    )
                )
            ))
            ->phase('deprecate-skill-patch', Operator::if(
                Store::get('ACTION') . ' == deprecate-skill',
                Operator::do(
                    BashTool::call('patch -p0 ' . Store::get('CANONICAL_SKILL') . ' < <(printf "%s" ' . Store::get('PATCH') . ')'),
                    'On patch failure: ABORT with clear error. Instruct user to run "git checkout -- ' . Store::get('CANONICAL_SKILL') . '" to restore.',
                    'Verify: frontmatter now contains deprecated: true (and replacement: ' . Store::get('REPLACEMENT') . ' if not "none")'
                )
            ));
    }

    /**
     * After the canonical write succeeds, log an audit record under the
     * per-skill history-references/ folder. One file per applied action.
     */
    private function defineHistoryRecordWorkflow(): void
    {
        $this->guideline('workflow-history-record')
            ->text('Record history-references/{date}-{action}-{slug}.md audit entry for the canonical write')
            ->example()
            ->phase('history-dir', Store::as('HISTORY_DIR', Store::get('SKILL_FOLDER') . '/history-references'))
            ->phase('mkdir-history', BashTool::call('mkdir -p ' . Store::get('HISTORY_DIR')))
            ->phase('date', BashTool::call('date +"%Y-%m-%d"') . ' ' . Store::as('DECIDED_DATE'))
            ->phase('iso', BashTool::call('date -u +"%Y-%m-%dT%H:%M:%SZ"') . ' ' . Store::as('DECIDED_AT'))
            ->phase('slug', Store::as('SLUG', 'kebab-case derived from ' . Store::get('RATIONALE') . ', ASCII, max 32 chars, [a-z0-9-]+, trimmed of leading/trailing dashes'))
            ->phase('decided-by', Store::as('DECIDED_BY', 'one of {user, brain, skillable} — explicit user invocation defaults to "user"'))
            ->phase('history-path', Store::as('HISTORY_PATH', Store::get('HISTORY_DIR') . '/' . Store::get('DECIDED_DATE') . '-' . Store::get('ACTION') . '-' . Store::get('SLUG') . '.md'))
            ->phase('write-history', WriteTool::call(Store::get('HISTORY_PATH'), '---' . "\n"
                . 'status: written' . "\n"
                . 'action: ' . Store::get('ACTION') . "\n"
                . 'decided_at: ' . Store::get('DECIDED_AT') . "\n"
                . 'decided_by: ' . Store::get('DECIDED_BY') . "\n"
                . 'confidence: ' . Store::get('CONFIDENCE') . "\n"
                . '---' . "\n\n"
                . Store::get('RATIONALE') . "\n\n"
                . 'Evidence:' . "\n"
                . '- {kind}: {ref} (one bullet per item in ' . Store::get('EVIDENCE') . ')' . "\n\n"
                . 'Result: one-line summary of what changed in ' . Store::get('CANONICAL_SKILL')));
    }

    /**
     * Commit a positive signal to vector-memory after a successful direct
     * write. Mirrors the negative signal emitted by recordDeclineSignal().
     */
    private function defineMemoryPositiveSignalWorkflow(): void
    {
        $this->guideline('workflow-memory-positive-signal')
            ->text('Commit positive signal to vector-memory after a successful direct write')
            ->example()
            ->phase('store-written', VectorMemoryMcp::callValidatedJson('store_memory', [
                'category' => 'skill-written',
                'content' => '{RATIONALE + one-line result summary + canonical path: ' . Store::get('CANONICAL_SKILL') . '}',
                'tags' => ['skill:' . Store::get('TARGET_SKILL'), 'outcome:written', 'action:' . Store::get('ACTION'), 'confidence:' . Store::get('CONFIDENCE')],
            ]))
            ->phase('graceful-degradation', Operator::if(
                'MCP vector-memory unavailable or store_memory fails',
                'Log warning "vector-memory positive signal not recorded" and continue. Do NOT fail the write — the canonical write already landed.'
            ));
    }

    /**
     * Final report. Direct-write semantics: no proposal id, no apply step.
     * The user sees the canonical path that changed, the history record
     * path, the rollback command, and the brain compile recommendation.
     */
    private function defineReportWorkflow(): void
    {
        $this->guideline('workflow-report')
            ->text('Report success: canonical path, history record, rollback command, brain compile recommendation')
            ->example()
            ->phase('summary', 'Display: "Successfully wrote to ' . Store::get('CANONICAL_SKILL') . '"')
            ->phase('history', 'Display: "History logged: ' . Store::get('HISTORY_PATH') . '"')
            ->phase('rollback', 'Display: "If you need to undo: git checkout -- ' . Store::get('SKILL_FOLDER') . ' (or git revert HEAD if already committed)"')
            ->phase('recommend-compile', 'Display: "Run brain compile to propagate the change to compiled artifacts."');
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
     * Shared error-handling guideline for all direct-write skill commands.
     */
    private function defineErrorHandlingGuideline(): void
    {
        $this->guideline('error-handling')
            ->text('Common failure modes and recovery for the direct-write flow')
            ->example()
            ->phase('missing-evidence', 'Stop and prompt user for at least one evidence pointer (task / file / commit / session / memory id) before continuing')
            ->phase('rationale-too-long', 'Truncate suggestion or ask user to shorten to single sentence (8-400 chars)')
            ->phase('slug-collision', 'Append short hash suffix to history filename or ask user for explicit slug override')
            ->phase('frontmatter-lint-fail', 'ABORT before disk write when validation detects malformed frontmatter. If write already happened, instruct user to run git checkout -- ' . Store::get('CANONICAL_SKILL') . ' to restore.')
            ->phase('patch-failure', 'Abort with clear error. Tell user the canonical may be in partial state and recommend git checkout -- ' . Store::get('CANONICAL_SKILL') . ' before retrying.')
            ->phase('mcp-unavailable', 'Skip the affected triage probe, continue with remaining sources, log warning. For vector-memory signal stores: log warning and continue — never fail the write because of MCP outage.');
    }
}