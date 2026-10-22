<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatAttributes;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\PhpUnitClassRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocTagNoNamedArgumentsFixer (priority 0). Every class / interface / trait / enum (not `new class`) gets `@no-named-arguments`
 * (with `description`) in its docblock, unless it has the tag (or, with fix_internal false, `@internal`), or is an `#[Attribute]` class with fix_attribute false.
 * Rides on the docblock machinery of php_unit_internal_class (PhpUnitClassRule::ensureDocAnnotation), but visits every classy token.
 */
final class PhpdocTagNoNamedArguments extends PhpUnitClassRule
{
    public function __construct(private string $description, private bool $fixAttribute, private bool $fixInternal, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'phpdoc_tag_no_named_arguments';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                return true;
            }
        }
        return false;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        for ($index = $t->count() - 1; $index > 0; --$index) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k !== \__McTokId::T_CLASS && $k !== \__McTokId::T_INTERFACE && $k !== \__McTokId::T_TRAIT && $k !== \__McTokId::T_ENUM) {
                continue;
            }
            $prev = $t->meaningful($index, -1);
            if ($prev < 0) {
                continue;
            }
            if (self::is($t, $prev, \__McTokId::T_NEW)) {
                continue;
            }
            if (!$this->fixAttribute && $this->isAttributeClass($s, $t, $prev)) {
                continue;
            }
            $this->ensureDocAnnotation($t, $index, 'no-named-arguments', $this->fixInternal ? ['no-named-arguments'] : ['internal', 'no-named-arguments'], [], $this->lineEnding);
            if ($s->abort) {
                return;
            }
            $doc = -1;
            for ($j = $index + 1; $j >= 0; $j--) {
                if ($j < $t->count() && $t->k[$j] === FlatTokens::COMMENT && Trivia::isDoc($t->c[$j])) {
                    $doc = $j;
                    break;
                }
            }
            if ($doc < 0) {
                $s->abort = true;
                return;
            }
            $content = $t->c[$doc];
            $new = Preg::replace('/@no-named-arguments.*\R/', \rtrim('@no-named-arguments ' . $this->description) . $this->lineEnding, $content);
            if ($new !== $content) {
                $t->c[$doc] = $new;
            }
        }
    }

    private function isAttributeClass(Source $s, FlatTokens $t, int $index): bool
    {
        while ($index >= 0 && ($t->k[$index] === \__McTokId::T_FINAL || $t->k[$index] === \__McTokId::T_READONLY) && $t->i[$index] !== -1) {
            $index = $t->meaningful($index, -1);
        }
        if ($index < 0 || !self::is($t, $index, CT::ATTRIBUTE_CLOSE)) {
            return false;
        }
        foreach (FlatAttributes::collect($t, $t->blockStart($index, \__McTokId::T_ATTRIBUTE)) as $group) {
            foreach ($group->attributes as $attribute) {
                if (\strtolower(FlatAttributes::qualify($s, $t, $attribute->name, $attribute->start)) === 'attribute') {
                    return true;
                }
            }
        }
        return false;
    }
}
