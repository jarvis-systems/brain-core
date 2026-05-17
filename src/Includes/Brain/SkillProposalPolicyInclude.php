<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose('Skill proposal flow policy: Brain MUST author skill changes via the four intent commands /skill:learn, /skill:new, /skill:edit, /skill:deprecate (diff-review gate), NEVER write directly to node/Skills/{id}/SKILL.md. Defines proposal trigger conditions and confidence anchors.')]
class SkillProposalPolicyInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->rule('skill-write-via-proposal')->critical()
            ->text('All skill modifications go via /skill:learn, /skill:new, /skill:edit, or /skill:deprecate then user review then /skill:apply. Direct writes to node/Skills/{id}/SKILL.md are forbidden.')
            ->why('Direct writes bypass the diff-review gate. Skills are load-bearing instructions — unchecked auto-modification creates drift and garbage accumulation that poisons future runs.')
            ->onViolation('ABORT direct write. Invoke the appropriate /skill:new (create), /skill:edit (modify), or /skill:deprecate (mark obsolete) command. Use /skill:learn if uncertain which to apply — Brain will derive intent from session signals.');

        $this->rule('skill-trigger-policy')->high()
            ->text('Brain creates a skill proposal only when one of: (a) explicit user request to record or improve a skill, (b) detected repeating pattern across >=2 sessions evidenced via vector-memory search, (c) skill-bug signal where an existing skill triggered then user correction indicates a needed update. Confidence anchors: explicit user request = 0.9; 2-3 detected repeats = 0.7; single-task observation = 0.4; post-correction validated pattern = 1.0.')
            ->why('Prevents proposal spam. Each proposal must justify its existence with evidence; otherwise it is noise.')
            ->onViolation('Skip proposal creation. Record the observation in vector-memory instead via store_memory.');

        $this->rule('skill-triage-required')->critical()
            ->text('Before writing any proposal, Brain MUST run Step 0 triage inside any skill creation command (/skill:learn, /skill:new, /skill:edit, /skill:deprecate): vector-memory probe, vector-task probe, docs_search probe, existing-skills scan, rejected-history scan, then classify. Classifications in {DUPLICATE, NOISE_OR_TRIVIAL, DUPLICATE_IN_DOCS, BELONGS_IN_DOCS} MUST ABORT without write. Classifications in {FITS_EXISTING, SPLIT, PREVIOUSLY_REJECTED} MUST require explicit user confirmation before write.')
            ->why('Skipping triage = blind self-modification. Defeats the diff-review gate by allowing duplicates, noise, docs-duplication, descriptive material that belongs in .docs/, and re-proposals of already-rejected ideas to pollute the skill surface.')
            ->onViolation('ABORT proposal write. Re-execute Step 0 triage phases and return classification result with action options before any disk write.');
    }
}