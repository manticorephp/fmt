<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Semicolon\NoSinglelineWhitespaceBeforeSemicolonsFixer (priority 0). A `;` loses the blanks (spaces / tabs
 * only) right before it; after another `;` they become one space. Blanks behind a comment stay.
 */
final class NoSinglelineWhitespaceBeforeSemicolons extends GapRule
{
    public function name(): string
    {
        return 'no_singleline_whitespace_before_semicolons';
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
        return [59];
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
        if ($gap === '' || $i >= $s->n || $s->kind[$i] !== 59 || \strspn($gap, " \t") !== \strlen($gap)) {
            return $gap;
        }
        if ($i > 0 && $s->kind[$i - 1] === 59) {
            return ' ';
        }
        return '';
    }
}
