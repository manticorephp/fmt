<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpdocToTypeRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\PhpdocToParamTypeFixer (priority 8, risky, experimental). An untyped parameter documented by a `@param` of a type PHP can declare
 * gets that type (scalar_types, union_types and types_map as upstream). Whole-file FlatTokens port; the slices go in with one rebuild at the end.
 */
final class PhpdocToParamType extends PhpdocToTypeRule
{
    public function name(): string
    {
        return 'phpdoc_to_param_type';
    }

    public function priority(): int
    {
        return 8;
    }

    protected function template(): string
    {
        return 'param';
    }

    protected function tag(): string
    {
        return '@param';
    }

    protected function shape(Source $s, int $u): bool
    {
        $l = self::parameterList($s, $u);
        if ($l === null) {
            return false;
        }
        for ($j = $l[0] + 1; $j < $l[1]; $j++) {
            if ($s->kind[$j] !== \__McTokId::T_VARIABLE) {
                continue;
            }
            $p = $j - 1;
            if ($s->text($p) === '&') {
                $p--;
            }
            if (($s->kind[$p] === 40 || $s->kind[$p] === 44) && $s->ct[$p] === 0) {
                return true;
            }
        }
        return false;
    }

    protected function isSkippedType(string $type): bool
    {
        return $type === 'resource' || $type === 'static' || $type === 'void';
    }

    protected function apply(Source $s, FlatTokens $t): void
    {
        /** @var array<int, array<int, array{0:int,1:string,2:int}>> $slices */
        $slices = [];
        /** @var string[] $exclude */
        $exclude = [];
        for ($index = 0, $m = $t->count(); $index < $m; $index++) {
            if (self::isDocItem($t, $index)) {
                $x = self::typesToExclude($t->c[$index]);
                if ($x === null) {
                    $s->abort = true;
                    return;
                }
                \array_push($exclude, ...$x);
                continue;
            }
            if ($t->i[$index] === -1 || ($t->k[$index] !== \__McTokId::T_FUNCTION && $t->k[$index] !== \__McTokId::T_FN)) {
                continue;
            }
            $name = $t->meaningful($index, 1);
            if ($name >= 0 && $t->k[$name] === \__McTokId::T_STRING && \in_array(\strtolower($t->content($name)), ['__clone', '__destruct'], true)) {
                continue;
            }
            $doc = $this->findFunctionDocComment($t, $index);
            if ($doc < 0) {
                continue;
            }
            $annotations = $this->annotations('param', $t, $doc);
            if ($annotations === null) {
                $s->abort = true;
                return;
            }
            foreach ($annotations as $a) {
                $te = $a->getTypeExpression();
                if ($te === null) {
                    continue;
                }
                $info = $this->getCommonTypeInfo($te, false);
                $union = null;
                if ($info === null) {
                    $union = $this->getUnionTypes($te, false);
                }
                if ($info === null && $union === null) {
                    continue;
                }
                if ($info !== null) {
                    $type = $info['commonType'];
                    $nullable = $info['isNullable'];
                } else {
                    $type = (string) $union;
                    $nullable = false;
                }
                if (\in_array($type, $exclude, true)) {
                    continue;
                }
                $start = self::nextOf($t, $index, 40);
                if ($start < 0) {
                    $s->abort = true;
                    return;
                }
                $var = $this->findCorrectVariable($t, $start, (string) $a->getVariableName());
                if ($var < 0) {
                    continue;
                }
                $prev = $t->meaningful($var, -1);
                if ($prev >= 0 && $t->i[$prev] !== -1 && $t->content($prev) === '&') {
                    $var = $prev;
                }
                $before = $t->meaningful($var, -1);
                if ($before < 0 || ($t->k[$before] !== 44 && $t->k[$before] !== 40)) {
                    continue;
                }
                $ok = $this->syntax($type);
                if ($ok < 0) {
                    $s->abort = true; // the parser ends upstream's process with a compile error
                    return;
                }
                if ($ok === 0) {
                    continue;
                }
                $items = $this->typeItems($type, $nullable);
                $items[] = FlatTokens::ws(' ');
                $slices[$var] = $items;
            }
        }
        self::insertSlices($t, $slices);
    }

    private function findCorrectVariable(FlatTokens $t, int $start, string $name): int
    {
        $end = $t->blockEnd($start, 41);
        if ($end < 0) {
            return -1;
        }
        for ($index = $start + 1; $index < $end; $index++) {
            if ($t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_VARIABLE && $t->content($index) === $name) {
                return $index;
            }
        }
        return -1;
    }
}
