<?php

declare(strict_types=1);

namespace BrainCore\Architectures\Traits;

use BrainCore\Attributes\Meta;
use ReflectionAttribute;

trait ExtractMetaAttributesTrait
{
    protected static array $alreadyCalled = [];

    /**
     * Extract class attributes.
     *
     * @return void
     */
    protected function extractMetaAttributes(): void
    {
        $reflection = static::reflection();
        $metaAttributes = $reflection->getAttributes(Meta::class);

        foreach ($metaAttributes as $attribute) {
            [$metaName, $value] = $this->extractAttribute($attribute);
            if ($metaName && ! isset(static::$alreadyCalled[$metaName])) {
                $this->setMeta([$metaName => $value]);
            }
        }
    }

    protected function getMetaValue(string $name, mixed $default = null): mixed
    {
        if (isset(static::$alreadyCalled[$name])) {
            return static::$alreadyCalled[$name];
        }

        $reflection = static::reflection();
        $metaAttributes = $reflection->getAttributes(Meta::class);

        foreach ($metaAttributes as $attribute) {
            [$metaName, $value] = $this->extractAttribute($attribute);
            if ($metaName === $name) {
                return static::$alreadyCalled[$name] = $value !== ''
                    ? $value : $default;
            }
        }
        return $default;
    }

    private function extractAttribute(ReflectionAttribute $attribute): array
    {
        /** @var Meta $metaInstance */
        $metaInstance = $attribute->newInstance();
        if (method_exists($this, 'metas')) { // @phpstan-ignore function.alreadyNarrowedType (guard needed: AgentArchetype, McpArchitecture lack metas())
            $this->metas()->meta($metaInstance->name)
                ->text($metaInstance->getText());
        }
        $name = null;
        if (is_string($metaInstance->name)) {
            $name = $metaInstance->name;
        } else {
            [$agent, $instanceName] = array_values($metaInstance->name);
            if ($agent === $this->var('AGENT')) {
                $name = $instanceName;
            }
        }
        if ($name) {
            return [$name, $metaInstance->getText()];
        }

        return [null, null];
    }
}
