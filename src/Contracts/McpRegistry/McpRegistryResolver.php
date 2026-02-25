<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpRegistry;

interface McpRegistryResolver
{
    public function resolve(): ResolvedRegistry;
}
