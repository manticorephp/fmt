<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Gap;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\NoBlankLinesAfterPhpdocFixer (priority -20): no blank line between a docblock and its code. */
final class NoBlankLinesAfterPhpdoc extends DocRule
{
    public function name(): string
    {
        return 'no_blank_lines_after_phpdoc';
    }

    public function priority(): int
    {
        return -20;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        return $doc;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        // only the last doc of a gap can be followed by exactly one whitespace token and code: the gap ends `<doc><ws>`
        if (($s->gapFlags[$i] & Gap::G_NL2) === 0 || ($s->gapFlags[$i] & Gap::G_DOC) === 0 || $i >= $s->n) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $m = \count($p);
        if ($m < 2 || !Gap::isSpace($p[$m - 1]) || !Gap::isDoc($p[$m - 2]) || \substr_count($p[$m - 1], "\n") < 2) {
            return $gap;
        }
        $kd = $s->kind[$i];
        if ($kd === \__McTokId::T_BREAK || $kd === \__McTokId::T_CONTINUE || $kd === \__McTokId::T_DECLARE || $kd === \__McTokId::T_GOTO
            || $kd === \__McTokId::T_INCLUDE || $kd === \__McTokId::T_INCLUDE_ONCE || $kd === \__McTokId::T_REQUIRE
            || $kd === \__McTokId::T_REQUIRE_ONCE || $kd === \__McTokId::T_RETURN || $kd === \__McTokId::T_THROW
            || (($kd === \__McTokId::T_NAMESPACE || $kd === \__McTokId::T_USE) && $s->ct[$i] === 0)) {
            return $gap;
        }
        $ws = $p[$m - 1];
        $p[$m - 1] = \substr($ws, (int) \strrpos($ws, "\n"));
        return \implode('', $p);
    }
}
