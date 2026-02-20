<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;

#[Purpose(<<<'PURPOSE'
Defines Brain-level agent response validation protocol.
Ensures delegated agent responses meet semantic, structural, and policy requirements before acceptance.
PURPOSE
)]
class ResponseValidationInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // === DEEP-COGNITIVE-ONLY: All validation guidelines ===
        // CoreInclude quality-gate rule already serves as compact always-on version.

        if (!$this->isDeepCognitive()) {
            return;
        }

        $this->guideline('validation-semantic')
            ->text('Validate semantic alignment between agent response and delegated task.')
            ->example('Compare response embedding vs task query using cosine similarity')->key('method')
            ->example('≥ 0.9 = PASS, 0.75-0.89 = WARN (accept with flag), < 0.75 = FAIL')->key('threshold')
            ->example('Request clarification, max 2 retries before reject')->key('on-fail');

        $this->guideline('validation-structural')
            ->text('Validate response structure and required components.')
            ->example('Verify response contains expected fields for task type')->key('method')
            ->example('Validate syntax if structured output (XML/JSON)')->key('method')
            ->example('Auto-repair if fixable, reject if malformed')->key('on-fail');

        $this->guideline('validation-policy')
            ->text('Validate response against safety and quality thresholds.')
            ->example('quality-score ≥ 0.95, trust-index ≥ 0.75')->key('threshold')
            ->example('Quarantine for review, decrease agent trust-index by 0.1')->key('on-fail');

        $this->guideline('validation-actions')
            ->text('Actions based on validation severity.')
            ->example('PASS: Accept response, increment trust-index by 0.01')->key('pass')
            ->example('FAIL: Any single validation < threshold, max 2 retries')->key('fail-criteria')
            ->example('CRITICAL: 3+ consecutive fails OR policy violation → suspend agent')->key('critical-criteria');
    }
}
