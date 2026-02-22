<?php

declare(strict_types=1);

namespace BrainCore\Blueprints\Guideline\Example;

use Bfg\Dto\Attributes\DtoMutateFrom;
use BrainCore\Architectures\BlueprintArchitecture;
use BrainCore\Blueprints\Guideline\Example;

class Phase extends BlueprintArchitecture
{
    /**
     * @param  non-empty-string|null  $name
     */
    public function __construct(
        #[DtoMutateFrom('mutateToString')]
        protected string|null $name = null,
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
        return 'phase';
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param  non-empty-string|array|null  $name
     * @param  non-empty-string|array|null  $text
     * @return static
     */
    public function phase(string|array|null $name = null, string|array|null $text = null): static
    {
        /** @var \BrainCore\Blueprints\Guideline\Example|null $parent */
        $parent = $this->getMeta('parentDto');

        /** @var static */
        return $parent->phase($name, $text);
    }

    /**
     * Set Other Next Example
     *
     * @param  non-empty-string|array|null  $text
     * @return Example
     */
    public function example(string|array|null $text = null): Example
    {
        /** @var \BrainCore\Blueprints\Guideline\Example|null $parent */
        $parent = $this->getMeta('parentDto');

        return $parent->example($text);
    }
}
