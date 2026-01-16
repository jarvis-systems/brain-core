<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Do;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\EditTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\GrepTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Direct synchronous task execution by Brain without agent delegation. Uses Read/Edit/Write/Glob/Grep tools directly. Single approval gate. Best for: simple tasks, quick fixes, single-file changes, when agent overhead is unnecessary. Accepts task description as input. Zero distractions, atomic execution, strict plan adherence.')]
class DoSyncInclude extends IncludeArchetype
{
    use DoCommandCommonTrait;
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // Iron Rules - Zero Tolerance
        $this->defineZeroDistractionsRule();

        $this->rule('no-delegation')->critical()
            ->text('Brain executes ALL steps directly. NO Task() delegation to agents. Use ONLY direct tools: Read, Edit, Write, Glob, Grep, Bash.')
            ->why('Sync mode is for direct execution without agent overhead')
            ->onViolation('Remove Task() calls. Execute directly.');

        $this->rule('single-approval-gate')->critical()
            ->text('User approval REQUIRED before execution. Present plan, WAIT for confirmation, then execute without interruption. EXCEPTION: If $HAS_Y_FLAG is true, auto-approve (skip waiting for user confirmation).')
            ->why('Single checkpoint for simple tasks - approve once, execute fully. The -y flag enables unattended/scripted execution.')
            ->onViolation('STOP. Wait for user approval before execution (unless $HAS_Y_FLAG is true).');

        $this->rule('atomic-execution')->critical()
            ->text('Execute ONLY approved plan steps. NO improvisation, NO "while we\'re here" additions. Atomic changes only.')
            ->why('Maintains plan integrity and predictability')
            ->onViolation('Revert to approved plan. Resume approved steps only.');

        $this->rule('read-before-edit')->high()
            ->text('ALWAYS Read file BEFORE Edit/Write. Never modify files blindly.')
            ->why('Ensures accurate edits based on current file state')
            ->onViolation('Read file first, then proceed with edit.');

