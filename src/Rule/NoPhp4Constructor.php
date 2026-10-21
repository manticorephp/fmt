<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\NoPhp4ConstructorFixer (priority 75, risky). A method named like its class becomes `__construct` (a wrapper that calls the other
 * constructor is dropped, `$this->__construct()` / `parent::Foo()` calls follow). Upstream quirks mirrored: the first `namespace X;` stops the whole run, classes of a braced namespace are
 * skipped, `findGivenKind(T_EXTENDS)` looks at every `extends` of the file. Whole-file FlatTokens port.
 */
final class NoPhp4Constructor extends FlatRule
{
    public function name(): string
    {
        return 'no_php4_constructor';
    }

    public function priority(): int
    {
        return 75;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_CLASS && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $classes = [];
        $m = $t->count();
        for ($q = 0; $q < $m; $q++) {
            if ($t->i[$q] !== -1 && $t->k[$q] === \__McTokId::T_CLASS) {
                $classes[] = $q;
            }
        }
        $num = \count($classes);
        for ($i = 0; $i < $num; $i++) {
            $index = $classes[$i];
            if ($t->i[$index] >= 0 && Analyzer::isAnonymousClass($s, $t->i[$index])) {
                continue;
            }
            $nsp = -1;
            for ($j = $index - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_NAMESPACE && $t->content($j) === 'namespace') {
                    $nsp = $j;
                    break;
                }
            }
            if ($nsp >= 0) {
                $nsp = $t->meaningful($nsp, 1);
                if (!self::is($t, $nsp, 123)) {
                    $nsp = $this->nextOfAny($t, $nsp, 59, 123);
                    if ($nsp < 0) {
                        return;
                    }
                    if (self::is($t, $nsp, 59)) {
                        break;
                    }
                    $nspEnd = $t->blockEnd($nsp, 125);
                    if ($index < $nspEnd) {
                        for ($j = $i + 1; $j < $num; $j++) {
                            if ($classes[$j] < $nspEnd) {
                                $i++;
                            }
                        }
                        continue;
                    }
                }
            }
            $nameIdx = $t->meaningful($index, 1);
            $className = $t->content($nameIdx);
            $classStart = self::nextOf($t, $nameIdx, 123);
            $classEnd = $t->blockEnd($classStart, 125);
            $this->fixConstructor($t, $className, $classStart, $classEnd);
            $this->fixParent($t, $classStart, $classEnd);
        }
    }

    private function nextOfAny(FlatTokens $t, int $q, int $a, int $b): int
    {
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === $a || $t->k[$j] === $b)) {
                return $j;
            }
        }
        return -1;
    }

    private function fixConstructor(FlatTokens $t, string $className, int $classStart, int $classEnd): void
    {
        $php4 = $this->findFunction($t, $className, $classStart, $classEnd);
        if ($php4 === null) {
            return;
        }
        if ($php4['abstract'] || $php4['static']) {
            return;
        }
        $php5 = $this->findFunction($t, '__construct', $classStart, $classEnd);
        if ($php5 === null) {
            self::setCode($t, $php4['name'], \__McTokId::T_STRING, '__construct');
            $this->fixInfiniteRecursion($t, $php4['body'], $php4['end']);
            return;
        }
        foreach ($this->wrapperSequences($t, '__construct', $php4['start'], $php4['body']) as $seq) {
            if (self::findSeq($t, $seq, $php4['body'] - 1, $php4['end'], [3 => true]) !== null) {
                for ($i = $php4['start']; $i <= $php4['end']; $i++) {
                    $t->clearAt($i);
                }
                return;
            }
        }
        foreach ($this->wrapperSequences($t, $className, $php4['start'], $php4['body']) as $seq) {
            if (self::findSeq($t, $seq, $php5['body'] - 1, $php5['end'], [3 => true]) !== null) {
                for ($i = $php5['start']; $i <= $php5['end']; $i++) {
                    $t->clearAt($i);
                }
                self::setCode($t, $php4['name'], \__McTokId::T_STRING, '__construct');
                return;
            }
        }
    }

    private function fixParent(FlatTokens $t, int $classStart, int $classEnd): void
    {
        $m = $t->count();
        for ($e = 0; $e < $m; $e++) {
            if ($t->i[$e] === -1 || $t->k[$e] !== \__McTokId::T_EXTENDS) {
                continue;
            }
            $parentIdx = $t->meaningful($e, 1);
            $parent = $t->content($parentIdx);
            $seq = self::findSeq($t, [[\__McTokId::T_STRING, null], [\__McTokId::T_DOUBLE_COLON, null], [\__McTokId::T_STRING, $parent], [40, null]], $classStart, $classEnd, [2 => true]);
            if ($seq !== null) {
                $c0 = \strtolower($t->content($seq[0]));
                if ($t->k[$seq[0]] === \__McTokId::T_STRING && ($c0 === 'parent' || $c0 === \strtolower($parent))) {
                    self::setCode($t, $seq[0], \__McTokId::T_STRING, 'parent');
                    self::setCode($t, $seq[2], \__McTokId::T_STRING, '__construct');
                }
            }
            foreach ([\__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $op) {
                $seq = self::findSeq($t, [[\__McTokId::T_VARIABLE, '$this'], [$op, null], [\__McTokId::T_STRING, $parent], [40, null]], $classStart, $classEnd, [2 => true]);
                if ($seq !== null) {
                    self::setCode($t, $seq[0], \__McTokId::T_STRING, 'parent');
                    self::setCode($t, $seq[1], \__McTokId::T_DOUBLE_COLON, '::');
                    self::setCode($t, $seq[2], \__McTokId::T_STRING, '__construct');
                }
            }
        }
    }

    private function fixInfiniteRecursion(FlatTokens $t, int $start, int $end): void
    {
        foreach ([\__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $op) {
            while (true) {
                $seq = self::findSeq($t, [[\__McTokId::T_VARIABLE, '$this'], [$op, null], [\__McTokId::T_STRING, '__construct']], $start, $end, [2 => true]);
                if ($seq === null) {
                    return; // upstream returns here, so the nullsafe operator is never reached
                }
                self::setCode($t, $seq[0], \__McTokId::T_STRING, 'parent');
                self::setCode($t, $seq[1], \__McTokId::T_DOUBLE_COLON, '::');
            }
        }
    }

    /**
     * @return array<int, array<int, array{0:int,1:?string}>>
     */
    private function wrapperSequences(FlatTokens $t, string $method, int $start, int $body): array
    {
        $out = [];
        foreach ([\__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $op) {
            $seq = [[123, null], [\__McTokId::T_VARIABLE, '$this'], [$op, null], [\__McTokId::T_STRING, $method], [40, null]];
            $index = $start;
            $m = $t->count();
            while (true) {
                $found = -1;
                for ($j = $index + 1; $j < $m; $j++) {
                    if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_VARIABLE) {
                        $found = $j;
                        break;
                    }
                }
                if ($found < 0 || $found >= $body) {
                    break;
                }
                $index = $found;
                if (\count($seq) > 5) {
                    $seq[] = [44, null];
                }
                $seq[] = [\__McTokId::T_VARIABLE, $t->content($index)];
            }
            $seq[] = [41, null];
            $seq[] = [59, null];
            $seq[] = [125, null];
            $out[] = $seq;
        }
        return $out;
    }

    /** @return array{name:int,start:int,end:int,body:int,abstract:bool,static:bool}|null */
    private function findFunction(FlatTokens $t, string $name, int $from, int $to): ?array
    {
        $f = self::findSeq($t, [[\__McTokId::T_FUNCTION, null], [\__McTokId::T_STRING, $name], [40, null]], $from, $to, [0 => true, 1 => true, 2 => true]);
        if ($f === null) {
            return null;
        }
        $abstract = false;
        $static = false;
        $prev = $t->meaningful($f[0], -1);
        while ($prev >= 0 && $t->i[$prev] !== -1) {
            $k = $t->k[$prev];
            if (!($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_ABSTRACT
                || $k === \__McTokId::T_FINAL)) {
                break;
            }
            if ($k === \__McTokId::T_ABSTRACT) {
                $abstract = true;
            } elseif ($k === \__McTokId::T_STATIC) {
                $static = true;
            }
            $prev = $t->meaningful($prev, -1);
        }
        if ($abstract) {
            $body = -1;
            $end = -1;
            $m = $t->count();
            for ($j = $f[2] + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && $t->k[$j] === 59) {
                    $end = $j;
                    break;
                }
            }
        } else {
            $body = self::nextOf($t, $f[2], 123);
            $end = $body >= 0 ? $t->blockEnd($body, 125) : -1;
        }
        return ['name' => $f[1], 'start' => $prev + 1, 'end' => $end, 'body' => $body, 'abstract' => $abstract, 'static' => $static];
    }
}
