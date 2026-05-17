<?php

declare(strict_types=1);

namespace BrainCore\Variations\Traits;

use BrainCore\Includes\Brain\ErrorHandlingInclude;
use BrainCore\Includes\Brain\CoreInclude;
use BrainCore\Includes\Brain\CoreConstraintsInclude;
use BrainCore\Includes\Brain\ResponseValidationInclude;
use BrainCore\Includes\Brain\DelegationProtocolsInclude;
use BrainCore\Includes\Brain\PreActionValidationInclude;
use BrainCore\Includes\Brain\SkillProposalPolicyInclude;
use BrainCore\Includes\Universal\BrainDocsInclude;
use BrainCore\Includes\Universal\CompileSafetyInclude;
use BrainCore\Includes\Universal\SecretOutputPolicyInclude;
use BrainCore\Includes\Universal\VectorMemoryInclude;
use BrainCore\Includes\Universal\VectorTaskInclude;

trait BrainIncludesTrait
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // === UNIVERSAL (Brain runtime essentials) ===
        $this->include(CoreConstraintsInclude::class);                  // Simplified constraints for Brain orchestration
        $this->include(CompileSafetyInclude::class);                     // Compile discipline and worktree hygiene
        $this->include(SecretOutputPolicyInclude::class);               // Secret output prevention policy
        $this->include(VectorMemoryInclude::class);                     // Vector memory primary knowledge base
        $this->include(VectorTaskInclude::class);                       // Vector task management and tracking
        $this->include(BrainDocsInclude::class);                        // Documentation indexing and search command
        //$this->include(BrainScriptsInclude::class);                     // Brain scripts automation command

        // === BRAIN ORCHESTRATION (Brain-specific) ===
        $this->include(CoreInclude::class);                             // Foundation + meta
        $this->include(PreActionValidationInclude::class);              // Pre-action safety gate
        if ($this->var('AGENT') === 'claude' || $this->var('AGENT') === 'opencode') {
            $this->include(DelegationProtocolsInclude::class);          // Delegation protocols
        }
        $this->include(ResponseValidationInclude::class);               // Agent response validation
        $this->include(SkillProposalPolicyInclude::class);              // Skill proposal flow policy (diff-review gate)
        $this->include(ErrorHandlingInclude::class);                    // Basic error handling
        if (class_exists('BrainNode\\Common')) {
            $this->include('BrainNode\\Common');                        // Common node utilities
        }

        // Quality gates - commands that MUST pass for validation
        $qualityCommands = $this->groupVars('QUALITY_COMMAND');

        if (!empty($qualityCommands)) {
            $this->rule('quality-gates-mandatory')->critical()
                ->text('ALL quality commands below MUST be executed and PASS. Any failure = create fix-task. Cannot mark validated until ALL pass.');

            foreach ($qualityCommands as $key => $cmd) {
                $this->rule('quality-' . $key)
                    ->critical()
                    ->text("QUALITY GATE [{$key}]: {$cmd}");
            }
        }
    }

}
