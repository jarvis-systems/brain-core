<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\TomlBuilder;
use PHPUnit\Framework\TestCase;

class TomlBuilderTest extends TestCase
{
    public function testBuildsNestedTomlFromAssociativeArray(): void
    {
        $data = [
            'mcp_servers' => [
                'laravel-boost' => [
                    'command' => 'vendor/bin/sail',
                    'args' => ['artisan', 'boost:mcp'],
                    'env' => [
                        'FORCE_COLOR' => '1',
                    ],
                ],
                'web-scout' => [
                    'command' => 'npx',
                    'args' => ['-y', '@pinkpixel/web-scout-mcp'],
                ],
                'github' => [
                    'command' => '/usr/local/bin/github-mcp-server',
                    'args' => ['stdio'],
                    'env' => [
                        'GITHUB_PERSONAL_ACCESS_TOKEN' => 'token',
                    ],
                ],
            ],
        ];

        $toml = TomlBuilder::from($data);

        $expected = <<<TOML
[mcp_servers.laravel-boost]
command = "vendor/bin/sail"
args = ["artisan", "boost:mcp"]

[mcp_servers.laravel-boost.env]
FORCE_COLOR = "1"

[mcp_servers.web-scout]
command = "npx"
args = ["-y", "@pinkpixel/web-scout-mcp"]

[mcp_servers.github]
command = "/usr/local/bin/github-mcp-server"
args = ["stdio"]

[mcp_servers.github.env]
GITHUB_PERSONAL_ACCESS_TOKEN = "token"
TOML;

        $this->assertSame($expected, $toml);
    }

    public function testFormatsScalarArraysAndBooleans(): void
    {
        $data = [
            'settings' => [
                'enabled' => true,
                'thresholds' => [1, 2, 3.5],
                'paths' => [
                    '/tmp/cache',
                    '/usr/bin',
                ],
            ],
        ];

        $toml = TomlBuilder::from($data);

        $expected = <<<TOML
[settings]
enabled = true
thresholds = [1, 2, 3.5]
paths = ["/tmp/cache", "/usr/bin"]
TOML;

        $this->assertSame($expected, $toml);
    }
}
