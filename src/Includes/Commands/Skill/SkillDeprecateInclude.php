<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;

#[Purpose('Interactive skill deprecator: takes a kebab-case skill name, asks for reason + optional replacement, builds a frontmatter patch adding deprecated: true (and replacement when given), then runs the shared triage + direct-write pipeline.')]
class SkillDeprecateInclude extends IncludeArchetype
{
    use SkillProposalSharedTrait;

    protected function handle(): void
    {
        // INTENT IRON RULES
        $this->rule('deprecate-target-must-exist')->critical()
            ->text('node/Skills/{TARGET_SKILL}/SKILL.md MUST exist before deprecation. If not → abort.')
            ->why('Deprecating a non-existent skill is a no-op patch that nothing can land against.')
            ->onViolation('ABORT with message "skill not found — nothing to deprecate".');

        $this->rule('deprecate-reason-required')->critical()
            ->text('Deprecation rationale MUST be a single sentence explaining WHY the skill is no longer load-bearing (replaced / wrong / merged / scope-changed). 8-400 chars.')
            ->why('Deprecation without reason becomes archaeological mystery for future maintainers and blocks intelligent replacement suggestions.')
            ->onViolation('Re-prompt user for reason. Abort if user refuses to provide one.');

        // ROLE
        $this->guideline('role')
            ->text('Interactive skill deprecator. Parses one positional argument as the existing skill name, asks the user for a reason and optional replacement, builds a unified diff that adds deprecated: true and (optionally) replacement: {id} to the YAML frontmatter, then hands off to the shared direct-write pipeline as a deprecate-skill change.');

        // WORKFLOW: PARSE + PROMPT
        $this->guideline('workflow-parse-and-prompt')
            ->text('Capture skill name, validate existence, prompt for reason and optional replacement')
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
                Operator::abort('node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md not found — nothing to deprecate')
            ))
            ->phase('ask-reason', 'Prompt user: "Why deprecate ' . Store::get('TARGET_SKILL') . '? (1 sentence — e.g. \"superseded by X\", \"merged into Y\", \"scope no longer applies\")"')
            ->phase('store-reason', Store::as('REASON', '{user input — 1 sentence, 8-400 chars}'))
            ->phase('validate-reason', Operator::validate(
                'length(' . Store::get('REASON') . ') between 8 and 400',
                Operator::abort('Reason must be 8-400 chars (1 sentence). Try again with /skill:deprecate ' . Store::get('TARGET_SKILL'))
            ))
            ->phase('ask-replacement', 'Prompt user: "Replacement skill id? (kebab-case existing skill, or \"none\" if no replacement)"')
            ->phase('store-replacement', Store::as('REPLACEMENT', '{kebab-case id or literal "none"}'))
            ->phase('validate-replacement', Operator::if(
                Store::get('REPLACEMENT') . ' != "none"',
                Operator::do(
                    Operator::validate(
                        Store::get('REPLACEMENT') . ' matches /^[a-z][a-z0-9-]*$/',
                        Operator::abort('Replacement must be kebab-case or literal "none"')
                    ),
                    BashTool::call('test -f node/Skills/' . Store::get('REPLACEMENT') . '/SKILL.md && echo EXISTS || echo MISSING'),
                    Operator::validate('result == EXISTS', Operator::abort('Replacement skill node/Skills/' . Store::get('REPLACEMENT') . ' does not exist. Use "none" or a real existing skill id.'))
                )
            ));

        // WORKFLOW: BUILD PATCH + DERIVE FIELDS
        $this->guideline('workflow-build-patch')
            ->text('Build the frontmatter patch and auto-fill remaining proposal fields')
            ->example()
            ->phase('set-action', Store::as('ACTION', 'deprecate-skill'))
            ->phase('rationale', Store::as('RATIONALE', Store::get('REASON')))
            ->phase('build-frontmatter-patch', 'Compute unified diff that adds `deprecated: true` and, when ' . Store::get('REPLACEMENT') . ' != "none", `replacement: ' . Store::get('REPLACEMENT') . '` to the YAML frontmatter of node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md')
            ->phase('store-patch', Store::as('PATCH', '{unified diff, paths relative to project root}'))
            ->phase('auto-set-evidence', Store::as('EVIDENCE', '[{kind: "file", ref: "node/Skills/' . Store::get('TARGET_SKILL') . '/SKILL.md"}, {kind: "session", ref: "deprecation requested in current session"}]'))
            ->phase('auto-set-confidence', Store::as('CONFIDENCE', '0.9 (explicit user request anchor)'))
            ->phase('auto-set-caveats', Store::as('CAVEATS', '[] unless user mentioned migration risks during reason prompt'));

        // SHARED DIRECT-WRITE PIPELINE
        $this->appendProposalWorkflow();
    }
}