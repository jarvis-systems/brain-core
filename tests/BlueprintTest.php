<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Architectures\BlueprintArchitecture;
use BrainCore\Blueprints\Guideline;
use BrainCore\Blueprints\Guideline\Example;
use BrainCore\Blueprints\IronRule;
use BrainCore\Blueprints\Response;
use BrainCore\Blueprints\Response\Sections;
use BrainCore\Blueprints\Style;
use BrainCore\Blueprints\Style\ForbiddenPhrases;
use BrainCore\Enums\IronRuleSeverityEnum;
use PHPUnit\Framework\TestCase;

/**
 * Blueprint builder contract tests.
 *
 * Enterprise invariant: Blueprint classes are the public API for
 * archetype authors. Method signatures, fluent returns, and child
 * accumulation must remain stable. Breaking these contracts breaks
 * every Brain/Agent/Command configuration.
 */
class BlueprintTest extends TestCase
{
    // ──────────────────────────────────────────────
    // BlueprintArchitecture::mutateToString
    // ──────────────────────────────────────────────

    public function testMutateToStringPassesThroughScalar(): void
    {
        $this->assertSame('hello', BlueprintArchitecture::mutateToString('hello'));
        $this->assertSame(42, BlueprintArchitecture::mutateToString(42));
        $this->assertNull(BlueprintArchitecture::mutateToString(null));
        $this->assertTrue(BlueprintArchitecture::mutateToString(true));
    }

    public function testMutateToStringImplodesArray(): void
    {
        $this->assertSame(
            'one two three',
            BlueprintArchitecture::mutateToString(['one', 'two', 'three'])
        );
    }

    public function testMutateToStringEmptyArray(): void
    {
        $this->assertSame('', BlueprintArchitecture::mutateToString([]));
    }

    // ──────────────────────────────────────────────
    // defaultElement contracts
    // ──────────────────────────────────────────────

    public function testIronRuleDefaultElement(): void
    {
        $rule = IronRule::fromEmpty();
        $array = $rule->toArray();

        $this->assertSame('rule', $array['element']);
    }

    public function testGuidelineDefaultElement(): void
    {
        $guideline = Guideline::fromEmpty();
        $array = $guideline->toArray();

        $this->assertSame('guideline', $array['element']);
    }

    public function testStyleDefaultElement(): void
    {
        $style = Style::fromEmpty();
        $array = $style->toArray();

        $this->assertSame('style', $array['element']);
    }

    public function testResponseDefaultElement(): void
    {
        $response = Response::fromEmpty();
        $array = $response->toArray();

        $this->assertSame('response_contract', $array['element']);
    }

    // ──────────────────────────────────────────────
    // IronRule — severity chain
    // ──────────────────────────────────────────────

    public function testIronRuleDefaultSeverityIsUnspecified(): void
    {
        $rule = IronRule::fromEmpty();
        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);

