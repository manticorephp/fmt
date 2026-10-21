<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassUsage\DateTimeImmutableFixer (priority 0, risky). `new DateTime` / `DateTime::createFromFormat(` -> `DateTimeImmutable` (a leading `\` is added in a namespace
 * when the class is used alone and imported with `use DateTime;`), `date_create()` -> `date_create_immutable()`, `date_create_from_format()` -> `date_create_immutable_from_format()`. Whole-file
 * FlatTokens port, first token first.
 */
final class DateTimeImmutable extends FlatRule
{
    public function name(): string
    {
        return 'date_time_immutable';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_STRING) {
                $len = $s->end[$i] - $s->start[$i];
                if ($len === 8 || $len === 11 || $len === 23) {
                    $l = \strtolower($s->text($i));
                    if ($l === 'datetime' || $l === 'date_create' || $l === 'date_create_from_format') {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $inNs = false;
        $imported = false;
        $limit = $t->count();
        for ($index = 0; $index < $limit; $index++) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_NAMESPACE) {
                $inNs = true;
                continue;
            }
            if ($inNs && $k === \__McTokId::T_USE) {
                $next = $t->meaningful($index, 1);
                if ($next < 0 || \strtolower($t->content($next)) !== 'datetime') {
                    continue;
                }
                $nn = $t->meaningful($next, 1);
                if (self::is($t, $nn, 59)) {
                    $imported = true;
                }
                $index = $nn;
                continue;
            }
            if ($k !== \__McTokId::T_STRING) {
                continue;
            }
            $prev = $t->meaningful($index, -1);
            if (self::is($t, $prev, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $lower = \strtolower($t->content($index));
            if ($lower === 'datetime') {
                $this->fixClass($t, $index, $inNs, $imported);
                $limit = $t->count();
                continue;
            }
            if (($lower === 'date_create' || $lower === 'date_create_from_format') && $this->isGlobalCall($s, $t, $index)) {
                self::setCode($t, $index, \__McTokId::T_STRING, $lower === 'date_create' ? 'date_create_immutable' : 'date_create_immutable_from_format');
            }
        }
    }

    private function fixClass(FlatTokens $t, int $index, bool $inNs, bool $imported): void
    {
        $next = $t->meaningful($index, 1);
        if (self::is($t, $next, \__McTokId::T_DOUBLE_COLON)) {
            $nn = $t->meaningful($next, 1);
            if (self::is($t, $nn, \__McTokId::T_STRING)) {
                $nnn = $t->meaningful($nn, 1);
                if (!self::is($t, $nnn, 40)) {
                    return;
                }
            }
        }
        $alone = false;
        $leading = false;
        $prev = $t->meaningful($index, -1);
        if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
            $pp = $t->meaningful($prev, -1);
            if (!self::is($t, $pp, \__McTokId::T_STRING)) {
                $leading = true;
            }
        } elseif (!self::is($t, $prev, \__McTokId::T_DOUBLE_COLON) && !self::is($t, $prev, \__McTokId::T_OBJECT_OPERATOR) && !self::is($t, $prev, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
            $alone = true;
        }
        if ($leading || ($alone && (($inNs && $imported) || !$inNs))) {
            self::setCode($t, $index, \__McTokId::T_STRING, 'DateTimeImmutable');
            if ($inNs && $alone) {
                $t->insertAt($index, [FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\')]);
            }
        }
    }
}
