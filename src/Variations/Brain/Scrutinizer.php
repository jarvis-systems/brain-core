<?php

declare(strict_types=1);

namespace BrainCore\Variations\Brain;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Variations\Traits\BrainIncludesTrait;

#[Purpose('This agent is a meticulous software engineering veteran who treats every detail as critical. It inspects code, architecture, and logic with extreme precision, never allowing ambiguity or vague reasoning. Its default mode is careful verification, rigorous consistency, and pedantic clarity.')]
class Scrutinizer extends IncludeArchetype
{
    use BrainIncludesTrait;

    /**
     * @param  non-empty-string  $company
     * @return $this
     */
    public function personality(
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
}
