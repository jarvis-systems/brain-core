<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Mem;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Lists recent memories in chronological order with content previews and metadata.')]
class MemListInclude extends IncludeArchetype
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
            ->text(Store::as('LIST_LIMIT', '{numeric limit extracted from $RAW_INPUT, default 10}'));

        // Role definition
        $this->guideline('role')
            ->text('Simple memory listing utility that displays recent memories chronologically with previews and metadata.');

        // Workflow Step 1 - Parse Arguments
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse Arguments for Limit')
            ->example()
            ->phase('format', '/mem:list OR /mem:list limit=20')
            ->phase('extract', Store::as('LIMIT', '{parse limit from $RAW_INPUT, default 10, max 50}'))
            ->phase('validate', Operator::if(
                '$LIMIT > 50',
                'Set $LIMIT = 50 (max allowed)'
            ));

        // Workflow Step 2 - Fetch Recent Memories
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Fetch Recent Memories')
            ->example()
            ->phase('fetch', VectorMemoryMcp::callValidatedJson('list_recent_memories', ['limit' => Store::get('LIMIT')]))
            ->phase('store', Store::as('MEMORIES', 'recent memories array'));

        // Workflow Step 3 - Handle Empty
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Handle No Memories')
            ->example()
            ->phase('check', Operator::if(
                Store::get('MEMORIES') . ' is empty',
                Operator::do(
                    'Display: "No memories stored yet."',
                    'Suggest: "Use /mem:store to add your first memory"'
                )
            ));

        // Workflow Step 4 - Display List
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Format and Display')
            ->example()
            ->phase('header', 'Display: "--- Recent Memories ({count}) ---"')
            ->phase('list', Operator::forEach(
                'memory in ' . Store::get('MEMORIES'),
                Operator::do(
                    'Display: "#{id} [{category}] {created_at}"',
                    'Display: "  {content_preview} (first 80 chars)..."',
                    'Display: "  Tags: {tags}"'
                )
            ))
            ->phase('footer', 'Display: "Use /mem:get {id} to view full content"');

        // Output Format
        $this->guideline('output-format')
            ->text('Display format for memory list')
            ->example('--- Recent Memories (10) ---')->key('header')
            ->example('#{id} [{category}] 2025-11-22')->key('item-line')
            ->example('  Content preview here...')->key('item-preview')
            ->example('  Tags: php, laravel, auth')->key('item-tags')
            ->example('Use /mem:get {id} to view full content')->key('footer');

        // Usage Examples
        $this->guideline('usage-examples')
            ->text('Command usage patterns')
            ->example('/mem:list → last 10 memories')->key('default')
            ->example('/mem:list limit=20 → last 20 memories')->key('custom')
            ->example('/mem:list limit=50 → maximum allowed')->key('max');
    }
}
