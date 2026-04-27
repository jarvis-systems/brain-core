<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Schemas;

final class Context7Schema
{
    public static function get(): array
    {
        return [
            'query-docs' => [
                'description' => 'Query documentation and code examples for a resolved Context7 library',
                'required' => [
                    'libraryId',
                    'query',
                ],
                'allowed' => [
                    'libraryId',
                    'query',
                    'researchMode',
                ],
                'types' => [
                    'libraryId' => 'string',
                    'query' => 'string',
                    'researchMode' => 'boolean',
                ],
            ],
            'resolve-library-id' => [
                'description' => 'Resolve a package/product name to a Context7-compatible library ID',
                'required' => ['libraryName'],
                'allowed' => [
                    'libraryName',
                    'query',
                ],
                'types' => [
                    'libraryName' => 'string',
                    'query' => 'string',
                ],
            ],
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
