<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class Context7Schema
{
    public static function get(): array
    {
        return [
            'search' => [
                'description' => 'Search documentation and code examples from programming libraries',
                'required' => ['query'],
                'allowed' => [
                    'libraryId',
                    'query',
                ],
                'types' => [
                    'libraryId' => 'string',
                    'query' => 'string',
                ],
            ],
        ];
    }
}
