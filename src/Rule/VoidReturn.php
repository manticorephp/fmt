<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Doc\DocBlock;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer (priority 5, risky). A function without a return type whose body has no value-returning `return` (and no `yield`) gets
 * `: void`; a bodiless one (abstract / interface) when its docblock says `@return void`; a `@return` annotation of another type blocks it. fix_lambda=false skips closures. Whole-file
 * FlatTokens port, last function first.
 */
final class VoidReturn extends FlatRule
{
    public function __construct(private bool $fixLambda)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'void_return';
    }

    public function priority(): int
    {
        return 5;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_FUNCTION) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index >= 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_FUNCTION) {
                continue;
            }
            if (!$this->fixLambda && $t->i[$index] >= 0 && Analyzer::isLambda($s, $t->i[$index])) {
                continue;
            }
            $nameIdx = $t->meaningful($index, 1);
            if ($nameIdx >= 0 && $t->i[$nameIdx] !== -1 && $t->k[$nameIdx] === \__McTokId::T_STRING) {
                $n = \strtolower($t->content($nameIdx));
                if ($n === '__clone' || $n === '__construct' || $n === '__debuginfo' || $n === '__destruct' || $n === '__isset' || $n === '__serialize'
                    || $n === '__set_state' || $n === '__sleep' || $n === '__tostring') {
                    continue;
                }
            }
            $start = -1;
            $m = $t->count();
            for ($j = $index + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === 59)) {
                    $start = $j;
                    break;
                }
            }
            if ($start < 0 || $this->prevCloseParen($t, $start) < 0) {
                $s->abort = true; // no `)` before the body (an anonymous class in a parameter attribute): upstream's getNextMeaningfulToken(null) throws, the file stays as it is
                return;
            }
            if ($this->hasReturnTypeHint($t, $start)) {
                continue;
            }
            if ($t->k[$start] === 59) {
                if ($this->hasVoidReturnAnnotation($t, $index)) {
                    $this->fixDefinition($t, $start);
                }
                continue;
            }
            if ($this->hasReturnAnnotation($t, $index)) {
                continue;
            }
            $end = $t->blockEnd($start, 125);
            if ($this->hasVoidReturn($s, $t, $start, $end)) {
                $this->fixDefinition($t, $start);
            }
        }
    }

    private function prevCloseParen(FlatTokens $t, int $from): int
    {
        for ($j = $from - 1; $j >= 0; $j--) {
            if ($t->i[$j] !== -1 && $t->k[$j] === 41) {
                return $j;
            }
        }
        return -1;
    }

    private function hasReturnTypeHint(FlatTokens $t, int $index): bool
    {
        $end = $this->prevCloseParen($t, $index);
        $next = $t->meaningful($end, 1);
        return $next >= 0 && $t->i[$next] !== -1 && $t->k[$next] === CT::TYPE_COLON;
    }

    private function fixDefinition(FlatTokens $t, int $index): void
    {
        $end = $this->prevCloseParen($t, $index);
        $t->insertAt($end + 1, [FlatTokens::code(CT::TYPE_COLON, ':'), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_STRING, 'void')]);
    }

    private function hasVoidReturn(Source $s, FlatTokens $t, int $start, int $end): bool
    {
        for ($i = $start; $i < $end; $i++) {
            if ($t->i[$i] === -1) {
                continue;
            }
            $k = $t->k[$i];
            if ($t->i[$i] >= 0 && (($k === \__McTokId::T_CLASS && Analyzer::isAnonymousClass($s, $t->i[$i])) || ($k === \__McTokId::T_FUNCTION && Analyzer::isLambda($s, $t->i[$i])))) {
                $o = self::nextOf($t, $i, 123);
                $i = $t->blockEnd($o, 125);
                continue;
            }
            if ($k === \__McTokId::T_YIELD || $k === \__McTokId::T_YIELD_FROM) {
                return false;
            }
            if ($k !== \__McTokId::T_RETURN) {
                continue;
            }
            $i = $t->meaningful($i, 1);
            if (!self::is($t, $i, 59)) {
                return false;
            }
        }
        return true;
    }

    /** @return \Fmt\Doc\Annotation[] */
    private function returnAnnotations(FlatTokens $t, int $index): array
    {
        do {
            $index = $t->nonWhitespace($index, -1);
            if ($index >= 0 && $t->i[$index] !== -1 && $t->k[$index] === CT::ATTRIBUTE_CLOSE) {
                $f = -1;
                for ($j = $index - 1; $j >= 0; $j--) {
                    if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_ATTRIBUTE) {
                        $f = $j;
                        break;
                    }
                }
                $index = $f;
            }
            $k = $index >= 0 && $t->i[$index] !== -1 ? $t->k[$index] : -1;
        } while ($k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC
            || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_ATTRIBUTE);
        if ($index < 0 || $t->k[$index] !== FlatTokens::COMMENT || !Trivia::isDoc($t->c[$index])) {
            return [];
        }
        $doc = new DocBlock($t->c[$index]);
        return $doc->getAnnotationsOfType(['return']);
    }

    private function hasReturnAnnotation(FlatTokens $t, int $index): bool
    {
        foreach ($this->returnAnnotations($t, $index) as $r) {
            if ($r->getTypes() !== ['void']) {
                return true;
            }
        }
        return false;
    }

    private function hasVoidReturnAnnotation(FlatTokens $t, int $index): bool
    {
        foreach ($this->returnAnnotations($t, $index) as $r) {
            if ($r->getTypes() === ['void']) {
                return true;
            }
        }
        return false;
    }
}
