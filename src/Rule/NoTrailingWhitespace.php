<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Whitespace\NoTrailingWhitespaceFixer (priority 0). */
final class NoTrailingWhitespace extends GapRule
{
    public function name(): string
    {
        return 'no_trailing_whitespace';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return Gap::G_LEADWS_NL | Gap::G_BLANK_BREAK;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    /**
     * Every whitespace token of the gap is looked at on its own. A whitespace token that holds a line break (or is the
     * last token of the file) loses the spaces/tabs before its first line break, unless it directly follows an open tag
     * that already ends in a line break. A `<?php ` (trailing space) followed by a line break trades that space
     * for the break.
     */
    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($gap === '' || ($i < $s->n && \strpbrk($gap, "\r\n\x0b\x0c") === false)) {
            return $gap; // no line break and not the last token: nothing to trim
        }
        $fl = $s->gapFlags[$i];
        $split = $s->wsSplit[$i] ?? 0;
        if ($split === 0 && $i < $s->n && ($fl & Gap::G_COMMENT) === 0 && ($i === 0 || $s->kind[$i - 1] !== \__McTokId::T_OPEN_TAG)) {
            // interior pure-whitespace gap: only the blanks before the first line break can go
            return ($fl & Gap::G_LEADWS_NL) !== 0 ? \substr($gap, \strcspn($gap, "\r\n")) : $gap;
        }
        $prevOpen = false;
        $prevNl = false;
        $prevHs = false;
        if ($i > 0 && $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG) {
            $prevOpen = true;
            $pt = $s->text($i - 1);
            $lb = \ord($pt[\strlen($pt) - 1]);
            $prevNl = $lb === 10 || $lb === 13;
            $prevHs = $lb === 32 || $lb === 9;
        }
        $pieces = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
        if ($split > 0) {
            $pieces = Gap::splitPieces($pieces, $split); // two adjacent whitespace tokens upstream: each is trimmed on its own
        }
        $cnt = \count($pieces);
        $out = '';
        for ($k = 0; $k < $cnt; $k++) {
            $p = $pieces[$k];
            if (!Gap::isSpace($p)) {
                $out .= $p;
                continue;
            }
            $first = $k === 0 && $prevOpen;
            // upstream walks the tokens backwards: the whitespace is trimmed first, then the open tag looks at the result
            $isLast = $i === $s->n && $k === $cnt - 1;
            $p = self::trimLines($p, $isLast, $first && $prevNl);
            $m = $p === '' ? 0 : Gap::rLen($p, 0);
            if ($first && $prevHs && $m > 0) {
                $pt = $s->text($i - 1);
                $s->setText($i - 1, \substr($pt, 0, \strlen($pt) - 1) . \substr($p, 0, $m));
                $p = \substr($p, $m);
            }
            $out .= $p;
            if ($split > 0 && $k === 0) {
                $s->wsSplit[$i] = \strlen($out);
            }
        }
        return $out;
    }

    /**
     * preg_split('/(\R+)/') + rtrim(" \t") of the text segments: segment 0 always (unless $keepFirst), later segments
     * only when something is left. Applies only to a piece holding a line break, or the last token of the file.
     */
    private static function trimLines(string $p, bool $isLast, bool $keepFirst): string
    {
        $n = \strlen($p);
        $out = '';
        $segStart = 0;
        $idx = 0;
        $i = 0;
        $hasNl = false;
        while ($i < $n) {
            $l = Gap::rLen($p, $i);
            if ($l === 0) {
                $i++;
                continue;
            }
            $hasNl = true;
            $out .= self::trimSeg(\substr($p, $segStart, $i - $segStart), $idx, $keepFirst);
            $out .= \substr($p, $i, $l);
            $i += $l;
            $segStart = $i;
            $idx++;
        }
        if (!$hasNl && !$isLast) {
            return $p;
        }
        return $out . self::trimSeg(\substr($p, $segStart), $idx, $keepFirst);
    }

    private static function trimSeg(string $seg, int $idx, bool $keepFirst): string
    {
        if ($idx === 0) {
            return $keepFirst ? $seg : \rtrim($seg, " \t");
        }
        $t = \rtrim($seg, " \t");
        return $t === '' ? $seg : $t;
    }
}
