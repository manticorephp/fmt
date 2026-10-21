<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\FopenFlagRule;
use Fmt\Preg;

/** Parity target: PhpCsFixer\Fixer\FunctionNotation\FopenFlagOrderFixer (priority 0, risky). The flags of the `fopen` mode are sorted, `b` and `t` last. */
final class FopenFlagOrder extends FopenFlagRule
{
    public function name(): string
    {
        return 'fopen_flag_order';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function fixFlag(FlatTokens $t, int $a, int $b): void
    {
        $f = self::soleString($t, $a, $b);
        if ($f < 0) {
            return;
        }
        $content = $t->content($f);
        $quote = $content[0];
        if ($quote === 'b' || $quote === 'B') {
            $bin = $quote;
            $quote = $content[1];
            $mode = \substr($content, 2, -1);
        } else {
            $bin = '';
            $mode = \substr($content, 1, -1);
        }
        if (\strlen($mode) < 2) {
            return;
        }
        if (!self::validMode($mode)) {
            return;
        }
        $flags = Preg::split('#([^\+]\+?)#', $mode, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
        \usort($flags, static function (string $x, string $y): int {
            if ($x === $y) {
                return 0;
            }
            if ($x === 'b') {
                return 1;
            }
            if ($y === 'b') {
                return -1;
            }
            if ($x === 't') {
                return 1;
            }
            if ($y === 't') {
                return -1;
            }
            return $x < $y ? -1 : 1;
        });
        $new = $bin . $quote . \implode('', $flags) . $quote;
        if ($content !== $new) {
            self::setCode($t, $f, \__McTokId::T_CONSTANT_ENCAPSED_STRING, $new);
        }
    }
}
