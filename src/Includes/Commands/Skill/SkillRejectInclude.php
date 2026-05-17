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

#[Purpose('Skill proposal rejecter: requires --reason, archives proposal metadata to history-references/{date}-rejected-{slug}.md with reason and decided_by, then removes the pending folder.')]
class SkillRejectInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // IRON RULES
        $this->rule('reason-required')->critical()
            ->text('--reason "..." is MANDATORY. Reject without explicit reason is FORBIDDEN.')
            ->why('Reject history is the primary signal for skillable miner heuristics in Phase 2. Empty reasons destroy that signal.')
            ->onViolation('ABORT and prompt user for --reason value.');

        $this->rule('never-mutate-canonical')->critical()
            ->text('Reject NEVER touches node/Skills/{target}/SKILL.md. Only writes to history-references/ and removes pending folder.')
            ->why('Reject is a no-op against canonical state. Mixing reject with canonical mutation breaks the audit model.')
            ->onViolation('ABORT any canonical write attempt.');

        $this->rule('preserve-history')->high()
            ->text('history-references/{date}-rejected-{slug}.md MUST capture: full proposal metadata, reject reason, decided_at, decided_by.')
            ->why('Skillable miner uses rejected history to learn what NOT to propose again.')
            ->onViolation('Re-emit history file before declaring success.');

        // INPUT
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('PROPOSAL_ID', '{id extracted from $RAW_INPUT}'))
            ->text(Store::as('REASON', '{string extracted from --reason flag, REQUIRED}'))
            ->text(Store::as('DECIDED_BY', '{user identifier from session context, defaults to "user"}'));

        // ROLE
        $this->guideline('role')
            ->text('Skill proposal rejecter. Records explicit reject decision with reason, archives metadata, removes pending folder. Touches nothing canonical.');

        // WORKFLOW
        $this->guideline('workflow-step1-validate-args')
            ->text('STEP 1 - Validate arguments')
            ->example()
            ->phase('check-reason', Operator::validate(Store::get('REASON') . ' not empty AND length >= 8', Operator::abort('--reason "..." is mandatory and must be at least 8 chars')))
            ->phase('check-id', Operator::validate(Store::get('PROPOSAL_ID') . ' matches /^\\d{4}-\\d{2}-\\d{2}-[a-z0-9-]+$/', Operator::abort('Invalid proposal id format')));

        $this->guideline('workflow-step2-locate')
            ->text('STEP 2 - Locate candidate folder')
            ->example()
            ->phase('locate', BashTool::call('ls -1d node/Skills/*/pending-proposals/' . Store::get('PROPOSAL_ID') . ' node/Skills/.new-proposals/' . Store::get('PROPOSAL_ID') . ' 2>/dev/null | head -1') . ' ' . Store::as('CANDIDATE_FOLDER'))
            ->phase('verify', Operator::validate(Store::get('CANDIDATE_FOLDER') . ' not empty', Operator::abort('Proposal ' . Store::get('PROPOSAL_ID') . ' not found')))
            ->phase('read', ReadTool::call(Store::get('CANDIDATE_FOLDER') . '/proposal.json') . ' ' . Store::as('PROPOSAL'));

        $this->guideline('workflow-step3-derive-history-target')
            ->text('STEP 3 - Derive history-references target')
            ->example()
            ->phase('target', Store::as('TARGET_SKILL', 'PROPOSAL.target_skill (when action != create-skill); when action == create-skill, history folder also lives under .new-proposals sibling .new-history-references/ OR under node/Skills/.new-proposals-history/ — Phase 1 default: write into the SAME parent folder as the candidate'))
            ->phase('history-dir', Operator::if(
                Store::get('TARGET_SKILL') . ' not null',
                Store::as('HISTORY_DIR', 'node/Skills/' . Store::get('TARGET_SKILL') . '/history-references'),
                Store::as('HISTORY_DIR', 'node/Skills/.new-proposals/.history-references')
            ));

        $this->guideline('workflow-step4-write-history')
            ->text('STEP 4 - Write rejected history record')
            ->example()
            ->phase('mkdir', BashTool::call('mkdir -p ' . Store::get('HISTORY_DIR')))
            ->phase('date', BashTool::call('date +"%Y-%m-%d"') . ' ' . Store::as('DECIDED_DATE'))
            ->phase('iso', BashTool::call('date -u +"%Y-%m-%dT%H:%M:%SZ"') . ' ' . Store::as('DECIDED_AT'))
            ->phase('slug', Store::as('SLUG', 'slug portion of ' . Store::get('PROPOSAL_ID')))
            ->phase('write', WriteTool::call(Store::get('HISTORY_DIR') . '/' . Store::get('DECIDED_DATE') . '-rejected-' . Store::get('SLUG') . '.md', '{frontmatter: status=rejected, proposal_id, action, decided_at, decided_by, reason=' . Store::get('REASON') . ', confidence, original rationale, evidence list; body: full proposal.json contents inline for audit}'));

        $this->guideline('workflow-step-vector-memory-commit')
            ->text('Commit negative signal to vector-memory (skill proposal rejected — prevent future re-proposing)')
            ->example()
            ->phase('store', VectorMemoryMcp::callValidatedJson('store_memory', [
                'category' => 'skill-rejected',
                'content' => '{PROPOSAL.rationale + reject reason: ' . Store::get('REASON') . '}',
                'tags' => ['skill:{TARGET_SKILL}', 'outcome:rejected', 'action:{PROPOSAL.action}'],
            ]))
            ->phase('graceful-degradation', Operator::if(
                'MCP vector-memory unavailable or store_memory fails',
                'Log warning "vector-memory negative signal not recorded" and continue. Do NOT fail the reject.'
            ));

        $this->guideline('workflow-step5-cleanup')
            ->text('STEP 5 - Remove pending folder')
            ->example()
            ->phase('rm-pending', BashTool::call('rm -rf ' . Store::get('CANDIDATE_FOLDER')));

        $this->guideline('workflow-step6-report')
            ->text('STEP 6 - Report success')
            ->example()
            ->phase('summary', 'Display: Proposal ' . Store::get('PROPOSAL_ID') . ' rejected. Reason: ' . Store::get('REASON'))
            ->phase('history', 'Display: History recorded at ' . Store::get('HISTORY_DIR'));

        // ERROR HANDLING
        $this->guideline('error-handling')
            ->text('Failure recovery rules')
            ->example()
            ->phase('history-write-fail', 'Do NOT remove pending folder if history write fails. Exit non-zero with error path.')
            ->phase('missing-proposal', 'If proposal id not found, exit non-zero, recommend brain skill:list')
            ->phase('unparseable-json', 'If proposal.json unparseable, still allow reject but record state="INVALID_JSON" in history');
    }
}