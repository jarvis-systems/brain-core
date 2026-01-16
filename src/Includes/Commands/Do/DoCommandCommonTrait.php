<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Do;

use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\TaskTool;
use BrainNode\Mcp\VectorMemoryMcp;

/**
 * Common patterns extracted from Do command includes.
 * Provides reusable rule and guideline definitions for do-related commands.
 *
 * Patterns identified across 4 Do command files:
 * - Input capture (RAW_INPUT, HAS_Y_FLAG/HAS_AUTO_APPROVE, TASK_DESCRIPTION/VALIDATION_TARGET)
 * - Entry point blocking rule
 * - Zero distractions rule
 * - Auto-approval handling
 * - Vector memory mandatory rule
 * - Error handling
 * - Response format
 *
 * DoValidateInclude + DoTestValidateInclude share ~85% structure:
 * - Parallel agent orchestration rule
 * - Idempotent validation rule
 * - Text description required rule (rejects task IDs)
 * - Phase 0 context setup
 * - Agent discovery phases
 * - Deep context gathering via VectorMaster
 * - Documentation extraction
 * - Results aggregation
 */
trait DoCommandCommonTrait
{
    // =========================================================================
    // INPUT CAPTURE PATTERNS
    // =========================================================================

    /**
     * Define input capture guideline for do:async/do:sync commands.
     * Captures $RAW_INPUT, $HAS_Y_FLAG, $TASK_DESCRIPTION
     */
    protected function defineInputCaptureWithYFlagGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_Y_FLAG', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('TASK_DESCRIPTION', '{$RAW_INPUT with flags removed}'));
    }

    /**
     * Define input capture guideline for do:validate/do:test-validate commands.
     * Captures $RAW_INPUT, $HAS_AUTO_APPROVE, $VALIDATION_TARGET
     */
    protected function defineInputCaptureWithAutoApproveGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('VALIDATION_TARGET', '{target to validate extracted from $RAW_INPUT}'));
    }

    // =========================================================================
    // COMMON RULES - ALL DO COMMANDS
    // =========================================================================

    /**
     * Define entry-point-blocking rule.
     * Used by: DoAsyncInclude, DoValidateInclude, DoTestValidateInclude
     *
     * @param string $commandType Command type for header (e.g., 'ASYNC', 'VALIDATE', 'TEST-VALIDATE')
     */
    protected function defineEntryPointBlockingRule(string $commandType): void
    {
        $this->rule('entry-point-blocking')->critical()
            ->text('ON RECEIVING $RAW_INPUT: Your FIRST output MUST be "=== DO:'.$commandType.' ACTIVATED ===" followed by Phase 0. ANY other first action is VIOLATION. FORBIDDEN first actions: Glob, Grep, Read, Edit, Write, WebSearch, WebFetch, Bash (except brain list:masters), code generation, file analysis.')
            ->why('Without explicit entry point, Brain skips workflow and executes directly. Entry point forces workflow compliance.')
            ->onViolation('STOP IMMEDIATELY. Delete any tool calls. Output "=== DO:'.$commandType.' ACTIVATED ===" and restart from Phase 0.');
    }

    /**
     * Define zero-distractions rule.
     * Used by: DoAsyncInclude, DoSyncInclude
     */
    protected function defineZeroDistractionsRule(): void
    {
        $this->rule('zero-distractions')->critical()
            ->text('ZERO distractions - implement ONLY specified task from $TASK_DESCRIPTION. NO creative additions, NO unapproved features, NO scope creep.')
            ->why('Ensures focused execution and prevents feature drift')
            ->onViolation('Abort immediately. Return to approved plan.');
    }

    /**
     * Define vector-memory-mandatory rule.
     * Used by: DoAsyncInclude, DoValidateInclude, DoTestValidateInclude
     *
     * @param string $subjectType What should use vector memory (e.g., 'ALL agents', 'ALL validation results')
     */
    protected function defineVectorMemoryMandatoryRule(string $subjectType = 'ALL agents'): void
    {
        $this->rule('vector-memory-mandatory')->high()
            ->text($subjectType.' MUST search vector memory BEFORE task execution AND store learnings AFTER completion. Vector memory is the primary communication channel between sequential agents.')
            ->why('Enables knowledge sharing between agents, prevents duplicate work, maintains execution continuity across steps')
            ->onViolation('Include explicit vector memory instructions in agent Task() delegation.');
    }

    // =========================================================================
    // VALIDATION-SPECIFIC RULES
    // =========================================================================

    /**
     * Define text-description-required rule (rejects task IDs).
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $commandName Command name for message (e.g., 'validate', 'test-validate')
     * @param string $alternativeCommand Alternative command for task IDs (e.g., '/task:validate')
     */
    protected function defineTextDescriptionRequiredRule(string $commandName, string $alternativeCommand): void
    {
        $this->rule('text-description-required')->critical()
            ->text('$RAW_INPUT MUST be a text description of work to '.$commandName.'. Optional flags (-y, --yes) may be appended. Extract flags first, then verify remaining text is NOT a task ID pattern (15, #15, task 15). Examples: "'.$commandName.' auth -y", "check user module --yes".')
            ->why('This command is exclusively for text-based validation. Vector task validation belongs to '.$alternativeCommand.'.')
            ->onViolation('STOP. Report: "For vector task validation, use '.$alternativeCommand.' {id}. This command accepts text descriptions only."');
    }

    /**
     * Define parallel-agent-orchestration rule.
     * Used by: DoValidateInclude, DoTestValidateInclude
     */
    protected function defineParallelAgentOrchestrationRule(): void
    {
        $this->rule('parallel-agent-orchestration')->high()
            ->text('Validation phases MUST use parallel agent orchestration (5-6 agents simultaneously) for efficiency. Each agent validates one aspect.')
            ->why('Parallel validation reduces time and maximizes coverage.')
            ->onViolation('Restructure validation into parallel Task() calls.');
    }

    /**
     * Define idempotent-validation rule.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $duplicateType What to check for duplicates (e.g., 'tasks', 'entries')
     */
    protected function defineIdempotentValidationRule(string $duplicateType = 'tasks'): void
    {
        $this->rule('idempotent-validation')->high()
            ->text('Validation is IDEMPOTENT. Running multiple times produces same result (no duplicate '.$duplicateType.', no repeated fixes).')
            ->why('Allows safe re-runs without side effects.')
            ->onViolation('Check existing '.$duplicateType.' before creating. Skip duplicates.');
    }

    /**
     * Define validation-only-no-execution rule.
     * Used by: DoValidateInclude
     */
    protected function defineValidationOnlyNoExecutionRule(): void
    {
        $this->rule('validation-only-no-execution')->critical()
            ->text('VALIDATION command validates EXISTING work. NEVER implement, fix, or create code directly. Only validate and CREATE TASKS for issues found.')
            ->why('Validation is read-only audit. Execution belongs to do:async.')
            ->onViolation('Abort any implementation. Create task instead of fixing directly.');
    }

    /**
     * Define no-direct-fixes rule.
     * Used by: DoValidateInclude
     */
    protected function defineNoDirectFixesRule(): void
    {
        $this->rule('no-direct-fixes')->critical()
            ->text('VALIDATION command NEVER fixes issues directly. ALL issues (critical, major, minor) MUST become tasks. No exceptions.')
            ->why('Traceability and audit trail. Every change must be tracked via task system.')
            ->onViolation('Create task for the issue instead of fixing directly.');
    }

    /**
     * Define test-validation-only rule.
     * Used by: DoTestValidateInclude
     */
    protected function defineTestValidationOnlyRule(): void
    {
        $this->rule('test-validation-only')->critical()
            ->text('TEST VALIDATION command validates EXISTING tests. NEVER write tests directly. Only validate and CREATE MEMORY ENTRIES for missing/broken tests.')
            ->why('Validation is read-only audit. Test writing belongs to do:async.')
            ->onViolation('Abort any test writing. Create memory entry instead.');
    }

    // =========================================================================
    // PHASE SEQUENCE RULES (for validation commands)
    // =========================================================================

    /**
     * Define phase sequence rules for strict ordering.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param int $totalPhases Total number of phases (e.g., 7 for DoValidate, 8 for DoTestValidate)
     */
    protected function definePhaseSequenceRules(int $totalPhases): void
    {
        $this->rule('phase-sequence-strict')->critical()
            ->text('Phases MUST execute in STRICT sequential order: Phase 0 → ... → Phase '.$totalPhases.'. NO phase may start until previous phase is FULLY COMPLETED. Each phase MUST output its header "=== PHASE N: NAME ===" before any actions.')
            ->why('Sequential execution ensures data dependencies are satisfied. Each phase depends on variables stored by previous phases.')
            ->onViolation('STOP. Return to last completed phase. Execute current phase fully before proceeding.');

        $this->rule('no-phase-skip')->critical()
            ->text('FORBIDDEN: Skipping phases. ALL phases 0-'.$totalPhases.' MUST execute even if a phase has no issues to report. Empty results are valid; skipped phases are VIOLATION.')
            ->why('Phase skipping breaks data flow. Later phases expect variables from earlier phases.')
            ->onViolation('ABORT. Return to first skipped phase. Execute ALL phases in sequence.');

        $this->rule('phase-completion-marker')->high()
            ->text('Each phase MUST end with its output block before next phase begins. Phase N output MUST appear before "=== PHASE N+1 ===" header.')
            ->why('Output markers confirm phase completion. Missing output = incomplete phase.')
            ->onViolation('Complete current phase output before starting next phase.');

        $this->rule('no-parallel-phases')->critical()
            ->text('FORBIDDEN: Executing multiple phases simultaneously. Only Phase 4/5 allows parallel AGENTS within the phase. Phase-level parallelism is NEVER allowed.')
            ->why('Phase parallelism causes race conditions on shared variables.')
            ->onViolation('Serialize phase execution. Wait for phase completion before starting next.');
    }

    // =========================================================================
    // APPROVAL HANDLING
    // =========================================================================

    /**
     * Generate auto-approval phase logic for do commands.
     * Returns an array of phases for Operator::if blocks.
     *
     * @param string $variableName Variable name to check (e.g., 'HAS_Y_FLAG', 'HAS_AUTO_APPROVE')
     * @return array Phases with auto-approval logic
     */
    protected function getAutoApprovalPhases(string $variableName = 'HAS_AUTO_APPROVE'): array
    {
        return [
            Operator::if('$'.$variableName.' === true', [
                'AUTO-APPROVED (unattended mode)',
                Operator::output([($variableName === 'HAS_Y_FLAG' ? '🤖' : '✅').' Auto-approved via -y flag']),
            ]),
            Operator::if('$'.$variableName.' === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Modify plan → Re-present → WAIT'),
            ]),
        ];
    }

    // =========================================================================
    // PHASE 0: CONTEXT SETUP (for validation commands)
    // =========================================================================

    /**
     * Define phase0 context setup guideline for validation commands.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $commandType Command type for header (e.g., 'VALIDATE', 'TEST-VALIDATE')
     * @param string $alternativeCommand Alternative command for task IDs (e.g., '/task:validate')
     */
    protected function definePhase0ContextSetupGuideline(string $commandType, string $alternativeCommand): void
    {
        $this->guideline('phase0-context-setup')
            ->goal('Process $RAW_INPUT (already captured), extract flags, store task context')
            ->example()
            ->phase(Operator::output([
                '=== DO:'.$commandType.' ACTIVATED ===',
            ]))
            ->phase(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags (-y, --yes) removed, trimmed}'))
            ->phase('Parse $CLEAN_ARGS - verify it is TEXT description, not task ID pattern')
            ->phase(Operator::if('$CLEAN_ARGS matches task ID pattern (15, #15, task 15, task:15, task-15)', [
                Operator::output([
                    '=== WRONG COMMAND ===',
                    'Detected vector task ID pattern in $RAW_INPUT.',
                    'Use '.$alternativeCommand.' {id} for vector task validation.',
                    'This command accepts text descriptions only.',
                ]),
                'ABORT command',
            ]))
            ->phase(Store::as('TASK_DESCRIPTION', '$CLEAN_ARGS'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: CONTEXT SETUP ===',
                'Validation target: {$TASK_DESCRIPTION}',
                '{IF $HAS_AUTO_APPROVE: "Auto-approve: enabled (-y flag)"}',
            ]));
    }

    // =========================================================================
    // PHASE 1: AGENT DISCOVERY / VALIDATION PREVIEW
    // =========================================================================

    /**
     * Define phase1 context preview guideline for validation commands.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $validationType Type of validation (e.g., 'VALIDATION', 'TEST VALIDATION')
     * @param array $agentDescriptions Agent descriptions for output
     */
    protected function definePhase1ValidationPreviewGuideline(string $validationType, array $agentDescriptions): void
    {
        $guideline = $this->guideline('phase1-context-preview')
            ->goal('Discover available agents and present validation scope for approval')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 1: '.$validationType.' PREVIEW ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::LIST_MASTERS, 'Get available agents with capabilities'))
            ->phase(Store::as('AVAILABLE_AGENTS', '{agent_id: description mapping}'))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from '.Store::var('TASK_DESCRIPTION').'}'), 'Get documentation INDEX preview'))
            ->phase(Store::as('DOCS_PREVIEW', 'Documentation files available'))
            ->phase(Operator::output([
                'Task: {'.Store::var('TASK_DESCRIPTION').'}',
                'Available agents: {'.Store::var('AVAILABLE_AGENTS.count').'}',
                'Documentation files: {'.Store::var('DOCS_PREVIEW.count').'}',
                '',
                $validationType.' will delegate to agents:',
                ...$agentDescriptions,
                '',
                'APPROVAL REQUIRED',
                'approved/yes - start validation | no/modifications',
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['Auto-approved via -y flag']),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                'WAIT for user approval',
                Operator::verify('User approved'),
                Operator::if('rejected', 'Accept modifications → Re-present → WAIT'),
            ]));
    }

    // =========================================================================
    // PHASE 2: DEEP CONTEXT GATHERING
    // =========================================================================

    /**
     * Define phase2 deep context gathering guideline via VectorMaster agent.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $researchType Research focus type (e.g., 'validation', 'test validation')
     * @param string $returnStructure Expected return structure from agent
     */
    protected function definePhase2DeepContextGatheringGuideline(string $researchType, string $returnStructure): void
    {
        $this->guideline('phase2-context-gathering')
            ->goal('Delegate deep memory research to VectorMaster agent')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 2: DEEP CONTEXT GATHERING ===',
                'Delegating to VectorMaster for deep memory research...',
            ]))
            ->phase('SELECT vector-master from '.Store::var('AVAILABLE_AGENTS'))
            ->phase(Store::as('CONTEXT_AGENT', '{vector-master agent_id}'))
            ->phase(TaskTool::agent('{'.Store::var('CONTEXT_AGENT').'}', 'DEEP MEMORY RESEARCH for '.$researchType.' of "'.Store::var('TASK_DESCRIPTION').'": 1) Multi-probe search: '.$returnStructure.' 2) Search across categories: code-solution, architecture, learning, bug-fix 3) Extract actionable insights for validation 4) Return structured results. Store consolidated context.'))
            ->phase(Store::as('MEMORY_CONTEXT', '{VectorMaster agent results}'))
            ->phase(VectorMemoryMcp::call('search_memories', '{query: "'.Store::var('TASK_DESCRIPTION').'", limit: 10, category: "code-solution"}'))
            ->phase(Store::as('RELATED_SOLUTIONS', 'Related solutions from memory'))
            ->phase(Operator::output([
                'Context gathered via {'.Store::var('CONTEXT_AGENT').'}:',
                '- Memory insights: {'.Store::var('MEMORY_CONTEXT.key_insights.count').'}',
                '- Related solutions: {'.Store::var('RELATED_SOLUTIONS.count').'}',
            ]));
    }

    // =========================================================================
    // PHASE 3: DOCUMENTATION EXTRACTION
    // =========================================================================

    /**
     * Define phase3 documentation requirements extraction guideline.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $requirementsType Type of requirements to extract (e.g., 'ALL requirements', 'ALL TESTABLE requirements')
     * @param string $extractionDetails Detailed extraction instructions for agent
     */
    protected function definePhase3DocumentationExtractionGuideline(string $requirementsType, string $extractionDetails): void
    {
        $this->guideline('phase3-documentation-extraction')
            ->goal('Extract '.$requirementsType.' from .docs/ via DocumentationMaster')
            ->example()
            ->phase(Operator::output([
                '',
                '=== PHASE 3: DOCUMENTATION REQUIREMENTS ===',
            ]))
            ->phase(BashTool::describe(BrainCLI::DOCS('{keywords from '.Store::var('TASK_DESCRIPTION').'}'), 'Get documentation INDEX'))
            ->phase(Store::as('DOCS_INDEX', 'Documentation file paths'))
            ->phase(Operator::if('{'.Store::var('DOCS_INDEX').'} not empty', [
                TaskTool::agent('documentation-master', $extractionDetails),
                Store::as('DOCUMENTATION_REQUIREMENTS', '{structured requirements list}'),
            ]))
            ->phase(Operator::if('{'.Store::var('DOCS_INDEX').'} empty', [
                Store::as('DOCUMENTATION_REQUIREMENTS', '[]'),
                Operator::output(['WARNING: No documentation found. Validation will be limited.']),
            ]))
            ->phase(Operator::output([
                'Requirements extracted: {'.Store::var('DOCUMENTATION_REQUIREMENTS.count').'}',
                '{requirements summary}',
            ]));
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    /**
     * Define error handling guideline for do commands.
     * Used by: DoAsyncInclude, DoSyncInclude, DoValidateInclude, DoTestValidateInclude
     *
     * @param bool $includeAgentErrors Whether to include agent-specific error handling
     * @param bool $includeDocErrors Whether to include documentation error handling
     * @param bool $isValidation Whether this is a validation command (different error patterns)
     */
    protected function defineErrorHandlingGuideline(
        bool $includeAgentErrors = true,
        bool $includeDocErrors = true,
        bool $isValidation = false
    ): void {
        $guideline = $this->guideline('error-handling')
            ->text('Graceful error handling with recovery options')
            ->example()
            ->phase()->if('user rejects plan', [
                'Accept modifications',
                'Rebuild plan',
                'Re-submit for approval',
            ]);

        if ($isValidation) {
            $guideline->phase()->if('task ID pattern detected', [
                'Report: "Detected vector task ID. Use /task:validate for vector tasks."',
                'Abort command',
            ]);
        }

        if ($includeAgentErrors) {
            $guideline->phase()->if('no agents available', [
                'Report: "No agents found via brain list:masters"',
                'Suggest: Run /init-agents first',
                'Abort command',
            ]);

            $guideline->phase()->if('agent execution fails', [
                'Log: "Step/Agent {N} failed: {error}"',
                'Offer options:',
                '  1. Retry current step',
                '  2. Skip and continue',
                '  3. Abort remaining steps',
                'WAIT for user decision',
            ]);
        }

        if ($includeDocErrors) {
            $guideline->phase()->if('documentation scan fails', [
                'Log: "brain docs command failed or no documentation found"',
                'Proceed without documentation context',
                'Note: "Documentation context unavailable"',
            ]);
        }

        $guideline->phase()->if('memory storage fails', [
            'Log: "Failed to store to memory: {error}"',
            'Report findings in output instead',
            'Continue with report',
        ]);
    }

    // =========================================================================
    // RESPONSE FORMAT
    // =========================================================================

    /**
     * Define response format guideline.
     * Used by: ALL Do commands
     *
     * @param string $formatSpec Format specification string
     */
    protected function defineResponseFormatGuideline(string $formatSpec): void
    {
        $this->guideline('response-format')
            ->text($formatSpec);
    }

    // =========================================================================
    // VALIDATION STATUS OUTPUT RULE
    // =========================================================================

    /**
     * Define output-status-report rule.
     * Used by: DoValidateInclude, DoTestValidateInclude
     */
    protected function defineOutputStatusReportRule(): void
    {
        $this->rule('output-status-report')->high()
            ->text('Output validation status: PASSED (no critical issues, no missing requirements) or NEEDS_WORK (issues found). Report all findings with severity.')
            ->why('Clear status enables informed decision-making on next steps.')
            ->onViolation('Include explicit status in validation report.');
    }

    // =========================================================================
    // COMMAND SELECTION GUIDANCE
    // =========================================================================

    /**
     * Define when-to-use guideline for do vs task commands.
     * Used by: DoValidateInclude, DoTestValidateInclude
     *
     * @param string $doCommand Do command (e.g., '/do:validate', '/do:test-validate')
     * @param string $taskCommand Task command (e.g., '/task:validate', '/task:test-validate')
     * @param string $doDescription When to use do command
     * @param string $taskDescription When to use task command
     */
    protected function defineCommandSelectionGuideline(
        string $doCommand,
        string $taskCommand,
        string $doDescription,
        string $taskDescription
    ): void {
        $this->guideline(str_replace(['/', ':'], ['', '-'], $doCommand).'-vs-'.str_replace(['/', ':'], ['', '-'], $taskCommand))
            ->text('When to use '.$doCommand.' vs '.$taskCommand)
            ->example()
            ->phase('USE '.$doCommand, $doDescription)
            ->phase('USE '.$taskCommand, $taskDescription);
    }
}
