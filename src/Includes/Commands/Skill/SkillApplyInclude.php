<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Skill proposal applier: validates proposal against schema, executes the action (patch | new file | create skill | deprecate), records history-references/{date}-applied-{slug}.md, removes the pending folder, recommends brain compile.')]
class SkillApplyInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // IRON RULES
        $this->rule('schema-validate-first')->critical()
            ->text('MUST validate proposal.json against cli/schema/skill-proposal.schema.json before any mutation.')
            ->why('A malformed proposal applied to canonical SKILL.md poisons the skill surface and downstream compile.')
            ->onViolation('ABORT before any write. Print schema error path plus reason.');

        $this->rule('frontmatter-required')->critical()
            ->text('Resulting SKILL.md MUST contain YAML frontmatter with non-empty name and description fields.')
            ->why('NativeSkillCollector rejects skills missing these fields. Apply would compile-break.')
            ->onViolation('ABORT before write. Show user the missing field and recommend skill:reject or proposal rework.');

        $this->rule('atomic-apply')->critical()
            ->text('Apply is atomic per proposal: either every effect lands (canonical write + history record + pending folder removal) or none. On failure mid-way, rollback any partial canonical write.')
            ->why('Half-applied proposals leave canonical SKILL.md in a poisoned state with no audit trail.')
            ->onViolation('Detect failure, restore canonical SKILL.md from backup snapshot taken before write, exit non-zero.');

        $this->rule('preserve-history')->high()
            ->text('history-references/{date}-applied-{slug}.md MUST capture: full proposal metadata, decided_at (ISO-8601), decided_by, and a one-line result summary.')
            ->why('Skill drift is investigated via the audit trail. Missing history = unrecoverable knowledge loss.')
            ->onViolation('Re-emit history file before declaring success.');

        // INPUT
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('PROPOSAL_ID', '{id extracted from $RAW_INPUT}'))
            ->text(Store::as('DECIDED_BY', '{user identifier from session context, defaults to "user"}'));

        // ROLE
        $this->guideline('role')
            ->text('Skill proposal applier. Owns the only legitimate write path into canonical SKILL.md. Executes the proposal action, records audit trail, removes pending folder.');

        // WORKFLOW
        $this->guideline('workflow-step1-locate-validate')
            ->text('STEP 1 - Locate proposal and validate against schema')
            ->example()
            ->phase('locate', BashTool::call('ls -1d node/Skills/*/pending-proposals/' . Store::get('PROPOSAL_ID') . ' node/Skills/.new-proposals/' . Store::get('PROPOSAL_ID') . ' 2>/dev/null | head -1') . ' ' . Store::as('CANDIDATE_FOLDER'))
            ->phase('verify', Operator::validate(Store::get('CANDIDATE_FOLDER') . ' not empty', Operator::abort('Proposal ' . Store::get('PROPOSAL_ID') . ' not found')))
            ->phase('read', ReadTool::call(Store::get('CANDIDATE_FOLDER') . '/proposal.json') . ' ' . Store::as('PROPOSAL'))
            ->phase('schema-check', Operator::validate('PROPOSAL conforms to cli/schema/skill-proposal.schema.json', Operator::abort('Schema validation failed, fix proposal or reject')));

        $this->guideline('workflow-step2-derive-target')
            ->text('STEP 2 - Derive canonical paths')
            ->example()
            ->phase('action', Store::as('ACTION', 'PROPOSAL.action'))
            ->phase('target', Store::as('TARGET_SKILL', 'PROPOSAL.target_skill (or carried inside SKILL.md.new frontmatter when action=create-skill)'))
            ->phase('skill-folder', Store::as('SKILL_FOLDER', 'node/Skills/' . Store::get('TARGET_SKILL')))
            ->phase('canonical', Store::as('CANONICAL_SKILL', Store::get('SKILL_FOLDER') . '/SKILL.md'))
            ->phase('history-dir', Store::as('HISTORY_DIR', Store::get('SKILL_FOLDER') . '/history-references'));

        $this->guideline('workflow-step3-backup')
            ->text('STEP 3 - Snapshot canonical SKILL.md before any write (rollback safety)')
            ->example()
            ->phase('snapshot', Operator::if(
                Store::get('ACTION') . ' != create-skill',
                BashTool::call('cp ' . Store::get('CANONICAL_SKILL') . ' ' . Store::get('CANDIDATE_FOLDER') . '/.rollback-snapshot.md')
            ));

        $this->guideline('workflow-step4-execute-action')
            ->text('STEP 4 - Execute the proposal action')
            ->example()
            ->phase('modify', Operator::if(Store::get('ACTION') . ' == modify-skill', Operator::do(
                BashTool::call('patch -p0 ' . Store::get('CANONICAL_SKILL') . ' < ' . Store::get('CANDIDATE_FOLDER') . '/SKILL.md.patch'),
                'Verify: file still parses YAML frontmatter, name + description non-empty'
            )))
            ->phase('append-reference', Operator::if(Store::get('ACTION') . ' == append-reference', Operator::do(
                'Derive {ref-name}.md filename from proposal metadata (slug field)',
                BashTool::call('mkdir -p ' . Store::get('SKILL_FOLDER') . '/references'),
                'Extract new file content from SKILL.md.patch (the unified diff that adds references/{ref-name}.md)',
                WriteTool::call(Store::get('SKILL_FOLDER') . '/references/{ref-name}.md', '{extracted content}'),
                Operator::if('patch also touches SKILL.md', BashTool::call('patch -p0 ' . Store::get('CANONICAL_SKILL') . ' < {filtered-patch}'))
            )))
            ->phase('create', Operator::if(Store::get('ACTION') . ' == create-skill', Operator::do(
                BashTool::call('mkdir -p ' . Store::get('SKILL_FOLDER')),
                BashTool::call('mv ' . Store::get('CANDIDATE_FOLDER') . '/SKILL.md.new ' . Store::get('CANONICAL_SKILL')),
                'Verify: YAML frontmatter present, name + description non-empty'
            )))
            ->phase('deprecate', Operator::if(Store::get('ACTION') . ' == deprecate-skill', Operator::do(
                BashTool::call('patch -p0 ' . Store::get('CANONICAL_SKILL') . ' < ' . Store::get('CANDIDATE_FOLDER') . '/SKILL.md.patch'),
                'Verify: frontmatter now contains deprecated: true (and replacement: {target} if PROPOSAL.replacement set)'
            )));

        $this->guideline('workflow-step5-frontmatter-lint')
            ->text('STEP 5 - Lint resulting frontmatter')
            ->example()
            ->phase('read', ReadTool::call(Store::get('CANONICAL_SKILL')) . ' ' . Store::as('FINAL_CONTENT'))
            ->phase('parse', 'Extract YAML between leading --- markers')
            ->phase('check-name', Operator::validate('name field is non-empty string', Operator::do(
                'Rollback canonical from .rollback-snapshot.md',
                Operator::abort('Frontmatter missing required field: name')
            )))
            ->phase('check-description', Operator::validate('description field is non-empty string', Operator::do(
                'Rollback canonical from .rollback-snapshot.md',
                Operator::abort('Frontmatter missing required field: description')
            )))
            ->phase('check-docs-topics-optional', Operator::if(
                'docs_topics field present in frontmatter',
                Operator::validate(
                    'docs_topics is array of strings, each 1-64 chars, contains no slashes (paths-with-slashes forbidden — keywords only), array non-empty',
                    Operator::do(
                        'Rollback canonical from .rollback-snapshot.md',
                        Operator::abort('docs_topics frontmatter field invalid: must be array of keyword strings (1-64 chars each, no slashes) or absent entirely')
                    )
                )
            ));

        $this->guideline('workflow-step6-history-record')
            ->text('STEP 6 - Record history-references/{date}-applied-{slug}.md')
            ->example()
            ->phase('mkdir', BashTool::call('mkdir -p ' . Store::get('HISTORY_DIR')))
            ->phase('date', BashTool::call('date +"%Y-%m-%d"') . ' ' . Store::as('DECIDED_DATE'))
            ->phase('iso', BashTool::call('date -u +"%Y-%m-%dT%H:%M:%SZ"') . ' ' . Store::as('DECIDED_AT'))
            ->phase('slug', Store::as('SLUG', 'slug portion of ' . Store::get('PROPOSAL_ID')))
            ->phase('write', WriteTool::call(Store::get('HISTORY_DIR') . '/' . Store::get('DECIDED_DATE') . '-applied-' . Store::get('SLUG') . '.md', '{frontmatter: status=applied, proposal_id, action, decided_at, decided_by, confidence, original rationale, evidence list; body: result summary plus link to proposal.json archived inline}'));

        $this->guideline('workflow-step6_5-vector-memory-commit')
            ->text('STEP 6.5 - Commit positive signal to vector-memory (skill applied successfully)')
            ->example()
            ->phase('store', VectorMemoryMcp::callValidatedJson('store_memory', [
                'category' => 'skill-applied',
                'content' => '{PROPOSAL.rationale + result summary + applied SKILL.md path}',
                'tags' => ['skill:{TARGET_SKILL}', 'outcome:accepted', 'action:{ACTION}', 'confidence:{PROPOSAL.confidence}'],
            ]))
            ->phase('graceful-degradation', Operator::if(
                'MCP vector-memory unavailable or store_memory fails',
                'Log warning "vector-memory positive signal not recorded" and continue. Do NOT fail the apply.'
            ));

        $this->guideline('workflow-step7-cleanup')
            ->text('STEP 7 - Remove pending folder and rollback snapshot')
            ->example()
            ->phase('rm-pending', BashTool::call('rm -rf ' . Store::get('CANDIDATE_FOLDER')));

        $this->guideline('workflow-step8-report')
            ->text('STEP 8 - Report success and recommend next steps')
            ->example()
            ->phase('summary', 'Display: Proposal ' . Store::get('PROPOSAL_ID') . ' applied to ' . Store::get('CANONICAL_SKILL'))
            ->phase('affected', 'Display affected files: canonical SKILL.md, history-references/{file}, optional new reference file')
            ->phase('recommend-compile', 'NOTE: run brain compile to propagate the change into compiled artifacts');

        // ERROR HANDLING
        $this->guideline('error-handling')
            ->text('Failure recovery rules')
            ->example()
            ->phase('patch-conflict', 'If patch hunks fail, rollback from snapshot, exit non-zero, recommend rebuilding proposal against current SKILL.md')
            ->phase('schema-violation', 'ABORT before any write, do NOT cleanup pending folder, let reviewer fix')
            ->phase('frontmatter-lint-fail', 'Rollback canonical, keep pending folder, exit non-zero with clear error')
            ->phase('history-write-fail', 'Treat as failure: rollback canonical, do NOT remove pending folder');
    }
}