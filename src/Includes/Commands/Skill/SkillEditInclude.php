<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;

#[Purpose('Interactive skill editor: reads current node/Skills/{name}/SKILL.md, analyzes session signals (git history, recent corrections, related tasks), suggests a unified diff, lets the user accept/edit/abort, then runs the shared triage + direct-write pipeline.')]
class SkillEditInclude extends IncludeArchetype
{
    use SkillProposalSharedTrait;

    protected function handle(): void
    {
        // INTENT IRON RULES
        $this->rule('edit-target-must-exist')->critical()
            ->text('node/Skills/{TARGET_SKILL}/SKILL.md MUST exist before suggesting an edit. If it does not → abort and recommend /skill:new {TARGET_SKILL} instead.')
            ->why('modify-skill on a non-existent target would fail at patch time and leaves no canonical for the direct write to land on.')
            ->onViolation('ABORT. Recommend /skill:new {TARGET_SKILL} for new skills.');

        $this->rule('edit-diff-required')->critical()
            ->text('Edit MUST produce a non-empty unified diff against the current SKILL.md. Empty diffs (no actual change) abort without writing.')
            ->why('A no-op write is noise — pollutes git history and the per-skill history-references/ audit log.')
            ->onViolation('ABORT with message "no changes detected — nothing to write".');

        // ROLE
        $this->guideline('role')
            ->text('Interactive skill editor. Parses one positional argument as the existing skill name, reads the canonical SKILL.md, mines session signals for what changed since authoring, drafts a unified diff, lets the user accept/edit/abort, then hands off to the shared direct-write pipeline as a modify-skill change.');

        // WORKFLOW: PARSE + READ
        $this->guideline('workflow-parse-and-read')
            ->text('Capture skill name, validate existence, read canonical SKILL.md')
            ->example()
            ->phase('capture', Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->phase('parse', Store::as('TARGET_SKILL', '{$ARGUMENTS positional arg 1 — first non-flag token}'))
            ->phase('validate-shape', Operator::validate(
                Store::get('TARGET_SKILL') . ' matches /^[a-z][a-z0-9-]*$/',
                Operator::abort('Skill name must be kebab-case (a-z, 0-9, -)')
            ))
            ->phase('validate-exists', BashTool::call('test -f node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md && echo EXISTS || echo MISSING'))
            ->phase('abort-if-missing', Operator::validate(
                'result == EXISTS',
                Operator::abort('node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md not found — use /skill:new ' . Store::get('TARGET_SKILL') . ' to create it')
            ))
            ->phase('read-current', ReadTool::call('node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md'))
            ->phase('store-current', Store::as('CURRENT_SKILL_MD', '{full file content with YAML frontmatter and body}'));

        // WORKFLOW: ANALYZE SIGNALS + SUGGEST DIFF
        $this->guideline('workflow-analyze-and-suggest')
            ->text('Mine signals for what changed since authoring, then propose a unified diff')
            ->example()
            ->phase('analyze-git-history', BashTool::call('git log --follow -p -n 5 -- node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md'))
            ->phase('store-git-history', Store::as('SKILL_GIT_HISTORY', '{recent commits + diffs touching this SKILL.md}'))
            ->phase('analyze-conversation', 'Identify in the current session any user corrections, "from now on..." statements, or anti-pattern observations that contradict or refine the current SKILL.md')
            ->phase('store-corrections', Store::as('SESSION_CORRECTIONS', 'array of {old_behavior, new_behavior, evidence_ref}'))
            ->phase('analyze-related-tasks', 'Scan recent vector-task changes for tasks tagged with this skill domain or referencing this skill — extract patterns that contradict current text')
            ->phase('store-task-signals', Store::as('TASK_SIGNALS', 'array of {task_id, observation, suggested_change}'))
            ->phase('suggest-diff', 'Synthesize SKILL_GIT_HISTORY + SESSION_CORRECTIONS + TASK_SIGNALS into a unified diff: modify a rule, add guideline, fix terminology, reorder sections. Keep the diff minimal and focused.')
            ->phase('store-suggested-diff', Store::as('SUGGESTED_DIFF', '{unified diff against $CURRENT_SKILL_MD, paths relative to project root}'))
            ->phase('validate-non-empty', Operator::validate(
                Store::get('SUGGESTED_DIFF') . ' contains at least one hunk',
                Operator::abort('No meaningful change detected — nothing to write')
            ))
            ->phase('user-review', 'Display $SUGGESTED_DIFF to user. Ask: "accept / edit / abort?"')
            ->phase('handle-response', Operator::if(
                'user response == edit',
                'Collect user edits, regenerate $SUGGESTED_DIFF, re-show, loop until accept or abort',
                Operator::if(
                    'user response == abort',
                    Operator::do(
                        'Invoke the shared workflow-decline-signal guideline (defined below) to record a vector-memory negative signal',
                        Operator::abort('User aborted diff acceptance — no canonical write performed')
                    ),
                    'Proceed with current $SUGGESTED_DIFF'
                )
            ));

        // WORKFLOW: DERIVE FIELDS
        $this->guideline('workflow-derive-fields')
            ->text('Auto-fill ACTION / RATIONALE / EVIDENCE / CONFIDENCE for shared pipeline')
            ->example()
            ->phase('set-action', Store::as('ACTION', 'modify-skill'))
            ->phase('rationale', Store::as('RATIONALE', '{1 sentence describing what improvement this diff makes, 8-400 chars}'))
            ->phase('evidence', Store::as('EVIDENCE', 'array combining: {kind:"file", ref:"node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md"} + refs from $SESSION_CORRECTIONS / $TASK_SIGNALS / $SKILL_GIT_HISTORY'))
            ->phase('confidence', Store::as('CONFIDENCE', 'between 0.7 and 0.9 based on signal strength: 0.9 when explicit user correction in session, 0.8 when reinforced across 2+ tasks, 0.7 when single observation'))
            ->phase('caveats', Store::as('CAVEATS', '[] unless user added concerns during edit loop'))
            ->phase('replacement', Store::as('REPLACEMENT', '"none" (edit flow never deprecates)'));

        // SHARED DIRECT-WRITE PIPELINE
        $this->appendProposalWorkflow();
        $this->recordDeclineSignal();
    }
}