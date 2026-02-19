<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose('brain docs CLI protocol — self-documenting tool for .docs/ indexing and search. Iron rules for documentation quality.')]
class BrainDocsInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        $this->guideline('brain-docs-tool')
            ->text('brain docs — PRIMARY tool for .docs/ project documentation discovery and search. Self-documenting: brain docs --help for usage, -v for examples, -vv for best practices. Key capabilities: --download=<url> persists external docs locally (lossless, zero tokens vs vector memory summaries), --undocumented finds code without docs. Always use brain docs BEFORE creating documentation, web research, or making assumptions about project.');

        $this->rule('no-manual-indexing')->critical()
            ->text('NEVER create index.md or README.md for documentation indexing. brain docs handles all indexing automatically.')
            ->why('Manual indexing creates maintenance burden and becomes stale.')
            ->onViolation('Remove manual index files. Use brain docs exclusively.');

        $this->rule('markdown-only')->critical()
            ->text('ALL documentation MUST be markdown format with *.md extension. No other formats allowed.')
            ->why('Consistency, parseability, brain docs indexing requires markdown format.')
            ->onViolation('Convert non-markdown files to *.md or reject them from documentation.');

        $this->rule('documentation-not-codebase')->critical()
            ->text('Documentation is DESCRIPTION for humans, NOT codebase. Minimize code to absolute minimum.')
            ->why('Documentation must be human-readable. Code makes docs hard to understand and wastes tokens.')
            ->onViolation('Remove excessive code. Replace with clear textual description.');

        $this->rule('code-only-when-cheaper')->high()
            ->text('Include code ONLY when it is cheaper in tokens than text explanation AND no other choice exists.')
            ->why('Code is expensive, hard to read, not primary documentation format. Text first, code last resort.')
            ->onViolation('Replace code examples with concise textual description unless code is genuinely more efficient.');
    }
}
