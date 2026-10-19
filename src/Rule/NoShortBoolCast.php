<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\CastNotation\NoShortBoolCastFixer (priority -9). Two `!` with only trivia between them become
 * `(bool)` after the comments between them (a whitespace token survives only behind a comment); `!!!$a` -> `!(bool)$a` (a pair is
 * consumed, the walk resumes before its first `!`). Upstream's `> 1` index bound: a first `!` directly behind the open tag is skipped.
 */
final class NoShortBoolCast extends EditRule
{
    public function name(): string
    {
        return 'no_short_bool_cast';
    }

    public function priority(): int
    {
        return -9;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === 33 && $s->kind[$i - 1] === 33) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 1; $i >= 2; $i--) {
            if ($s->kind[$i] !== 33 || $s->ct[$i] !== 0) {
                continue;
            }
            $j = $i - 1;
            if ($s->kind[$j] !== 33 || $s->ct[$j] !== 0 || ($j === 1 && $s->gapBefore[1] === '')) {
                continue;
            }
            $keep = '';
            $g = $s->gapBefore[$i];
            if ($g !== '' && Gap::hasComment($g)) {
                $p = Gap::pieces($g);
                for ($k = 0; $k < \count($p); $k++) {
                    if (!Gap::isSpace($p[$k]) || ($k > 0 && !Gap::isSpace($p[$k - 1]))) {
                        $keep .= $p[$k];
                    }
                }
            }
            $s->edit(Source::tokAt($j), Source::tokAt($i) + 1, $keep . '(bool)');
            $i = $j;
        }
    }
}
