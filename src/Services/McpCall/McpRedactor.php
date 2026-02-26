<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

final class McpRedactor
{
    private const STRING_PATTERNS = [
        '/(sk-[a-zA-Z0-9_-]{16,})/' => '[REDACTED]',
        '/(ctx7sk-[a-zA-Z0-9_-]{16,})/' => '[REDACTED]',
        '/(Bearer\s+)[a-zA-Z0-9_\-\.]{16,}/i' => '$1[REDACTED]',
        '/(["\']?(?:key|token|secret|password|auth|credential)["\']?\s*[:=]\s*["\']?)[a-zA-Z0-9_\-\.]{16,}/i' => '$1[REDACTED]',
        '#/(Users|home|var|etc|tmp|root|app)/[^\s"\']+#i' => '[REDACTED_PATH]',
        '#[A-Z]:\\\\[^\s"\']+#i' => '[REDACTED_PATH]',
    ];

    /**
     * Redact sensitive values in an array.
     * @return array{0: array, 1: bool}
     */
    public static function redactArray(array $data): array
    {
        $sensitiveKeys = [
            'api_key',
            'apikey',
            'token',
            'secret',
            'password',
            'auth',
            'credential',
            'CONTEXT7_API_KEY',
            'ANTHROPIC_API_KEY',
            'OPENAI_API_KEY'
        ];

        $sensitiveValuePatterns = [
            '/^sk-[a-zA-Z0-9_-]{16,}$/',
            '/^ctx7sk-[a-zA-Z0-9_-]{16,}$/',
            '/^Bearer\s+[a-zA-Z0-9_\-\.]{16,}$/i',
        ];

        $applied = false;
        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys, $sensitiveValuePatterns, &$applied) {
            // Check key-based redaction
            foreach ($sensitiveKeys as $sensitive) {
                if (is_string($key) && stripos($key, $sensitive) !== false) {
                    if ($value !== '[REDACTED]') {
                        $value = '[REDACTED]';
                        $applied = true;
                    }
                    return;
                }
            }

            // Check value-based redaction for token patterns
            if (is_string($value)) {
                foreach ($sensitiveValuePatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $value = '[REDACTED]';
                        $applied = true;
                        return;
                    }
                }
            }
        });

        return [$data, $applied];
    }

    public static function redactString(string $input): string
    {
        $output = $input;
        foreach (self::STRING_PATTERNS as $pattern => $replacement) {
            $output = preg_replace($pattern, $replacement, $output);
        }
        return $output;
    }
}
