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
            ->text('Validate agent response addresses the delegated task.')
            ->example('Does the response answer the actual question asked?')->key('check-1')
            ->example('Is the response structurally complete (expected fields, valid syntax)?')->key('check-2')
            ->example('Does it comply with active policy rules?')->key('check-3')
            ->example('PASS: accept. FAIL: request clarification, max 2 retries, then reject.')->key('action');
    }
}
