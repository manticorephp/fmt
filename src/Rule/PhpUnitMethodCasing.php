<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NsAnalysis;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitMethodCasingFixer (priority 0). In a PHPUnit test class (a class that extends something and names a `...Test` /
 * `...TestCase` type in its signature) every test method (`test...`, `#[Test]`, or a docblock with `@test`) is renamed to camelCase (or snake_case) unless the new
 * name is taken; `@depends` lines of its docblock follow. Whole-file FlatTokens port, the last test class first.
 */
final class PhpUnitMethodCasing extends FlatRule
{
    private Source $src;

    public function __construct(private bool $camel)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_method_casing';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_CLASS || $s->ct[$i] !== 0) {
                continue;
            }
            $ext = false;
            for ($j = $i + 1; $j < $s->n && !($s->kind[$j] === 123 && $s->ct[$j] === 0); $j++) {
                if ($s->kind[$j] === \__McTokId::T_EXTENDS) {
                    $ext = true;
                } elseif ($ext && $s->kind[$j] === \__McTokId::T_STRING && \preg_match('/(?:Test|TestCase)(?:Interface)?$/', $s->text($j)) === 1) {
                    return true;
                }
            }
            // the class's own name counts too (it precedes `extends`)
            if ($ext && $i + 1 < $s->n && \preg_match('/(?:Test|TestCase)(?:Interface)?$/', $s->text($i + 1)) === 1) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if (!$this->isPhpUnitClass($t, $q)) {
                continue;
            }
            $open = self::nextOf($t, $q, 123);
            $close = $t->blockEnd($open, 125);
            $this->fixClass($t, $open, $close);
        }
    }

    private function isPhpUnitClass(FlatTokens $t, int $q): bool
    {
        if (!self::is($t, $q, \__McTokId::T_CLASS)) {
            return false;
        }
        $m = $t->count();
        $e = -1;
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === \__McTokId::T_EXTENDS)) {
                $e = $j;
                break;
            }
        }
        if ($e < 0 || $t->k[$e] !== \__McTokId::T_EXTENDS) {
            return false;
        }
        if (\preg_match('/(?:Test|TestCase)$/', $t->content($q)) === 1) {
            return true;
        }
        for ($j = $t->meaningful($q, 1); $j >= 0; $j = $t->meaningful($j, 1)) {
            if ($t->k[$j] === 123) {
                break;
            }
            if ($t->k[$j] !== \__McTokId::T_STRING) {
                continue;
            }
            if (\preg_match('/(?:Test|TestCase)(?:Interface)?$/', $t->content($j)) === 1) {
                return true;
            }
        }
        return false;
    }

    private static function isMethod(FlatTokens $t, int $q): bool
    {
        if (!self::is($t, $q, \__McTokId::T_FUNCTION)) {
            return false;
        }
        $m = $t->meaningful($q, 1);
        if (self::is($t, $m, CT::RETURN_REF)) {
            $m = $t->meaningful($m, 1);
        }
        return !self::is($t, $m, 40);
    }

    private function fixClass(FlatTokens $t, int $start, int $end): void
    {
        $existing = [];
        for ($q = $end - 1; $q > $start; $q--) {
            if (!self::isMethod($t, $q)) {
                continue;
            }
            $existing[\strtolower($t->content($t->meaningful($q, 1)))] = true;
        }
        for ($q = $end - 1; $q > $start; $q--) {
            if (!$this->isTestMethod($t, $q)) {
                continue;
            }
            $ni = $t->meaningful($q, 1);
            $name = $t->content($ni);
            $new = $this->casing($name);
            $nl = \strtolower($new);
            if (isset($existing[$nl]) && \strtolower($name) !== $nl) {
                continue;
            }
            $existing[$nl] = true;
            if ($new !== $name) {
                $t->i[$ni] = FlatTokens::NEW;
                $t->k[$ni] = \__McTokId::T_STRING;
                $t->c[$ni] = $new;
            }
            $di = $this->docIndex($t, $q);
            if ($di >= 0 && $t->isComment($di) && \Fmt\Trivia::isDoc($t->c[$di])) {
                $this->updateDoc($t, $di);
            }
        }
    }

    private function casing(string $fn): string
    {
        $parts = \explode('::', $fn);
        $last = \array_pop($parts);
        if ($this->camel) {
            $last = \lcfirst(\str_replace('_', '', \ucwords($last, '_')));
        } else {
            $last = \mb_strtolower(Trivia::preplace('/(?<!^)(?<!_)((?=[\p{Lu}][^\p{Lu}])|(?<![\p{Lu}])(?=[\p{Lu}]))/', '_', $last));
        }
        $parts[] = $last;
        return \implode('::', $parts);
    }

    private function docIndex(FlatTokens $t, int $q): int
    {
        do {
            $q = $t->nonWhitespace($q, -1);
            if ($q < 0) {
                return -1;
            }
            if ($t->i[$q] !== -1 && $t->k[$q] === CT::ATTRIBUTE_CLOSE) {
                $q = $t->blockStart($q, \__McTokId::T_ATTRIBUTE);
                if ($q < 0) {
                    return -1;
                }
            }
            $k = $t->k[$q];
            $code = $t->i[$q] !== -1;
        } while (($code && ($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_FINAL
            || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_ATTRIBUTE || $k === \__McTokId::T_READONLY))
            || ($t->isComment($q) && !Trivia::isDoc($t->c[$q])));
        return $q;
    }

    private function isTestMethod(FlatTokens $t, int $q): bool
    {
        if (!self::isMethod($t, $q)) {
            return false;
        }
        $ni = $t->meaningful($q, 1);
        if (\str_starts_with($t->content($ni), 'test')) {
            return true;
        }
        if ($this->testAttribute($t, $q)) {
            return true;
        }
        $di = $this->docIndex($t, $q);
        return $di >= 0 && $t->isComment($di) && Trivia::isDoc($t->c[$di]) && \str_contains($t->c[$di], '@test');
    }

    /** AbstractPhpUnitFixer::isTestAttributePresent */
    private function testAttribute(FlatTokens $t, int $q): bool
    {
        $a = -1;
        for ($j = $q - 1; $j >= 0; $j--) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === \__McTokId::T_ATTRIBUTE)) {
                $a = $t->k[$j] === \__McTokId::T_ATTRIBUTE ? $j : -1;
                break;
            }
        }
        if ($a < 0) {
            return false;
        }
        // the group of attributes that starts the run of `#[...]` ending at $a: collect every attribute of the run
        $first = $a;
        while (true) {
            $p = $t->meaningful($first, -1);
            if ($p >= 0 && $t->i[$p] !== -1 && $t->k[$p] === CT::ATTRIBUTE_CLOSE) {
                $first = $t->blockStart($p, \__McTokId::T_ATTRIBUTE);
                continue;
            }
            break;
        }
        $o = $first;
        do {
            $close = $t->blockEnd($o, CT::ATTRIBUTE_CLOSE);
            if ($close < 0) {
                break;
            }
            $depth = 0;
            $j = $o + 1;
            $name = '';
            $atStart = true;
            for (; $j < $close; $j++) {
                if ($t->i[$j] === -1) {
                    continue;
                }
                $k = $t->k[$j];
                if ($k === 40 || $k === 91) {
                    $depth++;
                    $atStart = false;
                } elseif ($k === 41 || $k === 93) {
                    $depth--;
                } elseif ($depth === 0 && $k === 44) {
                    if ($name !== '' && $this->isTestName($name, $t->i[$o])) {
                        return true;
                    }
                    $name = '';
                    $atStart = true;
                } elseif ($depth === 0 && $atStart && ($k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR)) {
                    $name .= $t->content($j);
                } elseif ($depth === 0) {
                    $atStart = false;
                }
            }
            if ($name !== '' && $this->isTestName($name, $t->i[$o])) {
                return true;
            }
            $o = $t->meaningful($close, 1);
        } while ($o >= 0 && $t->i[$o] !== -1 && $t->k[$o] === \__McTokId::T_ATTRIBUTE);
        return false;
    }

    private function isTestName(string $name, int $srcIdx): bool
    {
        return \strtolower($this->fqn($name, $srcIdx)) === 'phpunit\framework\attributes\test';
    }

    private function fqn(string $name, int $idx): string
    {
        if ($name[0] === '\\') {
            return \ltrim($name, '\\');
        }
        $s = $this->src;
        $ns = '';
        $a = 0;
        $b = $s->n - 1;
        foreach (NsAnalysis::scopes($s) as [$nm, $x, $y]) {
            if ($idx >= $x && $idx <= $y) {
                $ns = $nm;
                $a = $x;
                $b = $y;
                break;
            }
        }
        $decl = [];
        foreach (NsAnalysis::singleImports($s) as $im) {
            if ($im[0] === 0 && $im[3] >= $a && $im[3] <= $b) {
                $decl[\strtolower($im[2])] = $im[1];
            }
        }
        $ln = \strtolower($name);
        foreach ($decl as $short => $full) {
            if ($ln === $short) {
                return \ltrim($full, '\\');
            }
            if (\str_starts_with($ln, $short . '\\')) {
                return \ltrim($full . \substr($name, \strlen($short)), '\\');
            }
        }
        return \ltrim($ns . '\\' . $name, '\\');
    }

    private function updateDoc(FlatTokens $t, int $di): void
    {
        $c = $t->c[$di];
        $lines = \preg_split('/(?<=\n)/', $c) ?: [$c];
        $changed = false;
        foreach ($lines as $k => $line) {
            if (!\str_contains($line, '@depends')) {
                continue;
            }
            $u = \preg_match('//u', $line) === 1 ? 'u' : '';
            $new = \preg_replace_callback('/(@depends\s+)(.+)(\b)/' . $u, fn (array $m): string => $m[1] . $this->casing($m[2]) . $m[3], $line);
            if ($new !== null && $new !== $line) {
                $lines[$k] = $new;
                $changed = true;
            }
        }
        if ($changed) {
            $t->c[$di] = \implode('', $lines);
        }
    }
}
