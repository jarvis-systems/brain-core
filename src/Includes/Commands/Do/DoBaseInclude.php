<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Do;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Includes\Commands\SharedCommandTrait;

#[Purpose('Shared baseline rules for all Do commands. Provides tag taxonomy, error handling, failure policy, git protections, and zero-exfiltration safety.')]
class DoBaseInclude extends IncludeArchetype
{
    use SharedCommandTrait;

    protected function handle(): void
    {
        // 1. Tag Taxonomy Rules
        $this->defineTagTaxonomyRules();

        // 2. Safety Rules
        $this->defineSecretsPiiProtectionRules();
        $this->defineNoDestructiveGitRules();

        // 3. Execution Rules
        $this->defineFailurePolicyRules();
        $this->defineAggressiveDocsSearchGuideline();
        $this->defineDocumentationIsLawRules();
    }
}
