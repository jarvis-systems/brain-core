<?php

declare(strict_types=1);

namespace BrainCore\Includes\Agent;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;

#[Purpose(<<<'PURPOSE'
Documentation-first execution policy: .docs folder is the canonical source of truth.
All agent actions (coding, research, decisions) must align with project documentation.
PURPOSE
)]
class DocumentationFirstInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        $this->rule('docs-is-canonical-source')->critical()
            ->text('.docs folder is the ONLY canonical source of truth. Documentation overrides external sources, assumptions, and prior knowledge.')
            ->why('Ensures consistency between design intent and implementation across all agents.')
            ->onViolation('STOP. Run ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '{keywords}']) . ' and align with documentation.');

        $this->rule('docs-before-action')->critical()
            ->text('Before ANY implementation, coding, architectural decision, analysis, or conclusion about project - check .docs first.')
            ->why('Prevents drift from documented architecture and specifications.')
            ->onViolation('Abort action. Search documentation via ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '...']) . ' before proceeding.');

        $this->rule('docs-before-web-research')->high()
            ->text('Before external web research - verify topic is not already documented in .docs.')
            ->why('Avoids redundant research and ensures internal knowledge takes precedence.')
            ->onViolation('Check ' . BrainCLI::MCP__DOCS_SEARCH(['keywords' => '{topic}']) . ' first. Web research only if .docs has no coverage.');

        $this->guideline('docs-conflict-resolution')
            ->text('When external sources conflict with .docs.')
            ->example('.docs wins over Stack Overflow, GitHub issues, blog posts')->key('priority')
            ->example('If .docs appears outdated, flag for update but still follow it')->key('outdated')
            ->example('Never silently override documented decisions')->key('override');
    }
}
