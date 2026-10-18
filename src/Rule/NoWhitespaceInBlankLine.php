<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Whitespace\NoWhitespaceInBlankLineFixer (priority -99). */
final class NoWhitespaceInBlankLine extends GapRule
{
    public function __construct(private string $lineEnding = "\n")
    {
    }

    public function name(): string
    {
        return 'no_whitespace_in_blank_line';
    }

    public function priority(): int
    {
        return -99;
    }

    public function needsMask(): int
    {
        return Gap::G_NL2;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    /**
     * A whitespace token with 3+ lines, the last token of the file, or one right after an open tag has its
     * horizontal-only lines emptied and is re-joined with the configured line ending (this also normalises its \r\n).
     */
    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i === 0 || $gap === '') {
            return $gap;
        }
        $prevOpen = $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG;
        if (!$prevOpen && $i < $s->n && \substr_count($gap, "\n") < 2) {
            return $gap; // fewer than 3 lines and not EOF / after an open tag
        }
        $prevText = '';
        if ($prevOpen) {
            $prevText = $s->text($i - 1);
        }
        $pieces = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
        $cnt = \count($pieces);
        $out = '';
        for ($k = 0; $k < $cnt; $k++) {
            $p = $pieces[$k];
            if (!Gap::isSpace($p)) {
                $out .= $p;
                continue;
            }
            $afterOpen = $k === 0 && $prevOpen;
            $hasNext = $i < $s->n || $k < $cnt - 1;
            $lines = \explode("\n", $p);
            $lc = \count($lines);
            if ($lc > 2 || !$hasNext || $afterOpen) {
                for ($l = 0; $l < $lc - 1; $l++) {
                    $ll = \strlen($lines[$l]);
                    if ($ll > 0 && \ord($lines[$l][$ll - 1]) === 13) {
                        $lines[$l] = \substr($lines[$l], 0, $ll - 1);
                    }
                }
                $lMax = $hasNext ? $lc - 1 : $lc;
                $lStart = ($afterOpen && \ord($prevText[\strlen($prevText) - 1]) === 10) ? 0 : 1;
                for ($l = $lStart; $l < $lMax; $l++) {
                    $len = \strlen($lines[$l]);
                    if ($len > 0 && \strspn($lines[$l], " \t") === $len) {
                        $lines[$l] = '';
                    }
                }
                $p = \implode($this->lineEnding, $lines);
            }
            $out .= $p;
        }
        return $out;
    }
}
