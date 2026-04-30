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
 * - RUN_MODE_FLAGS: automation controls parsed from $RAW_INPUT
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
    protected function defineInputCaptureWithCustomGuideline(
        array $customVars = [],
        ?string $cleanArgsDescription = null,
    ): void
    {
        $guideline = $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('RUN_MODE_FLAGS', '{dry_run: -n|--dry-run, json: -j|--json, checkpoint: -k=MODE|--checkpoint=MODE (auto|off|stage|commit), offline: -o|--offline, timeout_seconds: -t=SEC|--timeout=SEC, max_agents: -m=N|--max-agents=N, sequential: -s|--sequential, fail_fast: -F|--fail-fast, resume: -R|--resume, restart: --restart, full_suite: -S|--full-suite, audit_only: -a|--audit-only}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with '.$this->cleanArgsFlagList($cleanArgsDescription).' flags removed}'));

        foreach ($customVars as $name => $description) {
            $guideline->text(Store::as($name, $description));
        }

        $this->defineAutomationFlagContract();
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

    private function cleanArgsFlagList(?string $extraFlags = null): string
    {
        $flags = '-y/--yes/-n/--dry-run/-j/--json/-k/--checkpoint/-o/--offline/-t/--timeout/-m/--max-agents/-s/--sequential/-F/--fail-fast/-R/--resume/--restart/-S/--full-suite/-a/--audit-only';

        return $extraFlags ? $flags.'/'.$extraFlags : $flags;
    }

    private function defineAutomationFlagContract(): void
    {
        $this->rule('automation-flags-safety')->critical()
            ->text('Automation flags constrain execution; they NEVER bypass source-of-truth reads, lifecycle status checks, security rules, validation requirements, parent-readonly, or finalization safety nets. Forbidden flags by design: --force, --skip-tests, --skip-validation, --skip-docs, --run-all.')
            ->why('Flags are for unattended control, not for weakening quality gates or task lifecycle semantics.')
            ->onViolation('Ignore unsafe bypass intent. Continue with safe defaults or abort with blocker.');

        $this->guideline('automation-flags')
            ->text('Supported flags: -n/--dry-run (no writes), -j/--json (machine output), -k/--checkpoint=auto|off|stage|commit, -o/--offline (no web/context7/network), -t/--timeout=SEC, -m/--max-agents=N, -s/--sequential, -F/--fail-fast, -R/--resume, --restart, -S/--full-suite (validation only), -a/--audit-only (validation only).')
            ->example()
            ->phase('dry_run=true → no Edit/Write/task_update/task_create/store_memory/git mutating commands; report planned actions only')
            ->phase('json=true → preserve normal human/STATUS output and add RESULT_JSON with stable keys')
            ->phase('checkpoint=off|stage|commit → override git checkpoint only; never stage unrelated files')
            ->phase('offline=true → skip WebSearch/WebFetch/Context7/network; if required info is unavailable, return pending/blocker')
            ->phase('timeout/max_agents/sequential/fail_fast → constrain execution/delegation; lower-risk constraint wins on conflict')
            ->phase('resume/restart → applies only to recoverable in_progress workflows; --restart has no short alias by design')
            ->phase('full_suite/audit_only → validators only; non-validation commands ignore with warning');
    }
}
