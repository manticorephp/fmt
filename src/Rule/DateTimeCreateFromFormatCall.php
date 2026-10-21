<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NsAnalysis;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\DateTimeCreateFromFormatCallFixer (priority 0, risky). `DateTime::createFromFormat('Y-m-d', $s)` (also DateTimeImmutable, `\` prefixed, or in
 * the global namespace) gets a `!` in front of its string literal format. A class name that a `use` import aliases to something else is skipped. Whole-file FlatTokens port, namespace by namespace.
 */
final class DateTimeCreateFromFormatCall extends FlatRule
{
    public function name(): string
    {
        return 'date_time_create_from_format_call';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_STRING && $s->end[$i] - $s->start[$i] === 16 && \strtolower($s->text($i)) === 'createfromformat') {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $imports = NsAnalysis::singleImports($s);
        foreach (NsAnalysis::scopes($s) as [$name, $a, $b]) {
            $from = $t->pos[$a] ?? -1;
            $to = $t->pos[$b] ?? -1;
            if ($from < 0 || $to < 0) {
                continue;
            }
            $aliased = false;
            foreach ($imports as [$type, $full, $short, $u, $e]) {
                if ($u < $a || $e > $b) {
                    continue;
                }
                $sl = \strtolower($short);
                if (($sl === 'datetime' || $sl === 'datetimeimmutable') && $sl !== \strtolower($full)) {
                    $aliased = true;
                }
            }
            $global = $name === '';
            for ($index = $to; $index > $from; $index--) {
                if (!self::is($t, $index, \__McTokId::T_DOUBLE_COLON)) {
                    continue;
                }
                $fn = $t->meaningful($index, 1);
                if ($fn < 0 || $t->i[$fn] === -1 || $t->k[$fn] !== \__McTokId::T_STRING || \strtolower($t->content($fn)) !== 'createfromformat') {
                    continue;
                }
                if (!self::is($t, $t->meaningful($fn, 1), 40)) {
                    continue;
                }
                $cls = $t->meaningful($index, -1);
                if ($cls < 0 || $t->i[$cls] === -1 || $t->k[$cls] !== \__McTokId::T_STRING) {
                    continue;
                }
                $cl = \strtolower($t->content($cls));
                if ($cl !== 'datetime' && $cl !== 'datetimeimmutable') {
                    continue;
                }
                $pre = $t->meaningful($cls, -1);
                if (self::is($t, $pre, \__McTokId::T_NS_SEPARATOR)) {
                    if (self::is($t, $t->meaningful($pre, -1), \__McTokId::T_STRING)) {
                        continue;
                    }
                } elseif (!$global) {
                    continue;
                } elseif ($aliased) {
                    continue;
                }
                $open = self::nextOf($t, $fn, 40);
                $close = $t->blockEnd($open, 41);
                $args = self::callArguments($t, $open, $close);
                if (\count($args) !== 2) {
                    continue;
                }
                $starts = \array_keys($args);
                $as = $t->meaningful($starts[0] - 1, 1);
                $ae = $args[$starts[0]];
                if ($as !== $ae && $t->meaningful($as, 1) <= $ae) {
                    continue;
                }
                if (!self::is($t, $as, \__McTokId::T_CONSTANT_ENCAPSED_STRING)) {
                    continue;
                }
                $format = $t->content($as);
                if (\strlen($format) < 3) {
                    continue;
                }
                $off = $format[0] === 'b' || $format[0] === 'B' ? 2 : 1;
                if ($format[$off] === '!') {
                    continue;
                }
                self::setCode($t, $as, \__McTokId::T_CONSTANT_ENCAPSED_STRING, \substr_replace($format, '!', $off, 0));
            }
        }
    }
}
