<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;
use BrainNode\Mcp\VectorTaskMcp;

#[Purpose('Vector task iron rules with cookbook delegation.')]
class VectorTaskInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    protected function handle(): void
    {
        // === COOKBOOK DELEGATION (compile-time resolved) ===

        $preset = $this->getCookbookPreset('task');

        $this->guideline('cookbook-preset')
            ->text('Active cookbook preset for task operations. Mode: ' . $this->getCognitiveMode() . '/' . $this->getStrictMode())
            ->example('Call: ' . VectorTaskMcp::callValidatedJson('cookbook', $preset));

        if ($this->isJsonStrictRequired() || $this->isParanoidMode()) {
            $this->guideline('cookbook-first')
                ->text('Pull gates-rules from cookbook BEFORE task operations.');
        }

        // Shared cookbook governance (cookbook-governance, cookbook-constraints, gate5-satisfied)
        // is emitted by VectorMemoryInclude — always co-loaded via BrainIncludesTrait.

        // === IRON RULES ===

        $this->rule('mcp-json-only')->critical()
            ->text('ALL task operations MUST use MCP tool with JSON object payload.')
            ->why('MCP ensures embedding generation and data integrity.')
            ->onViolation(VectorTaskMcp::callValidatedJson('task_list', ['status' => 'in_progress', 'limit' => 50]));

        $this->rule('explore-before-execute')->critical()
            ->text('MUST explore task context (parent, children) BEFORE execution.')
            ->why('Prevents duplicate work, ensures alignment, discovers dependencies.')
            ->onViolation(VectorTaskMcp::callValidatedJson('task_get', ['task_id' => '{task_id}']) . ' + parent + children BEFORE task_update');

        if ($this->isJsonStrictRequired()) {
            $this->rule('estimate-required')->critical()
                ->text('EVERY task MUST have estimate in hours.')
                ->why('Estimates enable planning, prioritization, decomposition.')
                ->onViolation('Leaf tasks <=4h, parent = sum of children.');
        }

        $this->rule('parent-readonly')->critical()
            ->text('$PARENT task is READ-ONLY. NEVER update parent.')
            ->why('Parent lifecycle managed externally. Prevents loops, corruption.')
            ->onViolation('Only task_update on assigned $TASK.');

        $this->rule('timestamps-auto')->critical()
            ->text('NEVER set start_at/finish_at manually.')
            ->why('Manual values corrupt timeline.')
            ->onViolation('Remove from task_update call.');

        if (!$this->isMinimalRelaxed()) {
            $this->rule('single-in-progress')->high()
                ->text('Only ONE task in_progress per agent.')
                ->why('Prevents context switching, ensures focus.')
                ->onViolation('Complete current before starting new.');
        }

        // === MODE GOVERNANCE ===

        $this->rule('no-mode-self-switch')->critical()
            ->text('NEVER change strict/cognitive mode at runtime. Only RECOMMEND mode with risk explanation.')
            ->why('Mode is a compile-time decision. Runtime switching corrupts single-mode invariant.')
            ->onViolation('Remove mode change. Add recommendation as task comment with risk analysis.');

        if ($this->isDeepCognitive()) {
            $this->guideline('mode-selection-guide')
                ->text('Mode selection decision tree for task decomposition. Model recommends, system sets tags.')
                ->example('paranoid + exhaustive: security-critical, financial, compliance, data integrity')->key('paranoid')
                ->example('strict + deep: production features, API contracts, refactoring with tests')->key('strict')
                ->example('standard + standard: typical features, bugfixes, routine changes')->key('standard')
                ->example('relaxed + minimal: prototypes, experiments, throwaway scripts')->key('relaxed');
        }
    }

    private function isMinimalRelaxed(): bool
    {
        return $this->getCognitiveMode() === 'minimal' && $this->getStrictMode() === 'relaxed';
    }
}
