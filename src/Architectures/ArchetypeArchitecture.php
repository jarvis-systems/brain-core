<?php

declare(strict_types=1);

namespace BrainCore\Architectures;

use Bfg\Dto\Collections\DtoCollection;
use Bfg\Dto\Dto;
use BrainCore\Abstracts\ArchitectureAbstract;
use BrainCore\Architectures\Traits\CompilationHelpersTrait;
use BrainCore\Architectures\Traits\FactoryHelpersTrait;

abstract class ArchetypeArchitecture extends ArchitectureAbstract
{
    use FactoryHelpersTrait;
    use CompilationHelpersTrait;

    /**
     * Track which classes have already registered their 'created' event listener.
     * This prevents registering the same listener multiple times.
     * @var array<string, bool>
     */
    private static array $eventListenersRegistered = [];

    /**
     * Cache for compiled archetype instances to avoid redundant fromEmpty() calls.
     * Key: fully qualified class name
     * Value: compiled archetype instance
     * @var array<string, ArchetypeArchitecture>
     */
    private static array $archetypeInstanceCache = [];

    /**
     * @param  string  $element
     * @param  \Bfg\Dto\Collections\DtoCollection<int, Dto>  $child
     * @param  \Bfg\Dto\Collections\DtoCollection<int, ArchetypeArchitecture>  $includes
     */
    public function __construct(
        protected string $element,
        protected DtoCollection $child,
        protected DtoCollection $includes,
    ) {
        if (!isset(self::$eventListenersRegistered[static::class])) {
            static::on('created', function () {
                if (method_exists($this, 'extractAttributes')) {
                    $this->extractAttributes();
                }
                $this->init();
                $this->handle();
                $this->finalize();
            });
            self::$eventListenersRegistered[static::class] = true;
        }
    }

    /**
     * Init architecture.
     *
     * @return void
     */
    protected function init(): void
    {

    }

    /**
     * Finalize architecture.
     *
     * @return void
     */
    protected function finalize(): void
    {

    }

    protected function loadEnvInstructions(string $namePrefix): void
    {
        if (method_exists($this, 'rule')) {
            $i = 0;
            while ($ironRule = $this->var($namePrefix . '_RULE_' . $i)) {
                $this->rule('special-rule-' . $i)
                    ->critical()
                    ->text($ironRule);
                $i++;
            }
        }

        if (method_exists($this, 'guideline')) {
            $i = 0;
            while ($ironRule = $this->var($namePrefix . '_GUIDELINE_' . $i)) {
                $this->guideline('special-guideline-'.$i)
                    ->text($ironRule);
                $i++;
            }
        }
    }

    /**
     * @param  ArchetypeArchitecture|class-string<ArchetypeArchitecture>  $class
     * @return static
     */
    public function include(ArchetypeArchitecture|string $class): static
    {
        $classNamespace = is_object($class) ? get_class($class) : $class;

        if (static::class === $classNamespace) {
            return $this;
        }

        if (is_string($class)) {
            // Check cache first to avoid redundant fromEmpty() calls
            if (!isset(self::$archetypeInstanceCache[$class])) {
                self::$archetypeInstanceCache[$class] = $class::fromEmpty();
            }
            $class = self::$archetypeInstanceCache[$class];
        }

         $this->includes->add($class);

        return $this;
    }

    /**
     * Default element name.
     *
     * @return non-empty-string
     */
    abstract protected static function defaultElement(): string;

    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    abstract protected function handle(): void;
}
