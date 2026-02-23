<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConvertCommandDisablePatternTest extends TestCase
{
    protected array $envCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanEnv();
    }

    protected function tearDown(): void
    {
        $this->cleanEnv();
        parent::tearDown();
    }

    private function cleanEnv(): void
    {
        $keys = [
            'AGENT_MASTER_DISABLE',
            'AGENTS_AGENT_MASTER_DISABLE',
            'AGENTS_DISABLE',
            'COMMIT_MASTER_DISABLE',
            'AGENTS_COMMIT_MASTER_DISABLE',
            'COMMANDS_DO_DISABLE',
            'SKILLS_HEALTH_CHECK_DISABLE',
        ];
        foreach ($keys as $key) {
            putenv($key);
            $this->envCleanup[] = $key;
        }
    }

    private function getEnvValue(string $key): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return null;
        }
        if (in_array(strtolower($value), ['true', '1'], true)) {
            return true;
        }
        if (in_array(strtolower($value), ['false', '0'], true)) {
            return false;
        }
        return $value;
    }

    #[Test]
    public function it_excludes_agent_when_classname_disable_is_set(): void
    {
        putenv('AGENT_MASTER_DISABLE=true');

        $disabled = $this->getEnvValue('AGENT_MASTER_DISABLE');

        $this->assertTrue($disabled === true);
    }

    #[Test]
    public function it_excludes_agent_when_namespace_classname_disable_is_set(): void
    {
        putenv('AGENTS_AGENT_MASTER_DISABLE=true');

        $disabled = $this->getEnvValue('AGENTS_AGENT_MASTER_DISABLE');

        $this->assertTrue($disabled === true);
    }

    #[Test]
    public function it_excludes_all_agents_when_namespace_disable_is_set(): void
    {
        putenv('AGENTS_DISABLE=true');

        $disabled = $this->getEnvValue('AGENTS_DISABLE');

        $this->assertTrue($disabled === true);
    }

    #[Test]
    #[DataProvider('disablePatternProvider')]
    public function it_recognizes_all_documented_disable_patterns(string $envKey, bool $shouldDisable): void
    {
        putenv($envKey . '=true');

        $disabled = $this->getEnvValue($envKey);

        $this->assertSame($shouldDisable, $disabled === true);
    }

    public static function disablePatternProvider(): array
    {
        return [
            'CLASSNAME_DISABLE pattern' => ['AGENT_MASTER_DISABLE', true],
            'NAMESPACE_CLASSNAME_DISABLE pattern' => ['AGENTS_AGENT_MASTER_DISABLE', true],
            'NAMESPACE_DISABLE pattern' => ['AGENTS_DISABLE', true],
            'COMMANDS_CLASSNAME_DISABLE pattern' => ['COMMANDS_DO_DISABLE', true],
            'SKILLS_CLASSNAME_DISABLE pattern' => ['SKILLS_HEALTH_CHECK_DISABLE', true],
        ];
    }

    #[Test]
    public function it_does_not_exclude_when_disable_is_false(): void
    {
        putenv('AGENT_MASTER_DISABLE=false');

        $disabled = $this->getEnvValue('AGENT_MASTER_DISABLE');

        $this->assertFalse($disabled);
    }

    #[Test]
    public function it_does_not_exclude_when_disable_is_not_set(): void
    {
        $disabled = $this->getEnvValue('AGENT_MASTER_DISABLE');

        $this->assertNull($disabled);
    }

    #[Test]
    public function classname_pattern_takes_precedence_over_namespace(): void
    {
        putenv('AGENTS_DISABLE=true');
        putenv('AGENT_MASTER_DISABLE=false');

        $namespaceDisabled = $this->getEnvValue('AGENTS_DISABLE');
        $classnameDisabled = $this->getEnvValue('AGENT_MASTER_DISABLE');

        $this->assertTrue($namespaceDisabled === true);
        $this->assertFalse($classnameDisabled);
    }
}
