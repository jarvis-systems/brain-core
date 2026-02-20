<?php

declare(strict_types=1);

namespace BrainCore\Includes\Agent;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;

#[Purpose(<<<'PURPOSE'
Defines recursive web research protocol for agents using WebSearch and WebFetch tools.
Establishes actionable boundaries for querying, recursion depth, and result aggregation.
PURPOSE
)]
class WebRecursiveResearchInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // Always-on: compact workflow + limits + source priority + rules

        $this->guideline('research-workflow')
            ->text('Research execution flow with mandatory limits.')
            ->example('query (keywords → WebSearch) → evaluate (top 3-5 sources) → fetch (WebFetch selected) → recurse (if gaps, max depth 2) → aggregate (cross-reference, deduplicate) → output (cite all sources)')->key('flow')
            ->example('max-searches=3, max-fetches=5, max-depth=2, max-total-requests=10')->key('limits')
            ->example('Abort recursion if follow-up yields same information as previous round')->key('abort');

        $this->guideline('source-priority')
            ->text('Source selection priority order.')
            ->example('1. Official documentation, GitHub repos, academic sources')->key('high')
            ->example('2. Technical blogs, Stack Overflow, established news sites')->key('medium')
            ->example('3. Forums, personal blogs, aggregator sites')->key('low')
            ->example('SKIP: SEO-spam sites, paywalled content, social media posts')->key('avoid');

        // Rules (always-on)
        $this->rule('recursion-limit')->high()
            ->text('Never exceed max-depth=2 or max-total-requests=10.')
            ->why('Prevents resource exhaustion and infinite loops.')
            ->onViolation('Stop immediately, summarize partial results, mark as incomplete.');

        $this->rule('source-citation')->high()
            ->text('Every factual claim must have a source URL.')
            ->why('Enables verification and maintains research integrity.')
            ->onViolation('Remove uncited claims or mark as unverified.');

        $this->rule('no-speculation')->high()
            ->text('Report only information found in sources. Never invent or assume.')
            ->why('Research must be factual and verifiable.')
            ->onViolation('Remove speculative content from output.');

        // Deep-only: detailed phase specifications
        if ($this->isDeepCognitive()) {
            $this->guideline('phase-query')
                ->text('Goal: Formulate and execute initial web search.')
                ->example('Task requires external information not in vector memory')->key('trigger')
                ->example()
                    ->phase('step-1', 'Extract 2-4 keywords from task context')
                    ->phase('step-2', 'Execute WebSearch(query) with extracted keywords')
                    ->phase('step-3', 'Store search results for evaluation')
                    ->phase('validation', 'Query must be specific enough to return relevant results');

            $this->guideline('limits-query')
                ->text('Query phase limits.')
                ->example('max-searches = 3 WebSearch calls per research task')
                ->example('max-keywords = 6 per single query');

            $this->guideline('phase-evaluation')
                ->text('Goal: Select best sources from search results.')
                ->example('After WebSearch returns results')->key('trigger')
                ->example()
                    ->phase('step-1', 'Review titles and snippets for relevance to task')
                    ->phase('step-2', 'Discard duplicates and obviously irrelevant results')
                    ->phase('step-3', 'Select top 3-5 URLs for deep fetch')
                    ->phase('validation', 'Selected sources must directly address the query');

            $this->guideline('phase-fetch')
                ->text('Goal: Extract detailed content from selected sources.')
                ->example('After source selection completed')->key('trigger')
                ->example()
                    ->phase('step-1', 'Execute WebFetch(url, prompt) for each selected source')
                    ->phase('step-2', 'Extract specific facts, code examples, or data points')
                    ->phase('step-3', 'Note source URL for citation')
                    ->phase('validation', 'Fetched content must contain actionable information');

            $this->guideline('limits-fetch')
                ->text('Fetch phase limits.')
                ->example('max-fetches = 5 WebFetch calls per research task')
                ->example('Use focused prompts to extract only relevant sections');

            $this->guideline('phase-recursion')
                ->text('Goal: Follow references when initial results are incomplete.')
                ->example('Fetched content references other sources needed to answer query')->key('trigger')
                ->example()
                    ->phase('step-1', 'Identify specific gaps in collected information')
                    ->phase('step-2', 'Extract new URLs or keywords from current results')
                    ->phase('step-3', 'Execute additional WebSearch or WebFetch for missing data')
                    ->phase('validation', 'Recurse only if existing data cannot answer the query');

            $this->guideline('limits-recursion')
                ->text('Recursion safety limits.')
                ->example('max-depth = 2 (initial search + 2 follow-up rounds)')
                ->example('max-total-requests = 10 (WebSearch + WebFetch combined)')
                ->example('Abort if follow-up yields same information as previous round')->key('abort');

            $this->guideline('phase-aggregation')
                ->text('Goal: Merge collected information into coherent answer.')
                ->example('After all fetches complete or limits reached')->key('trigger')
                ->example()
                    ->phase('step-1', 'Extract key facts and data points from all sources')
                    ->phase('step-2', 'Remove duplicate information across sources')
                    ->phase('step-3', 'Cross-reference facts - prefer info confirmed by 2+ sources')
                    ->phase('step-4', 'Organize findings by relevance to original query');

            $this->guideline('phase-output')
                ->text('Goal: Format research results with proper citations.')
                ->example('After aggregation complete')->key('trigger')
                ->example()
                    ->phase('step-1', 'Summarize key findings addressing the original query')
                    ->phase('step-2', 'Include Sources section with URLs used')
                    ->phase('step-3', 'Store valuable insights to vector memory for future use')
                    ->phase('validation', 'Output must cite sources for all factual claims');
        }
    }
}
