<?php

declare(strict_types=1);

namespace BrainCore\Includes\Agent;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;

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
            ->onViolation('STOP. Run ' . BashTool::call('brain docs {keywords}') . ' and align with documentation.');

        $this->rule('docs-before-action')->critical()
            ->text('Before ANY implementation, coding, or architectural decision - check .docs first.')
            ->why('Prevents drift from documented architecture and specifications.')
            ->onViolation('Abort action. Search documentation via brain docs before proceeding.');

        $this->rule('docs-before-web-research')->high()
            ->text('Before external web research - verify topic is not already documented in .docs.')
            ->why('Avoids redundant research and ensures internal knowledge takes precedence.')
            ->onViolation('Check ' . BashTool::call('brain docs {topic}') . ' first. Web research only if .docs has no coverage.');

        $this->guideline('docs-discovery-workflow')
            ->text('Standard workflow for documentation discovery.')
            ->example()
                ->phase('step-1', BashTool::call('brain docs {keywords}') . ' ' . Store::as('DOCS', 'discover existing docs'))
                ->phase('step-2', Operator::if('docs found', 'Read and apply documented patterns'))
                ->phase('step-3', Operator::if('no docs', 'proceed with caution, flag for documentation'));

        $this->guideline('docs-conflict-resolution')
            ->text('When external sources conflict with .docs.')
            ->example('.docs wins over Stack Overflow, GitHub issues, blog posts')->key('priority')
            ->example('If .docs appears outdated, flag for update but still follow it')->key('outdated')
            ->example('Never silently override documented decisions')->key('override');
    }
}
