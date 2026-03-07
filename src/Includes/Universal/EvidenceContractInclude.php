<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose('Enforces PLAN-ONLY vs EVIDENCE-ONLY dual-mode output contract for readiness and snapshot reports.')]
class EvidenceContractInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->rule('evidence-contract')->critical()
            ->text('Two modes for readiness/snapshot reports — mixing them is a P0 failure. '
                . 'PLAN-ONLY: checklist/runbook, no commands executed, requires banner "PLAN-ONLY: No repo state was read." '
                . 'EVIDENCE-ONLY: current state verification, every row must have live command output, missing output = UNVERIFIED + STOP.')
            ->why('Plans presented as evidence create false confidence. Evidence packs without live output are unverifiable. '
                . 'The dual-mode contract prevents the most dangerous failure mode: a report that looks verified but was never executed.')
            ->onViolation('Determine mode from request: "evidence/verify/current/snapshot" = EVIDENCE-ONLY, "checklist/runbook/plan" = PLAN-ONLY, ambiguous = default EVIDENCE-ONLY. '
                . 'For EVIDENCE-ONLY: run all commands, paste output. For PLAN-ONLY: add banner. Never mix modes.');
    }
}
