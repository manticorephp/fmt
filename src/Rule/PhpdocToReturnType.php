<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\PhpdocToTypeRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\PhpdocToReturnTypeFixer (priority 13, risky, experimental). A function without a return type, documented by exactly one `@return` of a type PHP
 * can declare, gets `: type` behind its parameter list. A closure with a `use` list would get it in front of the list: upstream's lint rejects that file, so it stays as it is.
 */
final class PhpdocToReturnType extends PhpdocToTypeRule
{
    public function name(): string
    {
        return 'phpdoc_to_return_type';
    }

    public function priority(): int
    {
        return 13;
    }

    protected function template(): string
    {
        return 'return';
    }

    protected function tag(): string
    {
        return '@return';
    }

    protected function shape(Source $s, int $u): bool
    {
        $l = self::parameterList($s, $u);
        return $l !== null && !($l[1] + 1 < $s->n && $s->ct[$l[1] + 1] === CT::TYPE_COLON);
    }

    protected function isSkippedType(string $type): bool
    {
        return $type === 'resource' || $type === 'null';
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
            if ($name >= 0 && $t->k[$name] === \__McTokId::T_STRING && \in_array(\strtolower($t->content($name)), ['__construct', '__destruct', '__clone'], true)) {
                continue;
            }
            $doc = $this->findFunctionDocComment($t, $index);
            if ($doc < 0) {
                continue;
            }
            $annotations = $this->annotations('return', $t, $doc);
            if ($annotations === null) {
                $s->abort = true;
                return;
            }
            if (\count($annotations) !== 1) {
                continue;
            }
            $te = $annotations[0]->getTypeExpression();
            if ($te === null) {
                continue;
            }
            $info = $this->getCommonTypeInfo($te, true);
            $union = null;
            if ($info === null) {
                $union = $this->getUnionTypes($te, true);
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
            $open = self::nextOf($t, $index, 40);
            $close = $open < 0 ? -1 : $t->blockEnd($open, 41);
            if ($close < 0) {
                $s->abort = true;
                return;
            }
            $body = -1;
            for ($j = $close + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === 59 || $t->k[$j] === \__McTokId::T_DOUBLE_ARROW)) {
                    $body = $j;
                    break;
                }
            }
            if ($body < 0) {
                $s->abort = true;
                return;
            }
            $rp = -1;
            for ($j = $body - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && $t->k[$j] === 41) {
                    $rp = $j;
                    break;
                }
            }
            $after = $rp < 0 ? -1 : $t->meaningful($rp, 1);
            if ($after >= 0 && $t->k[$after] === CT::TYPE_COLON) {
                continue;
            }
            $ok = $this->syntax($type);
            if ($ok < 0) {
                $s->abort = true;
                return;
            }
            if ($ok === 0) {
                continue;
            }
            $next = $t->meaningful($close, 1);
            if ($next >= 0 && ($t->k[$next] === CT::USE_LAMBDA || $t->k[$next] === \__McTokId::T_USE)) {
                $s->abort = true; // `function () : T use (..)`: upstream's lint rejects the result
                return;
            }
            $items = [FlatTokens::code(CT::TYPE_COLON, ':'), FlatTokens::ws(' ')];
            foreach ($this->typeItems($type, $nullable) as $it) {
                $items[] = $it;
            }
            $slices[$close + 1] = $items;
        }
        $t->insertSlices($slices);
    }
}
