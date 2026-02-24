<?php

declare(strict_types=1);

namespace BrainCore\Contracts;

interface BrainToolInvoker
{
    public function docsSearch(string $query, int $limit = 5, int $headers = 2): array;

    public function diagnose(): array;

    public function status(): array;

    public function listIncludes(string $agent): array;

    public function listMasters(): array;

    public function readinessCheck(): array;
}
