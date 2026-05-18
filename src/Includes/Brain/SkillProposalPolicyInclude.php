<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose('Skill direct-write flow policy: Brain MUST author skill changes via the four intent commands /skill:learn, /skill:new, /skill:edit, /skill:deprecate. Triage Step 0 plus explicit user pick gate the canonical write. No intermediate proposal stage.')]
class SkillProposalPolicyInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->rule('skill-write-after-triage')->critical()
            ->text('Brain MUST run Step 0 triage AND obtain an explicit user pick/confirmation before writing to node/Skills/{name}/SKILL.md. Direct writes without triage + user pick are forbidden. All skill changes flow through /skill:learn, /skill:new, /skill:edit, or /skill:deprecate.')
            ->why('Triage prevents drift, noise, and duplicate accumulation. The user pick is the approval gate. Removing both creates a self-poisoning skill surface. Rollback is git revert.')
            ->onViolation('Re-run Step 0 triage and surface the user pick/confirmation prompt before any disk write. Invoke /skill:new (create), /skill:edit (modify), or /skill:deprecate (mark obsolete) as appropriate. Use /skill:learn when uncertain — Brain will derive intent from session signals.');

        $this->rule('skill-trigger-policy')->high()
            ->text('Brain creates a skill change only when one of: (a) explicit user request to record or improve a skill, (b) detected repeating pattern across >=2 sessions evidenced via vector-memory search, (c) skill-bug signal where an existing skill triggered then user correction indicates a needed update. Confidence anchors: explicit user request = 0.9; 2-3 detected repeats = 0.7; single-task observation = 0.4; post-correction validated pattern = 1.0.')
            ->why('Prevents change spam. Each direct write must justify its existence with evidence; otherwise it is noise that pollutes the canonical skill surface.')
            ->onViolation('Skip the skill change. Record the observation in vector-memory instead via store_memory.');

        $this->rule('skill-triage-required')->critical()
            ->text('Before writing any skill change, Brain MUST run Step 0 triage inside any skill change command (/skill:learn, /skill:new, /skill:edit, /skill:deprecate): vector-memory probe, vector-task probe, docs_search probe, existing-skills scan, declined-history scan, then classify. Classifications in {DUPLICATE, NOISE_OR_TRIVIAL, DUPLICATE_IN_DOCS, BELONGS_IN_DOCS} MUST ABORT without write. Classifications in {FITS_EXISTING, SPLIT, PREVIOUSLY_REJECTED} MUST require explicit user confirmation before write.')
            ->why('Skipping triage = blind self-modification. Defeats the safety gate by allowing duplicates, noise, docs-duplication, descriptive material that belongs in .docs/, and re-proposals of already-declined ideas to pollute the skill surface.')
            ->onViolation('ABORT the direct write. Re-execute Step 0 triage phases and return classification result with action options before any disk write.');
    }
}
