<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;

#[Purpose('Skill proposal reviewer: locates pending candidate by id, prints proposal.json metadata, renders diff (SKILL.md.patch) or new-content draft (SKILL.md.new), and emits explicit caveats plus a reviewer recommendation gate.')]
class SkillReviewInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // IRON RULES
        $this->rule('read-only')->critical()
            ->text('Review is read-only. NEVER apply, modify, or delete the candidate during review.')
            ->why('Review is a decision-support step; only skill:apply and skill:reject mutate state.')
            ->onViolation('ABORT any write attempt. Re-route to the correct command.');

        $this->rule('locate-exactly-one')->critical()
            ->text('Proposal id MUST resolve to exactly one folder across node/Skills/*/pending-proposals/{id} and node/Skills/.new-proposals/{id}.')
            ->why('Ambiguous ids indicate filesystem corruption or duplicate proposal authoring.')
            ->onViolation('ABORT and report all matching paths so the reviewer can resolve manually.');

        // INPUT
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('PROPOSAL_ID', '{id extracted from $RAW_INPUT, format YYYY-MM-DD-slug}'));

        // ROLE
        $this->guideline('role')
            ->text('Skill proposal reviewer. Surfaces all material a human needs to decide apply vs reject: metadata, diff, caveats.');

        // WORKFLOW
        $this->guideline('workflow-step1-locate')
            ->text('STEP 1 - Locate candidate folder')
            ->example()
            ->phase('search-modify', BashTool::call('ls -1d node/Skills/*/pending-proposals/' . Store::get('PROPOSAL_ID') . ' 2>/dev/null || true') . ' ' . Store::as('MATCH_MODIFY'))
            ->phase('search-create', BashTool::call('ls -1d node/Skills/.new-proposals/' . Store::get('PROPOSAL_ID') . ' 2>/dev/null || true') . ' ' . Store::as('MATCH_CREATE'))
            ->phase('resolve', Store::as('CANDIDATE_FOLDER', 'first non-empty of MATCH_MODIFY, MATCH_CREATE'))
            ->phase('verify', Operator::validate(Store::get('CANDIDATE_FOLDER') . ' not empty AND single match', Operator::abort('Proposal ' . Store::get('PROPOSAL_ID') . ' not found or ambiguous')));

        $this->guideline('workflow-step2-read-proposal')
            ->text('STEP 2 - Read and display proposal.json')
            ->example()
            ->phase('read', ReadTool::call(Store::get('CANDIDATE_FOLDER') . '/proposal.json') . ' ' . Store::as('PROPOSAL'))
            ->phase('display-meta', Operator::do(
                'Render: Proposal ID',
                'Render: Action and target_skill (or "(new skill)")',
                'Render: created_at, created_by, source.type:source.ref',
                'Render: confidence (with anchor commentary)',
                'Render: rationale (full text)',
                'Render: evidence list (kind:ref per line)',
                'Render: caveats list explicitly, or "(none stated)"',
                'Render: replacement target if present'
            ));

        $this->guideline('workflow-step3-render-payload')
            ->text('STEP 3 - Render diff or full draft based on action')
            ->example()
            ->phase('detect', Store::as('ACTION', 'PROPOSAL.action'))
            ->phase('show-patch', Operator::if(
                Store::get('ACTION') . ' in {modify-skill, append-reference, deprecate-skill}',
                Operator::do(
                    ReadTool::call(Store::get('CANDIDATE_FOLDER') . '/SKILL.md.patch'),
                    'Render unified diff verbatim, fenced as diff',
                    'Compute and display: stat (+N -M lines), files touched'
                )
            ))
            ->phase('show-new', Operator::if(
                Store::get('ACTION') . ' == create-skill',
                Operator::do(
                    ReadTool::call(Store::get('CANDIDATE_FOLDER') . '/SKILL.md.new'),
                    'Render full draft SKILL.md, fenced as markdown',
                    'Highlight YAML frontmatter (name, description) at top'
                )
            ))
            ->phase('show-deprecate-detail', Operator::if(
                Store::get('ACTION') . ' == deprecate-skill',
                'Render explicit deprecation rationale plus replacement pointer (if any)'
            ));

        $this->guideline('workflow-step4-recommendation-gate')
            ->text('STEP 4 - Emit reviewer recommendation gate')
            ->example()
            ->phase('caveats-emphasis', Operator::if('PROPOSAL.caveats not empty', 'WARN: caveats present, list each on its own line, label as REVIEWER ATTENTION'))
            ->phase('confidence-check', Operator::if('PROPOSAL.confidence < 0.7', 'NOTE: low confidence proposal (<0.7), recommend extra scrutiny of evidence'))
            ->phase('evidence-check', Operator::if('count(PROPOSAL.evidence) < 2 AND PROPOSAL.confidence > 0.7', 'NOTE: high-confidence proposal backed by single evidence item, consider asking author to enrich evidence'))
            ->phase('next-actions', Operator::do(
                'Approve: brain skill:apply ' . Store::get('PROPOSAL_ID'),
                'Reject: brain skill:reject ' . Store::get('PROPOSAL_ID') . ' --reason "..."'
            ));

        // ERROR HANDLING
        $this->guideline('error-handling')
            ->text('Recovery for malformed candidates')
            ->example()
            ->phase('missing-payload', 'If SKILL.md.patch or SKILL.md.new missing, report state="BROKEN" and recommend reject')
            ->phase('unparseable-json', 'If proposal.json unparseable, print path plus parse error, do not crash');
    }
}