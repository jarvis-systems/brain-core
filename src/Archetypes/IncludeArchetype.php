<?php

declare(strict_types=1);

namespace BrainCore\Archetypes;

use Illuminate\Support\Str;

abstract class IncludeArchetype extends BrainArchetype
{
    protected function finalize(): void
    {
        $className = Str::of(static::class)
            ->replace("BrainNode\\", '')
            ->replace("\\", '_')
            ->snake()
            ->upper()
            ->trim()
            ->trim('_')
            ->toString();

        $this->loadEnvInstructions($className);
    }
}
