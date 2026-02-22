<?php

declare(strict_types=1);

namespace BrainCore\Blueprints\Guideline;

use BrainCore\Architectures\BlueprintArchitecture;
use BrainCore\Blueprints\Guideline\Example\Phase;

class Example extends BlueprintArchitecture
{
    private int $count = 1;

    /**
     * @param  non-empty-string|null  $key
     */
    public function __construct(
        protected string|null $key = null,
    ) {
        //
    }

    /**
     * Set default element
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'example';
    }

    /**
     * @param  non-empty-string|array|null  $name
     * @param  non-empty-string|array|null  $text
     * @return Phase
     */
    public function phase(string|array|null $name = null, string|array|null $text = null): Phase
    {
        if ($name && ! $text) {
            $text = $name;
            $name = null;
        }
        if (! $name) {
            $name = (string) $this->count++;
        }

        $this->child->add(
            $phase = Phase::fromAssoc(
                compact('name', 'text')
            )
        );

        $phase->setMeta([
            'parentDto' => $this,
        ]);

        return $phase;
    }

    /**
     * Set Other Next Example
     *
     * @param  non-empty-string|array|null  $text
     * @return static
     */
    public function example(string|array|null $text = null): static
    {
        /** @var \BrainCore\Blueprints\Guideline|null $parent */
        $parent = $this->getMeta('parentDto');

        return $parent->example($text);
    }

    /**
     * Set Key
     *
     * @param  non-empty-string|null  $key
     * @return $this
     */
    public function key(string|null $key): static
    {
        $this->key = $key;

        return $this;
    }
}
