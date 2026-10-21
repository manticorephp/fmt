<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer (priority 6). In a double-quoted string or heredoc (not a backtick string) a variable
 * written without braces gets them: `"$a"` -> `"{$a}"`, `"$a->b"` / `"$a[0]"` / `"$a[k]"` -> `"{$a->b}"` / `"{$a[0]}"` / `"{$a['k']}"`. Walks from the last token to the
 * first; a variable directly after a string part starts a run (the following variables that are not subscripts of the first start their own).
 */
final class ExplicitStringVariable extends EditRule
{
    private Source $s;

    public function name(): string
    {
        return 'explicit_string_variable';
    }

    public function priority(): int
    {
        return 6;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_VARIABLE && ($s->kind[$i - 1] === 34 || $s->kind[$i - 1] === \__McTokId::T_ENCAPSED_AND_WHITESPACE
                || $s->kind[$i - 1] === \__McTokId::T_START_HEREDOC)) {
                return true;
            }
        }
        return false;
    }

    private function stringPart(int $j): bool
    {
        $s = $this->s;
        if ($j < 0 || $j >= $s->n) {
            return false;
        }
        $k = $s->kind[$j];
        return $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $k === \__McTokId::T_START_HEREDOC || ($k === 34 && $s->ct[$j] === 0);
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $n = $s->n;
        /** @var array<int, string> $text token => new text */
        $text = [];
        $backtick = false;
        for ($index = $n - 1; $index > 0; --$index) {
            $k = $s->kind[$index];
            if ($k === 96 && $s->ct[$index] === 0) {
                $backtick = !$backtick;
                continue;
            }
            if ($backtick || $k !== \__McTokId::T_VARIABLE || $s->ct[$index] !== 0) {
                continue;
            }
            if (!$this->stringPart($index - 1) || $s->gapBefore[$index] !== '') {
                continue;
            }
            $distinct = $index;
            /** @var array<int, int[]> $sets first index => member token indexes (in order) */
            $sets = [$distinct => [$index]];
            /** @var array<int, int> $last */
            $last = [$distinct => $index];
            $next = $index + 1;
            $squares = 0;
            while ($next < $n && !$this->stringPart($next)) {
                $nk = $s->kind[$next];
                if ($nk === \__McTokId::T_CURLY_OPEN) {
                    $close = -1;
                    for ($j = $next + 1; $j < $n; $j++) {
                        if ($s->ct[$j] === CT::CURLY_CLOSE) {
                            $close = $j;
                            break;
                        }
                    }
                    if ($close < 0) {
                        break;
                    }
                    $next = $close;
                } elseif ($nk === \__McTokId::T_VARIABLE && $squares !== 1) {
                    $distinct = $next;
                    $sets[$distinct] = [$next];
                    $last[$distinct] = $next;
                } else {
                    $sets[$distinct][] = $next;
                    $last[$distinct] = $next;
                    if (($nk === 91 || $nk === 93) && $s->ct[$next] === 0) {
                        ++$squares;
                    }
                }
                ++$next;
            }
            \krsort($sets, \SORT_NUMERIC);
            foreach ($sets as $first => $members) {
                if (\count($members) === 1) {
                    $text[$first] = '{' . $s->text($first) . '}';
                    continue;
                }
                foreach ($members as $m) {
                    $mk = $s->kind[$m];
                    if ($mk === \__McTokId::T_STRING && $m + 1 < $n && $s->kind[$m + 1] === 93 && $s->ct[$m + 1] === 0) {
                        $text[$m] = "'" . $s->text($m) . "'";
                    }
                }
                $l = $last[$first];
                $text[$l] = ($text[$l] ?? $s->text($l)) . '}';
                $text[$first] = '{' . ($text[$first] ?? $s->text($first));
            }
        }
        foreach ($text as $j => $t) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, $t);
        }
    }
}
