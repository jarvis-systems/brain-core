<?php

declare(strict_types=1);

namespace BrainCore\Services;

use BrainCore\Contracts\BrainToolInvoker;
use BrainCore\Contracts\ProcessResult;
use BrainCore\Contracts\ProcessRunner;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

final class BrainCliInvoker implements BrainToolInvoker
{
    private const BRAIN_BIN = 'brain';

    private const SECRET_PATTERNS = [
        '/_TOKEN["\']?\s*[:=]\s*["\']?[A-Za-z0-9_-]{20,}/i',
        '/_KEY["\']?\s*[:=]\s*["\']?[A-Za-z0-9_-]{20,}/i',
        '/_SECRET["\']?\s*[:=]\s*["\']?[A-Za-z0-9_-]{20,}/i',
        '/PASSWORD["\']?\s*[:=]\s*["\']?[^\s"\']{8,}/i',
        '/Bearer\s+[A-Za-z0-9_-]{20,}/i',
    ];

    private readonly ProcessRunner $runner;

    private readonly ?string $workingDirectory;

    public function __construct(
        ?ProcessRunner $runner = null,
        ?string $workingDirectory = null,
    ) {
        $this->runner = $runner ?? new IlluminateProcessRunner();
        $this->workingDirectory = $workingDirectory;
    }

    public function docsSearch(string $query, int $limit = 5, int $headers = 2): array
    {
        $args = sprintf(
            '%s --limit=%d --headers=%d --json',
            escapeshellarg($query),
            $limit,
            $headers
        );

        return $this->executeJson("docs {$args}");
    }

    public function diagnose(): array
    {
        return $this->executeJson('diagnose --json');
    }

    public function status(): array
    {
        return $this->executeJson('status --json');
    }

    public function listIncludes(string $agent): array
    {
        return $this->executeJson(sprintf('list:includes %s --json', escapeshellarg($agent)));
    }

    public function listMasters(): array
    {
        return $this->executeJson('list:masters --json');
    }

    public function readinessCheck(): array
    {
        return $this->executeJson('readiness:check --json');
    }

    private function executeJson(string $command): array
    {
        $fullCommand = sprintf('%s %s', self::BRAIN_BIN, $command);
        $result = $this->runner->run($fullCommand, $this->workingDirectory);

        if (! $result->successful()) {
            throw new RuntimeException(
                sprintf('Brain CLI failed: %s', $result->errorOutput() ?: $result->output()),
                $result->exitCode() ?? 1
            );
        }

        $output = $result->output();
        $this->assertNoSecrets($output);

        try {
            $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                sprintf('Invalid JSON from Brain CLI: %s', $e->getMessage()),
                previous: $e
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException('Brain CLI returned non-array JSON');
        }

        return $data;
    }

    private function assertNoSecrets(string $output): void
    {
        foreach (self::SECRET_PATTERNS as $pattern) {
            if (preg_match($pattern, $output)) {
                throw new RuntimeException('Secret pattern detected in CLI output');
            }
        }
    }
}
