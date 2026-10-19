<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\NoMultipleStatementsPerLineFixer (priority -1), a file rule (the indent of the new line is
 * read from the live gaps). A `;` followed on the same line by anything but `}`, a close tag or an `end...` keyword gets a line
 * break + the indent of its own line: the first non-comment trivia piece behind it is replaced, or one is inserted after the
 * comments. `for (...)` headers and property hook bodies are jumped over.
 */
final class NoMultipleStatementsPerLine extends GapRule
{
    public function name(): string
    {
        return 'no_multiple_statements_per_line';
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
        $n = $s->n;
        for ($j = 1; $j < $n - 1; $j++) {
            if ($s->kind[$j] === 59) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 1; $i < $n - 1; $i++) {
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            if ($k === \__McTokId::T_FOR && $c === 0) {
                $o = $i + 1;
                while ($o < $n && !($s->kind[$o] === 40 && $s->ct[$o] === 0)) {
                    $o++;
                }
                if ($o < $n) {
                    $i = $s->pair[$o];
                }
                continue;
            }
            if ($c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                $i = $s->pair[$i];
                continue;
            }
            if ($k !== 59 || $c !== 0) {
                continue;
            }
            $gi = $i + 1;
            if ($gi >= $n) {
                continue;
            }
            $p = Analyzer::pieces($s, $gi);
            $m = \count($p);
            $nl = false;
            for ($q = 0; $q < $m; $q++) {
                if (Gap::pieceHasR($p[$q])) {
                    $nl = true;
                    break;
                }
            }
            if ($nl) {
                continue;
            }
            if ($gi === $n - 1 && $s->gapBefore[$n] === '') {
                continue;
            }
            $nk = $s->kind[$gi];
            $nc = $s->ct[$gi];
            if ($nc === 0 && ($nk === 125 || $nk === \__McTokId::T_CLOSE_TAG || $nk === \__McTokId::T_ENDIF || $nk === \__McTokId::T_ENDFOR
                || $nk === \__McTokId::T_ENDSWITCH || $nk === \__McTokId::T_ENDWHILE || $nk === \__McTokId::T_ENDFOREACH)) {
                continue;
            }
            $newline = "\n" . Analyzer::lineIndentation($s, $i);
            $q = 0;
            while ($q < $m && !Gap::isSpace($p[$q])) {
                $q++;
            }
            if ($q < $m) {
                $p[$q] = $newline;
            } else {
                $p[] = $newline;
            }
            $s->setGap($gi, \implode('', $p));
        }
    }
}
