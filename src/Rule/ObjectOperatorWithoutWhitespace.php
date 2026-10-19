<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\ObjectOperatorWithoutWhitespaceFixer (priority 0). The blanks (spaces / tabs only) before and
 * after `->` / `?->` go unless a comment sits on the other side of them.
 */
final class ObjectOperatorWithoutWhitespace extends GapRule
{
    public function name(): string
    {
        return 'object_operator_without_whitespace';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function beforeKinds(): array
    {
        return [\__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR];
    }

    public function afterKinds(): array
    {
        return [\__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    private static function isOp(Source $s, int $i): bool
    {
        $k = $s->kind[$i];
        return $s->ct[$i] === 0 && ($k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR);
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($gap === '') {
            return $gap;
        }
        $p = null;
        if ($i < $s->n && self::isOp($s, $i)) { // the gap before the operator: its last piece
            $p = Trivia::pieces($gap);
            $m = \count($p);
            if (Trivia::blank($p[$m - 1]) && ($m < 2 || !Trivia::isComment($p[$m - 2]))) {
                \array_pop($p);
            }
            $gap = \implode('', $p);
            $p = null;
        }
        if ($gap !== '' && $i > 0 && self::isOp($s, $i - 1)) { // the gap after it: its first piece
            $p = Trivia::pieces($gap);
            if (Trivia::blank($p[0]) && (\count($p) < 2 || !Trivia::isComment($p[1]))) {
                \array_shift($p);
                $gap = \implode('', $p);
            }
        }
        return $gap;
    }
}
