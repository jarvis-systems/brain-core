<?php

declare(strict_types=1);

namespace BrainCore\Contracts;

interface ProcessRunner
{
    public function run(string $command, ?string $cwd = null): ProcessResult;
}
