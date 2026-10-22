<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\NotOperatorWithSpaceFixer (priority -10) and, with $successorOnly, NotOperatorWithSuccessorSpaceFixer (-10).
 * The first puts a whitespace token before and after every `!` unless one is there already (a comment is no whitespace: the space goes between
 * the `!` and the comment); the second ensures ONE space after it (`ensureWhitespaceAtIndex`: any whitespace token there becomes ' ').
 */
final class NotOperatorWithSpace extends GapRule
{
    public function __construct(private bool $successorOnly = false)
    {
    }

    public function name(): string
    {
        return $this->successorOnly ? 'not_operator_with_successor_space' : 'not_operator_with_space';
    }

    public function priority(): int
    {
        return -10;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [33];
    }

    public function beforeKinds(): array
    {
        return $this->successorOnly ? [] : [33];
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
        $after = $i > 0 && $i <= $s->n && $s->kind[$i - 1] === 33;
        $before = !$this->successorOnly && $i < $s->n && $s->kind[$i] === 33;
        if (!$after && !$before) {
            return $gap;
        }
        if ($gap === '') {
            return ' ';
        }
        $split = isset($s->wsSplit[$i]);
        if (($s->gapFlags[$i] & Gap::G_COMMENT) === 0 && !$split) {
            return $this->successorOnly && $after ? ' ' : $gap;
        }
        $p = Gap::pieces($gap);
        if ($split) {
            $p = Gap::splitPieces($p, $s->wsSplit[$i]);
        }
        $c = \count($p);
        if ($after) {
            if (Gap::isSpace($p[0])) {
                if ($this->successorOnly) {
                    $p[0] = ' ';
                }
            } else {
                \array_unshift($p, ' ');
                $c++;
            }
        }
        if ($before && !Gap::isSpace($p[$c - 1])) {
            $p[] = ' ';
        }
        return \implode('', $p);
    }
}
