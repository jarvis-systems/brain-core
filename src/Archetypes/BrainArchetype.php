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
use Illuminate\Support\Str;

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
     * @param  non-empty-string  $company
     * @return $this
     */
    protected function personality(
        string $company,
    ): static {
        $developerName = $this->getMetaValue('developer_name');
        $developerName = $this->var('DEVELOPER_NAME', $developerName);
        $repositories = $this->var('DEVELOPER_REPOSITORIES');
        $player = "user/developer";

        $instruction = "You are a full-fledged personal assistant for a developer who works at the company \"$company\"." . PHP_EOL;
        if ($developerName) {
            $instruction .= "All tasks you implement must always be executed on behalf of the user/developer ({$developerName}).";
            $player .= "($developerName)";
        }
        $instruction .= "Your personality is that of a meticulous software engineering veteran who treats every detail as critical. You inspect code, architecture, and logic with extreme precision, never allowing ambiguity or vague reasoning. Your default mode is careful verification, rigorous consistency, and pedantic clarity." . PHP_EOL;

        if ($repositories) {
            $repositories = array_map('trim', explode(",", $repositories));
            $instruction .= "The $player has the following repositories: " . implode(", ", $repositories) . ". You have read and understood the contents of these repositories and can refer to them when implementing tasks. Always ensure that your implementations are consistent with the code and architecture of these repositories." . PHP_EOL;
            $instruction .= "The $player has access to these repositories. If you need to refer to the code in these repositories to implement a task, you can do so. Always ensure that your implementations are consistent with the code and architecture of these repositories." . PHP_EOL;
        }

        $instruction .= "You and the $player must have complete teamwork." . PHP_EOL;
        $instruction .= "Improve yourself and the $player.";

        $this->extractMetaAttributes();

        $this->guideline('personality')
            ->text($instruction);

        return $this;
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
        $className = Str::of(static::class)
            ->replace("BrainNode\\", '')
            ->replace("\\", '_')
            ->snake()
            ->replace("__", '_')
            ->upper()
            ->trim()
            ->trim('_')
            ->toString();

        $this->loadEnvInstructions($className);
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
