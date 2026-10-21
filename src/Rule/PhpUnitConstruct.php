<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitConstructFixer (priority -8, risky). `$this->assertSame(true, $a)` -> `$this->assertTrue($a)` (also assertEquals, and the
 * Not variants with false / null / true as the first argument); the `assertions` option picks the methods. Per PHPUnit class, the search runs to the end of the file as upstream's.
 */
final class PhpUnitConstruct extends PhpUnitClassRule
{
    /** @param string[] $assertions */
    public function __construct(private array $assertions)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_construct';
    }

    public function priority(): int
    {
        return -8;
    }

    /** the same-class call test: `$this->` / `self::` / `static::` right before the name, `(` right after */
    public static function sameClassCall(FlatTokens $t, int $index): bool
    {
        $op = $t->meaningful($index, -1);
        if ($op < 0 || !($t->k[$op] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$op] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $t->k[$op] === \__McTokId::T_DOUBLE_COLON)) {
            return false;
        }
        $ref = $t->meaningful($op, -1);
        if ($ref < 0 || $t->i[$ref] === -1) {
            return false;
        }
        $k = $t->k[$ref];
        $c = \strtolower($t->content($ref));
        if (!(($k === \__McTokId::T_VARIABLE && $c === '$this') || ($k === \__McTokId::T_STRING && $c === 'self') || ($k === \__McTokId::T_STATIC && $c === 'static'))) {
            return false;
        }
        $n = $t->meaningful($index, 1);
        return $n >= 0 && $t->i[$n] !== -1 && $t->k[$n] === 40;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        foreach ($this->assertions as $method) {
            $positive = $method === 'assertSame' || $method === 'assertEquals';
            for ($index = $start; $index < $end; $index++) {
                $r = $this->fixAssert($t, $index, $method, $positive);
                if ($r < 0) {
                    break;
                }
                $index = $r;
            }
        }
    }

    private function fixAssert(FlatTokens $t, int $index, string $method, bool $positive): int
    {
        $m = $t->count();
        $name = -1;
        $open = -1;
        for ($j = $index; $j < $m; $j++) {
            if ($t->i[$j] === -1 || $t->k[$j] !== \__McTokId::T_STRING || $t->content($j) !== $method) {
                continue;
            }
            $o = $t->meaningful($j, 1);
            if ($o >= 0 && $t->i[$o] !== -1 && $t->k[$o] === 40) {
                $name = $j;
                $open = $o;
                break;
            }
        }
        if ($name < 0) {
            return -1;
        }
        if (!self::sameClassCall($t, $name)) {
            return -1;
        }
        $first = $t->meaningful($open, 1);
        $lower = $first >= 0 ? \strtolower($t->content($first)) : '';
        if ($first < 0 || $t->i[$first] === -1 || $t->k[$first] < 256 || !($lower === 'true' || $lower === 'false' || $lower === 'null')) {
            return $first;
        }
        $comma = $t->meaningful($first, 1);
        if ($comma < 0 || !self::is($t, $comma, 44)) {
            return $comma;
        }
        $new = ($positive ? 'assert' : 'assertNot') . \ucfirst($lower);
        self::setCode($t, $name, \__McTokId::T_STRING, $new);
        $stop = $t->nonWhitespace($comma, 1) - 1;
        for ($i = $first; $i <= $stop; $i++) {
            $t->clearAt($i);
        }
        return $comma;
    }
}
