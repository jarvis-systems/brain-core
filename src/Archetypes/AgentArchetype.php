<?php

declare(strict_types=1);

namespace BrainCore\Archetypes;

use BrainCore\Archetypes\Traits\DeterminismTrait;
use BrainCore\Archetypes\Traits\ExtractAttributesTrait;
use BrainCore\Archetypes\Traits\GuidelinesTrait;
use BrainCore\Archetypes\Traits\IronRulesTrait;
use BrainCore\Archetypes\Traits\PurposeTrait;
use BrainCore\Archetypes\Traits\ResponseTrait;
use BrainCore\Archetypes\Traits\StyleTrait;
use BrainCore\Architectures\ArchetypeArchitecture;
use BrainCore\Attributes\Meta;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Tools\TaskTool;
use Illuminate\Support\Str;

abstract class AgentArchetype extends ArchetypeArchitecture
{
    use StyleTrait;
    use PurposeTrait;
    use ResponseTrait;
    use IronRulesTrait;
    use GuidelinesTrait;
    use DeterminismTrait;
    use ExtractAttributesTrait;

    /**
     * Default element name.
     *
     * @return non-empty-string
     */
    protected static function defaultElement(): string
    {
        return 'system';
    }

    /**
     * Init architecture.
     *
     * @return void
     */
    protected function init(): void
    {
        $agent = $this->var('AGENT', 'claude');
        $upperCaseAgent = strtoupper((string) $agent);
        $varName = $upperCaseAgent . '_MASTER_MODEL';
        $className = Str::of(static::class)->snake()->upper()->explode('\\')->last();
        $className = trim(trim($className), '_');
        $model = $this->var($className . '_MODEL', $this->var('MASTER_MODEL', $this->var($varName)));
        if ($model) {
            $this->setMeta('model', $model);
        }
    }

    protected function finalize(): void
    {
        $className = Str::of(static::class)
            ->replace("BrainNode\\Agents\\", '')
            ->replace("\\", '_')
            ->snake()
            ->upper()
            ->trim()
            ->trim('_')
            ->toString();

        $this->loadEnvInstructions($className);
    }

    /**
     * Print task representation.
     *
     * @param  non-empty-string  ...$text
     * @return string
     */
    public static function call(...$text): string
    {
        return TaskTool::call(static::class, ...$text);
    }

    public static function delegate(): string
    {
        return Operator::delegate(static::id());
    }

    /**
     * Get agent ID.
     *
     * @return string
     */
    public static function id(): string
    {
        $ref = new \ReflectionClass(static::class);
        $attributes = $ref->getAttributes(Meta::class);
        $id = 'explore';
        foreach ($attributes as $attribute) {
            $meta = $attribute->newInstance();
            if ($meta->name === 'id') {
                $id = $meta->getText();
                break;
            }
        }
        return puzzle('agent', $id);
    }
}
