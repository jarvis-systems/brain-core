<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands;

use BrainCore\Compilation\Store;

/**
 * Base input capture pattern — single source of truth.
 * All command input capture methods MUST call defineInputCaptureWithCustomGuideline().
 *
 * Base variables (always included):
 * - RAW_INPUT: raw $ARGUMENTS
 * - HAS_AUTO_APPROVE: true if -y/--yes flag present
 * - CLEAN_ARGS: $RAW_INPUT with flags removed
 *
 * Used by: TaskCommandCommonTrait, DoCommandCommonTrait
 */
trait InputCaptureTrait
{
    /**
     * Define input capture guideline with custom variables.
     * Base: RAW_INPUT, HAS_AUTO_APPROVE, CLEAN_ARGS (always included).
     * Custom: caller-defined variables from $customVars array.
     *
     * @param  array<string, string>  $customVars  Variable name => description pairs
     */
    protected function defineInputCaptureWithCustomGuideline(array $customVars = []): void
    {
        $guideline = $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with -y/--yes flags removed}'));

        foreach ($customVars as $name => $description) {
            $guideline->text(Store::as($name, $description));
        }
    }

    /**
     * Define input capture guideline for description-based commands.
     * Base + TASK_DESCRIPTION extracted from CLEAN_ARGS.
     * Used by: TaskCreateInclude, DoAsyncInclude, DoSyncInclude
     */
    protected function defineInputCaptureWithDescriptionGuideline(): void
    {
        $this->defineInputCaptureWithCustomGuideline([
            'TASK_DESCRIPTION' => '{task description from $CLEAN_ARGS}',
        ]);
    }
}
