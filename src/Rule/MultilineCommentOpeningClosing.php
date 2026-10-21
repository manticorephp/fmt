<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\MultilineCommentOpeningClosingFixer (priority 0). A `/* ... *\/` comment opens with a single `*` (`/***` -> `/*`;
 * a docblock keeps its two) and every block comment or docblock ends with a single `*` before the closing slash. Gap rule over the comment pieces.
 */
final class MultilineCommentOpeningClosing extends GapRule
{
    public function name(): string
    {
        return 'multiline_comment_opening_closing';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return Gap::G_COMMENT;
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
        if (($s->gapFlags[$i] & Gap::G_COMMENT) === 0 || \strpos($gap, '**') === false) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $changed = false;
        foreach ($p as $k => $x) {
            if (Gap::isSpace($x) || $x[0] !== '/' || !\str_starts_with($x, '/*')) {
                continue;
            }
            $new = $x;
            if (!Trivia::isDoc($x)) {
                $new = Trivia::preplace('/^\/\*{2,}(?!\/)/', '/*', $new);
            }
            $new = Trivia::preplace('/(?<!\/)\*{2,}\/$/', '*/', $new);
            if ($new !== $x) {
                $p[$k] = $new;
                $changed = true;
            }
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
