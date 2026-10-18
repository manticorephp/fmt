<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\SingleBlankLineAtEofFixer (priority -100): unless the file ends in inline
 * HTML or a close/open tag, its last token is made a single line ending (Tokens::ensureWhitespaceAtIndex(last, 1, "\n")):
 * a trailing whitespace token is replaced, after anything else one is added. A whitespace token right after an open tag
 * is folded into the tag (`rtrim(tag) . "\n"`) and disappears.
 */
final class SingleBlankLineAtEof extends GapRule
{
    public function __construct(private string $lineEnding = "\n")
    {
    }

    public function name(): string
    {
        return 'single_blank_line_at_eof';
    }

    public function priority(): int
    {
        return -100;
    }

    /** only the trailing gap, which the Engine always runs */
    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        $n = $s->n;
        if ($i !== $n || $n === 0) {
            return $gap;
        }
        if ($gap === '') {
            $k = $s->kind[$n - 1];
            if ($k === \__McTokId::T_INLINE_HTML || $k === \__McTokId::T_CLOSE_TAG || $k === \__McTokId::T_OPEN_TAG) {
                return $gap;
            }
            return $this->lineEnding;
        }
        $pieces = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
        $cnt = \count($pieces);
        if (!Gap::isSpace($pieces[$cnt - 1])) {
            return $gap . $this->lineEnding;
        }
        if ($cnt === 1 && $s->kind[$n - 1] === \__McTokId::T_OPEN_TAG) {
            $le = $this->lineEnding;
            $tag = \rtrim($s->text($n - 1));
            if (\str_starts_with($le, "\r\n")) {
                $s->setText($n - 1, $tag . "\r\n");
                return \substr($le, 2);
            }
            $s->setText($n - 1, $tag . $le[0]);
            return \substr($le, 1);
        }
        $out = '';
        for ($k = 0; $k < $cnt - 1; $k++) {
            $out .= $pieces[$k];
        }
        return $out . $this->lineEnding;
    }
}
