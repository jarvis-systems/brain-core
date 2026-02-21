<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Mem;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Semantic memory search with flexible filters. Displays results with similarity scores and content previews.')]
class MemSearchInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('SEARCH_QUERY', '{search query extracted from $RAW_INPUT}'));

        // Role definition
        $this->guideline('role')
            ->text('Semantic memory search utility that queries vector storage with optional filters and displays formatted results with similarity scores.');

        // Workflow Step 1 - Parse Arguments
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse Arguments for Query and Filters')
            ->example()
            ->phase('format-1', 'Simple query: /mem:search "authentication patterns"')
            ->phase('format-2', 'With filters: /mem:search query="auth" category=code-solution limit=20')
            ->phase('format-3', 'With tags: /mem:search query="api" tags=laravel,php')
            ->phase('extract', Store::as('QUERY', '{parse query from $RAW_INPUT, required}'))
            ->phase('filters', Store::as('FILTERS', '{parse category?, limit?, offset?, tags? from $RAW_INPUT}'))
            ->phase('defaults', 'Defaults: limit=10, offset=0')
            ->phase('output', Store::as('PARAMS', '{query: $QUERY, ...$FILTERS}'));

        // Workflow Step 2 - Execute Search
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Execute Semantic Search')
            ->example()
            // @mcp-schema-bypass: Store::get() returns runtime placeholder string ($VAR),
            // not array — cannot be validated at compile time. Migrate to callValidatedJson()
            // when Store supports structured array params.
            ->phase('search', VectorMemoryMcp::call('search_memories', Store::get('PARAMS')))
            ->phase('store', Store::as('RESULTS', 'search results array'));

        // Workflow Step 3 - Handle No Results
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Handle Empty Results')
            ->example()
            ->phase('check', Operator::if(
                Store::get('RESULTS') . ' is empty',
                Operator::do(
                    'Display: "No memories found for: {query}"',
                    'Suggest: "Try broader search terms"',
                    'Suggest: "Remove category/tag filters"',
                    'Suggest: "Use /mem:list to see recent memories"'
                )
            ));

        // Workflow Step 4 - Format and Display
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Format and Display Results')
            ->example()
            ->phase('header', 'Display: "--- Memory Search Results ---"')
            ->phase('meta', 'Display: "Query: {query} | Found: {count} | Category: {category or all}"')
            ->phase('list', Operator::forEach(
                'memory in ' . Store::get('RESULTS'),
                Operator::do(
                    'Display: "#{id} [{category}] (similarity: {score})"',
                    'Display: "  {content_preview} (first 100 chars)"',
                    'Display: "  Tags: {tags} | Accessed: {access_count}x"'
                )
            ))
            ->phase('pagination', Operator::if(
                'more results available (total > limit + offset)',
                'Display: "More results available. Use offset={next_offset} to see more"'
            ));

        // Output Format
        $this->guideline('output-format')
            ->text('Result display format')
            ->example('--- Memory Search Results ---')->key('header')
            ->example('Query: "auth patterns" | Found: 5 | Category: code-solution')->key('meta')
            ->example('#{id} [{category}] (similarity: 0.85)')->key('item-header')
            ->example('  Content preview here...')->key('item-content')
            ->example('  Tags: php, laravel | Accessed: 3x')->key('item-meta')
            ->example('More results available. Use offset=10 to see more')->key('pagination');

        // Similarity Score Interpretation
        $this->guideline('similarity-guide')
            ->text('Similarity score interpretation')
            ->example('0.90-1.00: Highly relevant, almost exact match')->key('high')
            ->example('0.75-0.89: Relevant, good semantic match')->key('medium')
            ->example('0.50-0.74: Somewhat related, partial match')->key('low')
            ->example('< 0.50: Weak match, may not be useful')->key('weak');

        // Filter Examples
        $this->guideline('filter-examples')
            ->text('Supported filter combinations')
            ->example('/mem:search "query" → simple search')->key('simple')
            ->example('/mem:search query="auth" category=bug-fix → filtered by category')->key('category')
            ->example('/mem:search query="api" tags=laravel → filtered by tag')->key('tags')
            ->example('/mem:search query="cache" limit=20 → more results')->key('limit')
            ->example('/mem:search query="db" offset=10 limit=10 → pagination')->key('pagination');
    }
}
