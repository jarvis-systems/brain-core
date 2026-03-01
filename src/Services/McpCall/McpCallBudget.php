<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

/**
 * MCP Call Budget handles enforcement of call limits.
 * Default limit is 10 calls.
 */
final class McpCallBudget
{
    private const DEFAULT_LIMIT = 10;
    private const ENV_VAR = 'BRAIN_MCP_CALL_BUDGET';

    public static function create(string $projectRoot): self
    {
        $path = $projectRoot . '/memory/mcp-budget.json';

        // Test mode isolation: move to dist/tmp if BRAIN_TEST_MODE is active
        if (getenv('BRAIN_TEST_MODE')) {
            $path = $projectRoot . '/dist/tmp/mcp-budget.json';
        }

        return new self($path);
    }

    public function __construct(
        private readonly string $budgetFile,
    ) {}

    /**
     * Get the configured budget limit.
     */
    public function getLimit(): int
    {
        $env = getenv(self::ENV_VAR);
        if ($env === false || $env === '') {
            return self::DEFAULT_LIMIT;
        }

        return (int) $env;
    }

    /**
     * Get the remaining budget.
     */
    public function getRemaining(): int
    {
        $used = $this->getUsed();
        $limit = $this->getLimit();

        return max(0, $limit - $used);
    }

    /**
     * Check if budget is exhausted.
     */
    public function isExhausted(): bool
    {
        return $this->getRemaining() <= 0;
    }

    /**
     * Get the storage path for the budget file.
     */
    public function getStoragePath(): string
    {
        return $this->budgetFile;
    }

    /**
     * Reset the budget (used count = 0).
     */
    public function reset(): void
    {
        if ($this->isKillSwitchActive()) {
            return;
        }
        $this->saveUsed(0);
    }

    /**
     * Record a logical call.
     */
    public function recordCall(): void
    {
        if ($this->isKillSwitchActive()) {
            return;
        }
        $used = $this->getUsed();
        $this->saveUsed($used + 1);
    }

    /**
     * Check if kill-switch is active.
     */
    private function isKillSwitchActive(): bool
    {
        $val = getenv('BRAIN_DISABLE_MCP');
        return $val !== false && in_array(strtolower((string)$val), ['true', '1', 'yes'], true);
    }

    /**
     * Get used call count from file.
     */
    private function getUsed(): int
    {
        if (! is_file($this->budgetFile)) {
            return 0;
        }

        $content = file_get_contents($this->budgetFile);
        if ($content === false) {
            return 0;
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            return (int) ($data['used'] ?? 0);
        } catch (\JsonException) {
            return 0;
        }
    }

    /**
     * Save used call count to file.
     */
    private function saveUsed(int $count): void
    {
        $dir = dirname($this->budgetFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->budgetFile, json_encode(['used' => $count]));
    }
}
