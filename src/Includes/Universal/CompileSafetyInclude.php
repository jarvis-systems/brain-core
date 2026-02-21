<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose('Compile discipline: single-writer lock, WIP quarantine, worktree hygiene. See .docs/product/04-security-model.md Compile Safety Contract.')]
class CompileSafetyInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->rule('compile-single-writer')->critical()
            ->text('Single-writer lock for brain compile is mandatory. Concurrent compilation is forbidden.')
            ->why('flock() mutex prevents race conditions. Kernel auto-releases on process death.')
            ->onViolation('Wait for active compilation to finish. Use --no-lock only with BRAIN_ALLOW_NO_LOCK=1 under paranoid/strict modes.');

        $this->rule('worktree-quarantine')->high()
            ->text('If repo contains unrelated WIP, quarantine it (git stash/branch) before starting enterprise work.')
            ->why('Mixed WIP and enterprise changes create cross-contamination risk in commits.')
            ->onViolation('Run git stash push -u -m "wip-quarantine" before proceeding. Restore with git stash pop after.');

        $this->rule('compile-clean-worktree')->high()
            ->text('brain compile must never produce new uncommitted changes to tracked files.')
            ->why('Deterministic builds require clean worktree. Non-determinism indicates compile bug.')
            ->onViolation('Run scripts/check-compile-clean.sh to verify. Fix source if compile dirties worktree.');
    }
}
