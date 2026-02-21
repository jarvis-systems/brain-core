<?php

declare(strict_types=1);

namespace BrainCore\Variations\Traits;

use BrainCore\Includes\Agent\CoreInclude;
use BrainCore\Includes\Agent\DocumentationFirstInclude;
use BrainCore\Includes\Universal\BrainDocsInclude;
use BrainCore\Includes\Universal\LaravelBoostClassToolsInclude;
use BrainCore\Includes\Universal\LaravelBoostGuidelinesInclude;
use BrainCore\Includes\Universal\SecretOutputPolicyInclude;
use BrainCore\Includes\Universal\SequentialReasoningInclude;
use BrainCore\Includes\Universal\VectorMemoryInclude;
use BrainCore\Includes\Universal\VectorTaskInclude;

trait AgentIncludesTrait
{
    /**
     * Whether to include the Vector task usage module.
     *
     * @var bool
     */
    protected bool $taskUsage = true;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // === UNIVERSAL ===
        $this->include(SecretOutputPolicyInclude::class);           // Secret output prevention policy
        $this->include(VectorMemoryInclude::class);                 // Vector memory primary knowledge base
        if ($this->taskUsage) {
            $this->include(VectorTaskInclude::class);               // Vector task management and tracking
        }
        $this->include(BrainDocsInclude::class);                    // Documentation indexing and search command
        $this->include(SequentialReasoningInclude::class);          // Sequential reasoning capability

        // === AGENT CORE ===
        $this->include(CoreInclude::class);                         // Core identity and purpose
        $this->include(DocumentationFirstInclude::class);           // Documentation-first execution policy

        if ($this->var('HAS_LARAVEL')) {
            $this->include(LaravelBoostGuidelinesInclude::class);   // Laravel boosting guidelines
            $this->include(LaravelBoostClassToolsInclude::class);   // Laravel boosting class tools
        }

        if (class_exists('BrainNode\\Common')) {
            $this->include('BrainNode\\Common');                    // Common node utilities
        }

        if (class_exists('BrainNode\\Master')) {
            $this->include('BrainNode\\Master');                    // Node for agent-specific logic
        }
    }
}
