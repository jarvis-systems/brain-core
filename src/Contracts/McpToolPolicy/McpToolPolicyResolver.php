<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpToolPolicy;

interface McpToolPolicyResolver
{
    public function resolve(): ResolvedPolicy;

    public function isAllowed(string $command): bool;

    public function isNever(string $command): bool;

    public function isEnabled(): bool;
}
