<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\ModeResolverTrait;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Vector memory iron rules with cookbook delegation.')]
class VectorMemoryInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    protected function handle(): void
    {
        // === COOKBOOK DELEGATION (compile-time resolved) ===

        $preset = $this->getCookbookPreset('memory');

        $this->guideline('cookbook-preset')
            ->text('Active cookbook preset for memory operations. Mode: ' . $this->getCognitiveMode() . '/' . $this->getStrictMode())
            ->example('Call: ' . VectorMemoryMcp::callValidatedJson('cookbook', $preset));

        if ($this->isJsonStrictRequired() || $this->isParanoidMode()) {
            $this->guideline('cookbook-first')
                ->text('Pull gates-rules from cookbook BEFORE memory operations.');
        }

        // === COOKBOOK GOVERNANCE POLICY ===

        $budgetCap = $this->isParanoidMode() || $this->isDeepCognitive() ? 4 : 2;

        $this->rule('cookbook-governance')->critical()
            ->text('Cookbook calls ONLY via: (1) compile-time preset above, (2) explicit onViolation. BANNED: uncertainty triggers, speculative pulls, runtime param construction.')
            ->why('Compile-time preset = determinism. Speculative pulls = budget waste + non-determinism.')
            ->onViolation('Remove unauthorized cookbook() call. Iron rules in context are the source of truth.');

        $this->guideline('cookbook-constraints')
            ->text('Cookbook operational constraints.')
            ->example('Compiled iron rules override cookbook case text on conflict')->key('precedence')
            ->example('Cookbook case MUST NOT trigger another cookbook pull')->key('no-recursion')
            ->example($budgetCap . ' pulls max/session. Most operations need preset only (0 extra). Do not seek reasons to use quota.')->key('budget-cap')
            ->example('Do NOT pull when: trivial task, answer already in context, same query repeated, token budget >80%')->key('when-not-to-pull');

        $this->guideline('gate5-satisfied')
            ->text('Gate 5 (Cookbook-First) is satisfied by compile-time preset baked above. It is NOT a runtime uncertainty trigger.');

        // === IRON RULES ===

        $this->rule('mcp-json-only')->critical()
            ->text('ALL memory operations MUST use MCP tool with JSON object payload.')
            ->why('Ensures valid JSON, embedding generation, data integrity.')
            ->onViolation(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '...', 'limit' => 3]));

        if ($this->isJsonStrictRequired() || $this->isDeepCognitive()) {
            $this->rule('multi-probe-mandatory')->critical()
                ->text('2-3 probes REQUIRED. Single query = missed context.')
                ->why('Vector search has semantic radius. Multiple probes cover knowledge space.')
                ->onViolation(VectorMemoryMcp::callValidatedJson('cookbook', ['include' => 'cases', 'case_category' => 'search', 'priority' => 'critical']));
        }

        $this->rule('search-before-store')->high()
            ->text('ALWAYS search before store.')
            ->why('Prevents memory pollution. Keeps knowledge base clean.')
            ->onViolation(VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{insight_summary}', 'limit' => 3]));

        if ($this->isParanoidMode() || $this->isDeepCognitive()) {
            $this->rule('triggered-suggestion')->high()
                ->text('Suggestion/proposal mode ONLY when triggered.')
                ->why('Continuous proposals waste tokens and clutter memory.')
                ->onViolation('Do not store proposals by default; store only after trigger.');
        }
    }
}
