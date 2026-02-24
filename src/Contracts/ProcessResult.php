<?php

declare(strict_types=1);

namespace BrainCore\Contracts;

interface ProcessResult
{
    public function successful(): bool;

    public function output(): string;

    public function errorOutput(): string;

    public function exitCode(): ?int;
}
