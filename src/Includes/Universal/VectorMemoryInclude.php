<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Vector memory iron rules with cookbook delegation.')]
class VectorMemoryInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        $this->rule('mcp-json-only')->critical()
            ->text('ALL memory operations MUST use MCP tool with JSON object payload.')
            ->why('Ensures valid JSON, embedding generation, data integrity.')
            ->onViolation(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '...', 'limit' => 3]));

        $this->rule('search-before-store')->high()
            ->text('ALWAYS search before store.')
            ->why('Prevents memory pollution. Keeps knowledge base clean.')
            ->onViolation(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{insight_summary}', 'limit' => 3]));
    }
}
