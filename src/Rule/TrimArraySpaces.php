<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\TrimArraySpacesFixer (priority 0). The blanks (spaces / tabs only) right after the opening
 * and right before the closing bracket of an array / destructuring list go: after the opener unless a `//` comment follows them without
 * blanks behind it; before the closer unless the token before them is a comma.
 */
final class TrimArraySpaces extends GapRule
{
    /** @var int[][] */
    private array $lists = [];

    public function name(): string
    {
        return 'trim_array_spaces';
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
        $this->lists = [];
        for ($a = 0; $a < $s->n; $a++) {
            $c = $s->ct[$a];
            if ($c === CT::ARRAY_BRACKET_OPEN || $c === CT::DESTRUCTURING_BRACKET_OPEN) {
                $this->lists[] = [$a, $s->pair[$a]];
            } elseif ($c === 0 && $s->kind[$a] === \__McTokId::T_ARRAY && $a + 1 < $s->n && $s->kind[$a + 1] === 40 && $s->pair[$a + 1] > 0) {
                $this->lists[] = [$a + 1, $s->pair[$a + 1]];
            }
        }
        return $this->lists !== [];
    }

    public function fixFile(Source $s): void
    {
        foreach ($this->lists as [$o, $e]) {
            $g = $s->gapBefore[$o + 1];
            $g2 = $s->gapBefore[$e];
            $trimEnd = false;
            if ($g2 !== '') {
                $q = Trivia::pieces($g2);
                $m2 = \count($q);
                $last = $q[$m2 - 1];
                $trimEnd = Trivia::blank($last) && ($m2 >= 2 || $s->kind[$e - 1] !== 44 || $s->ct[$e - 1] !== 0);
            }
            if ($g !== '') {
                $p = Trivia::pieces($g);
                if (Trivia::blank($p[0])) {
                    $m = \count($p);
                    $ok = $m === 1 || !Trivia::isComment($p[1]) || \str_starts_with($p[1], '/*') || ($m >= 3 && Trivia::blank($p[2]));
                    if (!$ok && $e === $o + 1) {
                        $lastNonWs = Trivia::isComment($p[$m - 1]) ? $m - 1 : $m - 2;
                        $ok = $lastNonWs === 1;
                    }
                    if ($ok) {
                        \array_shift($p);
                        $s->setGap($o + 1, \implode('', $p));
                    }
                }
            }
            if ($trimEnd) {
                $g2 = $s->gapBefore[$e];
                if ($g2 !== '') {
                    $q = Trivia::pieces($g2);
                    $m2 = \count($q);
                    if (Trivia::blank($q[$m2 - 1])) {
                        \array_pop($q);
                        $s->setGap($e, \implode('', $q));
                    }
                }
            }
        }
    }
}
