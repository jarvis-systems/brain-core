<?php

declare(strict_types=1);

namespace BrainCore\Services\McpCall;

final class McpRedactor
{
    public const SENSITIVE_KEYS = [
        'api-key',
        'apikey',
        'api_key',
        'token',
        'bearer',
        'authorization',
        'secret',
        'key',
        'password',
        'auth',
        'credential',
        'anthropic_api_key',
        'openai_api_key',
        'google_api_key',
        'slack',
        'xoxb',
        'context7_api_key',
    ];

    private const SENSITIVE_FLAG_PATTERNS = [
        '/^--api[-_]?key$/i',
        '/^--token$/i',
        '/^--secret$/i',
        '/^--password$/i',
        '/^--auth$/i',
        '/^--key$/i',
        '/^--bearer$/i',
        '/^--authorization$/i',
        '/^-H$/i',
        '/^Authorization:$/i',
        '/^Bearer:$/i',
    ];

    private const SENSITIVE_VALUE_PATTERNS = [
        '/^sk-[a-zA-Z0-9_-]{16,}$/',
        '/^sk-ant-[a-zA-Z0-9_-]{16,}$/',
        '/^ctx7sk-[a-zA-Z0-9_-]{16,}$/',
        '/^xoxb-[a-zA-Z0-9_-]+$/',
        '/^Bearer\s+[a-zA-Z0-9_\-\.]{16,}$/i',
    ];

    private const STRING_PATTERNS = [
        '/(sk-[a-zA-Z0-9_-]{16,})/' => '[REDACTED]',
        '/(ctx7sk-[a-zA-Z0-9_-]{16,})/' => '[REDACTED]',
        '/(Bearer\s+)[a-zA-Z0-9_\-\.]{16,}/i' => '$1[REDACTED]',
        '/(["\']?(?:key|token|secret|password|auth|credential)["\']?\s*[:=]\s*["\']?)[a-zA-Z0-9_\-\.]{16,}/i' => '$1[REDACTED]',
        '#/(Users|home|var|etc|tmp|root|app)/[^\s"\']+#i' => '[REDACTED_PATH]',
        '#[A-Z]:\\\\[^\s"\']+#i' => '[REDACTED_PATH]',
    ];

    private const PLACEHOLDER = '<REDACTED_ARG>';

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

    /**
     * Sanitize command arguments for dry-run preview.
     * Replaces sensitive flags AND their values with a single <REDACTED_ARG> placeholder.
     * This prevents "infrastructure fingerprinting" via dry-run output.
     *
     * @param list<string> $args
     * @return array{0: list<string>, 1: bool}
     */
    public static function sanitizeCommandArgs(array $args): array
    {
        $sanitized = [];
        $applied = false;
        $skipNext = false;

        foreach ($args as $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            if (self::isSensitiveFlag($arg)) {
                $sanitized[] = self::PLACEHOLDER;
                $applied = true;
                $skipNext = true;
                continue;
            }

            if (self::isSensitiveValue($arg)) {
                $sanitized[] = self::PLACEHOLDER;
                $applied = true;
                continue;
            }

            $sanitized[] = $arg;
        }

        return [$sanitized, $applied];
    }

    /**
     * Check if an argument is a sensitive flag (requires value redaction).
     */
    private static function isSensitiveFlag(string $arg): bool
    {
        foreach (self::SENSITIVE_FLAG_PATTERNS as $pattern) {
            if (preg_match($pattern, $arg)) {
                return true;
            }
        }

        if (preg_match('/^--([a-zA-Z0-9_-]+)$/i', $arg, $m)) {
            $flagName = strtolower(str_replace(['-', '_'], '', $m[1]));
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                $normalized = strtolower(str_replace(['-', '_'], '', $sensitive));
                if ($flagName === $normalized || str_contains($flagName, $normalized)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a value matches sensitive patterns (token prefixes).
     */
    private static function isSensitiveValue(string $arg): bool
    {
        foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $arg)) {
                return true;
            }
        }
        return false;
    }
}
