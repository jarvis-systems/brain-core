<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;

#[Purpose('Interactive new skill author: takes a kebab-case skill name, asks the user for purpose, enriches body from session context, auto-suggests docs_topics, drafts the SKILL.md content with YAML frontmatter, then runs the shared triage + direct-write pipeline.')]
class SkillNewInclude extends IncludeArchetype
{
    use SkillProposalSharedTrait;

    protected function handle(): void
    {
        // INTENT IRON RULES
        $this->rule('new-target-must-not-exist')->critical()
            ->text('Before any direct write: node/Skills/{TARGET_SKILL}/ MUST NOT exist. If it does → abort and recommend /skill:edit {TARGET_SKILL} instead.')
            ->why('create-skill on top of an existing folder would overwrite the canonical SKILL.md without any review step.')
            ->onViolation('ABORT. Recommend /skill:edit {TARGET_SKILL} for existing skills.');

        $this->rule('new-name-kebab-case')->critical()
            ->text('Skill name MUST match /^[a-z][a-z0-9-]*$/ (kebab-case). Reject anything else before proceeding.')
            ->why('Storage paths use the name verbatim — non-kebab-case names break filesystem and command parsing.')
            ->onViolation('ABORT with message "Skill name must be kebab-case (lowercase letters, digits, hyphens, must start with a letter)".');

        // ROLE
        $this->guideline('role')
            ->text('Interactive new-skill author. Parses one positional argument as the skill name, validates filesystem state, asks the user one focused purpose question, drafts the SKILL.md content with frontmatter (name, description, optional docs_topics) plus body sections derived from purpose and session signals, then hands off to the shared direct-write pipeline.');

        // WORKFLOW: PARSE NAME
        $this->guideline('workflow-parse-name')
            ->text('Capture and validate the skill name argument')
            ->example()
            ->phase('capture', Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->phase('parse', Store::as('TARGET_SKILL', '{$ARGUMENTS positional arg 1 — first non-flag token}'))
            ->phase('validate-shape', Operator::validate(
                Store::get('TARGET_SKILL') . ' matches /^[a-z][a-z0-9-]*$/',
                Operator::abort('Skill name must be kebab-case (a-z, 0-9, -; must start with a letter)')
            ))
            ->phase('check-exists', BashTool::call('test -e node/Skills/' . Store::get('TARGET_SKILL') . ' && echo EXISTS || echo FREE'))
            ->phase('abort-if-exists', Operator::validate(
                'result == FREE',
                Operator::abort('node/Skills/' . Store::get('TARGET_SKILL') . ' already exists — use /skill:edit ' . Store::get('TARGET_SKILL') . ' to modify it')
            ));

        // WORKFLOW: COLLECT PURPOSE
        $this->guideline('workflow-collect-purpose')
            ->text('Ask the user one focused purpose question and enrich body context from session signals')
            ->example()
            ->phase('ask-purpose', 'Prompt user: "What should this skill do? Brief purpose (1-3 sentences)."')
            ->phase('store-purpose', Store::as('PURPOSE', '{user input — trimmed multi-line text}'))
            ->phase('validate-purpose', Operator::validate(
                'length(' . Store::get('PURPOSE') . ') between 16 and 1200',
                Operator::abort('Purpose must be 16-1200 chars (1-3 sentences). Try again with /skill:new ' . Store::get('TARGET_SKILL'))
            ))
            ->phase('enrich-from-session', 'Scan current conversation context, recent vector-task changes, and recent vector-memory entries for material that should inform body sections (related files, decisions, anti-patterns)')
            ->phase('store-session-context', Store::as('SESSION_CONTEXT', '{related_files, related_tasks, related_memories, recurring_decisions}'));

        // WORKFLOW: DRAFT SKILL.md CONTENT
        $this->guideline('workflow-draft')
            ->text('Build the full SKILL.md draft and gather intent-collection variables for shared pipeline')
            ->example()
            ->phase('set-action', Store::as('ACTION', 'create-skill'))
            ->phase('rationale', Store::as('RATIONALE', '{first sentence of $PURPOSE, trimmed to 8-400 chars}'))
            ->phase('build-frontmatter', 'YAML frontmatter: name: ' . Store::get('TARGET_SKILL') . ', description: {single-line summary distilled from $PURPOSE, max 200 chars}')
            ->phase('docs-suggest', 'Invoke the shared docs_search probe (Step 0 triage produces $DOCS_HITS) — Step 5.5 will suggest docs_topics keywords automatically')
            ->phase('build-body', 'Sections derived from $PURPOSE and $SESSION_CONTEXT: ## Purpose, ## When to use, ## How to use (numbered steps), ## References (if related files known)')
            ->phase('assemble-draft', Store::as('DRAFT', '{full SKILL.md content: --- frontmatter --- + body sections}'))
            ->phase('show-draft', 'Display draft SKILL.md to user. Ask: "accept / edit / abort?"')
            ->phase('handle-response', Operator::if(
                'user response == edit',
                'Collect user edits, regenerate $DRAFT, re-show, loop until accept or abort',
                Operator::if(
                    'user response == abort',
                    Operator::do(
                        'Invoke the shared workflow-decline-signal guideline (defined below) to record a vector-memory negative signal',
                        Operator::abort('User aborted draft acceptance — no canonical write performed')
                    ),
                    'Proceed with current $DRAFT'
                )
            ))
            ->phase('auto-set-evidence', Store::as('EVIDENCE', '[{kind: "session", ref: "current session"}] ++ refs from $SESSION_CONTEXT.related_files / related_tasks / related_memories'))
            ->phase('auto-set-confidence', Store::as('CONFIDENCE', '0.9 (explicit user request anchor)'))
            ->phase('auto-set-caveats', Store::as('CAVEATS', '[] unless user added concerns during edit loop'))
            ->phase('auto-set-replacement', Store::as('REPLACEMENT', '"none" (new flow never deprecates)'));

        // SHARED DIRECT-WRITE PIPELINE
        $this->appendProposalWorkflow();
        $this->recordDeclineSignal();
    }
}