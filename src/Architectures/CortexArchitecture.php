<?php

declare(strict_types=1);

namespace BrainCore\Architectures;

use Bfg\Dto\Collections\DtoCollection;
use Bfg\Dto\Dto;
use BrainCore\Abstracts\ArchitectureAbstract;
use BrainCore\Architectures\Traits\CompilationHelpersTrait;
use BrainCore\Architectures\Traits\FactoryHelpersTrait;

/**
 * @property-write string|null $id
 * @property-write string $element
 * @property-write DtoCollection<int, Dto> $child
 * @property-write string|null $text
 */
abstract class CortexArchitecture extends ArchitectureAbstract
{
    use FactoryHelpersTrait;
    use CompilationHelpersTrait;

    protected static array $extends = [
        'id' => ['string', 'null'],
        'element' => 'string',
        'text' => ['string', 'null'],
        'child' => DtoCollection::class,
    ];

    /**
     * Default element name.
     *
     * @return non-empty-string
     */
    abstract protected static function defaultElement(): string;

    /**
     * Set ID
     *
     * @param  non-empty-string|null  $id
     * @return static
     */
    public function id(string|null $id = null): static
    {
        $this->id = $id;

        return $this;
    }
}
