<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Semicolon\SpaceAfterSemicolonFixer (priority -1). A `;` followed by code gets one space (not before `)`, inline
 * HTML, or with remove_in_empty_for_expressions another `;`); blanks behind it become one space unless a comment or `)` follows. With the
 * option, blanks inside the `for (...)` header before a `;` or the closing `)` go.
 */
final class SpaceAfterSemicolon extends GapRule
{
    public function __construct(private bool $removeInEmptyFor) {}

    public function name(): string
    {
        return 'space_after_semicolon';
    }

    public function priority(): int
    {
        return -1;
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
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === 59) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $until = -1;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($this->removeInEmptyFor) {
                if ($k === \__McTokId::T_FOR && $s->ct[$i] === 0) {
                    $i++;
                    $until = $s->pair[$i];
                    continue;
                }
                if ($i === $until) {
                    $until = -1;
                    continue;
                }
            }
            if ($k !== 59) {
                continue;
            }
            $g = $s->gapBefore[$i + 1];
            if ($i + 1 >= $n && $g === '') {
                continue; // upstream's loop stops before the last token
            }
            $p = Trivia::pieces($g);
            $m = \count($p);
            if ($m === 0 || Trivia::isComment($p[0])) {
                if ($m === 0) {
                    $nk = $s->kind[$i + 1];
                    $nc = $s->ct[$i + 1];
                    if (($nk === 41 && $nc === 0) || $nk === \__McTokId::T_INLINE_HTML || ($this->removeInEmptyFor && $nk === 59)) {
                        continue;
                    }
                }
                $s->setGap($i + 1, ' ' . $g);
                continue;
            }
            // the token after the whitespace token: a comment piece, else the next code token (none at the end of the file)
            $after = -1; // -1 none, 0 comment, 1 `;`, 2 `)`, 3 other
            if ($m >= 2) {
                $after = 0;
            } elseif ($i + 1 < $n) {
                $nk = $s->kind[$i + 1];
                $nc = $s->ct[$i + 1];
                $after = $nc === 0 && $nk === 59 ? 1 : ($nc === 0 && $nk === 41 ? 2 : 3);
            }
            if ($until >= 0 && $m === 1 && ($after === 1 || $i + 1 === $until) && \strpbrk($p[0], "\n\r\x0b\x0c") === false) {
                $s->setGap($i + 1, '');
                continue;
            }
            if (($after === 1 || $after === 3) && $p[0] !== ' ' && Trivia::blank($p[0])) {
                $p[0] = ' ';
                $s->setGap($i + 1, \implode('', $p));
            }
        }
    }
}
