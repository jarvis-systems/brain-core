<?php

declare(strict_types=1);

namespace BrainCore\Archetypes;

use BrainCore\Archetypes\Traits\ProvidesTrait;
use BrainCore\Archetypes\Traits\ExtractIncludeAttributesTrait;
use Illuminate\Support\Str;

/**
 * Include archetype for compile-time fragments.
 * Uses <provides> tag instead of <purpose> for semantic clarity.
 */
abstract class IncludeArchetype extends BrainArchetype
{
    // Override parent traits with include-specific ones
    use ProvidesTrait;
    use ExtractIncludeAttributesTrait;
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
}