        $this->rule('vector-memory-integration')->high()
            ->text('Search vector memory BEFORE planning. Store learnings AFTER completion.')
            ->why('Leverages past solutions, builds knowledge base')
            ->onViolation('Include memory search in analysis, store insights after.');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('TASK_DESCRIPTION', '{task description extracted from $RAW_INPUT}'));

        // Phase 1: Context Analysis
        $this->guideline('phase1-context-analysis')
            ->goal('Analyze task and gather context from conversation + memory')
            ->example()
            ->phase(Store::as('HAS_Y_FLAG', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->phase(Store::as('TASK', '{$TASK_DESCRIPTION with flags removed, trimmed}'))
            ->phase('Analyze conversation: requirements, constraints, preferences, prior decisions')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "similar: {$TASK}", limit: 5, category: "code-solution"}'))
            ->phase(Store::as('PRIOR_SOLUTIONS', 'Relevant past approaches'))
            ->phase(Operator::output([
                '=== CONTEXT ===',
                'Task: {$TASK}',
                'Prior solutions: {summary or "none found"}',
            ]));

        // Phase 1.5: Material Gathering with Vector Storage
        $this->guideline('phase1.5-material-gathering')
            ->goal('Collect materials per plan and store to vector memory. NOTE: command `brain docs` returns file index (Path, Name, Description, etc.), then Read relevant files')
            ->example()
            ->phase(Operator::forEach('scan_target in $REQUIREMENTS_PLAN.scan_targets', [
                Operator::do('Context extraction from {scan_target}'),
                Store::as('GATHERED_MATERIALS[{target}]', 'Extracted context'),
            ]))
            ->phase(Operator::if('$DOCS_SCAN_NEEDED === true', [
                BashTool::describe(BrainCLI::DOCS('{keywords}'), 'Find documentation index (returns: Path, Name, Description)'),
                Store::as('DOCS_INDEX', 'Documentation file index'),
                Operator::forEach('doc in $DOCS_INDEX', [
                    ReadTool::call('{doc.path}'),
                ]),
                Store::as('DOCS_SCAN_FINDINGS', 'Documentation content'),
            ]))
            ->phase(Operator::if('$WEB_RESEARCH_NEEDED === true', [
                WebSearchTool::describe('Research best practices for {$TASK}'),
                Store::as('WEB_RESEARCH_FINDINGS', 'External knowledge'),
            ]))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for {$TASK}\n\nMaterials: {summary}", category: "tool-usage", tags: ["do-command", "context-gathering"]}'))
            ->phase(Operator::output([
                '=== PHASE 1.5: MATERIALS GATHERED ===',
                'Materials: {count} | Docs: {status} | Web: {status}',
                'Context stored to vector memory ✓',
            ]));

        // Phase 2: Exploration & Planning
        $this->guideline('phase2-exploration-planning')
            ->goal('Explore codebase, identify targets, create execution plan')
            ->example()
            ->phase('Identify files to examine based on task description')
            ->phase(GlobTool::describe('Find relevant files: patterns based on task'))
            ->phase(GrepTool::describe('Search for relevant code patterns'))
            ->phase(ReadTool::describe('Read identified files for context'))
            ->phase(Store::as('CONTEXT', '{files_found, code_patterns, current_state}'))
            ->phase('Create atomic execution plan: specific edits with exact changes')
            ->phase(Store::as('PLAN', '[{step_N, file, action: read|edit|write, description, exact_changes}, ...]'))
            ->phase(Operator::output([
                '',
                '=== EXECUTION PLAN ===',
                'Files: {list}',
                'Steps:',
                '{numbered_steps_with_descriptions}',
                '',
                '⚠️ APPROVAL REQUIRED',
                '✅ approved/yes | ❌ no/modifications',
            ]))
            ->phase(Operator::if('$HAS_Y_FLAG === true', [
                'AUTO-APPROVED (unattended mode)',
                Operator::output(['🤖 Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_Y_FLAG === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Modify plan → Re-present → WAIT'),
            ]));

        // Phase 3: Direct Execution
        $this->guideline('phase3-direct-execution')
            ->goal('Execute plan directly using Brain tools - no delegation')
            ->example()
            ->phase(Operator::forEach('step in $PLAN', [
                Operator::output(['▶️ Step {N}: {step.description}']),
                Operator::if('step.action === "read"', [
                    ReadTool::call('{step.file}'),
                    Store::as('FILE_CONTENT[{N}]', 'File content'),
                ]),
                Operator::if('step.action === "edit"', [
                    ReadTool::call('{step.file}'),
                    EditTool::call('{step.file}', '{old_string}', '{new_string}'),
                ]),
                Operator::if('step.action === "write"', [
                    WriteTool::call('{step.file}', '{content}'),
                ]),
                Store::as('STEP_RESULTS[{N}]', 'Result'),
                Operator::output(['✅ Step {N} complete']),
            ]))
            ->phase(Operator::if('step fails', [
                'Log error',
                'Offer: Retry / Skip / Abort',
                'WAIT for user decision',
            ]));

        // Phase 4: Completion
        $this->guideline('phase4-completion')
            ->goal('Report results and store learnings to vector memory')
            ->example()
            ->phase(Store::as('SUMMARY', '{completed_steps, files_modified, outcome}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Completed: {$TASK}\n\nApproach: {steps}\n\nFiles: {list}\n\nLearnings: {insights}", category: "code-solution", tags: ["do:sync", "completed"]}'))
            ->phase(Operator::output([
                '',
                '=== COMPLETE ===',
                'Task: {$TASK} | Status: {SUCCESS/PARTIAL/FAILED}',
                '✓ Steps: {completed}/{total} | 📁 Files: {count}',
                '{outcomes}',
            ]));

        // Error Handling
        $this->guideline('error-handling')
            ->text('Direct error handling without agent fallback')
            ->example()
            ->phase()->if('file not found', [
                'Report: "File not found: {path}"',
                'Offer: Create new file / Specify correct path / Abort',
            ])
            ->phase()->if('edit conflict', [
                'Report: "old_string not found in file"',
                'Re-read file, adjust edit, retry',
            ])
            ->phase()->if('user rejects plan', [
                'Accept modifications',
                'Rebuild plan',
                'Re-present for approval',
            ]);

        // Examples
        $this->guideline('example-simple-fix')
            ->scenario('Simple bug fix')
            ->example()
            ->phase('input', '"Fix typo in UserController.php line 42"')
            ->phase('plan', '1 step: Edit UserController.php')
            ->phase('execution', 'Read → Edit → Done')
            ->phase('result', '1/1 ✓');

        $this->guideline('example-add-method')
            ->scenario('Add method to existing class')
            ->example()
            ->phase('input', '"Add getFullName() method to User model"')
            ->phase('plan', '2 steps: Read User.php → Edit to add method')
            ->phase('execution', 'Read → Edit → Done')
            ->phase('result', '2/2 ✓');

        $this->guideline('example-config-change')
            ->scenario('Configuration update')
            ->example()
            ->phase('input', '"Change cache driver to redis in config"')
            ->phase('plan', '2 steps: Read config/cache.php → Edit driver value')
            ->phase('execution', 'Read → Edit → Done')
            ->phase('result', '2/2 ✓');

        // When to use sync vs async
        $this->guideline('sync-vs-async')
            ->text('When to use /do:sync vs /do:async')
            ->example()
            ->phase('USE /do:sync', 'Simple tasks, single-file changes, quick fixes, config updates, typo fixes, adding small methods')
            ->phase('USE /do:async', 'Complex multi-file tasks, tasks requiring research, architecture changes, tasks benefiting from specialized agents');

        // Response Format
        $this->defineResponseFormatGuideline('=== headers | single approval | progress | files | Direct execution, no filler');
    }
}
