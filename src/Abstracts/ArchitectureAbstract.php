<?php

declare(strict_types=1);

namespace BrainCore\Abstracts;

use Bfg\Dto\Dto;
use BrainCore\Support\Brain;

abstract class ArchitectureAbstract extends Dto
{
    /**
     * Get any runtime variable in compile time with default value and processor
     *
     * @param  string  $name
     * @param  mixed|null  $default
     * @return mixed
     */
    public function var(string $name, mixed $default = null): mixed
    {
        if (Brain::hasEnv($name)) {
            return Brain::getEnv($name);
        }

        return Brain::getVariable($name, function () use ($name, $default) {
            $value = $this->getMeta($name, $default);

            if (method_exists($this, $name)) {
                return call_user_func([$this, $name], $value);
            }

            return $value;
        });
    }

    public static function disableByDefault(): bool
    {
        return false;
    }

    public function allVars(string|null $findName = null): array
    {
        return array_merge(
            Brain::allVariables($findName),
            Brain::allEnv($findName),
        );
    }

    public function groupVars(string $group): array
    {
        $all = $this->allVars($group);
        $result = [];
        foreach ($all as $key => $value) {
            $groupQuote = preg_quote($group, '/');
            $newKey = preg_replace('/^' . $groupQuote . '_?/', '', $key);
            $newKey = trim($newKey, '_');
            $result[$newKey] = $value;
        }
        return $result;
    }

    public function varIs(string $name, mixed $value, bool $strict = true): bool
    {
        if ($strict) {
            return $this->var($name) === $value;
        }
        return $this->var($name) == $value;
    }

    public function varIsPositive(string $name): bool
    {
        return $this->varIs($name, true, false);
    }

    public function varIsNegative(string $name): bool
    {
        return $this->varIs($name, false, false);
    }

    /**
     * Get puzzle variable for architecture
     *
     * @param  string  $name
     * @param  mixed  $value
     * @return string
     */
    public function puzzle(string $name, mixed $value): string
    {
        return puzzle_replace(
            $this->var(...puzzle_params($name)), $value
        );
    }
}
