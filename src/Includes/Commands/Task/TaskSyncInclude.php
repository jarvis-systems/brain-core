<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

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
use BrainCore\Compilation\Tools\TaskTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainNode\Mcp\Context7Mcp;
use BrainNode\Mcp\SequentialThinkingMcp;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Synchronous vector task execution by Brain. Sync = blocking execution (not background). Agent delegation allowed for research to keep context clean. Critical thinking: validates clarity, adapts examples, researches when needed.')]
class TaskSyncInclude extends IncludeArchetype
{
    use TaskCommandCommonTrait;

    protected function handle(): void
    {
        // IRON EXECUTION LAW - READ THIS FIRST
        $this->rule('task-get-first')->critical()->text('FIRST TOOL CALL = mcp__vector-task__task_get. No text before. Load task, THEN analyze and validate.');
        $this->defineIronExecutionRules();
        $this->defineMachineReadableProgressRule();

        // DOCUMENTATION IS LAW (from trait - prevents stupid questions)
        $this->defineDocumentationIsLawRules();
        $this->defineNoDestructiveGitRules();

        // SECRETS & PII PROTECTION (from trait - no secret exfiltration via output or storage)
        $this->defineSecretsPiiProtectionRules();

        // CODEBASE PATTERN REUSE (from trait - prevents reinventing the wheel)
        $this->defineCodebasePatternReuseRule();
        $this->defineCodebasePatternReuseGuideline();

        // IMPACT RADIUS (from trait - check reverse dependencies before editing)
        $this->defineImpactRadiusAnalysisRule();
        $this->defineImpactRadiusAnalysisGuideline();

        // CODE QUALITY DURING EXECUTION (from trait - prevent common AI code issues)
        $this->defineLogicEdgeCaseVerificationRule();
        $this->definePerformanceAwarenessRule();
        $this->defineCodeHallucinationPreventionRule();
        $this->defineCleanupAfterChangesRule();

        // TEST COVERAGE (from trait - write tests alongside implementation to pass validator on first try)
        $this->defineTestCoverageDuringExecutionRule();

        // COMMENT CONTEXT (from trait - read accumulated context from task.comment)
        $this->defineCommentContextRules();

        // TAG TAXONOMY (from trait - predefined tags for tasks and memory)
        $this->defineTagTaxonomyRules();

        // CRITICAL THINKING RULES
        $this->rule('fast-path')->high()->text('Simple task (clear intent, specific files, no ambiguity) → skip research, execute directly. Complex/ambiguous → full validation flow.');
        $this->rule('research-triggers')->critical()->text('Research REQUIRED when ANY: 1) content <50 chars, 2) contains "example/like/similar/e.g./такий як", 3) no file paths AND no class/function names, 4) references unknown library/pattern, 5) contradicts existing code, 6) multiple valid interpretations, 7) task asks "how to" without specifics.');
        $this->rule('research-flow')->high()->text('Research order: 1) context7 for library docs, 2) web-research-master for patterns/practices. -y flag: auto-select best. No -y: present options to user.');

        // FAILURE-AWARE EXECUTION (CRITICAL - prevents repeating same mistakes)
        $this->defineFailureAwarenessRules();

        // FAILURE POLICY (from trait - universal tool error / missing docs / ambiguous spec handling)
        $this->defineFailurePolicyRules();

        $this->rule('escalate-stuck-problems')->high()
            ->text('If task matches pattern that failed 2+ times (from memory/sibling analysis) → DO NOT attempt same approach. Escalate: research alternatives, ask user, or delegate to web-research-master.')
            ->why('Definition of insanity: doing same thing expecting different results.');

        // SYNC EXECUTION RULES (sync = blocking, not "no agents")
        $this->rule('sync-meaning')->medium()->text('Sync = synchronous/blocking execution (vs async/background). Agent delegation IS allowed for research - keeps main context clean.');
        $this->rule('read-before-edit')->critical()->text('ALWAYS Read file BEFORE Edit/Write.');
        $this->rule('understand-then-execute')->critical()->text('Understand INTENT behind task, not just literal text. Adapt examples to actual context.');

        // AUTO-APPROVE & WORKFLOW ATOMICITY (from trait)
        $this->defineAutoApprovalRules();

        // DEPENDENCY HANDLING (language-agnostic)
        $this->rule('dependency-detection')->high()
            ->text('Detect missing dependencies: import/require/use statements that fail, unknown classes/modules, task explicitly mentions "add/install/use {package}". Store list for installation.');
        $this->rule('dependency-install')->high()
            ->text('Install dependencies: detect package manager (composer, npm, pip, cargo, go mod, etc.) from project files. -y: auto-install. No -y: ask "Need to install {packages}. Proceed?"')
            ->why('Task cannot complete without required dependencies.');
        $this->rule('dependency-audit')->medium()
            ->text('After install: run audit if available (npm audit, composer audit, pip-audit, cargo audit). Vulnerabilities found: -y = WARN and continue, no -y = ask user.');
        $this->rule('dependency-dev-vs-prod')->medium()
            ->text('Dev dependencies (test frameworks, linters, dev tools) install to dev. Production dependencies install to main. Detect from usage context.');

        // BACKUP & ROLLBACK (language-agnostic) — git prohibition from trait via defineNoDestructiveGitRules()
        $this->rule('rollback-on-failure')->high()
            ->text('If execution fails mid-way: revert ONLY your own changes by re-reading original content and restoring via Edit/Write. NEVER use git commands for rollback.')
            ->why('Git-level rollback destroys ALL uncommitted changes including other agents work, memory/ SQLite databases, and user WIP.');
        $this->rule('no-git-fallback')->medium()
            ->text('No git repo: create backup files (.bak) before edit. Rollback = restore from .bak. Clean .bak files on success.');

        // SECURITY WHILE CODING (language-agnostic)
        $this->rule('security-no-secrets')->critical()
            ->text('NEVER write hardcoded secrets (passwords, API keys, tokens). Use: env variables, config files (gitignored), secret managers. If task asks to hardcode secret: REFUSE, suggest secure alternative.');
        $this->rule('security-input-validation')->high()
            ->text('Code that receives external input (user, API, file): add validation at boundaries. Validate type, format, length, allowed values. Reject/sanitize invalid input.');
        $this->rule('security-output-escaping')->high()
            ->text('Code that outputs to HTML/JS/SQL/shell: escape appropriately. HTML = htmlspecialchars/equivalent, SQL = parameterized queries, shell = escapeshellarg/equivalent.');
        $this->rule('security-parameterized-queries')->critical()
            ->text('Database queries with variables: ALWAYS parameterized/prepared statements. NEVER string concatenation. No exceptions.');

        // POST-EXECUTION VALIDATION (language-agnostic)
        $this->rule('post-exec-syntax')->critical()
            ->text('After ALL edits: verify syntax. Run language-specific check (php -l, node --check, python -m py_compile, rustc --emit=metadata, go build). Syntax error = fix immediately.');
        $this->rule('post-exec-linter')->high()
            ->text('After syntax OK: run linter if configured (eslint, phpcs, pylint, clippy, golint). Errors: -y = auto-fix if possible, no -y = show and ask. Cannot auto-fix = manual fix.');
        $this->rule('post-exec-tests')->high()
            ->text('After linter OK: run ONLY related tests. Detect test files: same directory, *Test/*_test suffix, test/ mirror structure. ONLY files directly related to CHANGED_FILES. -y = run automatically, no -y = ask "Run tests?"')
            ->why('Related tests give fast feedback on changed code. Full suite = validator job.');
        $this->rule('no-full-test-suite')->critical()
            ->text('NEVER run full test suite (composer test, php artisan test without --filter, phpunit without path). Sync executor runs ONLY related tests scoped to changed files. Full test suite is EXCLUSIVELY the validator\'s responsibility (task:validate). Brain-level quality gates (QUALITY_COMMAND) do NOT apply during sync execution — they apply during validation phase ONLY.')
            ->why('Full suite on 15-min task = overkill. Related tests already cover risk zone. Validator will run full suite anyway. Running it twice wastes 2+ minutes and risks timeouts.')
            ->onViolation('ABORT full suite command. Scope to --filter or specific test file paths only.');
        $this->rule('post-exec-test-failure')->high()
            ->text('Tests fail: analyze failure, attempt fix (max 2 attempts). Still fails: -y = mark task pending with error comment, no -y = ask user for guidance.');

        // PARTIAL FAILURE HANDLING
        $this->rule('partial-failure-tracking')->high()
            ->text('Track execution state: {completed_steps: [], current_step: N, total_steps: M, changed_files: []}. Persist in task comment for recovery.');
        $this->rule('partial-failure-decision')->high()
            ->text('Step fails after previous steps changed files: 1) Attempt fix (max 2), 2) If unfixable AND -y: rollback all + mark pending, 3) If unfixable AND no -y: ask "Rollback/Skip/Manual fix?"');
        $this->rule('partial-success-option')->medium()
            ->text('If 80%+ steps succeeded and remaining are non-critical: -y = complete with warning comment, no -y = ask "Complete partial or rollback?"');

        // RETRY & TIMEOUT LIMITS
        $this->rule('retry-limit')->high()
            ->text('Edit conflict: max 3 retries. File locked: wait 2s, retry, max 5 attempts. Network error: retry with backoff, max 3. After max: fail step.');
        $this->rule('timeout-limits')->medium()
            ->text('Long operations: dependency install 120s, test suite 300s, linter 60s. Timeout exceeded: -y = skip with warning, no -y = ask "Wait/Skip/Abort?"');

        // SESSION RECOVERY (detailed)
        $this->rule('session-recovery-detection')->high()
            ->text('Task status=in_progress: check task.comment for execution state. Has completed_steps AND recent timestamp (<1h): crashed session. No state OR old timestamp (>1h): stale session.');
        $this->rule('session-recovery-action')->high()
            ->text('Crashed session: -y = continue from last completed step, no -y = ask "Continue from step N or restart?" Stale session: reset to pending, start fresh.');

        // PARALLEL ISOLATION (from trait - strict criteria for parallel execution)
        $this->defineParallelIsolationRules();

        // PARALLEL EXECUTION AWARENESS (from trait - know sibling tasks when parallel: true)
        $this->defineParallelExecutionAwarenessRules();

        // SUBTASKS HANDLING
        $this->rule('subtasks-before-parent')->high()
            ->text('Parent task with pending subtasks: complete subtasks FIRST. Order by: order field (strict). -y = execute per parallel flags, no -y = show list and ask.');
        $this->rule('subtasks-parallel-option')->medium()
            ->text('USE parallel field from subtask data BUT verify isolation before concurrent execution. Apply parallel-isolation-checklist: list files per subtask, cross-reference for overlap. Override parallel: true → false if isolation violated. -y = auto-execute, no -y = show grouping and ask.');

        // BREAKING CHANGES
        $this->rule('breaking-change-detection')->high()
            ->text('Detect breaking changes: public method signature change, removed public API, changed return type, renamed exported symbol. Flag for review.');
        $this->rule('breaking-change-action')->high()
            ->text('Breaking change detected: -y = proceed with deprecation notice in comment + update callers if found, no -y = ask "This is breaking change. Proceed/Modify/Abort?"');

        // FAILURE LEARNING
        $this->rule('failure-memory')->medium()
            ->text('On task failure: store to memory with category "debugging". Content: task summary, failure reason, attempted fixes, final state. Learnings help future similar tasks.');

        // INPUT CAPTURE
        $this->defineInputCaptureGuideline();

        // WORKFLOW
        $this->guideline('workflow')->example()
            // 1. Load task (ALWAYS FIRST)
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}') . ' ' . Store::as('TASK'))
            ->phase(Operator::if('not found', Operator::abort()))
            ->phase(Operator::if('status=completed', [
                Operator::if('$HAS_AUTO_APPROVE', Operator::abort('Already completed. Use different task ID.')),
                'ask "Re-execute completed task?"',
            ]))
            ->phase(Operator::if('status=in_progress', [
                'Parse task.comment for execution_state JSON',
                Operator::if('has completed_steps AND timestamp <1h', [
                    Store::as('IS_CRASHED_SESSION', 'true'),
                    Operator::if('$HAS_AUTO_APPROVE', 'Continue from last completed step'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Crashed session. Continue from step N or restart?"'),
                ]),
                Operator::if('no state OR timestamp >1h', [
                    'Stale session detected',
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Stale session reset", append_comment: true}'),
                ]),
            ]))
            ->phase(Operator::if('status=tested AND comment contains "TDD MODE"', 'TDD execution mode → jump to tdd-mode guideline'))
            ->phase(Operator::if('parent_id', VectorTaskMcp::call('task_get', '{task_id: parent_id}') . ' ' . Store::as('PARENT') . ' (READ-ONLY context)'))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID}') . ' ' . Store::as('SUBTASKS'))

