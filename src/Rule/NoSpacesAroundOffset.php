<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\NoSpacesAroundOffsetFixer (priority 0). Index brackets `$a[ 0 ]`: with `inside` the blanks
 * (spaces / tabs) after `[` and before `]` go; with `outside` the whitespace before `[` goes (unless a comment precedes it).
 */
final class NoSpacesAroundOffset extends GapRule
{
    public function __construct(private bool $inside, private bool $outside)
    {
    }

    public function name(): string
    {
        return 'no_spaces_around_offset';
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
            if (($s->kind[$i] === 91 && $s->ct[$i] === 0) || $s->ct[$i] === CT::ARRAY_INDEX_BRACE_OPEN) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 1; $i < $n; $i++) {
            $c = $s->ct[$i];
            if (!(($s->kind[$i] === 91 && $c === 0) || $c === CT::ARRAY_INDEX_BRACE_OPEN)) {
                continue;
            }
            if ($this->inside) {
                $e = $s->pair[$i];
                $g = $s->gapBefore[$i + 1];
                if ($g !== '') {
                    $p = Trivia::pieces($g);
                    if (Trivia::blank($p[0])) {
                        \array_shift($p);
                        $s->setGap($i + 1, \implode('', $p));
                    }
                }
                $g = $s->gapBefore[$e];
                if ($g !== '') {
                    $p = Trivia::pieces($g);
                    $m = \count($p);
                    if (Trivia::blank($p[$m - 1])) {
                        \array_pop($p);
                        $s->setGap($e, \implode('', $p));
                    }
                }
            }
            if ($this->outside) {
                $g = $s->gapBefore[$i];
                if ($g === '') {
                    continue;
                }
                $p = Trivia::pieces($g);
                $m = \count($p);
                if (!\Fmt\Gap::isSpace($p[$m - 1]) || ($m >= 2 && Trivia::isComment($p[$m - 2]))) {
                    continue; // a comment right before: upstream's previous non-whitespace token
                }
                $s->setGap($i, Trivia::removeLeading($g));
            }
        }
    }
}
