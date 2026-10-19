<?php

declare(strict_types=1);

namespace Fmt;

/**
 * A phpdoc rule: docblocks are trivia (T_DOC_COMMENT pieces of a gap), so most phpdoc fixers are gap rules that rewrite the doc
 * pieces of a gap. The Engine calls fixGap only for gaps flagged G_DOC (plus the trailing gap / the gap after an open tag, which
 * are re-checked here); fixDoc gets one doc piece and answers its new text ('' deletes it: upstream's clearTokenAndMergeSurroundingWhitespace
 * leaves exactly the two surrounding whitespace pieces concatenated). A rule's own cheap text test (`str_contains`) goes first in
 * fixDoc so a docblock that cannot match costs no parse.
 */
abstract class DocRule extends GapRule
{
    public function needsMask(): int
    {
        return Gap::G_DOC;
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
        if (($s->gapFlags[$i] & Gap::G_DOC) === 0) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $changed = false;
        foreach ($p as $k => $x) {
            if (!Gap::isDoc($x)) {
                continue;
            }
            $new = $this->fixDoc($x, $s, $i, $p, $k);
            if ($new !== $x) {
                $p[$k] = $new;
                $changed = true;
            }
        }
        return $changed ? \implode('', $p) : $gap;
    }

    /**
     * For file rules: rewrites the doc pieces of gap $g with $f (doc text => new text); $from = the first piece to look at.
     * @param \Closure(string): string $f
     */
    public static function mapDocs(Source $s, int $g, \Closure $f, int $from = 0): void
    {
        if (($s->gapFlags[$g] & Gap::G_DOC) === 0) {
            return;
        }
        $p = Gap::pieces($s->gapBefore[$g]);
        $changed = false;
        foreach ($p as $k => $x) {
            if ($k >= $from && Gap::isDoc($x)) {
                $new = $f($x);
                if ($new !== $x) {
                    $p[$k] = $new;
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $s->setGap($g, \implode('', $p));
        }
    }

    /** index of the last doc piece of gap $g, -1 when none */
    public static function lastDoc(Source $s, int $g): int
    {
        if (($s->gapFlags[$g] & Gap::G_DOC) === 0) {
            return -1;
        }
        $p = Gap::pieces($s->gapBefore[$g]);
        for ($k = \count($p) - 1; $k >= 0; $k--) {
            if (Gap::isDoc($p[$k])) {
                return $k;
            }
        }
        return -1;
    }

    /**
     * New text of doc piece $k of gap $i ($p = the gap's pieces, already updated for the doc pieces before $k).
     * @param string[] $p
     */
    abstract public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string;
}
