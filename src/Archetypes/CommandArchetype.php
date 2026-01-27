<?php

declare(strict_types=1);

namespace BrainCore\Archetypes;

use BrainCore\Archetypes\Traits\MetasTrait;
use BrainCore\Archetypes\Traits\ExecuteTrait;
use BrainCore\Archetypes\Traits\ResponseTrait;
use BrainCore\Archetypes\Traits\IronRulesTrait;
use BrainCore\Archetypes\Traits\GuidelinesTrait;
use BrainCore\Architectures\ArchetypeArchitecture;
use BrainCore\Archetypes\Traits\ExtractCommandAttributesTrait;
use BrainCore\Attributes\Meta;
use Illuminate\Support\Str;
use Symfony\Component\VarExporter\VarExporter;

abstract class CommandArchetype extends ArchetypeArchitecture
{
    use MetasTrait;
    use ExecuteTrait;
    use ResponseTrait;
    use IronRulesTrait;
    use GuidelinesTrait;
    use ExtractCommandAttributesTrait;

    /**
     * Default element name.
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'command';
    }

    protected function init(): void
    {
        $agent = $this->var('AGENT', 'claude');
        $upperCaseAgent = strtoupper((string) $agent);
        $varName = $upperCaseAgent . '_COMMAND_MODEL';
        $className = Str::of(static::class)->snake()->upper()->explode('\\')->last();
        $model = $this->var($className . '_MODEL', $this->var('COMMAND_MODEL', $this->var($varName)));
        if ($model) {
            $this->setMeta('model', $model);
        }
    }

    protected function finalize(): void
    {
        $className = Str::of(static::class)
            ->replace("BrainNode\\Commands\\", '')
            ->replace("\\", '_')
            ->snake()
            ->replace("__", '_')
            ->upper()
            ->trim()
            ->trim('_')
            ->toString();

        $this->loadEnvInstructions($className);
    }

    /**
     * Get command ID.
     *
     * @param  mixed  ...$args
     * @return string
     */
    public static function id(...$args): string
    {
        foreach ($args as $index => $arg) {
            try {
                $args[$index] = VarExporter::export($arg);
            } catch (\Throwable $e) {
                unset($args[$index]);
            }
        }
        $ref = new \ReflectionClass(static::class);
        $attributes = $ref->getAttributes(Meta::class);
        $id = 'unknown';
        foreach ($attributes as $attribute) {
            $meta = $attribute->newInstance();
            if ($meta->name === 'id') {
                $id = $meta->getText();
                break;
            }
        }
        return "/" . $id . (empty($args) ? '' : ' (' . implode(', ', $args) . ')');
    }
}
