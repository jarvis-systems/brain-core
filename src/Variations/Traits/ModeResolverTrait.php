<?php

declare(strict_types=1);

namespace BrainCore\Variations\Traits;

trait ModeResolverTrait
{
    private const VALID_COGNITIVE = ['minimal', 'standard', 'deep', 'exhaustive'];
    private const VALID_STRICT = ['relaxed', 'standard', 'strict', 'paranoid'];

    public function getCognitiveMode(): string
    {
        $cognitive = $this->var('COGNITIVE_LEVEL', 'standard');
        if (!in_array($cognitive, self::VALID_COGNITIVE, true)) {
            throw new \InvalidArgumentException(
                "Invalid COGNITIVE_LEVEL='$cognitive'. Allowed: " . implode(', ', self::VALID_COGNITIVE)
            );
        }
        return $cognitive;
    }

    public function getStrictMode(): string
    {
        $strict = $this->var('STRICT_MODE', 'standard');
        if (!in_array($strict, self::VALID_STRICT, true)) {
            throw new \InvalidArgumentException(
                "Invalid STRICT_MODE='$strict'. Allowed: " . implode(', ', self::VALID_STRICT)
            );
        }
        return $strict;
    }

    public function isJsonStrictRequired(): bool
    {
        $strict = $this->getStrictMode();
        return in_array($strict, ['strict', 'paranoid'], true);
    }

    public function isDeepCognitive(): bool
    {
        $cognitive = $this->getCognitiveMode();
        return in_array($cognitive, ['deep', 'exhaustive'], true);
    }

    public function isParanoidMode(): bool
    {
        return $this->getStrictMode() === 'paranoid';
    }

    public function getMcpValidationMode(): string
    {
        return $this->getStrictMode();
    }

    public function getCookbookPreset(string $domain = 'memory'): array
    {
        $cognitive = $this->getCognitiveMode();
        $strict = $this->getStrictMode();
        $limit = $this->getCookbookLimit($cognitive, $strict);

        $base = [
            'include' => 'cases',
            'limit' => $limit,
        ];

        if (in_array($strict, ['strict', 'paranoid'], true)) {
            return array_merge($base, [
                'case_category' => 'store,gates-rules,essential-patterns',
                'priority' => 'critical',
                'strict' => $strict,
                'cognitive' => $cognitive,
            ]);
        }

        if (in_array($cognitive, ['deep', 'exhaustive'], true)) {
            return array_merge($base, [
                'case_category' => $domain === 'memory' ? 'search,store' : 'plan,validate,essential-patterns',
                'priority' => 'high',
                'strict' => $strict,
                'cognitive' => $cognitive,
            ]);
        }

        return array_merge($base, [
            'case_category' => $domain === 'memory' ? 'search' : 'plan',
            'priority' => 'high',
            'strict' => $strict,
            'cognitive' => $cognitive,
        ]);
    }

    private function getCookbookLimit(string $cognitive, string $strict): int
    {
        return match (true) {
            $strict === 'paranoid' || $cognitive === 'exhaustive' => 40,
            $strict === 'strict' || $cognitive === 'deep' => 30,
            $cognitive === 'standard' => 20,
            default => 12,
        };
    }
}
