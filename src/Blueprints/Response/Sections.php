<?php

declare(strict_types=1);

namespace BrainCore\Blueprints\Response;

use BrainCore\Architectures\BlueprintArchitecture;
use BrainCore\Blueprints\Response\Sections\Section;
use BrainCore\Blueprints\Style\ForbiddenPhrases\Phrase;

class Sections extends BlueprintArchitecture
{
    public function __construct(
        protected string|null $order = null,
    ) {
    }

    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'sections';
    }

    /**
     * Set Order
     *
     * @param  non-empty-string  $order
     * @return $this
     */
    public function order(string $order): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Add a forbidden phrase
     *
     * @param  string  $name
     * @param  string|null  $brief
     * @param  bool  $required
     * @return static
     */
    public function section(
        string $name,
        string|null $brief = null,
        bool $required = false
    ): static {
        $this->child->add(
            Section::fromAssoc(
                compact('name', 'brief', 'required')
            )
        );

        return $this;
    }

    /**
     * @param  string  $brief
     * @param  bool  $required
     * @return static
     */
    public function plan(string $brief, bool $required = false): static
    {
        return $this->section('plan', $brief, $required);
    }

    /**
     * @param  string  $brief
     * @param  bool  $required
     * @return static
     */
    public function patches(string $brief, bool $required = false): static
    {
        return $this->section('patches', $brief, $required);
    }

    /**
     * @param  string  $brief
     * @param  bool  $required
     * @return static
     */
    public function validation(string $brief, bool $required = false): static
    {
        return $this->section('validation', $brief, $required);
    }

    /**
     * @param  string  $brief
     * @param  bool  $required
     * @return static
     */
    public function result(string $brief, bool $required = false): static
    {
        return $this->section('result', $brief, $required);
    }

    /**
     * @param  string  $brief
     * @param  bool  $required
     * @return static
     */
    public function clarificationsNeeded(string $brief, bool $required = false): static
    {
        return $this->section('clarifications_needed', $brief, $required);
    }
}
