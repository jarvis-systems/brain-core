<?php

declare(strict_types=1);

namespace BrainCore\Archetypes;

use BrainCore\Archetypes\Traits\ContextTrait;
use BrainCore\Archetypes\Traits\InputTrait;
use BrainCore\Archetypes\Traits\InstructionsTrait;
use BrainCore\Archetypes\Traits\MetasTrait;
use BrainCore\Archetypes\Traits\StyleTrait;
use BrainCore\Archetypes\Traits\PurposeTrait;
use BrainCore\Archetypes\Traits\ResponseTrait;
use BrainCore\Archetypes\Traits\IronRulesTrait;
use BrainCore\Archetypes\Traits\GuidelinesTrait;
use BrainCore\Archetypes\Traits\DeterminismTrait;
use BrainCore\Architectures\ArchetypeArchitecture;
use BrainCore\Archetypes\Traits\ExtractAttributesTrait;
use BrainNode\Brain;

abstract class BrainArchetype extends ArchetypeArchitecture
{
    use MetasTrait;
    use InputTrait;
    use InstructionsTrait;
    use ContextTrait;
    use StyleTrait;
    use PurposeTrait;
    use ResponseTrait;
    use IronRulesTrait;
    use GuidelinesTrait;
    use DeterminismTrait;
    use ExtractAttributesTrait;

    /**
     * Default element name.
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'system';
    }

    /**
     * Init architecture.
     *
     * @return void
     */
    protected function init(): void
    {
        $agent = $this->var('AGENT_CONST', 'CLAUDE');
        $varName = $agent . '_BRAIN_MODEL';
        $model = $this->var($varName, $this->var('BRAIN_MODEL'));
        if ($model) {
            $this->setMeta('model', $model);
        }
    }

    protected function finalize(): void
    {
        if (static::class === Brain::class) {
            $i = 0;
            while ($ironRule = $this->var('BRAIN_IRON_RULE_' . $i)) {
                $this->rule('special-rule-' . $i)
                    ->critical()
                    ->text($ironRule);
                $i++;
            }

            $i = 0;
            while ($ironRule = $this->var('BRAIN_GUIDELINE_' . $i)) {
                $this->guideline('special-guideline-' . $i)
                    ->text($ironRule);
                $i++;
            }
        }
    }

    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        $this->defineRules();
        $this->defineGuidelines();
    }

    protected function defineRules(): void
    {

    }

    protected function defineGuidelines(): void
    {

    }
}
