<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;

#[Purpose('docs_search MCP tool protocol — PRIMARY tool for .docs/ indexing and search. Iron rules for documentation quality.')]
class BrainDocsInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        $this->guideline('brain-docs-tool')
            ->text('Use docs_search MCP for .docs/ project documentation discovery before project-related reasoning, recommendations, or implementation: ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '...']) . '. Load detailed documentation rules through the docs-truth-sync skill when editing docs.');

        $this->rule('no-manual-indexing')->critical()
            ->text('NEVER create index.md or README.md for documentation indexing. docs_search MCP tool handles all indexing automatically.')
            ->why('Manual indexing creates maintenance burden and becomes stale.')
            ->onViolation('Remove manual index files. Use ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '...']) . ' exclusively.');
    }
}
