<?php

declare(strict_types=1);

namespace BrainCore\Services;

use BrainCore\Contracts\ProcessResult;
use BrainCore\Contracts\ProcessRunner;
use Illuminate\Support\Facades\Process;

final class IlluminateProcessRunner implements ProcessRunner
{
    public function run(string $command, ?string $cwd = null): ProcessResult
    {
        $result = $cwd !== null
            ? Process::path($cwd)->run($command)
            : Process::run($command);

        return new IlluminateProcessResult($result);
    }
}
