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
use BrainNode\Mcp\SequentialThinkingMcp;
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
        // ABSOLUTE FIRST - BLOCKING ENTRY RULE
        $this->defineEntryPointBlockingRule('SYNC');

        // Universal safety rules
        $this->defineSecretsPiiProtectionRules();
        $this->defineNoDestructiveGitRules();
        $this->defineTagTaxonomyRules();
        $this->defineFailurePolicyRules();
        $this->defineAggressiveDocsSearchGuideline();
        $this->defineDocumentationIsLawRules();

        // Code quality rules (DoSync executes directly — needs same quality as TaskSync)
        $this->defineCodebasePatternReuseRule();
        $this->defineImpactRadiusAnalysisRule();
        $this->defineLogicEdgeCaseVerificationRule();
        $this->definePerformanceAwarenessRule();
        $this->defineCodeHallucinationPreventionRule();
        $this->defineCleanupAfterChangesRule();
        $this->defineTestCoverageDuringExecutionRule();
        $this->defineDocumentationDuringExecutionRule();

        // Iron Rules - Zero Tolerance
        $this->defineZeroDistractionsRule();

        // Task workflow integration
        $this->defineScopeEscalationRule('sync');
        $this->defineDoCircuitBreakerRule('sync');
        $this->defineDoFailureAwarenessRule();
        $this->defineDoMachineReadableProgressRule();

        $this->rule('no-delegation')->critical()
            ->text('Brain executes ALL steps directly. NO Task() delegation to agents. Use ONLY direct tools: Read, Edit, Write, Glob, Grep, Bash.')
            ->why('Sync mode is for direct execution without agent overhead')
            ->onViolation('Remove Task() calls. Execute directly.');

        $this->rule('single-approval-gate')->critical()
            ->text('User approval REQUIRED before execution. Present plan, WAIT for confirmation, then execute without interruption. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve (skip waiting for user confirmation).')
            ->why('Single checkpoint for simple tasks - approve once, execute fully. The -y flag enables unattended/scripted execution.')
            ->onViolation('STOP. Wait for user approval before execution (unless $HAS_AUTO_APPROVE is true).');

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
        $this->defineInputCaptureWithDescriptionGuideline();

        // Phase 1: Context Analysis
        $this->guideline('phase1-context-analysis')
            ->goal('Analyze task and gather context from conversation + memory')
            ->example()
            ->phase(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
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
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Context for {$TASK}\n\nMaterials: {summary}", category: "'.self::CAT_CODE_SOLUTION.'", tags: ["'.self::MTAG_SOLUTION.'", "'.self::MTAG_REUSABLE.'"]}'))
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
            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Planning direct execution. Analyzing: file dependencies, edit sequence, atomic steps, potential conflicts, rollback strategy.",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
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
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                'AUTO-APPROVED (unattended mode)',
                Operator::output(['🤖 Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Modify plan → Re-present → WAIT'),
            ]));

        // Phase 3: Direct Execution
        $this->guideline('phase3-direct-execution')
            ->goal('Execute plan directly using Brain tools - no delegation. Track changed files.')
            ->example()
            ->phase(Store::as('CHANGED_FILES', '[]'))
            ->phase(Operator::forEach('step in $PLAN', [
                Operator::output(['▶️ Step {N}: {step.description}']),
                Operator::if('step.action === "read"', [
                    ReadTool::call('{step.file}'),
                    Store::as('FILE_CONTENT[{N}]', 'File content'),
                ]),
                Operator::if('step.action === "edit"', [
                    ReadTool::call('{step.file}'),
                    EditTool::call('{step.file}', '{old_string}', '{new_string}'),
                    'Append {step.file} to '.Store::get('CHANGED_FILES'),
                ]),
                Operator::if('step.action === "write"', [
                    WriteTool::call('{step.file}', '{content}'),
                    'Append {step.file} to '.Store::get('CHANGED_FILES'),
                ]),
                Store::as('STEP_RESULTS[{N}]', 'Result'),
                Operator::output(['✅ Step {N} complete']),
            ]))
            ->phase(Operator::if('step fails', [
                'Log error',
                'Offer: Retry / Skip / Abort',
                'WAIT for user decision',
            ]));

        // Phase 3.5: Post-Execution Validation (mirrors TaskSync pipeline)
        $this->guideline('phase3.5-post-execution-validation')
            ->goal('Validate all changes before reporting completion')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3.5: POST-EXECUTION VALIDATION ===',
            ]))

            ->phase('1. SYNTAX CHECK: Run language-specific syntax validator on '.Store::get('CHANGED_FILES'))
            ->phase(Operator::if('syntax errors', [
                'Attempt auto-fix (max 2 tries)',
                Operator::if('still errors', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Log error, mark as PARTIAL'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'Show errors, ask for guidance'),
                ]),
            ]))

            ->phase('2. HALLUCINATION CHECK: Verify all method/class/function calls in '.Store::get('CHANGED_FILES').' reference REAL code. Read source to confirm methods exist with correct signatures.')
            ->phase(Operator::if('non-existent method/class found', 'Fix: replace with actual method from source. Re-read target file to find correct API.'))

            ->phase('3. LINTER: Run project linter if configured')
            ->phase(Operator::if('linter errors', [
                'Auto-fix if possible, otherwise fix manually',
            ]))

            ->phase('4. LOGIC VERIFICATION: Review each changed function in '.Store::get('CHANGED_FILES').'. For each: what happens with null input? empty collection? boundary value (0, -1, MAX)? error path? off-by-one?')
            ->phase(Operator::if('logic issues found', 'Fix immediately: add guards, fix boundaries, handle edge cases'))

            ->phase('5. PERFORMANCE REVIEW: Check '.Store::get('CHANGED_FILES').' for: nested loops over data (O(n²)), query/I/O inside loops (N+1), loading full datasets without pagination, unnecessary serialization')
            ->phase(Operator::if('performance anti-pattern found', 'Refactor: batch queries, optimize algorithm, add pagination'))

            ->phase('6. TESTS: Detect related test files for '.Store::get('CHANGED_FILES').' (scoped, NEVER full suite)')
            ->phase(Store::as('RELATED_TESTS', 'test files in same dir, *Test suffix, test/ mirror — ONLY for CHANGED_FILES'))
            ->phase(Operator::if(Store::get('RELATED_TESTS').' exist', [
                'Run ONLY related tests with --filter or specific paths',
                Operator::if('tests fail', [
                    'Analyze failure, attempt fix (max 2 tries)',
                    Operator::if('still fails', 'Log as PARTIAL, report in completion'),
                ]),
                'Check coverage: existing tests cover >=80% of changed code? Critical paths 100%?',
                Operator::if('coverage insufficient', [
                    'WRITE additional tests. Follow existing test patterns. Run to verify passing.',
                ]),
            ]))
            ->phase(Operator::if(Store::get('RELATED_TESTS').' empty (NO tests for changed code)', [
                'WRITE TESTS for '.Store::get('CHANGED_FILES'),
                'Detect test framework, follow existing patterns, meaningful assertions, edge cases',
                'Target: >=80% coverage, critical paths 100%. Run to verify passing.',
            ]))

            ->phase('7. CLEANUP: Scan '.Store::get('CHANGED_FILES').' for: unused imports/use/require, dead code from refactoring, orphaned helpers no longer called, commented-out blocks')
            ->phase(Operator::if('cleanup needed', 'Remove dead code. Re-run syntax check after cleanup.'));

        // Phase 4: Completion
        $this->guideline('phase4-completion')
            ->goal('Report results and store learnings to vector memory')
            ->example()
            ->phase(Store::as('SUMMARY', '{completed_steps, files_modified, outcome}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Completed: {$TASK}\n\nApproach: {steps}\n\nFiles: {list}\n\nLearnings: {insights}", category: "'.self::CAT_CODE_SOLUTION.'", tags: ["'.self::MTAG_SOLUTION.'", "'.self::MTAG_REUSABLE.'"]}'))
            ->phase(Operator::output([
                '',
                '=== COMPLETE ===',
                'Task: {$TASK} | Status: {SUCCESS/PARTIAL/FAILED}',
                '✓ Steps: {completed}/{total} | 📁 Files: {count}',
                '{outcomes}',
                '',
                'RESULT: {SUCCESS|PARTIAL|FAILED} — steps={completed}/{total}, files={count}',
                'NEXT: /do:validate {$TASK}',
            ]));

        // Error Recovery
        $this->guideline('error-recovery')
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
            ])
            ->phase()->if('memory storage fails', [
                'Log: "Failed to store to memory: {error}"',
                'Report findings in output instead',
                'Continue with report',
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
        $this->guideline('response-format')
            ->text('=== headers | single approval | progress | files | Direct execution, no filler');
    }
}
