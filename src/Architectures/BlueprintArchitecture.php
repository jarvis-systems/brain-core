<?php

declare(strict_types=1);

namespace BrainCore\Architectures;

use Bfg\Dto\Attributes\DtoMutateFrom;
use Bfg\Dto\Collections\DtoCollection;
use Bfg\Dto\Dto;
use BrainCore\Abstracts\ArchitectureAbstract;
use BrainCore\Architectures\Traits\CompilationHelpersTrait;
use BrainCore\Architectures\Traits\FactoryHelpersTrait;

/**
 * @property-write string|null $id
 * @property-write string $element
 * @property-write DtoCollection<int, Dto<null>> $child
 * @property-write string|null $text
 */
abstract class BlueprintArchitecture extends ArchitectureAbstract
{
    use FactoryHelpersTrait;
    use CompilationHelpersTrait;

    /**
     * @var array<string, class-string|array<int, class-string>|string>
     */
    #[DtoMutateFrom('mutateToString', 'text')]
    protected static array $extends = [
        'id' => ['string', 'null'],
        'element' => 'string',
        'text' => ['string', 'null'],
        'child' => DtoCollection::class,
    ];

    /**
     * Is single element
     *
     * @var bool
     */
    protected bool $single = false;

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

    /**
     * Set Text
     *
     * @param  non-empty-string|array  $text
     * @return $this
     */
    public function text(string|array $text): static
    {
        if (is_array($text)) {
            $text = implode(" ", $text);
        }

        if ($this->text) {
            $this->text .= PHP_EOL . $text;
        } else {
            $this->text = $text;
        }

        return $this;
    }

    public static function mutateToString(mixed $value)
    {
        if (is_array($value)) {
            $value = implode(" ", $value);
        }

        return $value;
    }
}
