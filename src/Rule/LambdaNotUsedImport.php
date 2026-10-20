<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\LambdaNotUsedImportFixer (priority 31). `function () use ($a, $b) { ... $a ... }` loses the `use` variables the
 * body never mentions (by-reference imports and `$this` / superglobals stay); all of them unused: the whole `use (...)`. A body with `compact()`, `eval`,
 * `include`/`require`, `$$x` or `${...}` is left. Nested functions / anonymous classes are skipped but their parameters, `use` lists and constructor arguments count as uses.
 * Whole-file FlatTokens port (clearing merges the whitespace around the removed tokens).
 */
final class LambdaNotUsedImport extends FlatRule
{
    private NativeFunctionCasing $calls;
    private TypeWalk $tw;
    private Source $src;

    public function __construct()
    {
        parent::__construct();
        $this->calls = new NativeFunctionCasing();
    }

    public function name(): string
    {
        return 'lambda_not_used_import';
    }

    public function priority(): int
    {
        return 31;
    }

    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($u = 1; $u < $n; $u++) {
            if ($s->ct[$u] !== CT::USE_LAMBDA) {
                continue;
            }
            $o = $u + 1;
            if ($o >= $n || $s->kind[$o] !== 40) {
                continue;
            }
            $c = $s->pair[$o];
            $b = $c + 1;
            while ($b < $n && !($s->kind[$b] === 123 && $s->ct[$b] === 0)) {
                $b++;
            }
            if ($b >= $n) {
                continue;
            }
            $e = $s->pair[$b];
            $imports = [];
            for ($j = $o + 1; $j < $c; $j++) {
                if ($s->kind[$j] === \__McTokId::T_VARIABLE) {
                    $imports[$s->text($j)] = true;
                }
            }
            if ($imports === []) {
                continue;
            }
            for ($j = $b + 1; $j < $e && $imports !== []; $j++) {
                $k = $s->kind[$j];
                if ($k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_STRING_VARNAME) {
                    unset($imports[$k === \__McTokId::T_VARIABLE ? $s->text($j) : '$' . $s->text($j)]);
                } elseif ($s->ct[$j] === 0 && ($k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_CLASS)) {
                    // nested function / class: its parentheses count, its body does not
                    $p = $j + 1;
                    while ($p < $e && !(($s->kind[$p] === 40 || $s->kind[$p] === 123) && $s->ct[$p] === 0)) {
                        $p++;
                    }
                    while ($p < $e && $s->kind[$p] === 40) {
                        for ($x = $p + 1; $x < $s->pair[$p]; $x++) {
                            if ($s->kind[$x] === \__McTokId::T_VARIABLE) {
                                unset($imports[$s->text($x)]);
                            }
                        }
                        $p = $s->pair[$p] + 1;
                        while ($p < $e && !(($s->kind[$p] === 40 || $s->kind[$p] === 123) && $s->ct[$p] === 0)) {
                            $p++;
                        }
                    }
                    $j = $p < $e && $s->kind[$p] === 123 ? $s->pair[$p] : $j;
                }
            }
            if ($imports !== []) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        $this->tw = new TypeWalk($t);
        for ($q = $t->count() - 4; $q > 0; $q--) {
            $u = $this->lambdaUse($t, $q);
            if ($u >= 0) {
                $this->fixLambda($t, $u);
            }
        }
    }

    private function lambdaUse(FlatTokens $t, int $q): int
    {
        if (!self::is($t, $q, \__McTokId::T_FUNCTION)) {
            return -1;
        }
        $m = $t->meaningful($q, 1);
        if (self::is($t, $m, CT::RETURN_REF)) {
            $m = $t->meaningful($m, 1);
        }
        if (!self::is($t, $m, 40)) {
            return -1; // not a lambda
        }
        $c = $t->blockEnd($m, 41);
        $u = $t->meaningful($c, 1);
        return self::is($t, $u, CT::USE_LAMBDA) ? $u : -1;
    }

    /** ArgumentsAnalyzer::getArguments: start => end (flat indexes) */
    private function arguments(FlatTokens $t, int $open, int $close): array
    {
        $out = [];
        if (self::is($t, $t->meaningful($open, 1), 41)) {
            return $out;
        }
        $start = $open + 1;
        $p = $start;
        for (; $p < $close; $p++) {
            if ($t->i[$p] === -1) {
                continue;
            }
            $cl = TypeWalk::closer($t->k[$p]);
            if ($cl >= 0) {
                $p = $t->blockEnd($p, $cl);
                continue;
            }
            if ($t->k[$p] === 44) {
                if ($t->meaningful($p, 1) === $close) {
                    break;
                }
                $out[$start] = $p - 1;
                $start = $p + 1;
            }
        }
        $out[$start] = $p - 1;
        return $out;
    }

    /** the first T_VARIABLE of an argument range (ArgumentAnalysis::getNameIndex), attributes skipped */
    private function nameIndex(FlatTokens $t, int $a, int $b): int
    {
        for ($q = $a; $q <= $b; $q++) {
            if ($t->k[$q] === \__McTokId::T_ATTRIBUTE) {
                $q = $t->blockEnd($q, CT::ATTRIBUTE_CLOSE);
                continue;
            }
            if ($t->i[$q] !== -1 && $t->k[$q] === \__McTokId::T_VARIABLE) {
                return $q;
            }
        }
        return -1;
    }

