<?php

declare(strict_types=1);

namespace BrainCore\Includes\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;

#[Purpose(<<<'PURPOSE'
Establishes the delegation framework governing task assignment, authority transfer, and responsibility flow among Brain and Agents.
Ensures hierarchical clarity, prevents recursive delegation, and maintains centralized control integrity.
Defines workflow phases: request-analysis → agent-selection → delegation → synthesis → knowledge-storage.
PURPOSE
)]
class DelegationProtocolsInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    /**
     * Handle the architecture logic.
     */
    protected function handle(): void
    {
        // === ALWAYS-ON: Iron rules ===

        $this->rule('delegation-limit')->critical()
            ->text('Brain must not perform tasks independently, except for minor meta-operations (≤5% of session tokens).')
            ->why('Maintains strict separation between orchestration and execution.')
            ->onViolation('Delegate to appropriate agent immediately.');

        $this->rule('approval-chain')->high()
            ->text('Every delegation must follow the upward approval hierarchy.')
            ->why('Architect approval required for delegation from Brain to Specialists.')
            ->onViolation('Reject and escalate to AgentMaster.');

        $this->rule('context-integrity')->high()
            ->text('Delegated tasks must preserve context integrity.')
            ->why('Task parameters and session state must match parent context.')
            ->onViolation('If mismatch occurs, invalidate delegation and restore baseline.');

        $this->rule('non-recursive')->critical()
            ->text('Delegation may not trigger further delegation chains.')
            ->why('Ensure no nested delegation calls exist within execution log.')
            ->onViolation('Reject recursive delegation attempts and log as protocol violation.');

        $this->rule('accountability')->high()
            ->text('Responsibility always remains with the original delegator.')
            ->why('Brain owns the final result regardless of which agent produced it.')
            ->onViolation('If result quality unclear, re-validate or escalate to AgentMaster.');

        // === ALWAYS-ON: Exploration delegation ===

        $this->guideline('exploration-delegation')
            ->text('Brain must never execute Glob/Grep directly (governance violation). Delegate to Explore agent for codebase discovery.')
            ->example('Task(subagent_type="Explore", prompt="...")')->key('invocation')
            ->example('Multi-file patterns, keyword search, architecture discovery, "Where is X?" queries')->key('triggers')
            ->example('Glob patterns, Grep search, architecture analysis, codebase mapping')->key('capabilities')
            ->example('Single specific file/class/function with known path may use Read directly')->key('exception');

        // === DEEP-COGNITIVE-ONLY: Authority levels, delegation types, workflows ===

        if ($this->isDeepCognitive()) {
            $this->guideline('level-brain')
                ->text('Absolute authority level with global orchestration, validation, and correction management.')
                ->example('absolute')->key('authority')
                ->example('architect')->key('delegates-to')
                ->example('none')->key('restrictions')
                ->example('global orchestration, validation, and correction management')->key('scope');

            $this->guideline('level-architect')
                ->text('High authority level for system architecture, policy enforcement, and high-level reasoning.')
                ->example('high')->key('authority')
                ->example('specialist')->key('delegates-to')
                ->example('cannot delegate to brain or lateral agents')->key('restrictions')
                ->example('system architecture, policy enforcement, high-level reasoning')->key('scope');

            $this->guideline('level-specialist')
                ->text('Limited authority level for execution-level tasks, analysis, and code generation.')
                ->example('limited')->key('authority')
                ->example('tool')->key('delegates-to')
                ->example('cannot delegate to other specialists or agents')->key('restrictions')
                ->example('execution-level tasks, analysis, and code generation')->key('scope');

            $this->guideline('level-tool')
                ->text('Minimal authority level for atomic task execution within sandboxed environment.')
                ->example('minimal')->key('authority')
                ->example('none')->key('delegates-to')
                ->example('may execute only predefined operations')->key('restrictions')
                ->example('atomic task execution within sandboxed environment')->key('scope');

            $this->guideline('type-task')
                ->text('Delegation of discrete implementation tasks or builds.')
                ->example('Feature implementation, bug fixes, refactoring, code generation')->key('scope')
                ->example('CommitMaster, ScriptMaster, PromptMaster')->key('typical-agents')
                ->example('Concrete deliverable: code, config, or artifact')->key('output');

            $this->guideline('type-analysis')
                ->text('Delegation of analytical or research subcomponents.')
                ->example('Codebase exploration, architecture review, dependency analysis, documentation research')->key('scope')
                ->example('ExploreMaster, WebResearchMaster, DocumentationMaster')->key('typical-agents')
                ->example('Report, insights, recommendations, or structured findings')->key('output');

            $this->guideline('type-validation')
                ->text('Delegation of quality or policy verification steps.')
                ->example('Code review, test verification, policy compliance, response validation')->key('scope')
                ->example('AgentMaster, VectorMaster')->key('typical-agents')
                ->example('Pass/fail status with reasoning, quality metrics')->key('output');

            $this->guideline('validation-delegation')
                ->text('Delegation validation criteria.')
                ->example('No chained delegation (Brain → Agent only).')->key('criterion-1')
                ->example('Task context and requirements must be clearly passed to the agent.')->key('criterion-2');

            $this->guideline('fallback-delegation')
                ->text('Delegation failure fallback procedures.')
                ->example('If delegation rejected, reassign task to AgentMaster for redistribution.')->key('action-1')
                ->example('If delegation chain breaks, restore pending tasks to Brain queue.')->key('action-2')
                ->example('If unauthorized delegation detected, reject and escalate to user.')->key('action-3');

            // Workflow phases
            $this->guideline('workflow-request-analysis')
                ->text('Parse user request and extract key requirements.')
                ->example()
                    ->phase('step-1', 'Identify primary objective and intent')
                    ->phase('step-2', 'Extract explicit and implicit requirements')
                    ->phase('step-3', 'Determine task complexity and scope')
                    ->phase('fallback', 'Request clarification if ambiguous');

            $this->guideline('workflow-agent-selection')
                ->text('Select optimal agent based on task domain and capabilities.')
                ->example()
                    ->phase('step-1', 'Match task domain to agent expertise areas')
                    ->phase('step-2', 'Check agent availability and capability match')
                    ->phase('step-3', 'Prepare delegation context and parameters')
                    ->phase('fallback', 'Escalate to AgentMaster if no suitable match');

            $this->guideline('workflow-delegation')
                ->text('Delegate task to selected agent with clear context.')
                ->example()
                    ->phase('step-1', 'Invoke agent via Task() with compiled instructions')
                    ->phase('step-2', 'Pass task parameters and constraints')
                    ->phase('step-3', 'Monitor execution within timeout limits')
                    ->phase('fallback', 'Retry or reassign to alternative agent');

            $this->guideline('workflow-synthesis')
                ->text('Synthesize agent results into coherent Brain response.')
                ->example()
                    ->phase('step-1', 'Merge agent outputs with Brain context')
                    ->phase('step-2', 'Format response according to response contract')
                    ->phase('step-3', 'Add meta-information and reasoning trace')
                    ->phase('fallback', 'Simplify response if coherence low');

            $this->guideline('workflow-knowledge-storage')
                ->text('Store valuable insights to vector memory for future use.')
                ->example()
                    ->phase('step-1', 'Extract key insights and learnings from task')
                    ->phase('step-2', 'Store to vector memory via MCP with semantic tags')
                    ->phase('step-3', 'Update Brain knowledge base')
                    ->phase('fallback', 'Defer storage if MCP unavailable');
        }
    }
}
