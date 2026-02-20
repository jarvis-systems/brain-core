<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;

#[Purpose(<<<'PURPOSE'
Multi-phase sequential reasoning framework for structured cognitive processing.
Enforces strict phase progression: analysis → inference → evaluation → decision.
Each phase must pass validation gate before proceeding to next.
PURPOSE
)]
class SequentialReasoningInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // Always-on: phase-flow summary (compact reasoning contract)
        $this->guideline('phase-flow')
            ->text('Strict sequential execution with mandatory validation gates.')
            ->example('Phases execute in order: analysis → inference → evaluation → decision.')->key('order')
            ->example('No phase proceeds without passing its validation gate.')->key('gates')
            ->example('Self-consistency check required before final output.')->key('consistency')
            ->example('On gate failure: retry current phase or return to previous phase.')->key('fallback');

        // Deep-only: detailed phase specifications
        if ($this->isDeepCognitive()) {
            $this->guideline('phase-analysis')
                ->text('Decompose task into objectives, variables, and constraints.')
                ->example()
                    ->phase('extract', 'Identify explicit and implicit requirements from context.')
                    ->phase('classify', 'Determine problem type: factual, analytical, creative, or computational.')
                    ->phase('map', 'List knowns, unknowns, dependencies, and constraints.')
                    ->phase('validate', 'Verify all variables identified, no contradictory assumptions.')
                    ->phase('gate', 'If ambiguous or incomplete → request clarification before proceeding.');

            $this->guideline('phase-inference')
                ->text('Generate and rank hypotheses from analyzed data.')
                ->example()
                    ->phase('connect', 'Link variables through logical or causal relationships.')
                    ->phase('project', 'Simulate outcomes and implications for each hypothesis.')
                    ->phase('rank', 'Order hypotheses by evidence strength and logical coherence.')
                    ->phase('validate', 'Confirm all hypotheses derived from facts, not assumptions.')
                    ->phase('gate', 'If no valid hypothesis → return to analysis with adjusted scope.');

            $this->guideline('phase-evaluation')
                ->text('Test hypotheses against facts, logic, and prior knowledge.')
                ->example()
                    ->phase('verify', 'Cross-check with memory, sources, or documented outcomes.')
                    ->phase('filter', 'Eliminate hypotheses with weak or contradictory evidence.')
                    ->phase('coherence', 'Ensure causal and temporal consistency across reasoning chain.')
                    ->phase('validate', 'Selected hypothesis passes logical and factual verification.')
                    ->phase('gate', 'If contradiction found → downgrade hypothesis and re-enter inference.');

            $this->guideline('phase-decision')
                ->text('Formulate final conclusion from validated reasoning chain.')
                ->example()
                    ->phase('synthesize', 'Consolidate validated insights, eliminate residual uncertainty.')
                    ->phase('format', 'Structure output per response contract requirements.')
                    ->phase('trace', 'Preserve reasoning path for audit and learning.')
                    ->phase('validate', 'Decision directly supported by chain, no speculation or circular logic.')
                    ->phase('gate', 'If uncertain → append uncertainty note or request clarification.');
        }
    }
}
