<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Whitespace\LineEndingFixer (priority 0). */
final class LineEnding extends GapRule
{
    public function __construct(private string $lineEnding = "\n")
    {
    }

    public function name(): string
    {
        return 'line_ending';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return $this->lineEnding === "\n" ? Gap::G_CR | Gap::G_ODD : Gap::G_CR | Gap::G_ODD | Gap::G_NL;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_TEXT;
    }

    /** Whitespace and comments: every gap, token by token (upstream picks the /u mode per token). */
    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($gap === '' || ($this->lineEnding === "\n" ? \strpbrk($gap, "\r\x0b\x0c\x85\xe2") : \strpbrk($gap, "\r\n\x0b\x0c\x85\xe2")) === false) {
            return $gap;
        }
        if (!Gap::hasComment($gap)) {
            return Gap::replaceR($gap, $this->lineEnding);
        }
        $out = '';
        foreach (Gap::pieces($gap) as $p) {
            $out .= Gap::replaceR($p, $this->lineEnding);
        }
        return $out;
    }

    /**
     * Open/close tags and heredoc starts; upstream also touches the heredoc body chunk that directly precedes the
     * end label (and only that one), never inline HTML or ordinary strings.
     */
    public function usesText(Source $s): bool
    {
        return true;
    }

    public function textKinds(): array
    {
        return [\__McTokId::T_OPEN_TAG, \__McTokId::T_CLOSE_TAG, \__McTokId::T_START_HEREDOC, \__McTokId::T_ENCAPSED_AND_WHITESPACE];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $k = $s->kind[$i];
        if ($k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_CLOSE_TAG || $k === \__McTokId::T_START_HEREDOC
            || ($k === \__McTokId::T_ENCAPSED_AND_WHITESPACE && $i + 1 < $s->n && $s->kind[$i + 1] === \__McTokId::T_END_HEREDOC)) {
            return Gap::replaceR($s->text($i), $this->lineEnding);
        }
        return null;
    }
}
