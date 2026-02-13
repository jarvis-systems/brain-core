<?php

declare(strict_types=1);

namespace BrainCore;

use BackedEnum;
use BrainCore\Support\Brain;

class XmlBuilder
{
    private static array $buildCache = [];

    private static array $cache = [];

    /**
     * Track seen rule IDs to prevent duplicates
     */
    private array $seenRuleIds = [];

    /**
     * @param  array<string, mixed>  $structure
     */
    public function __construct(
        protected array $structure
    ) {
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    public static function from(array $structure): string
    {
        // Cache by structure hash for identical structures
        $cacheKey = md5(serialize($structure));

        if (isset(self::$buildCache[$cacheKey])) {
            return self::$buildCache[$cacheKey];
        }

        $result = (new static($structure))->build();
        self::$buildCache[$cacheKey] = $result;

        return $result;
    }

    protected function build(): string
    {
        return $this->renderNode($this->structure, true);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function renderNode(array $node, bool $isRoot = false, int $i = 0): string
    {
        $element = $node['element'] ?? '';
        $scc = max($i - 3, 0);
        $sccNext = $scc+1;

        if ($element === '') {
            return '';
        }
        if (Brain::getEnv('BRAIN_COMPILE_WITHOUT_META') && in_array($element, ['meta', 'metadata'], true)) {
            return '';
        }

        [$attributes, $cleanNode, $params] = $this->extractAttributes($node);

//        if ($element === 'guideline') {
//            dd($cleanNode, $params);
//        }

        $text = $cleanNode['text'] ?? null;
        $children = isset($cleanNode['child']) && is_array($cleanNode['child'])
            ? $cleanNode['child']
            : [];
        $single = (bool) ($cleanNode['single'] ?? false);

        // Flatten purpose/execute/mission/provides+guidelines: extract guidelines and render as siblings
        // All these elements can have nested guidelines that should be flattened
        $extractedGuidelines = null;
        $purposeLikeElements = ['purpose', 'execute', 'mission', 'provides'];
        if (in_array($element, $purposeLikeElements, true)) {
            foreach ($children as $idx => $child) {
                if (is_array($child) && ($child['element'] ?? '') === 'guidelines') {
                    $extractedGuidelines = $child;
                    unset($children[$idx]);
                    $children = array_values($children); // Re-index
                    break;
                }
            }
        }

        if ($single && $this->isEmptyContent($text, $children)) {
            return '<' . $element . $attributes . '/>';
        }

        if ($this->hasInlineText($text, $children)) {
            $str = '<' . $element . $attributes . '>';
            $str .= $this->escape((string) $text);
            $str .= '</' . $element . '>';
            // Append extracted guidelines after purpose
            if ($extractedGuidelines !== null) {
                $str .= "\n" . $this->renderNode($extractedGuidelines, false, $i + 1);
            }
            return $str;
        }

        $lines = [];
        $examples = [];
        if ($element === 'iron_rules') {
            $lines[] = "";
            $lines[] = MD::fromArray([
                'Iron Rules' => null
            ], $scc);
        } elseif ($element === 'guideline') {
            $lines[] = MD::fromArray([
                (isset($params['id']) && $params['id'] ? str_replace(['_','-'], ' ', $params['id']) : 'Guideline') => null
            ], $scc);
        } elseif ($element === 'rule') {
            // Format rule with bold labels and auto-coded status values
            // Research shows bold increases model focus on key terms
            // Backticks for status values prevent semantic confusion
            $ruleText = collect($children)->where('element', 'text')->first()['text'] ?? null;
            $ruleChildren = collect($children)->where('element', '!=', 'text')->mapWithKeys(function ($child) {
                $key = $child['element'] ?? 'item';
                $value = $child['text'] ?? null;
                // Bold the label + auto-code status values in the text
                return [MD::bold($key) => $value !== null ? MD::autoCode($value) : null];
            })->toArray();

            $lines[] = MD::fromArray([
                (isset($params['id']) && $params['id'] ? $params['id'] : 'Rule')
                . (isset($params['severity']) && $params['severity'] ? ' ' . MD::severity($params['severity']) : '')
                => $ruleText !== null ? MD::autoCode($ruleText) : null,
                $ruleChildren
            ], $sccNext);
        } elseif (! isset(static::$cache['iron_rules_exists'])) {
            $lines[] = '<' . $element . $attributes . '>';
        }

        if ($text !== null && $text !== '') {
            $lines[] = $this->escape((string) $text);
        }

        if ($element === 'guidelines') {
            $lines[] = '';
        }

        $firstChildRendered = false;
        $iron_rules_exists = false;

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (isset($child['element']) && $child['element'] === 'iron_rules') {
                $iron_rules_exists = true;
            }

            // Deduplicate rules by ID (skip if already seen)
            if (isset($child['element']) && $child['element'] === 'rule') {
                $ruleId = $child['id'] ?? null;
                if ($ruleId !== null) {
                    if (isset($this->seenRuleIds[$ruleId])) {
                        continue; // Skip duplicate rule
                    }
                    $this->seenRuleIds[$ruleId] = true;
                }
            }

            if ($firstChildRendered && $isRoot && $element === 'system') {
                $lines[] = '';
            }

            if ($element === 'guideline') {
                if ($child['element'] === 'text') {
                    // Auto-code status values in guideline text
                    $guidelineText = MD::autoCode($child['text']);
                    $lines[] = MD::fromArray([
                        $guidelineText
                    ], $sccNext);
                } elseif ($child['element'] === 'example') {
                    if (isset($child['text']) && trim($child['text']) !== '') {
                        // Auto-code status values in examples
                        $examples[] = MD::autoCode($child['text']);
                    }
                    foreach ($child['child'] as $idx => $item) {
                        if (isset($item['text']) && trim($item['text']) !== '') {
                            $key = $item['name'] ?? $idx;
                            // Format example key in code style for clarity
                            $examples[MD::code($key)] = MD::autoCode($item['text']);
                        }
                    }
                }
            } elseif ($element !== 'rule') {
                if ($iron_rules_exists) {
                    static::$cache['iron_rules_exists'] = true;
                }
                $lines[] = $this->renderNode($child, false, $i + 1);
                if ($iron_rules_exists) {
                    unset(static::$cache['iron_rules_exists']);
                }
            }
            $firstChildRendered = true;
        }

        if ($element === 'guideline') {
//            dd($lines);
        }

        if ($examples) {
            // Render examples directly without "## Examples" header for compactness
            foreach ($examples as $key => $example) {
                if (is_string($key) && !is_numeric($key)) {
                    $lines[] = "- {$key}: {$example}";
                } else {
                    $lines[] = "- {$example}";
                }
            }
        }

        if (
            $element === 'guideline'
            || $element === 'rule'
        ) {
            //$lines[] = '---';
            $lines[] = '';
        } elseif (! isset(static::$cache['iron_rules_exists'])) {
            $lines[] = '</' . $element . '>';
        }

        // Append extracted guidelines after purpose/execute/mission/provides closing tag
        if (in_array($element, $purposeLikeElements, true) && $extractedGuidelines !== null) {
            $lines[] = $this->renderNode($extractedGuidelines, false, $i + 1);
        }

        $return = implode("\n", $lines);

        return $return;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function extractAttributes(array $node): array
    {
        $attributes = [];
        $cleanNode = $node;
        $params = [];

        foreach ($node as $key => $value) {
            if (in_array($key, ['element', 'child', 'text', 'single'], true)) {
                continue;
            }

            if ($this->isAttributeValue($value)) {
                $attributes[] = $this->formatAttribute($key, $value);
                $params[$key] = $value;
                unset($cleanNode[$key]);
                continue;
            }

            if ($value === null) {
                unset($cleanNode[$key]);
                continue;
            }

            if (is_array($value)) {
                if (! isset($cleanNode['child']) || ! is_array($cleanNode['child'])) {
                    $cleanNode['child'] = [];
                }

                $cleanNode['child'][] = $value;
                unset($cleanNode[$key]);
            }
        }

        $attributeString = $attributes ? ' ' . implode(' ', $attributes) : '';

        return [$attributeString, $cleanNode, $params];
    }

    protected function isAttributeValue(mixed $value): bool
    {
        return is_scalar($value)
            || $value instanceof BackedEnum
            || $value instanceof \Stringable;
    }

    protected function formatAttribute(string $key, mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return $key . '="' . $this->escape((string) $value) . '"';
    }

    /**
     * @param  array<int, mixed>  $children
     */
    protected function isEmptyContent(mixed $text, array $children): bool
    {
        return ($text === null || $text === '') && empty($children);
    }

    /**
     * @param  array<int, mixed>  $children
     */
    protected function hasInlineText(mixed $text, array $children): bool
    {
        return $text !== null && $text !== '' && empty($children);
    }

    protected function escape(string $value): string
    {
//        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
//        return htmlspecialchars($value, 0, 'UTF-8');
        return $value;
    }
}
