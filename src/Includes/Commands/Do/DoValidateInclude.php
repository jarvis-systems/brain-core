<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Do;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Text-based work validation with parallel agent orchestration. Accepts text description (example: "validate user authentication"). Validates work against documentation requirements, code consistency, and completeness. Creates follow-up tasks for gaps. Idempotent. For vector task validation use /task:validate.')]
class DoValidateInclude extends IncludeArchetype
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
        $this->defineEntryPointBlockingRule('VALIDATE');

        // Iron Rules - Zero Tolerance
        $this->defineValidationOnlyNoExecutionRule();

        $this->defineTextDescriptionRequiredRule('validate', '/task:validate');

        $this->defineParallelAgentOrchestrationRule();

        $this->defineIdempotentValidationRule('tasks');

        $this->defineNoDirectFixesRule();

        $this->defineVectorMemoryMandatoryRule('ALL validation results');

        // Phase Execution Sequence - STRICT ORDERING
        $this->definePhaseSequenceRules(7);

        $this->defineOutputStatusReportRule();

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->defineInputCaptureWithAutoApproveGuideline();

        // Phase 0: Context Setup
        $this->definePhase0ContextSetupGuideline('VALIDATE', '/task:validate');

        // Phase 1: Agent Discovery and Validation Scope Preview
        $this->guideline('phase1-context-preview')
            ->goal('Discover available agents and present validation scope for approval')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 1: VALIDATION PREVIEW ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents with capabilities'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agent_id: description mapping}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from ' . Store::var('TASK_DESCRIPTION') . '}'), 'Get documentation INDEX preview'))
            ->phase(Store::as('DOCS_PREVIEW', 'Documentation files available'))
            ->phase(Operator::output([
                'Task: {' . Store::var('TASK_DESCRIPTION') . '}',
                'Available agents: {' . Store::var('AVAILABLE_AGENTS.count') . '}',
                'Documentation files: {' . Store::var('DOCS_PREVIEW.count') . '}',
                '',
                'Validation will delegate to agents:',
                '1. VectorMaster - deep memory research for context',
                '2. DocumentationMaster - requirements extraction',
                '3. Selected agents - parallel validation (5 aspects)',
                '',
                '⚠️  APPROVAL REQUIRED',
                '✅ approved/yes - start validation | ❌ no/modifications',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['✅ Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]));

        // Phase 2: Deep Context Gathering via VectorMaster Agent
        $this->guideline('phase2-context-gathering')
            ->goal('Delegate deep memory research to VectorMaster agent')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: DEEP CONTEXT GATHERING ===',
                'Delegating to VectorMaster for deep memory research...',
            ]))
            ->phase('SELECT vector-master from ' . Store::var('AVAILABLE_AGENTS'))
            ->phase(Store::as('CONTEXT_AGENT', '{vector-master agent_id}'))
            ->phase(TaskTool::agent('{' . Store::var('CONTEXT_AGENT') . '}', 'DEEP MEMORY RESEARCH for validation of "' . Store::var('TASK_DESCRIPTION') . '": 1) Multi-probe search: implementation patterns, requirements, architecture decisions, past validations, bug fixes 2) Search across categories: code-solution, architecture, learning, bug-fix 3) Extract actionable insights for validation 4) Return: {implementations: [...], requirements: [...], patterns: [...], past_validations: [...], key_insights: [...]}. Store consolidated context.'))
            ->phase(Store::as('MEMORY_CONTEXT', '{VectorMaster agent results}'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "' . Store::var('TASK_DESCRIPTION') . '", limit: 10, category: "code-solution"}'))
            ->phase(Store::as('RELATED_SOLUTIONS', 'Related solutions from memory'))
            ->phase(Operator::output([
                'Context gathered via {' . Store::var('CONTEXT_AGENT') . '}:',
                '- Memory insights: {' . Store::var('MEMORY_CONTEXT.key_insights.count') . '}',
                '- Related solutions: {' . Store::var('RELATED_SOLUTIONS.count') . '}',
            ]));

        // Phase 3: Documentation Requirements Extraction
        $this->guideline('phase3-documentation-extraction')
            ->goal('Extract ALL requirements from .docs/ via DocumentationMaster')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: DOCUMENTATION REQUIREMENTS ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from ' . Store::var('TASK_DESCRIPTION') . '}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', 'Documentation file paths'))
            ->phase(Operator::if('{' . Store::var('DOCS_INDEX') . '} not empty', [
                TaskTool::agent('documentation-master', 'Extract ALL requirements, acceptance criteria, constraints, and specifications from documentation files: {' . Store::var('DOCS_INDEX') . ' paths}. Return structured list: [{requirement_id, description, acceptance_criteria, related_files, priority}]. Store to vector memory.'),
                Store::as('DOCUMENTATION_REQUIREMENTS', '{structured requirements list}'),
            ]))
            ->phase(Operator::if('{' . Store::var('DOCS_INDEX') . '} empty', [
                Store::as('DOCUMENTATION_REQUIREMENTS', '[]'),
                Operator::output(['WARNING: No documentation found. Validation will be limited.']),
            ]))
            ->phase(Operator::output([
                'Requirements extracted: {' . Store::var('DOCUMENTATION_REQUIREMENTS.count') . '}',
                '{requirements summary}',
            ]));

        // Phase 4: Dynamic Agent Selection and Parallel Validation
        $this->guideline('phase4-parallel-validation')
            ->goal('Select best agents from ' . Store::var('AVAILABLE_AGENTS') . ' and launch parallel validation')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 4: PARALLEL VALIDATION ===',
            ]))
            ->phase('AGENT SELECTION: Analyze ' . Store::var('AVAILABLE_AGENTS') . ' descriptions and select BEST agent for each validation aspect:')
            ->phase(Operator::do([
                'ASPECT 1 - COMPLETENESS: Select agent best suited for requirements verification (vector-master for memory research, explore for codebase)',
                'ASPECT 2 - CODE CONSISTENCY: Select agent for code pattern analysis (explore for codebase scanning)',
                'ASPECT 3 - TEST COVERAGE: Select agent for test analysis (explore for test file discovery)',
                'ASPECT 4 - DOCUMENTATION SYNC: Select agent for documentation analysis (documentation-master if docs-focused, explore otherwise)',
                'ASPECT 5 - DEPENDENCIES: Select agent for dependency analysis (explore for import scanning)',
            ]))
            ->phase(Store::as('SELECTED_AGENTS', '{aspect: agent_id mapping based on ' . Store::var('AVAILABLE_AGENTS') . '}'))
            ->phase(Operator::output([
                'Selected agents for validation:',
                '{' . Store::var('SELECTED_AGENTS') . ' mapping}',
                '',
                'Launching validation agents in parallel...',
            ]))
            ->phase('PARALLEL BATCH: Launch selected agents simultaneously with DEEP RESEARCH tasks')
            ->phase(Operator::do([
                TaskTool::agent('{' . Store::var('SELECTED_AGENTS.completeness') . '}', 'DEEP RESEARCH - COMPLETENESS: For "' . Store::var('TASK_DESCRIPTION') . '": 1) Search vector memory for past implementations and requirements 2) Scan codebase for implementation evidence 3) Map each requirement from {' . Store::var('DOCUMENTATION_REQUIREMENTS') . '} to code 4) Return: [{requirement_id, status: implemented|partial|missing, evidence: file:line, memory_refs: [...]}]. Store findings.'),
                TaskTool::agent('{' . Store::var('SELECTED_AGENTS.consistency') . '}', 'DEEP RESEARCH - CODE CONSISTENCY: For "' . Store::var('TASK_DESCRIPTION') . '": 1) Search memory for project coding standards 2) Scan related files for pattern violations 3) Check naming, architecture, style consistency 4) Return: [{file, issue_type, severity, description, suggestion}]. Store findings.'),
                TaskTool::agent('{' . Store::var('SELECTED_AGENTS.tests') . '}', 'DEEP RESEARCH - TEST COVERAGE: For "' . Store::var('TASK_DESCRIPTION') . '": 1) Search memory for test patterns 2) Discover all related test files 3) Analyze coverage gaps 4) Run tests if possible 5) Return: [{test_file, coverage_status, missing_scenarios}]. Store findings.'),
                TaskTool::agent('{' . Store::var('SELECTED_AGENTS.docs') . '}', 'DEEP RESEARCH - DOCUMENTATION SYNC: For "' . Store::var('TASK_DESCRIPTION') . '": 1) Search memory for documentation standards 2) Compare code vs documentation 3) Check docblocks, README, API docs 4) Return: [{doc_type, sync_status, gaps}]. Store findings.'),
                TaskTool::agent('{' . Store::var('SELECTED_AGENTS.deps') . '}', 'DEEP RESEARCH - DEPENDENCIES: For "' . Store::var('TASK_DESCRIPTION') . '": 1) Search memory for dependency issues 2) Scan imports and dependencies 3) Check for broken/unused/circular refs 4) Return: [{file, dependency_issue, severity}]. Store findings.'),
            ]))
            ->phase(Store::as('VALIDATION_BATCH', '{results from all agents}'))
            ->phase(Operator::output([
                'Batch complete: {' . Store::var('SELECTED_AGENTS.count') . '} validation checks finished',
            ]));

        // Phase 5: Results Aggregation and Analysis
        $this->guideline('phase5-results-aggregation')
            ->goal('Aggregate all validation results and categorize issues')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 5: RESULTS AGGREGATION ===',
            ]))
            ->phase('Merge results from all validation agents')
            ->phase(Store::as('ALL_ISSUES', '{merged issues from all agents}'))
            ->phase('Categorize issues:')
            ->phase(Store::as('CRITICAL_ISSUES', '{issues with severity: critical}'))
            ->phase(Store::as('MAJOR_ISSUES', '{issues with severity: major}'))
            ->phase(Store::as('MINOR_ISSUES', '{issues with severity: minor}'))
            ->phase(Store::as('MISSING_REQUIREMENTS', '{requirements not implemented}'))
            ->phase(Operator::output([
                'Validation results:',
                '- Critical issues: {' . Store::var('CRITICAL_ISSUES.count') . '}',
                '- Major issues: {' . Store::var('MAJOR_ISSUES.count') . '}',
                '- Minor issues: {' . Store::var('MINOR_ISSUES.count') . '}',
                '- Missing requirements: {' . Store::var('MISSING_REQUIREMENTS.count') . '}',
            ]));

        // Phase 6: Task Creation for ALL Issues (Consolidated 5-8h Tasks)
        $this->guideline('phase6-task-creation')
            ->goal('Create consolidated tasks (5-8h each) for issues with comprehensive context')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 6: TASK CREATION (CONSOLIDATED) ===',
            ]))
            ->phase('Check existing tasks in memory to avoid duplicates')
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "fix issues ' . Store::var('TASK_DESCRIPTION') . '", limit: 20, category: "code-solution"}'))
            ->phase(Store::as('EXISTING_FIX_MEMORIES', 'Existing fix task memories'))
            ->phase('CONSOLIDATION STRATEGY: Group issues into 5-8 hour task batches')
            ->phase(Operator::do([
                'Calculate total estimate for ALL issues:',
                '- Critical issues: ~2h per issue (investigation + fix + test)',
                '- Major issues: ~1.5h per issue (fix + verify)',
                '- Minor issues: ~0.5h per issue (fix + verify)',
                '- Missing requirements: ~4h per requirement (implement + test)',
                Store::as('TOTAL_ESTIMATE', '{sum of all issue estimates in hours}'),
            ]))
            ->phase(Operator::if('{' . Store::var('TOTAL_ESTIMATE') . '} <= 8', [
                'ALL issues fit into ONE consolidated task (5-8h range)',
                Operator::if('({' . Store::var('CRITICAL_ISSUES.count') . '} + {' . Store::var('MAJOR_ISSUES.count') . '} + {' . Store::var('MINOR_ISSUES.count') . '} + {' . Store::var('MISSING_REQUIREMENTS.count') . '}) > 0 AND NOT exists similar in ' . Store::var('EXISTING_FIX_MEMORIES'), [
                    VectorMemoryMcp::call('store_memory', '{content: "Validation fix task: ' . Store::var('TASK_DESCRIPTION') . '\\n\\nTotal estimate: {' . Store::var('TOTAL_ESTIMATE') . '}h\\n\\n## Critical Issues ({' . Store::var('CRITICAL_ISSUES.count') . '})\\n{FOR each issue: - [{issue.severity}] {issue.description}\\n  File: {issue.file}:{issue.line}\\n  Type: {issue.type}\\n  Suggestion: {issue.suggestion}\\n}\\n\\n## Major Issues ({' . Store::var('MAJOR_ISSUES.count') . '})\\n{FOR each issue: - [{issue.severity}] {issue.description}\\n  File: {issue.file}:{issue.line}\\n}\\n\\n## Minor Issues ({' . Store::var('MINOR_ISSUES.count') . '})\\n{FOR each issue: - [{issue.severity}] {issue.description}\\n}\\n\\n## Missing Requirements ({' . Store::var('MISSING_REQUIREMENTS.count') . '})\\n{FOR each req: - {req.description}\\n  Acceptance criteria: {req.acceptance_criteria}\\n}\\n\\n## Context References\\n- Memory IDs: {' . Store::var('MEMORY_CONTEXT.memory_ids') . '}\\n- Documentation: {' . Store::var('DOCS_INDEX.paths') . '}\\n- Validation agents used: {' . Store::var('SELECTED_AGENTS') . '}", category: "code-solution", tags: ["validation-fix", "consolidated", "do:validate"]}'),
                    Store::as('CREATED_TASKS[]', '{memory_id}'),
                    Operator::output(['Created consolidated fix task in memory ({' . Store::var('TOTAL_ESTIMATE') . '}h, {issues_count} issues)']),
                ]),
            ]))
            ->phase(Operator::if('{' . Store::var('TOTAL_ESTIMATE') . '} > 8', [
                'Split into multiple 5-8h task batches',
                Store::as('BATCH_SIZE', '6'),
                Store::as('NUM_BATCHES', '{ceil(' . Store::var('TOTAL_ESTIMATE') . ' / 6)}'),
                'Group issues by priority (critical first) into batches of ~6h each',
                Operator::forEach('batch_index in range(1, ' . Store::var('NUM_BATCHES') . ')', [
                    Store::as('BATCH_ISSUES', '{slice of issues for this batch, ~6h worth, priority-ordered}'),
                    Store::as('BATCH_ESTIMATE', '{sum of batch issue estimates}'),
                    Store::as('BATCH_CRITICAL', '{count of critical issues in batch}'),
                    Store::as('BATCH_MAJOR', '{count of major issues in batch}'),
                    Store::as('BATCH_MISSING', '{count of missing requirements in batch}'),
                    Operator::if('NOT exists similar in ' . Store::var('EXISTING_FIX_MEMORIES'), [
                        VectorMemoryMcp::call('store_memory', '{content: "Validation fix batch {batch_index}/{' . Store::var('NUM_BATCHES') . '}: ' . Store::var('TASK_DESCRIPTION') . '\\n\\nBatch estimate: {' . Store::var('BATCH_ESTIMATE') . '}h\\nBatch composition: {' . Store::var('BATCH_CRITICAL') . '} critical, {' . Store::var('BATCH_MAJOR') . '} major, {' . Store::var('BATCH_MISSING') . '} missing reqs\\n\\n## Issues in this batch\\n{FOR each issue in ' . Store::var('BATCH_ISSUES') . ':\\n### [{issue.severity}] {issue.title}\\n- File: {issue.file}:{issue.line}\\n- Description: {issue.description}\\n- Suggestion: {issue.suggestion}\\n}\\n\\n## Context References\\n- Memory IDs: {' . Store::var('MEMORY_CONTEXT.memory_ids') . '}\\n- Documentation: {' . Store::var('DOCS_INDEX.paths') . '}\\n- Total batches: {' . Store::var('NUM_BATCHES') . '} ({' . Store::var('TOTAL_ESTIMATE') . '}h total)\\n- Validation agents: {' . Store::var('SELECTED_AGENTS') . '}", category: "code-solution", tags: ["validation-fix", "batch-{batch_index}", "do:validate"]}'),
                        Store::as('CREATED_TASKS[]', '{memory_id}'),
                        Operator::output(['Created batch {batch_index}/{' . Store::var('NUM_BATCHES') . '}: {' . Store::var('BATCH_ESTIMATE') . '}h ({' . Store::var('BATCH_ISSUES.count') . '} issues)']),
                    ]),
                ]),
            ]))
            ->phase(Operator::output([
                'Fix tasks created: {' . Store::var('CREATED_TASKS.count') . '} (total estimate: {' . Store::var('TOTAL_ESTIMATE') . '}h)',
            ]));

        // Task Consolidation Rules
        $this->rule('task-size-5-8h')->high()
            ->text('Each created task MUST have estimate between 5-8 hours. Never create tasks < 5h (consolidate) or > 8h (split).')
            ->why('Optimal task size for focused work sessions. Too small = context switching overhead. Too large = hard to track progress.')
            ->onViolation('Merge small issues into consolidated task OR split large task into 5-8h batches.');

        $this->rule('task-comprehensive-context')->critical()
            ->text('Each task MUST include: all file:line references, memory IDs, documentation paths, detailed issue descriptions with suggestions, evidence from validation.')
            ->why('Enables full context restoration without re-exploration. Saves agent time on task pickup.')
            ->onViolation('Add missing context references before creating task.');

        // Phase 7: Validation Completion
        $this->guideline('phase7-completion')
            ->goal('Complete validation, store summary to memory')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 7: VALIDATION COMPLETE ===',
            ]))
            ->phase(Store::as('VALIDATION_SUMMARY', '{all_issues_count, tasks_created_count, pass_rate}'))
            ->phase(Store::as('VALIDATION_STATUS', Operator::if('{' . Store::var('CRITICAL_ISSUES.count') . '} === 0 AND {' . Store::var('MISSING_REQUIREMENTS.count') . '} === 0', 'PASSED', 'NEEDS_WORK')))
            ->phase(VectorMemoryMcp::call('store_memory', '{content: "Validation of ' . Store::var('TASK_DESCRIPTION') . '\\n\\nStatus: {' . Store::var('VALIDATION_STATUS') . '}\\nCritical: {' . Store::var('CRITICAL_ISSUES.count') . '}\\nMajor: {' . Store::var('MAJOR_ISSUES.count') . '}\\nMinor: {' . Store::var('MINOR_ISSUES.count') . '}\\nTasks created: {' . Store::var('CREATED_TASKS.count') . '}\\n\\nFindings:\\n{summary of key findings}", category: "code-solution", tags: ["validation", "audit", "do:validate"]}'))
            ->phase(Operator::output([
                '',
                '=== VALIDATION REPORT ===',
                'Task: {' . Store::var('TASK_DESCRIPTION') . '}',
                'Status: {' . Store::var('VALIDATION_STATUS') . '}',
                '',
                '| Metric | Count |',
                '|--------|-------|',
                '| Critical issues | {' . Store::var('CRITICAL_ISSUES.count') . '} |',
                '| Major issues | {' . Store::var('MAJOR_ISSUES.count') . '} |',
                '| Minor issues | {' . Store::var('MINOR_ISSUES.count') . '} |',
                '| Missing requirements | {' . Store::var('MISSING_REQUIREMENTS.count') . '} |',
                '| Fix tasks created | {' . Store::var('CREATED_TASKS.count') . '} |',
                '',
                '{IF ' . Store::var('CREATED_TASKS.count') . ' > 0: "Follow-up fix memories: {' . Store::var('CREATED_TASKS') . '}"}',
                '',
                'Validation stored to vector memory.',
            ]));

        // Error Handling
        $this->defineErrorHandlingGuideline(
            includeAgentErrors: true,
            includeDocErrors: true,
            isValidation: true
        );

        // Constraints and Validation
        $this->guideline('constraints')
            ->text('Validation constraints and limits')
            ->example()
            ->phase('Max 6 parallel validation agents per batch')
            ->phase('Max 20 fix tasks created per validation run')
            ->phase('Validation timeout: 5 minutes per agent')
            ->phase(Operator::verify([
                'text_description_validated = true',
                'parallel_agents_used = true',
                'documentation_checked = true',
                'results_stored_to_memory = true',
                'no_direct_fixes = true',
            ]));

        // Examples
        $this->guideline('example-text-validation')
            ->scenario('Validate work by text description')
            ->example()
            ->phase('input', '"validate user authentication implementation"')
            ->phase('flow', 'Context from memory → Docs requirements → Parallel Validation → Aggregate → Store Findings → Report')
            ->phase('result', 'Validation report with findings and fix task memories');

        $this->guideline('example-feature-validation')
            ->scenario('Validate specific feature')
            ->example()
            ->phase('input', '"validate payment processing module"')
            ->phase('flow', 'Search memory for payment patterns → Docs → 5 parallel agents → Aggregate → Create fix tasks → Report')
            ->phase('result', 'Validation PASSED/NEEDS_WORK, N fix task memories created');

        $this->guideline('example-rerun')
            ->scenario('Re-run validation (idempotent)')
            ->example()
            ->phase('input', '"validate user authentication" (already validated before)')
            ->phase('behavior', 'Skips existing memories, only creates NEW issues found')
            ->phase('result', 'Same/updated validation report, no duplicate memories');

        // When to use do:validate vs task:validate
        $this->defineCommandSelectionGuideline(
            '/do:validate',
            '/task:validate',
            'Text-based validation ("validate user authentication"). Best for: ad-hoc validation, exploratory checks, no existing vector task.',
            'Vector task validation (15, #15, task 15). Best for: systematic task workflow, hierarchical task management, fix task creation as children.'
        );

        // Response Format
        $this->defineResponseFormatGuideline('=== headers | Parallel: agent batch indicators | Tables: validation results | No filler | Created memories listed');
    }
}
