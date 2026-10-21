<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\PhpUnitClassRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitMethodCasingFixer (priority 0). In a PHPUnit test class (a class that extends something and names a `...Test` /
 * `...TestCase` type in its signature) every test method (`test...`, `#[Test]`, or a docblock with `@test`) is renamed to camelCase (or snake_case) unless the new
 * name is taken; `@depends` lines of its docblock follow. Whole-file FlatTokens port, the last test class first.
 */
final class PhpUnitMethodCasing extends PhpUnitClassRule
{
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

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $this->fixClass($t, $start, $end);
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
        foreach (self::attributeNames($t, $a) as [$name, $at]) {
            if ($this->isTestName($name, $at)) {
                return true;
            }
        }
        return false;
    }

    private function isTestName(string $name, int $srcIdx): bool
    {
        return \strtolower($this->fqn($name, $srcIdx)) === 'phpunit\framework\attributes\test';
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
