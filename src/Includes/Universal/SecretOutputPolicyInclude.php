<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose('Enforces secret output prevention policy across all Brain and Agent responses.')]
class SecretOutputPolicyInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        $this->rule('no-secret-output')->critical()
            ->text('NEVER output secrets, API keys, tokens, passwords, or sensitive ENV variable values in responses, logs, or delegated outputs.')
            ->why('Secrets in output leak through conversation logs, vector memory, screen sharing, CI artifacts, and MCP responses. Redaction is the only safe default.')
            ->onViolation('Redact the value immediately. Show only the variable name and status: FOUND or NOT FOUND. Never echo, print, or embed secret values.');
    }
}
