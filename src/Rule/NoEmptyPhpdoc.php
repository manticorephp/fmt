<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Gap;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer (priority 3): an empty docblock is removed; on a line of its own, its line
 * goes with it (the blanks before it and the line break after it). Upstream's previous TOKEN is tested, so a docblock right behind one
 * this pass removed (an emptied token there) keeps its surroundings.
 */
final class NoEmptyPhpdoc extends DocRule
{
    public function name(): string
    {
        return 'no_empty_phpdoc';
    }

    public function priority(): int
    {
        return 3;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        return $doc;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if (($s->gapFlags[$i] & Gap::G_DOC) === 0) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $m = \count($p);
        /** @var bool[] $empty upstream holds an emptied token here */
        $empty = \array_fill(0, $m, false);
        $changed = false;
        for ($k = 0; $k < $m; $k++) {
            $x = $p[$k];
            if ($empty[$k] || !Gap::isDoc($x) || !Preg::test('#^/\*\*[\s\*]*\*/$#', $x)) {
                continue;
            }
            $changed = true;
            // the previous token: a piece, or the code token before the gap
            if ($k > 0) {
                $prevWs = !$empty[$k - 1] && Gap::isSpace($p[$k - 1]) && $p[$k - 1] !== '';
                $prevOk = $prevWs && \str_contains($p[$k - 1], "\n");
            } else {
                $prevWs = false;
                $prevOk = $i > 0 && $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG && \str_contains($s->text($i - 1), "\n");
            }
            if ($prevOk && $i === $s->n && $k === $m - 1) {
                $s->abort = true; // upstream reads the token behind the last one and throws: the file stays as it is
                return $gap;
            }
            $nextWs = $k + 1 < $m && Gap::isSpace($p[$k + 1]) && $p[$k + 1] !== '';
            if ($prevOk && $nextWs && Preg::test('/^\R/', $p[$k + 1])) {
                if ($prevWs) {
                    $p[$k - 1] = Preg::replace('/\h*$/', '', $p[$k - 1]);
                }
                $p[$k + 1] = Preg::replace('/^\R/', '', $p[$k + 1]);
                $nextWs = $p[$k + 1] !== '';
                if (!$nextWs) {
                    $empty[$k + 1] = true;
                }
            }
            // clearTokenAndMergeSurroundingWhitespace: the next whitespace joins the previous non-empty one (or takes the doc's place)
            $p[$k] = '';
            $empty[$k] = true;
            if ($nextWs) {
                $q = $k - 1;
                while ($q >= 0 && $empty[$q]) {
                    $q--;
                }
                if ($q >= 0 && Gap::isSpace($p[$q])) {
                    $p[$q] .= $p[$k + 1];
                } else {
                    $p[$q + 1] = $p[$k + 1]; // a comment or the code token before: the whitespace takes the first emptied slot after it
                    $empty[$q + 1] = false;
                }
                $p[$k + 1] = '';
                $empty[$k + 1] = true;
            }
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
