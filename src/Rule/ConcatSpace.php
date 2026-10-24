<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\ConcatSpaceFixer (priority 0), both spacings. The whitespace token before / after
 * a `.` is the last / first trivia piece of the gap. 'one': a missing blank is inserted, an existing one becomes a single space
 * unless it holds a line break or a comment sits past it (a comment is a piece of the same gap, so that means: only a
 * lone whitespace gap is rewritten). 'none': a blank-only whitespace piece is dropped, except before a `.` that follows an
 * integer or a `//` / `#` comment and after a `.` that precedes an integer or any comment.
 */
final class ConcatSpace extends GapRule
{
    public function __construct(private bool $one) {}

    public function name(): string
    {
        return 'concat_space';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    /** @return int[] */
    public function afterKinds(): array
    {
        return [46];
    }

    /** @return int[] */
    public function beforeKinds(): array
    {
        return [46];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        $before = $i < $s->n && $s->kind[$i] === 46;
        $after = $i >= 1 && $s->kind[$i - 1] === 46;
        if (!$before && !$after) {
            return $gap;
        }
        if ($gap !== '' && Gap::hasComment($gap)) {
            return $this->withComment($gap, $before, $after);
        }
        if ($this->one) {
            return $gap === ' ' || ($gap !== '' && \strpos($gap, "\n") !== false) ? $gap : ' ';
        }
        if ($gap === '' || \strspn($gap, " \t") !== \strlen($gap)) {
            return $gap;
        }
        $int = \__McTokId::T_LNUMBER;
        // before: the dot is token i, the token ahead of the blank is i-1; after: the dot is i-1, the token past the blank is i
        if (($before && $i >= 1 && $s->kind[$i - 1] !== $int) || ($after && ($i >= $s->n || $s->kind[$i] !== $int))) {
            return '';
        }
        return $gap;
    }

    private function withComment(string $gap, bool $before, bool $after): string
    {
        $one = $this->one;
        $p = Gap::pieces($gap);
        $m = \count($p);
        if ($before) {
            $z = $p[$m - 1];
            if (!Gap::isSpace($z)) {
                if ($one) {
                    $p[] = ' ';
                }
            } elseif (!$one && $m > 1 && \substr($p[$m - 2], 0, 2) === '/*' && \strspn($z, " \t") === \strlen($z)) {
                \array_pop($p);
            }
        }
        if ($after && $one && !Gap::isSpace($p[0])) {
            \array_unshift($p, ' ');
        }
        return \implode('', $p);
    }
}
