<?php

declare(strict_types=1);

namespace BrainCore\Services;

use BrainCore\Contracts\ProcessResult as ProcessResultContract;

final readonly class IlluminateProcessResult implements ProcessResultContract
{
    public function __construct(
        private \Illuminate\Process\ProcessResult $result
    ) {}

    public function successful(): bool
    {
        return $this->result->successful();
    }

    public function output(): string
    {
        return $this->result->output();
    }

    public function errorOutput(): string
    {
        return $this->result->errorOutput();
    }

    public function exitCode(): ?int
    {
        return $this->result->exitCode();
    }
}
