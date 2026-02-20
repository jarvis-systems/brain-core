<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Mem;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Memory cleanup utility for bulk deletion by age/count or specific ID deletion. Requires confirmation for destructive operations.')]
class MemCleanupInclude extends IncludeArchetype
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
            ->text('Memory cleanup utility that handles bulk deletion by age/count or specific ID deletion. All destructive operations require explicit confirmation.');

        // Iron Rules
        $this->rule('mandatory-confirmation')->critical()
            ->text('ALL delete operations MUST require explicit user confirmation')
            ->why('Deletion is permanent and cannot be undone')
            ->onViolation('Show preview, ask for YES/DELETE confirmation, never auto-delete');

        $this->rule('show-preview')->high()
            ->text('MUST show what will be deleted before confirmation')
            ->why('User must understand impact before confirming')
            ->onViolation('Display count, preview content, then ask confirmation');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('CLEANUP_PARAMS', '{cleanup parameters extracted from $RAW_INPUT: days_old, max_to_keep, or memory_id}'));

        // Workflow Step 1 - Parse Arguments
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse $RAW_INPUT for Operation Type')
            ->example()
            ->phase('format-1', '/mem:cleanup → preview default cleanup (30 days, keep 1000)')
            ->phase('format-2', '/mem:cleanup days=60 → cleanup older than 60 days')
            ->phase('format-3', '/mem:cleanup max_to_keep=500 → keep only 500 most recent')
            ->phase('format-4', '/mem:cleanup id=15 → delete specific memory')
            ->phase('format-5', '/mem:cleanup ids=15,16,17 → delete multiple specific')
            ->phase('detect', Store::as('MODE', '{detect mode: bulk|single|multi from ' . Store::get('RAW_INPUT') . '}'))
            ->phase('extract-ids', Store::as('DELETE_IDS', '{extract id/ids from ' . Store::get('RAW_INPUT') . ' if present}'))
            ->phase('extract-params', 'Use ' . Store::get('CLEANUP_PARAMS') . ' for days_old, max_to_keep values');

        // Workflow Step 2 - Single ID Delete
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Handle Single ID Delete')
            ->example()
            ->phase('check', Operator::if(
                Store::get('MODE') . ' === "single"',
                Operator::do(
                    'Use ID from ' . Store::get('DELETE_IDS'),
                    VectorMemoryMcp::callValidatedJson('get_by_memory_id', ['memory_id' => '{id}']),
                    Store::as('TARGET', 'memory to delete'),
                    'Display: "--- Memory to Delete ---"',
                    'Display: "ID: {id}"',
                    'Display: "Category: {category}"',
                    'Display: "Content: {content_preview}"',
                    'Display: "Tags: {tags}"',
                    'Display: ""',
                    'Prompt: "DELETE this memory? This cannot be undone. (yes/no)"'
                )
            ));

        // Workflow Step 3 - Multi ID Delete
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Handle Multiple IDs Delete')
            ->example()
            ->phase('check', Operator::if(
                Store::get('MODE') . ' === "multi"',
                Operator::do(
                    'Use IDs array from ' . Store::get('DELETE_IDS'),
                    'ForEach ID: fetch memory preview',
                    'Display: "--- Memories to Delete ({count}) ---"',
                    'ForEach: "#{id} [{category}] {preview}"',
                    'Display: ""',
                    'Prompt: "DELETE all {count} memories? This cannot be undone. (yes/no)"'
                )
            ));

        // Workflow Step 4 - Bulk Cleanup Preview
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Bulk Cleanup Preview')
            ->example()
            ->phase('check', Operator::if(
                Store::get('MODE') . ' === "bulk"',
                Operator::do(
                    'Parse: days_old (default 30), max_to_keep (default 1000)',
                    VectorMemoryMcp::callValidatedJson('get_memory_stats', []),
                    'Calculate: how many would be deleted',
                    'Display: "--- Cleanup Preview ---"',
                    'Display: "Current total: {total} memories"',
                    'Display: "Settings: days_old={days}, max_to_keep={max}"',
                    'Display: "Would delete: ~{estimate} memories"',
                    'Display: "Would keep: ~{remaining} memories"',
                    'Display: ""',
                    'Prompt: "Proceed with cleanup? (yes/no)"'
                )
            ));

        // Workflow Step 5 - Execute Delete
        $this->guideline('workflow-step5')
            ->text('STEP 5 - Execute After Confirmation')
            ->example()
            ->phase('validate', Operator::validate(
                'User response is YES, DELETE, or CONFIRM',
                'Abort: "Cleanup cancelled."'
            ))
            ->phase('execute-single', Operator::if(
                Store::get('MODE') . ' === "single"',
                Operator::do(
                    VectorMemoryMcp::callValidatedJson('delete_by_memory_id', ['memory_id' => '{id}']),
                    'Display: "Memory #{id} deleted successfully."'
                )
            ))
            ->phase('execute-multi', Operator::if(
                Store::get('MODE') . ' === "multi"',
                Operator::do(
                    'ForEach ID: ' . VectorMemoryMcp::callValidatedJson('delete_by_memory_id', ['memory_id' => '{id}']),
                    'Display: "Deleted {count} memories successfully."'
                )
            ))
            ->phase('execute-bulk', Operator::if(
                Store::get('MODE') . ' === "bulk"',
                Operator::do(
                    VectorMemoryMcp::callValidatedJson('clear_old_memories', ['days_old' => '{days}', 'max_to_keep' => '{max}']),
                    'Display: "Cleanup completed. Removed {count} old memories."'
                )
            ));

        // Output Format
        $this->guideline('output-format')
            ->text('Cleanup display format')
            ->example('--- Cleanup Preview ---')->key('header-bulk')
            ->example('--- Memory to Delete ---')->key('header-single')
            ->example('Current total: 37 memories')->key('total')
            ->example('Would delete: ~12 memories')->key('estimate')
            ->example('DELETE this memory? This cannot be undone. (yes/no)')->key('confirm-single')
            ->example('Proceed with cleanup? (yes/no)')->key('confirm-bulk')
            ->example('Cleanup cancelled.')->key('cancelled')
            ->example('Memory #15 deleted successfully.')->key('success-single')
            ->example('Cleanup completed. Removed 12 old memories.')->key('success-bulk');

        // Usage Examples
        $this->guideline('usage-examples')
            ->text('Command usage patterns')
            ->example('/mem:cleanup → preview default cleanup')->key('preview')
            ->example('/mem:cleanup days=60 → older than 60 days')->key('days')
            ->example('/mem:cleanup max_to_keep=500 → limit to 500')->key('limit')
            ->example('/mem:cleanup id=15 → delete specific memory')->key('single')
            ->example('/mem:cleanup ids=15,16,17 → delete multiple')->key('multi');
    }
}