        $this->assertSame(IronRuleSeverityEnum::UNSPECIFIED, $severity);
    }

    public function testIronRuleCriticalSetsCorrectSeverity(): void
    {
        $rule = IronRule::fromEmpty();
        $result = $rule->critical();

        $this->assertSame($rule, $result, 'critical() must return $this');
        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::CRITICAL, $severity);
    }

    public function testIronRuleHighSetsCorrectSeverity(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->high();

        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::HIGH, $severity);
    }

    public function testIronRuleMediumSetsCorrectSeverity(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->medium();

        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::MEDIUM, $severity);
    }

    public function testIronRuleLowSetsCorrectSeverity(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->low();

        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::LOW, $severity);
    }

    public function testIronRuleSeverityFromString(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->severity('critical');

        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::CRITICAL, $severity);
    }

    public function testIronRuleSeverityFromEnum(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->severity(IronRuleSeverityEnum::HIGH);

        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::HIGH, $severity);
    }

    // ──────────────────────────────────────────────
    // IronRule — builder chain
    // ──────────────────────────────────────────────

    public function testIronRuleTextReturnsSelf(): void
    {
        $rule = IronRule::fromEmpty();
        $result = $rule->text('Do not hardcode secrets');

        $this->assertSame($rule, $result, 'text() must return $this');
    }

    public function testIronRuleWhyReturnsSelf(): void
    {
        $rule = IronRule::fromEmpty();
        $result = $rule->why('Security risk');

        $this->assertSame($rule, $result, 'why() must return $this');
    }

    public function testIronRuleOnViolationReturnsSelf(): void
    {
        $rule = IronRule::fromEmpty();
        $result = $rule->onViolation('Reject and escalate');

        $this->assertSame($rule, $result, 'onViolation() must return $this');
    }

    public function testIronRuleTextWithArrayImplodes(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->text(['Never', 'commit', 'secrets']);

        $array = $rule->toArray();
        $textChild = $array['child'][0] ?? null;

        $this->assertNotNull($textChild);
        $this->assertSame('Never commit secrets', $textChild['text']);
    }

    public function testIronRuleWhyWithArrayImplodes(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->why(['Leaked', 'credentials']);

        $array = $rule->toArray();
        $whyChild = $array['child'][0] ?? null;

        $this->assertNotNull($whyChild);
        $this->assertSame('Leaked credentials', $whyChild['text']);
    }

    public function testIronRuleFullChainAccumulatesChildren(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->critical()
            ->text('No debug artifacts')
            ->why('Breaks production')
            ->onViolation('Remove immediately');

        $array = $rule->toArray();

        $this->assertCount(3, $array['child'], 'text + why + onViolation = 3 children');

        $severity = (new \ReflectionProperty($rule, 'severity'))->getValue($rule);
        $this->assertSame(IronRuleSeverityEnum::CRITICAL, $severity);
    }

    // ──────────────────────────────────────────────
    // IronRule — id contract
    // ──────────────────────────────────────────────

    public function testIronRuleIdSetViaFindOrCreate(): void
    {
        // Production pathway: findOrCreateChild uses set('id', ...)
        $rule = IronRule::fromEmpty();
        $rule->set('id', 'no-secrets');

        $array = $rule->toArray();
        $this->assertSame('no-secrets', $array['id']);
    }

    public function testIronRuleIdMethodSyncsWithDtoStorage(): void
    {
        // id() method must sync with Dto internal storage via set()
        $rule = IronRule::fromEmpty();
        $result = $rule->id('no-hardcoded-paths');

        $this->assertSame($rule, $result, 'id() must return $this');

        $array = $rule->toArray();
        $this->assertSame('no-hardcoded-paths', $array['id']);
    }

    // ──────────────────────────────────────────────
    // Guideline — builder chain
    // ──────────────────────────────────────────────

    public function testGuidelineTextReturnsSelf(): void
    {
        $guideline = Guideline::fromEmpty();
        $result = $guideline->text('Always validate input');

        $this->assertSame($guideline, $result, 'text() must return $this');
    }

    public function testGuidelineExampleReturnsExampleDto(): void
    {
        $guideline = Guideline::fromEmpty();
        $example = $guideline->example('Sample usage');

        $this->assertInstanceOf(Example::class, $example);
    }

    public function testGuidelineExampleWithoutTextReturnsExampleDto(): void
    {
        $guideline = Guideline::fromEmpty();
        $example = $guideline->example();

        $this->assertInstanceOf(Example::class, $example);
    }

    public function testGuidelineHasNoWorkflowMethod(): void
    {
        $this->assertFalse(
            method_exists(Guideline::class, 'workflow'),
            'workflow() dead method must be removed'
        );
    }

    public function testGuidelineIdSetViaFindOrCreate(): void
    {
        // Production pathway: findOrCreateChild uses set('id', ...)
        $guideline = Guideline::fromEmpty();
        $guideline->set('id', 'input-validation');

        $array = $guideline->toArray();
        $this->assertSame('input-validation', $array['id']);
    }

    public function testGuidelineIdMethodSyncsWithDtoStorage(): void
    {
        // id() method must sync with Dto internal storage via set()
        $guideline = Guideline::fromEmpty();
        $result = $guideline->id('validate-all-inputs');

        $this->assertSame($guideline, $result, 'id() must return $this');

        $array = $guideline->toArray();
        $this->assertSame('validate-all-inputs', $array['id']);
    }

    public function testGuidelineAccumulatesChildren(): void
    {
        $guideline = Guideline::fromEmpty();
        $guideline->text('Rule one');
        $guideline->example('Example one');

        $array = $guideline->toArray();
        $this->assertCount(2, $array['child'], 'text + example = 2 children');
    }

    // ──────────────────────────────────────────────
    // Style — builder chain
    // ──────────────────────────────────────────────

    public function testStyleLanguageReturnsSelf(): void
    {
        $style = Style::fromEmpty();
        $result = $style->language('English');

        $this->assertSame($style, $result, 'language() must return $this');
    }

    public function testStyleToneReturnsSelf(): void
    {
        $style = Style::fromEmpty();
        $result = $style->tone('Analytical');

        $this->assertSame($style, $result, 'tone() must return $this');
    }

    public function testStyleBrevityReturnsSelf(): void
    {
        $style = Style::fromEmpty();
        $result = $style->brevity('Medium');

        $this->assertSame($style, $result, 'brevity() must return $this');
    }

    public function testStyleFormattingReturnsSelf(): void
    {
        $style = Style::fromEmpty();
        $result = $style->formatting('Strict XML');

        $this->assertSame($style, $result, 'formatting() must return $this');
    }

    public function testStyleForbiddenPhrasesReturnsForbiddenPhrasesDto(): void
    {
        $style = Style::fromEmpty();
        $phrases = $style->forbiddenPhrases();

        $this->assertInstanceOf(ForbiddenPhrases::class, $phrases);
    }

    public function testStyleForbiddenPhrasesReturnsSameInstance(): void
    {
        $style = Style::fromEmpty();
        $first = $style->forbiddenPhrases();
        $second = $style->forbiddenPhrases();

        $this->assertSame($first, $second, 'forbiddenPhrases() must return same instance');
    }

    public function testStyleIdMethodSyncsWithDtoStorage(): void
    {
        $style = Style::fromEmpty();
        $result = $style->id('default-style');

        $this->assertSame($style, $result, 'id() must return $this');

        $array = $style->toArray();
        $this->assertSame('default-style', $array['id']);
    }

    public function testStyleFullChainAccumulatesChildren(): void
    {
        $style = Style::fromEmpty();
        $style->language('Ukrainian')
            ->tone('Direct')
            ->brevity('Short')
            ->formatting('Markdown');

        $array = $style->toArray();
        $this->assertCount(4, $array['child'], 'language + tone + brevity + formatting = 4 children');
    }

    // ──────────────────────────────────────────────
    // Response — builder chain
    // ──────────────────────────────────────────────

    public function testResponseSectionsReturnsSectionsDto(): void
    {
        $response = Response::fromEmpty();
        $sections = $response->sections();

        $this->assertInstanceOf(Sections::class, $sections);
    }

    public function testResponseCodeBlocksReturnsSelf(): void
    {
        $response = Response::fromEmpty();
        $result = $response->codeBlocks('Strict formatting');

        $this->assertSame($response, $result, 'codeBlocks() must return $this');
    }

    public function testResponsePatchesReturnsSelf(): void
    {
        $response = Response::fromEmpty();
        $result = $response->patches('Changes allowed after validation');

        $this->assertSame($response, $result, 'patches() must return $this');
    }

    public function testResponseIdMethodSyncsWithDtoStorage(): void
    {
        $response = Response::fromEmpty();
        $result = $response->id('main-contract');

        $this->assertSame($response, $result, 'id() must return $this');

        $array = $response->toArray();
        $this->assertSame('main-contract', $array['id']);
    }

    public function testResponseFullChainAccumulatesChildren(): void
    {
        $response = Response::fromEmpty();
        $response->sections();
        $response->codeBlocks('Strict');
        $response->patches('Validated');

        $array = $response->toArray();
        $this->assertCount(3, $array['child'], 'sections + codeBlocks + patches = 3 children');
    }

    // ──────────────────────────────────────────────
    // BlueprintArchitecture::text — append contract
    // ──────────────────────────────────────────────

    public function testTextAppendsWithNewline(): void
    {
        $rule = IronRule::fromEmpty();
        $rule->text('Line one');
        // Using BlueprintArchitecture::text (inherited), not IronRule::text
        // IronRule::text adds children, BlueprintArchitecture::text sets $text property
        // These are different methods — IronRule overrides text()

        // Verify IronRule::text creates child nodes
        $array = $rule->toArray();
        $this->assertCount(1, $array['child']);
    }

    public function testBlueprintTextPropertyAppends(): void
    {
        // Style doesn't override text(), so it uses BlueprintArchitecture::text()
        $style = Style::fromEmpty();
        $style->text('First line');
        $style->text('Second line');

        $array = $style->toArray();
        $this->assertSame("First line\nSecond line", $array['text']);
    }

    public function testBlueprintTextWithArrayImplodes(): void
    {
        $style = Style::fromEmpty();
        $style->text(['multiple', 'words']);

        $array = $style->toArray();
        $this->assertSame('multiple words', $array['text']);
    }

    // ──────────────────────────────────────────────
    // IronRuleSeverityEnum contract
    // ──────────────────────────────────────────────

    public function testSeverityEnumHasAllExpectedCases(): void
    {
        $cases = IronRuleSeverityEnum::cases();
        $values = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('critical', $values);
        $this->assertContains('high', $values);
        $this->assertContains('medium', $values);
        $this->assertContains('low', $values);
        $this->assertContains('unspecified', $values);
        $this->assertCount(5, $cases, 'Enum must have exactly 5 severity levels');
    }

    // ──────────────────────────────────────────────
    // Determinism proof
    // ──────────────────────────────────────────────

    public function testBlueprintBuildingIsDeterministic(): void
    {
        $build = static function (): array {
            $rule = IronRule::fromEmpty();
            $rule->critical()
                ->text('No secrets')
                ->why('Security')
                ->onViolation('Remove');
            return $rule->toArray();
        };

        $this->assertSame($build(), $build(), 'Blueprint building must be deterministic');
    }

    public function testMutateToStringIsDeterministic(): void
    {
        $this->assertSame(
            BlueprintArchitecture::mutateToString(['a', 'b']),
            BlueprintArchitecture::mutateToString(['a', 'b']),
            'mutateToString must be deterministic'
        );
    }
}
