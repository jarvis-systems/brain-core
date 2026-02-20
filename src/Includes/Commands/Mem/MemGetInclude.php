<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Mem;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Retrieves and displays full content of a specific memory by ID.')]
class MemGetInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // Role definition
        $this->guideline('role')
            ->text('Memory retrieval utility that fetches and displays full content of a specific memory by ID.');

        // Workflow Step 1 - Parse Arguments
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse Arguments for Memory ID')
            ->example()
            ->phase('format-1', '/mem:get 15 → get memory by ID')
            ->phase('format-2', '/mem:get id=15 → explicit parameter')
            ->phase('validate', Operator::if(
                Store::get('RAW_INPUT') . ' is empty or not a number',
                Operator::do(
                    'Display: "Error: Memory ID required"',
                    'Display: "Usage: /mem:get {id}"',
                    Operator::skip('No ID provided')
                )
            ));

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('MEMORY_ID', '{numeric ID extracted from $RAW_INPUT}'));

        // Workflow Step 2 - Fetch Memory
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Fetch Memory by ID')
            ->example()
            ->phase('fetch', VectorMemoryMcp::callValidatedJson('get_by_memory_id', ['memory_id' => Store::get('MEMORY_ID')]))
            ->phase('store', Store::as('MEMORY', 'memory object or null'));

        // Workflow Step 3 - Handle Not Found
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Handle Memory Not Found')
            ->example()
            ->phase('check', Operator::if(
                Store::get('MEMORY') . ' is null',
                Operator::do(
                    'Display: "Memory #{id} not found."',
                    'Suggest: "Use /mem:list to see available memories"',
                    'Suggest: "Use /mem:search to find by content"'
                )
            ));

        // Workflow Step 4 - Display Full Content
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Display Full Memory Content')
            ->example()
            ->phase('header', 'Display: "--- Memory #{id} ---"')
            ->phase('meta', Operator::do(
                'Display: "Category: {category}"',
                'Display: "Tags: {tags}"',
                'Display: "Created: {created_at}"',
                'Display: "Updated: {updated_at}"',
                'Display: "Access count: {access_count}"'
            ))
            ->phase('divider', 'Display: "---"')
            ->phase('content', 'Display: "{full_content}"')
            ->phase('divider-end', 'Display: "---"')
            ->phase('actions', Operator::do(
                'Display: "Actions:"',
                'Display: "  /mem:cleanup id={id} → delete this memory"',
                'Display: "  /mem:search \"{first_words}\" → find similar"'
            ));

        // Output Format
        $this->guideline('output-format')
            ->text('Memory display format')
            ->example('--- Memory #15 ---')->key('header')
            ->example('Category: code-solution')->key('category')
            ->example('Tags: php, laravel, auth')->key('tags')
            ->example('Created: 2025-11-20 14:30:00')->key('created')
            ->example('Access count: 5')->key('access')
            ->example('---')->key('divider')
            ->example('{full memory content here}')->key('content')
            ->example('Actions:')->key('actions')
            ->example('  /mem:cleanup id=15 → delete')->key('action-delete')
            ->example('  /mem:search "keywords" → find similar')->key('action-search');

        // Usage Examples
        $this->guideline('usage-examples')
            ->text('Command usage patterns')
            ->example('/mem:get 15 → get memory #15')->key('simple')
            ->example('/mem:get id=15 → explicit parameter')->key('explicit');

        // Error Messages
        $this->guideline('error-messages')
            ->text('Error handling messages')
            ->example('Error: Memory ID required')->key('no-id')
            ->example('Memory #15 not found.')->key('not-found')
            ->example('Use /mem:list to see available memories')->key('suggest-list');
    }
}
