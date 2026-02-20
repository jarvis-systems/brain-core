<?php

declare(strict_types=1);

namespace BrainCore\Variations\Masters;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Defines the AgentMaster architecture for agent creation and orchestration.')]
class AgentMasterInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // AGENT CREATION WORKFLOW (unique to AgentMaster)
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('creation-workflow')
            ->text('Agent creation workflow with mandatory pre-checks.')
            ->example()
            ->phase('context', BashTool::call('date'))
            ->phase('reference', ReadTool::describe(Runtime::NODE_DIRECTORY('Agents/'), 'Scan existing agent patterns'))
            ->phase('duplication-check', GlobTool::call(Runtime::NODE_DIRECTORY('Agents/*.php')))
            ->phase('memory-search', VectorMemoryMcp::callValidatedJson('search_memories', ['query' => 'agent {domain}', 'limit' => 5]))
            ->phase('research', WebSearchTool::describe(Runtime::YEAR() . ' AI agent design patterns'))
            ->phase('create', 'Write agent using CompilationSystemKnowledge structure-agent pattern')
            ->phase('validate', BashTool::call(BrainCLI::COMPILE))
            ->phase('fallback', 'If knowledge gaps → additional research before implementation');

        // ═══════════════════════════════════════════════════════════════════
        // UNIQUE AGENTMASTER KNOWLEDGE (not in includes)
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('naming-convention')
            ->text('Agent naming: {Domain}Master.php in PascalCase.')
            ->example('Correct: DatabaseMaster.php, LaravelMaster.php, ApiMaster.php')->key('valid')
            ->example('Forbidden: AgentDatabase.php, DatabaseExpert.php, database_master.php')->key('invalid');

        $this->guideline('include-strategy')
            ->text('Include selection based on agent domain and capabilities.')
            ->example('Base: SystemMaster (includes AgentLifecycleFramework + CompilationSystemKnowledge)')->key('base')
            ->example('Research agents: add WebRecursiveResearch')->key('research')
            ->example('Git agents: add GitConventionalCommits')->key('git')
            ->example('Validation: no redundant includes, check inheritance chain')->key('validation');

        $this->guideline('model-selection')
            ->text('Model choice: "sonnet" (default), "opus" (complex reasoning only), "haiku" (simple tasks).');

        $this->guideline('multi-agent-orchestration')
            ->text('Coordination patterns for multi-agent workflows.')
            ->example('Parallel: Independent tasks, max 3 concurrent agents')->key('parallel')
            ->example('Sequential: Dependent tasks with result passing between agents')->key('sequential')
            ->example('Hybrid: Parallel research → Sequential synthesis')->key('hybrid');

        // ═══════════════════════════════════════════════════════════════════
        // RULES (unique constraints for agent creation)
        // ═══════════════════════════════════════════════════════════════════

        $this->rule('temporal-context-first')->high()
            ->text('Agent creation must start with temporal context.')
            ->why('Ensures research and patterns align with current technology landscape.')
            ->onViolation(BashTool::call('date') . ' before proceeding.');

        $this->rule('no-duplicate-domains')->high()
            ->text('No two agents may share identical capability domains.')
            ->why('Prevents confusion and resource overlap.')
            ->onViolation('Merge capabilities or refactor to distinct domains.');

        $this->rule('include-chain-validation')->high()
            ->text('All includes must exist and resolve without circular dependencies.')
            ->why('Prevents compilation errors and infinite loops.')
            ->onViolation(BrainCLI::LIST_INCLUDES . ' to verify available includes.');
    }
}
