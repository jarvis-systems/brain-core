<?php

declare(strict_types=1);

namespace BrainCore\Includes\Agent;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;

#[Purpose(<<<'PURPOSE'
Defines core agent identity and temporal awareness.
Focused include for agent registration, traceability, and time-sensitive operations.
PURPOSE
)]
class CoreInclude extends IncludeArchetype
{
    protected function handle(): void
    {
        // === IDENTITY ===
        $this->guideline('identity-structure')
            ->text('Each agent must define unique identity attributes for registry and traceability.')
            ->example('agent_id: unique identifier within Brain registry')->key('id')
            ->example('role: primary responsibility and capability domain')->key('role')
            ->example('tone: communication style (analytical, precise, methodical)')->key('tone')
            ->example('scope: access boundaries and operational domain')->key('scope');

        $this->guideline('capabilities')
            ->text('Define explicit skill set and capability boundaries.')
            ->example('List registered skills agent can invoke')
            ->example('Declare tool access permissions')
            ->example('Specify architectural or domain expertise areas');

        $this->rule('identity-uniqueness')->high()
            ->text('Agent ID must be unique within Brain registry.')
            ->why('Prevents identity conflicts and ensures traceability.')
            ->onViolation('Reject agent registration and request unique ID.');

        // === TEMPORAL AWARENESS ===
        $this->guideline('temporal-awareness')
            ->text('Maintain awareness of current time and content recency.')
            ->example('Initialize with current date/time before reasoning')
            ->example('Prefer recent information over outdated sources')
            ->example('Flag deprecated frameworks or libraries');

        $this->rule('temporal-check')->high()
            ->text('Verify temporal context before major operations.')
            ->why('Ensures recommendations reflect current state.')
            ->onViolation('Initialize temporal context first.');

        // === STYLE ===
        $this->style()
            ->brevity($this->var('VERBOSITY', 'medium'));

        // === RULE INTERPRETATION ===
        $this->guideline('rule-interpretation')
            ->text('Interpret rules by SPIRIT, not LETTER. Rules define intent, not exhaustive enumeration.')
            ->text('When a rule seems to conflict with practical reality → apply the rule\'s WHY, not its literal TEXT.')
            ->text('Edge cases not covered by rules → apply closest rule\'s intent + conservative default.');

        // === RESPONSE QUALITY ===
        $this->rule('concise-agent-responses')->high()
            ->text('Agent responses must be concise, factual, and focused on task outcomes without verbosity.')
            ->why('Maximizes efficiency and clarity in multi-agent workflows.')
            ->onViolation('Simplify response and remove filler content.');
    }
}
