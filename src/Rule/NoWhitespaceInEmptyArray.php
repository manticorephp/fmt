<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\NoWhitespaceInEmptyArrayFixer (priority 0). `[ ]` / `[\n]`: the one whitespace token between a short
 * array's brackets goes (a comment between them, or two whitespace tokens left by an earlier edit, keep it).
 */
final class NoWhitespaceInEmptyArray extends GapRule
{
    public function name(): string
    {
        return 'no_whitespace_in_empty_array';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->ct[$i] === CT::ARRAY_BRACKET_CLOSE && $s->ct[$i - 1] === CT::ARRAY_BRACKET_OPEN && $s->gapBefore[$i] !== '') {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 1; $i > 0; $i--) {
            if ($s->ct[$i] === CT::ARRAY_BRACKET_CLOSE && $s->ct[$i - 1] === CT::ARRAY_BRACKET_OPEN && $s->gapBefore[$i] !== ''
                && ($s->gapFlags[$i] & Gap::G_COMMENT) === 0 && !isset($s->wsSplit[$i])) {
                $s->setGap($i, '');
            }
        }
    }
}
