<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Skill;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Skill candidate auto-detector: scans current session signals (git, recent tasks, recent memory, conversation) → identifies 1-5 candidate patterns → user picks → Brain auto-derives action/target/rationale/evidence/confidence → shared triage → direct write to canonical node/Skills/{target}/.')]
class SkillLearnInclude extends IncludeArchetype
{
    use SkillProposalSharedTrait;

    protected function handle(): void
    {
        // INTENT IRON RULES
        $this->rule('learn-zero-args')->high()
            ->text('/skill:learn takes ZERO positional args. Brain derives all proposal fields from the current session context (git status/diff, recent vector-task changes, recent vector-memory entries, conversation signals).')
            ->why('The whole point of /skill:learn is autonomy: no flags, no name, no rationale typed by hand. Asking the user to type anything except a pick number defeats the UX.')
            ->onViolation('Discard arguments. Proceed with signal scan and present numbered candidates.');

        $this->rule('learn-user-pick-required')->critical()
            ->text('Brain MUST present 1-5 detected candidates and wait for an explicit user pick before triage. Empty input or "none" aborts cleanly without writing and records a decline signal in vector-memory.')
            ->why('Without user pick the command becomes blind self-modification — exactly the failure mode the direct-write flow exists to prevent. User invocation + explicit pick IS the consent gate.')
            ->onViolation('Re-present the candidate list. If user response is "none" or empty, exit with no canonical write and store a vector-memory decline signal.');

        // ROLE
        $this->guideline('role')
            ->text('Session-context analyst. Scans recent activity for skill-worthy patterns, asks the user to pick, then auto-fills the rest of the proposal payload before handing off to the shared triage workflow.');

        // WORKFLOW: SIGNAL SCAN
        $this->guideline('workflow-signal-scan')
            ->text('Collect raw signals from the current session')
            ->example()
            ->phase('scan-git', BashTool::call('git status --short && git diff HEAD --stat'))
            ->phase('store-git', Store::as('GIT_SIGNALS', '{branch state, changed files, diff size summary}'))
            ->phase('scan-recent-tasks', VectorTaskMcp::callValidatedJson('task_list', ['limit' => 10, 'status' => 'completed']))
            ->phase('store-tasks', Store::as('RECENT_TASKS', 'array of recent completed tasks with id/title/tags'))
            ->phase('scan-recent-memory', VectorMemoryMcp::callValidatedJson('list_recent_memories', ['limit' => 10]))
            ->phase('store-memory', Store::as('RECENT_MEMORY', 'array of recent memory entries with id/category/tags/summary'))
            ->phase('scan-conversation', 'Extract key decisions, repeated patterns, user corrections, and explicit "always do X" / "never do Y" statements from the current session conversation context')
            ->phase('store-conversation', Store::as('CONVO_SIGNALS', '{decisions: [], corrections: [], reinforced_rules: [], anti_patterns: []}'));

        // WORKFLOW: PATTERN EXTRACT
        $this->guideline('workflow-pattern-extract')
            ->text('Synthesize signals into 1-5 candidate skill patterns and present for user pick')
            ->example()
            ->phase('detect-patterns', 'Cross-reference GIT_SIGNALS, RECENT_TASKS, RECENT_MEMORY, CONVO_SIGNALS — identify 1-5 candidate patterns worth recording as skill insights (each must have >=2 supporting evidence pointers)')
            ->phase('store-candidates', Store::as('CANDIDATE_PATTERNS', 'array of {label, summary (1 sentence), suggested_action (create-skill|modify-skill|append-reference), suggested_target (kebab-case skill id or null), evidence_refs, confidence_hint}'))
            ->phase('present', 'Display numbered list of candidates with 1-sentence summary, suggested action, and suggested target each')
            ->phase('user-pick', 'Prompt user: "Which to record? (1,2,3 / multiple comma-separated / none)"')
            ->phase('store-pick', Store::as('PICKED', 'array of selected candidate indices, or empty for none'))
            ->phase('decline-on-none', Operator::if(
                Store::get('PICKED') . ' is empty',
                Operator::do(
                    'Invoke the shared workflow-decline-signal guideline (defined below) to record a vector-memory negative signal',
                    Operator::abort('User picked none — no canonical write performed')
                )
            ));

        // WORKFLOW: DERIVE FIELDS PER PICKED CANDIDATE
        $this->guideline('workflow-derive-fields')
            ->text('For each picked candidate: auto-derive ACTION / TARGET_SKILL / RATIONALE / EVIDENCE / CONFIDENCE then run the shared triage + build + write pipeline. If multiple candidates picked, the shared pipeline runs once per candidate.')
            ->example()
            ->phase('foreach-picked', Operator::forEach(
                'candidate in ' . Store::get('PICKED'),
                [
                    'Probe existing skills (re-using triage-skills-scan result if cached) — match by suggested_target or by semantic similarity of summary',
                    Operator::if(
                        'match found',
                        Operator::do(
                            Store::as('ACTION', 'modify-skill'),
                            Store::as('TARGET_SKILL', '{matched skill id}')
                        ),
                        Operator::do(
                            Store::as('ACTION', '{candidate.suggested_action or "create-skill"}'),
                            Store::as('TARGET_SKILL', '{candidate.suggested_target or freshly generated kebab-case slug}')
                        )
                    ),
                    Store::as('RATIONALE', '{candidate.summary, max 1 sentence, 8-400 chars}'),
                    Store::as('EVIDENCE', '{candidate.evidence_refs as array of {kind, ref} drawn from GIT_SIGNALS/RECENT_TASKS/RECENT_MEMORY/CONVO_SIGNALS}'),
                    Store::as('CONFIDENCE', '0.9 (explicit user pick anchor)'),
                    Store::as('CAVEATS', '[] (learn flow defaults to empty caveats unless candidate.caveats present)'),
                    Store::as('REPLACEMENT', '"none" (learn never proposes deprecate-skill)'),
                ]
            ))
            ->phase('handoff', 'Hand off to shared triage + build + write workflow (defined below) once per picked candidate');

        // SHARED DIRECT-WRITE PIPELINE
        $this->appendProposalWorkflow();
        $this->recordDeclineSignal();
    }
}