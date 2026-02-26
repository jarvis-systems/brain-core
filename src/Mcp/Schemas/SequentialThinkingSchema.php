<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class SequentialThinkingSchema
{
    public static function get(): array
    {
        return [
            'think' => [
                'description' => 'Execute a structured thinking step for problem analysis',
                'required' => ['thought'],
                'allowed' => [
                    'branchFromThought',
                    'branchId',
                    'isRevision',
                    'needsMoreThoughts',
                    'nextThoughtNeeded',
                    'revisesThought',
                    'thought',
                    'thoughtNumber',
                    'totalThoughts',
                ],
                'types' => [
                    'branchFromThought' => 'integer',
                    'branchId' => 'string',
                    'isRevision' => 'boolean',
                    'needsMoreThoughts' => 'boolean',
                    'nextThoughtNeeded' => 'boolean',
                    'revisesThought' => 'integer',
                    'thought' => 'string',
                    'thoughtNumber' => 'integer',
                    'totalThoughts' => 'integer',
                ],
            ],
        ];
    }
}
