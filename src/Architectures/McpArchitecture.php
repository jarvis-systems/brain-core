<?php

declare(strict_types=1);

namespace BrainCore\Architectures;

use BrainCore\Abstracts\ArchitectureAbstract;
use BrainCore\Architectures\Traits\ExtractMetaAttributesTrait;
use BrainCore\Attributes\Meta;
use BrainCore\Compilation\Traits\LogDegradationTrait;
use Symfony\Component\VarExporter\VarExporter;

abstract class McpArchitecture extends ArchitectureAbstract
{
    use ExtractMetaAttributesTrait;
    use LogDegradationTrait;

    /**
     * Track which classes have already registered their 'created' event listener.
     * This prevents registering the same listener multiple times.
     * @var array<string, bool>
     */
    private static array $eventListenersRegistered = [];

    public function __construct()
    {
        if (!isset(self::$eventListenersRegistered[static::class])) {
            static::on('created', function () {
                $this->extractMetaAttributes();
                $this->construct();
            });
            self::$eventListenersRegistered[static::class] = true;
        }
    }

    protected function construct(): void
    {

    }

    public static function call(string $name, ...$args): string
    {
        foreach ($args as $index => $arg) {
            try {
                $args[$index] = VarExporter::export($arg);
            } catch (\Throwable $e) {
                static::logDegradation('McpArchitecture::call', $e);
                $args[$index] = '"unserializable_argument"';
            }
        }
        return static::id() . "__$name" . (empty($args) ? '' : '(' . implode(', ', $args) . ')');
    }

    public static function method(string $name): string
    {
        return static::call($name);
    }

    public static function callJson(string $method, array $args = []): string
    {
        self::ksortRecursive($args);
        $json = json_encode(
            $args,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        return static::id() . "__$method($json)";
    }

    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
        ksort($array);
    }

    /**
     * Get MCP server ID with optional arguments formatted for tool invocation.
     */
    public static function id(...$args): string
    {
        foreach ($args as $index => $arg) {
            try {
                $args[$index] = VarExporter::export($arg);
            } catch (\Throwable $e) {
                static::logDegradation('McpArchitecture::id', $e);
                $args[$index] = '"unserializable_argument"';
            }
        }
        $ref = new \ReflectionClass(static::class);
        $attributes = $ref->getAttributes(Meta::class);
        $id = null;
        foreach ($attributes as $attribute) {
            $meta = $attribute->newInstance();
            if ($meta->name === 'id') {
                $id = $meta->getText();
                break;
            }
        }
        if ($id === null) {
            throw new \RuntimeException(
                sprintf('MCP class %s requires #[Meta(\'id\', ...)] attribute. No silent fallback allowed.', static::class)
            );
        }
        return "mcp__" . $id . (empty($args) ? '' : '(' . implode(', ', $args) . ')');
    }
}
