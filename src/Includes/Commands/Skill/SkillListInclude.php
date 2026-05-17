<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;

#[Purpose('Skill proposal lister: globs node/Skills/*/pending-proposals/*/proposal.json and node/Skills/.new-proposals/*/proposal.json, parses each, prints a summary table sorted by created_at DESC.')]
class SkillListInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // IRON RULES
        $this->rule('read-only')->critical()
            ->text('This command is read-only. NEVER mutate any file under node/Skills/ during listing.')
            ->why('Listing must be safe to run anytime, including during ongoing review sessions.')
            ->onViolation('ABORT any write attempt and report violation.');

        // INPUT
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('FILTERS', '{optional filters extracted from $RAW_INPUT: action, target, min-confidence}'));

        // ROLE
        $this->guideline('role')
            ->text('Skill proposal enumerator. Aggregates all pending proposals across canonical skill folders and the create-skill staging folder, then renders a sortable summary.');

        // WORKFLOW
        $this->guideline('workflow-step1-discover')
            ->text('STEP 1 - Discover proposal.json files')
            ->example()
            ->phase('glob-modify', BashTool::call('ls -1d node/Skills/*/pending-proposals/*/proposal.json 2>/dev/null || true') . ' ' . Store::as('MODIFY_PROPOSALS'))
            ->phase('glob-create', BashTool::call('ls -1d node/Skills/.new-proposals/*/proposal.json 2>/dev/null || true') . ' ' . Store::as('CREATE_PROPOSALS'))
            ->phase('union', Store::as('ALL_PROPOSALS', 'union of MODIFY_PROPOSALS and CREATE_PROPOSALS'));

        $this->guideline('workflow-step2-parse')
            ->text('STEP 2 - Parse each proposal.json')
            ->example()
            ->phase('foreach', Operator::forEach('proposal_file in ' . Store::get('ALL_PROPOSALS'), [
                ReadTool::call('{proposal_file}'),
                'Extract: id, action, target_skill (or null), confidence, created_at, rationale (truncate to 80 chars)',
                'Push to ' . Store::get('TABLE_ROWS'),
            ]));

        $this->guideline('workflow-step3-filter-sort')
            ->text('STEP 3 - Apply optional filters and sort')
            ->example()
            ->phase('filter', Operator::if(Store::get('FILTERS') . ' not empty', 'Keep only rows matching filters (action, target, confidence >= threshold)'))
            ->phase('sort', 'Sort ' . Store::get('TABLE_ROWS') . ' by created_at DESC')
            ->phase('age', 'For each row: compute age = now - created_at, format as "Nd Nh"');

        $this->guideline('workflow-step4-render')
            ->text('STEP 4 - Render summary table')
            ->example()
            ->phase('empty', Operator::if('count(' . Store::get('TABLE_ROWS') . ') == 0', 'Display: "No pending skill proposals."'))
            ->phase('header', 'Columns: ID | ACTION | TARGET | CONFIDENCE | AGE | RATIONALE')
            ->phase('rows', Operator::forEach('row in ' . Store::get('TABLE_ROWS'), 'Render single line: {id} | {action} | {target or "(new)"} | {confidence} | {age} | {rationale}'))
            ->phase('footer', 'Display: "Total pending: {count}. Run brain skill:review {id} to inspect."');

        // ERROR HANDLING
        $this->guideline('error-handling')
            ->text('Robustness rules for malformed or partial proposal folders')
            ->example()
            ->phase('missing-json', 'If proposal.json missing in a discovered folder, list with state="BROKEN" and skip parsing')
            ->phase('unparseable', 'If JSON unparseable, list with state="INVALID" plus filename for manual cleanup')
            ->phase('stale-folder', 'If folder name does not match id field inside JSON, mark as MISMATCH for reviewer attention');
    }
}