            // 1.2 Extract comment context (accumulated inter-session history)
            ->phase(Store::as('COMMENT_CONTEXT', '{parsed from $TASK.comment: memory_ids: [#NNN], file_paths: [...], execution_history: [...], failures: [...], blockers: [...], decisions: [], mode_flags: []}'))

            // 1.25 Parallel execution awareness (if parallel: true → identify siblings, interpret statuses, read scopes from comments)
            ->phase(Operator::if(Store::get('TASK') . '.parallel === true AND parent_id', [
                VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}'),
                Store::as('PARALLEL_SIBLINGS', 'filter: parallel=true AND id != $TASK.id → {id, title, status, comment}'),
                Store::as('ACTIVE_SIBLINGS', 'filter PARALLEL_SIBLINGS where status=in_progress — ONLY these are concurrent threats'),
                'Extract "PARALLEL SCOPE: [...]" from each ACTIVE_SIBLINGS comment → ' . Store::as('SIBLING_SCOPES', '{sibling_id → [files from comment] — REAL planned files, not guesses}'),
                'INTERPRET SIBLINGS: pending={N} (not started, no threat). completed={N} (done, stable). in_progress={N} (concurrent). in_progress without scope in comment = still planning, NOT red flag.',
                'LOG: "PARALLEL CONTEXT: {total} siblings ({active} active, {pending} pending, {done} completed). Active scopes from comments: {SIBLING_SCOPES or NONE}."',
            ]))

            // 1.3 Set in_progress IMMEDIATELY (all checks passed, work begins NOW)
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "Started work", append_comment: true}'))

            // 1.5 Subtasks check
            ->phase(Operator::if(Store::get('SUBTASKS') . ' has pending items', [
                Store::as('PENDING_SUBTASKS', 'filter SUBTASKS where status=pending, order by priority,order,created_at'),
                Operator::if('$HAS_AUTO_APPROVE', 'Execute subtasks sequentially before parent'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Has N pending subtasks. Execute them first?"'),
            ]))

            // 1.7 Documentation check (MANDATORY before any work)
            ->phase(BashTool::call(BrainCLI::DOCS('{task keywords}')) . ' ' . Store::as('TASK_DOCS'))
            ->phase(Operator::if(Store::get('TASK_DOCS') . ' found', [
                ReadTool::call('{doc_paths}') . ' ' . Store::as('DOCUMENTATION'),
                'Documentation is LAW. All execution MUST follow docs. No alternatives unless docs are ambiguous.',
            ]))

            // 1.8 Partial implementation check
            ->phase('Scan target files for existing implementation')
            ->phase(Operator::if('partial implementation exists', [
                'MANDATORY: Re-read ' . Store::get('DOCUMENTATION') . ' to understand FULL target state',
                'Compare: current state vs documented target state',
                Store::as('REMAINING_WORK', 'difference between current and documented target'),
                'Continue implementation per docs. DO NOT ask "keep/rewrite/both" - docs define target.',
            ]))

            // 2. Fast-path check (simple tasks skip to step 4)
            ->phase(Store::as('IS_SIMPLE', 'task.content >=50 chars AND has specific file/class/function AND no "example/like/similar" AND single clear interpretation'))
            ->phase(Operator::if(Store::get('IS_SIMPLE'), 'SKIP to step 4 (Explore & Plan)'))

            // 3. Research (ONLY if triggers matched)
            ->phase(Store::as('NEEDS_RESEARCH', 'ANY: content <50 chars, contains "example/like/similar/e.g./такий як/як у", no paths AND no class names, unknown lib/pattern, contradicts code, ambiguous, "how to" without specifics'))
            ->phase(Operator::if(Store::get('NEEDS_RESEARCH'), [
                '3.1: ' . Context7Mcp::call('resolve-library-id', '{libraryName: "{detected_lib}"}') . ' → IF library mentioned',
                '3.2: ' . Context7Mcp::call('query-docs', '{query: "{task question}"}') . ' → get docs',
                '3.3: IF context7 insufficient → ' . TaskTool::agent('web-research-master', 'Research: {task.title}. Find: implementation patterns, best practices, concrete examples.'),
                Store::as('RESEARCH_OPTIONS', '[{option, source, pros, cons}]'),
            ]))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND $HAS_AUTO_APPROVE', 'Auto-select BEST: fit with existing code > simplicity > best practices'))
            ->phase(Operator::if(Store::get('RESEARCH_OPTIONS') . ' AND NOT $HAS_AUTO_APPROVE', 'Present: "Found N approaches: 1)... 2)... Which? (or your variant)"'))

            // 4. Context gathering (memory + local docs + FAILURES)
            ->phase(VectorMemoryMcp::call('search_memories', '{query: task.title, limit: 5, category: "' . self::CAT_CODE_SOLUTION . '"}') . ' → past solutions')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "{task.title} {problem keywords} failed error not working broken", limit: 5}') . ' ' . Store::as('KNOWN_FAILURES') . ' ← CRITICAL: what already FAILED (search by failure keywords, not category)')
            ->phase(VectorTaskMcp::call('task_list', '{query: task.title, limit: 3}') . ' → related tasks')
            ->phase(Operator::if(
                Store::get('TASK') . '.parent_id',
                [
                    VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' ' . Store::as('SIBLING_TASKS'),
                    // CRITICAL: Search vector memory for EACH sibling task to get stored insights/failures
                    Operator::forEach('sibling in ' . Store::get('SIBLING_TASKS'), [
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title}", limit: 3}') . ' → ALL memories for this sibling (failures, solutions, insights)',
                        VectorMemoryMcp::call('search_memories', '{query: "{sibling.title} failed error not working", limit: 3}') . ' → specifically failure-related memories',
                        'Append results to ' . Store::as('SIBLING_MEMORIES'),
                    ]),
                    'Extract from siblings comments + ' . Store::get('SIBLING_MEMORIES') . ': what was tried, what failed, what worked',
                    Store::as('FAILURE_PATTERNS', 'solutions that were tried and failed (from sibling comments + sibling memories)'),
                ]
            ))
            ->phase(Operator::if(
                Store::get('KNOWN_FAILURES') . ' OR ' . Store::get('FAILURE_PATTERNS') . ' not empty',
                [
                    'BLOCKED APPROACHES: ' . Store::get('KNOWN_FAILURES') . ' + ' . Store::get('FAILURE_PATTERNS'),
                    'If planned solution matches blocked approach → STOP, research alternative or escalate',
                ]
            ))
            ->phase(BashTool::call(BrainCLI::DOCS('{keywords}')) . ' → project docs')
            ->phase(Operator::if('docs found', ReadTool::call('{doc.path}')))

            // 4.5 Codebase similarity search (reuse > reinvent)
            ->phase('4.5 PATTERN REUSE: Extract class type/feature domain from task → search for similar implementations in codebase')
            ->phase(GrepTool::describe('Search for analogous: class names, method patterns, trait usage, helper utilities'))
            ->phase(Operator::if('similar code found', [
                ReadTool::call('{similar_files}') . ' → study approach, conventions, base classes',
                Store::as('EXISTING_PATTERNS', '{files, approach, conventions, base_classes, reusable_utilities}'),
                'USE $EXISTING_PATTERNS as implementation blueprint. Follow same conventions, extend existing helpers.',
            ]))

            // 5. Explore & Plan
            ->phase(GlobTool::describe('Find relevant files'))
            ->phase(GrepTool::describe('Search existing patterns'))
            ->phase(ReadTool::describe('Read target files'))

            // 5.1 Impact radius (who depends on target files?)
            ->phase('5.1 IMPACT RADIUS: For each target file, Grep who imports/uses/extends/implements it → ' . Store::as('DEPENDENTS_MAP'))
            ->phase(Operator::if(Store::get('DEPENDENTS_MAP') . ' has entries', [
                'Classify: NONE (internal) | LOW (private) | MEDIUM (few consumers) | HIGH (widely used)',
                'HIGH impact → review all callers, plan signature-compatible changes or include dependents in PLAN',
            ]))

            ->phase(SequentialThinkingMcp::call('sequentialthinking', '{
                thought: "Planning: 1) INTENT? 2) $EXISTING_PATTERNS? → follow same approach. 3) $DEPENDENTS_MAP? → ensure compatibility with consumers. 4) Fit with existing code? 5) Minimal change? 6) Reuse helpers/base classes?",
                thoughtNumber: 1,
                totalThoughts: 2,
                nextThoughtNeeded: true
            }'))
            ->phase(Store::as('PLAN', '[{step, file, action, changes, rationale}]'))
            ->phase(Operator::if('$HAS_AUTO_APPROVE', 'execute immediately', 'show plan, wait "yes"'))

            // 5.4 Register own file scope in task comment (siblings read via task_list — zero extra calls)
            ->phase(Operator::if(Store::get('TASK') . '.parallel === true', [
                Store::as('MY_FILE_SCOPE', '{all unique files from $PLAN steps}'),
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "PARALLEL SCOPE: [$MY_FILE_SCOPE]", append_comment: true}'),
            ]))

            // 5.5 Dependency check & install
            ->phase(Operator::if('PLAN requires new dependencies', [
                Store::as('DEPS_NEEDED', '[{package, version?, dev?}]'),
                'Detect package manager from project (composer.json, package.json, requirements.txt, Cargo.toml, go.mod, etc.)',
                Operator::if('$HAS_AUTO_APPROVE', [
                    'Auto-install: run package manager install command',
                    'Run audit if available, WARN on vulnerabilities',
                ]),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Need to install: {packages}. Proceed?"'),
            ]))

            // 5.6 Git awareness (READ-ONLY, no stash/checkout)
            ->phase(BashTool::call('git status --porcelain 2>/dev/null || echo "NO_GIT"') . ' ' . Store::as('GIT_STATUS'))
            ->phase(Operator::if(Store::get('GIT_STATUS') . ' has uncommitted changes', 'LOG: uncommitted changes detected. Proceeding carefully — will NOT stash or checkout.'))
            ->phase(Store::as('CHANGED_FILES', '[]'))
            ->phase(Store::as('FILE_BACKUPS', '{} — map of file_path → original_content for rollback via Edit/Write'))

            // 5.9 Final parallel conflict check (re-fetch siblings — comments may have updated since phase 1.25)
            ->phase(Operator::if(Store::get('TASK') . '.parallel === true', [
                VectorTaskMcp::call('task_list', '{parent_id: $TASK.parent_id, limit: 20}') . ' → re-fetch siblings with fresh comments',
                Store::as('ACTIVE_SIBLINGS', 'filter: parallel=true AND id != $TASK.id AND status=in_progress → {id, title, comment}'),
                'Extract "PARALLEL SCOPE: [...]" from each ACTIVE_SIBLINGS comment → ' . Store::as('SIBLING_SCOPES', '{updated sibling_id → [files]}'),
                'Cross-reference ' . Store::get('MY_FILE_SCOPE') . ' vs ' . Store::get('SIBLING_SCOPES') . ' (active only) → ' . Store::as('SHARED_FILES', '{overlapping files — FORBIDDEN}'),
                Operator::if(Store::get('SHARED_FILES') . ' not empty', 'WARN: "SHARED FILES with active siblings: {SHARED_FILES}. DO NOT edit. Record as SCOPE EXTENSION NEEDED."'),
                Operator::if(Store::get('SHARED_FILES') . ' empty', 'No conflicts with active siblings. Proceed.'),
            ]))

            // 6. Execute with tracking (NEVER use git checkout/stash/restore for rollback)
            ->phase(Operator::forEach('step in ' . Store::get('PLAN'), [
                Store::as('CURRENT_STEP', '{step_index}'),
                ReadTool::call('{step.file}') . ' → save content to ' . Store::get('FILE_BACKUPS') . '[{step.file}]',
                EditTool::call('{step.file}', '{old}', '{new}') . ' OR ' . WriteTool::call('{step.file}', '{content}'),
                'Append {step.file} to ' . Store::get('CHANGED_FILES'),
                Operator::if('step fails', [
                    'Retry up to 2 times with adjusted approach',
                    Operator::if('still fails', [
                        Operator::if('$HAS_AUTO_APPROVE AND previous steps changed files', [
                            'Rollback via Write: for each file in ' . Store::get('CHANGED_FILES') . ' → Write(file, ' . Store::get('FILE_BACKUPS') . '[file])',
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Failed at step N: {error}. Rolled back via Write.", append_comment: true}'),
                            VectorMemoryMcp::call('store_memory', '{content: "FAILURE: Task #{id}, step {N}, error: {msg}, attempted: {fixes}", category: "' . self::CAT_DEBUGGING . '"}'),
                            Operator::abort('Step failed, rolled back via Write (no git commands)'),
                        ]),
                        Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Step N failed: {error}. Retry/Skip/Rollback(via Write)/Abort?"'),
                    ]),
                ]),
                'Update task.comment with execution_state JSON for recovery',
            ]))

            // 7. Post-execution validation
            ->phase('7.1 SYNTAX CHECK: Run language-specific syntax validator on ' . Store::get('CHANGED_FILES'))
            ->phase(Operator::if('syntax errors', [
                'Attempt auto-fix (max 2 tries)',
                Operator::if('still errors', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Rollback + mark pending'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'Show errors, ask for guidance'),
                ]),
            ]))

            ->phase('7.1.5 HALLUCINATION CHECK: Verify all method/class/function calls in ' . Store::get('CHANGED_FILES') . ' reference REAL code. Read source to confirm methods exist with correct signatures.')
            ->phase(Operator::if('non-existent method/class found', 'Fix: replace with actual method from source. Re-read target file to find correct API.'))

            ->phase('7.2 LINTER: Run project linter if configured')
            ->phase(Operator::if('linter errors', [
                Operator::if('$HAS_AUTO_APPROVE', 'Auto-fix if possible (--fix flag)'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'Show issues, ask "Auto-fix/Manual/Ignore?"'),
                Operator::if('cannot auto-fix critical errors', 'Fix manually or rollback'),
            ]))

            ->phase('7.2.5 LOGIC VERIFICATION: Review each changed function in ' . Store::get('CHANGED_FILES') . '. For each: what happens with null input? empty collection? boundary value (0, -1, MAX)? error path? off-by-one?')
            ->phase(Operator::if('logic issues found', 'Fix immediately: add guards, fix boundaries, handle edge cases'))

            ->phase('7.2.6 PERFORMANCE REVIEW: Check ' . Store::get('CHANGED_FILES') . ' for: nested loops over data (O(n²)), query/I/O inside loops (N+1), loading full datasets without pagination, unnecessary serialization')
            ->phase(Operator::if('performance anti-pattern found', 'Refactor: batch queries, optimize algorithm, add pagination. Re-run syntax check after fix.'))

            ->phase('7.3 TESTS: Detect related test files for ' . Store::get('CHANGED_FILES') . ' (scoped, NEVER full suite)')
            ->phase(Store::as('RELATED_TESTS', 'test files in same dir, *Test suffix, test/ mirror — ONLY for CHANGED_FILES'))
            ->phase(Operator::if(Store::get('RELATED_TESTS') . ' exist', [
                Operator::if('$HAS_AUTO_APPROVE', 'Run ONLY related tests with --filter or specific paths'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Run related tests? Files: {list}"'),
                Operator::if('tests fail', [
                    'Analyze failure, attempt fix (max 2 tries)',
                    Operator::if('still fails', [
                        Operator::if('$HAS_AUTO_APPROVE', [
                            VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "pending", comment: "Tests failing: {failures}", append_comment: true}'),
                            Operator::abort('Tests fail, task marked pending'),
                        ]),
                        Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Tests fail. Fix/Skip/Rollback?"'),
                    ]),
                ]),
                'Check coverage: existing tests cover >=80% of changed code? Critical paths 100%?',
                Operator::if('coverage insufficient', [
                    'WRITE additional tests to reach threshold: >=80%, critical paths 100%',
                    'Follow existing test patterns, meaningful assertions, edge cases',
                    'Run new tests to verify passing',
                ]),
            ]))
            ->phase(Operator::if(Store::get('RELATED_TESTS') . ' empty (NO tests for changed code)', [
                'WRITE TESTS for ' . Store::get('CHANGED_FILES') . ' — validator expects >=80% coverage',
                'Detect test framework from project (existing tests, config files, test runner)',
                'Follow existing test patterns: directory structure, naming conventions, base test classes',
                'Write tests with: meaningful assertions, edge cases (null, empty, boundary, error paths)',
                'Target: >=80% coverage, critical paths 100%',
                'Run written tests to verify passing',
                Operator::if('written tests fail', [
                    'Fix test or implementation (max 2 tries)',
                    Operator::if('still fails', [
                        Operator::if('$HAS_AUTO_APPROVE', 'Mark in comment: "Tests written but failing: {details}". Continue.'),
                        Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Written tests fail. Fix/Skip tests/Rollback?"'),
                    ]),
                ]),
                'Append test files to ' . Store::get('CHANGED_FILES'),
            ]))

            // 7.4 CLEANUP: Remove artifacts from changes
            ->phase('7.4 CLEANUP: Scan ' . Store::get('CHANGED_FILES') . ' for: unused imports/use/require, dead code from refactoring, orphaned helpers no longer called, commented-out blocks')
            ->phase(Operator::if('cleanup needed', 'Remove dead code, re-run syntax check on cleaned files'))

            // 8. Complete
            ->phase(VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "Done. Files: {changed_files}. Tests: {pass/skip/none}.", append_comment: true}'))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Task #{id}: {approach}, files: {list}, patterns used, learnings", category: "' . self::CAT_CODE_SOLUTION . '"}'));

        // TDD mode
        $this->guideline('tdd-mode')->example()
            ->phase(Operator::if('task.comment contains "TDD MODE" AND status=tested', 'Execute implementation based on task.content'))
            ->phase('Implement feature following existing code patterns')
            ->phase('Run tests: detect test framework from project (jest, pytest, phpunit, pest, cargo test, go test, etc.)')
            ->phase(Operator::if('all tests pass', [
                VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, status: "completed", comment: "TDD: All tests PASSED", append_comment: true}'),
                VectorMemoryMcp::call('store_memory', '{content: "TDD success: {feature}, implementation approach: {summary}", category: "' . self::CAT_CODE_SOLUTION . '"}'),
            ]))
            ->phase(Operator::if('tests fail', [
                'Analyze failure: assertion error vs exception vs timeout',
                'Implement fix based on test expectation',
                'Re-run tests (max 5 iterations)',
                Operator::if('still failing after 5 iterations', [
                    VectorTaskMcp::call('task_update', '{task_id: $VECTOR_TASK_ID, comment: "TDD stuck: {failing_tests}. Need guidance.", append_comment: true}'),
                    Operator::if('$HAS_AUTO_APPROVE', Operator::abort('TDD: Cannot pass tests after 5 iterations')),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Cannot pass tests. Show failures for manual review?"'),
                ]),
            ]));

        // Error handling
        $this->guideline('error-handling')->example()
            ->phase(Operator::if('task not found', Operator::abort('suggest task_list or task_create')))
            ->phase(Operator::if('task already completed AND -y', Operator::abort('Already completed')))
            ->phase(Operator::if('task already completed AND no -y', 'ask "Re-execute completed task?"'))
            ->phase(Operator::if('research triggers matched but context7 empty AND web-research empty', [
                Operator::if('$HAS_AUTO_APPROVE', 'Proceed with best-effort based on existing codebase patterns'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'Ask user for clarification with specific questions'),
            ]))
            ->phase(Operator::if('multiple research options, user chose "other"', 'Ask for details, incorporate into plan'))
            ->phase(Operator::if('file not found for edit', [
                Operator::if('$HAS_AUTO_APPROVE AND file should exist', Operator::abort('Expected file not found: {path}')),
                Operator::if('$HAS_AUTO_APPROVE AND new file needed', 'Create file with Write'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "File not found. Create/Specify path/Abort?"'),
            ]))
            ->phase(Operator::if('edit conflict (old_string not found)', [
                'Re-read file to get current content',
                'Adjust old_string to match current state',
                'Retry edit (max 3 attempts)',
                Operator::if('3 failures', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Use Write to replace entire file if safe'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Cannot edit. Show diff for manual resolution?"'),
                ]),
            ]))
            ->phase(Operator::if('user rejects plan', 'Accept modifications, rebuild plan, re-present'))
            ->phase(Operator::if('partial implementation AND tempted to ask "keep/rewrite/both"', [
                'STOP. This is FORBIDDEN question.',
                'Read documentation again',
                'Documentation defines target state',
                'Implement REMAINING parts per docs',
                'NEVER ask about non-existent alternatives',
            ]))
            ->phase(Operator::if('dependency install fails', [
                'Check: network, permissions, version conflicts',
                Operator::if('$HAS_AUTO_APPROVE', VectorTaskMcp::call('task_update', '{status: "pending", comment: "Dependency install failed: {error}"}') . ' + abort'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Install failed: {error}. Retry/Skip dependency/Abort?"'),
            ]))
            ->phase(Operator::if('syntax check fails after edit', [
                'Parse error message, identify line/column',
                'Attempt fix (missing semicolon, bracket, import, etc.)',
                'Re-check (max 2 attempts)',
                Operator::if('still fails', 'Rollback file, report syntax error'),
            ]))
            ->phase(Operator::if('linter finds critical issues', [
                Operator::if('auto-fixable', 'Run linter --fix'),
                Operator::if('not auto-fixable', [
                    Operator::if('$HAS_AUTO_APPROVE', 'Add TODO comment, proceed with warning'),
                    Operator::if('NOT $HAS_AUTO_APPROVE', 'Show issues, ask for action'),
                ]),
            ]))
            ->phase(Operator::if('timeout on long operation', [
                Operator::if('$HAS_AUTO_APPROVE', 'Skip with warning, continue'),
                Operator::if('NOT $HAS_AUTO_APPROVE', 'ask "Operation timed out. Wait longer/Skip/Abort?"'),
            ]));
    }
}