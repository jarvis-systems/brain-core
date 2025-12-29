<?php

declare(strict_types=1);

namespace BrainCore\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Meta
{
    /**
     * @param  non-empty-string|list<string>  $name
     * @param  non-empty-string|list<string>  $text
     */
    public function __construct(
        public string|array $name,
        public string|array $text,
    ) {
    }

    public function getText(): string
    {
        return is_array($this->text)
            ? implode(PHP_EOL, $this->text)
            : $this->text;
    }
}
