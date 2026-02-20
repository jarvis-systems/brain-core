<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Mem;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Displays memory statistics and health information with optional category/tag filtering.')]
class MemStatsInclude extends IncludeArchetype
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
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'));

        // Role definition
        $this->guideline('role')
            ->text('Memory statistics utility that displays storage health, category breakdown, and usage metrics.');

        // Workflow Step 1 - Parse Arguments
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse Arguments for Filters')
            ->example()
            ->phase('parse', 'Parse ' . Store::get('RAW_INPUT') . ' for filter parameters')
            ->phase('format-1', '/mem:stats → full statistics overview')
            ->phase('format-2', '/mem:stats category=code-solution → category-specific stats')
            ->phase('format-3', '/mem:stats tags=php → tag-specific stats')
            ->phase('format-4', '/mem:stats top=10 → top accessed memories')
            ->phase('output', Store::as('FILTER', '{parse filter type and value from ' . Store::get('RAW_INPUT') . ': default|category|tags|top}'));

        // Workflow Step 2 - Fetch Statistics
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Fetch Memory Statistics')
            ->example()
            ->phase('fetch', VectorMemoryMcp::callValidatedJson('get_memory_stats', []))
            ->phase('store', Store::as('STATS', 'statistics object'));

        // Workflow Step 3 - Default Overview
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Display Default Overview (when no filter)')
            ->example()
            ->phase('check', Operator::if(
                Store::get('FILTER') . '.type === "default"',
                Operator::do(
                    'Display: "--- Memory Statistics ---"',
                    'Display: "Total: {total} memories"',
                    'Display: "Limit: {memory_limit} | Usage: {usage_percentage}%"',
                    'Display: "Database: {database_size_mb} MB"',
                    'Display: ""',
                    'Display: "Categories:"',
                    'ForEach category: "  {name}: {count}"',
                    'Display: ""',
                    'Display: "Health: {health_status}"',
                    'Display: "Recent week: {recent_week_count} new"'
                )
            ));

        // Workflow Step 4 - Category Filter
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Category-Specific Stats')
            ->example()
            ->phase('check', Operator::if(
                Store::get('FILTER') . '.type === "category"',
                Operator::do(
                    VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '*', 'category' => '{category}', 'limit' => 50]),
                    'Calculate: count, avg access, date range',
                    'Display: "--- Category: {category} ---"',
                    'Display: "Count: {count} memories"',
                    'Display: "Avg access: {avg_access}x"',
                    'Display: "Date range: {oldest} to {newest}"'
                )
            ));

        // Workflow Step 5 - Tags Stats
        $this->guideline('workflow-step5')
            ->text('STEP 5 - Tag-Specific Stats')
            ->example()
            ->phase('check', Operator::if(
                Store::get('FILTER') . '.type === "tags"',
                Operator::do(
                    VectorMemoryMcp::callValidatedJson('get_unique_tags', []),
                    'Display: "--- All Tags ---"',
                    'ForEach tag: "{tag}: {count} memories"',
                    'Sort by count descending'
                )
            ));

        // Workflow Step 6 - Top Accessed
        $this->guideline('workflow-step6')
            ->text('STEP 6 - Top Accessed Memories')
            ->example()
            ->phase('check', Operator::if(
                Store::get('FILTER') . '.type === "top"',
                Operator::do(
                    'Extract top_accessed from ' . Store::get('STATS'),
                    'Display: "--- Top Accessed Memories ---"',
                    'ForEach memory: "#{id} ({access_count}x) {preview}"'
                )
            ));

        // Output Format
        $this->guideline('output-format')
            ->text('Statistics display format')
            ->example('--- Memory Statistics ---')->key('header')
            ->example('Total: 37 memories')->key('total')
            ->example('Limit: 2000000 | Usage: 0%')->key('usage')
            ->example('Database: 1.65 MB')->key('db-size')
            ->example('code-solution: 16')->key('category-item')
            ->example('Health: Healthy')->key('health')
            ->example('Recent week: 5 new')->key('recent');

        // Usage Examples
        $this->guideline('usage-examples')
            ->text('Command usage patterns')
            ->example('/mem:stats → full overview')->key('default')
            ->example('/mem:stats category=bug-fix → category breakdown')->key('category')
            ->example('/mem:stats tags → all tags with counts')->key('tags')
            ->example('/mem:stats top=5 → top 5 accessed')->key('top');
    }
}