    private function fixLambda(FlatTokens $t, int $u): void
    {
        $open = self::nextOf($t, $u, 40);
        $close = $t->blockEnd($open, 41);
        $args = $this->arguments($t, $open, $close);
        $imports = [];
        foreach ($args as $a => $b) {
            $ni = $this->nameIndex($t, $a, $b);
            if ($ni < 0) {
                continue;
            }
            $amp = $t->meaningful($ni, -1);
            if (self::is($t, $amp, 38) || self::is($t, $amp, \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG) || self::is($t, $amp, \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
                continue;
            }
            $name = $t->content($ni);
            if ($name === '$this') {
                continue;
            }
            $up = \strtoupper($name);
            if ($up === '$_COOKIE' || $up === '$_ENV' || $up === '$_FILES' || $up === '$_GET' || $up === '$_POST' || $up === '$_REQUEST' || $up === '$_SERVER'
                || $up === '$_SESSION' || $up === '$GLOBALS') {
                continue;
            }
            $imports[$name] = $ni;
        }
        if ($imports === []) {
            return;
        }
        $notUsed = $this->notUsed($t, $imports, $close);
        $cnt = \count($notUsed);
        if ($cnt === 0) {
            return;
        }
        if ($cnt === \count($args)) {
            for ($i = $close; $i >= $u; $i--) {
                if ($t->isComment($i)) {
                    continue;
                }
                if ($t->isWs($i)) {
                    $p = $t->nonWhitespace($i, -1);
                    if ($p >= 0 && $t->isComment($p)) {
                        continue;
                    }
                }
                self::clearMerge($t, $i);
            }
            return;
        }
        foreach (\array_reverse($notUsed) as $ri) {
            self::clearMerge($t, $ri);
            $p = $t->meaningful($ri, -1);
            if (self::is($t, $p, 44)) {
                self::clearMerge($t, $p);
            } elseif (self::is($t, $p, 40)) {
                self::clearMerge($t, $t->meaningful($ri, 1));
            }
        }
    }

    /**
     * @param array<string, int> $imports
     * @return array<string, int>
     */
    private function notUsed(FlatTokens $t, array $imports, int $closeUse): array
    {
        $o = self::nextOf($t, $closeUse, 123);
        if ($o < 0) {
            return [];
        }
        $level = 0;
        $m = $t->count();
        for ($q = $o; $q < $m; $q++) {
            if ($t->i[$q] === -1) {
                continue;
            }
            $k = $t->k[$q];
            if ($k === 123) {
                $level++;
                continue;
            }
            if ($k === 125) {
                $level--;
                if ($level === 0) {
                    break;
                }
                continue;
            }
            if ($k === \__McTokId::T_STRING && \strtolower($t->content($q)) === 'compact') {
                $si = $t->i[$q];
                if ($si >= 0 && $this->calls->isGlobalCallAt($this->src, $si, 'compact')) {
                    return [];
                }
            }
            if ($k === CT::DYNAMIC_VAR_BRACE_OPEN || $k === \__McTokId::T_EVAL || $k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE
                || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE) {
                return [];
            }
            if ($k === 36) {
                if (self::is($t, $t->meaningful($q, 1), \__McTokId::T_VARIABLE)) {
                    return [];
                }
            }
            if ($k === \__McTokId::T_VARIABLE) {
                $c = $t->content($q);
                if (isset($imports[$c])) {
                    unset($imports[$c]);
                    if ($imports === []) {
                        return $imports;
                    }
                }
            }
            if ($k === \__McTokId::T_STRING_VARNAME) {
                $c = '$' . $t->content($q);
                if (isset($imports[$c])) {
                    unset($imports[$c]);
                    if ($imports === []) {
                        return $imports;
                    }
                }
            }
            if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) {
                $q = $this->nextOfAny($t, $q, 40, 123);
                if ($q >= 0 && $t->k[$q] === 40) {
                    $cb = $t->blockEnd($q, 41);
                    $imports = $this->usedAsArgument($t, $imports, $this->arguments($t, $q, $cb));
                    $q = self::nextOf($t, $cb, 123);
                }
                if ($q < 0) {
                    return $imports;
                }
                $q = $t->blockEnd($q, 125);
                if ($q < 0) {
                    return $imports;
                }
                continue;
            }
            if ($k === \__McTokId::T_FUNCTION) {
                $po = self::nextOf($t, $q, 40);
                $pc = $t->blockEnd($po, 41);
                $imports = $this->usedAsArgument($t, $imports, $this->arguments($t, $po, $pc));
                $q = $this->nextOfAny($t, $q, CT::USE_LAMBDA, 123);
                if ($q >= 0 && $t->k[$q] === CT::USE_LAMBDA) {
                    $uo = self::nextOf($t, $q, 40);
                    $uc = $t->blockEnd($uo, 41);
                    $imports = $this->usedAsArgument($t, $imports, $this->arguments($t, $uo, $uc));
                    $q = self::nextOf($t, $uc, 123);
                }
                if ($q < 0) {
                    return $imports;
                }
                $q = $t->blockEnd($q, 125);
                if ($q < 0) {
                    return $imports;
                }
                continue;
            }
        }
        return $imports;
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

    /**
     * @param array<string, int> $imports
     * @param array<int, int> $args
     * @return array<string, int>
     */
    private function usedAsArgument(FlatTokens $t, array $imports, array $args): array
    {
        foreach ($args as $a => $b) {
            $info = $this->tw->argumentInfo($a, $b);
            if ($info[0] !== '' && isset($imports[$info[0]])) {
                unset($imports[$info[0]]);
                if ($imports === []) {
                    return $imports;
                }
            }
        }
        return $imports;
    }
}
