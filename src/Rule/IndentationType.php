<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\IndentationTypeFixer (priority 50). With a four-space indent only tabs change,
 * so a gap without a tab (Gap::G_TAB) is never touched; with a tab indent every leading run of four spaces becomes a tab.
 */
final class IndentationType extends GapRule
{
    private bool $tabs;

    public function __construct(string $indent = '    ')
    {
        $this->tabs = $indent === "\t";
    }

    public function name(): string
    {
        return 'indentation_type';
    }

    public function priority(): int
    {
        return 50;
    }

    public function needsMask(): int
    {
        return $this->tabs ? (Gap::G_TAB | Gap::G_NL | Gap::G_CR | Gap::G_COMMENT) : Gap::G_TAB;
    }

    /** a whitespace token right after a token whose text holds "\n" is indented from its start: reads token i-1 */
    public function reads(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if (!$this->tabs && \strpos($gap, "\t") === false) {
            return $gap;
        }
        if (!Gap::hasComment($gap)) {
            return $this->fixSpace($gap, $i > 0 && \str_contains($s->text($i - 1), "\n"));
        }
        $out = '';
        $prev = $i > 0 ? $s->text($i - 1) : '';
        foreach (Gap::pieces($gap) as $p) {
            $out .= Gap::isSpace($p) ? $this->fixSpace($p, \str_contains($prev, "\n")) : $this->fixComment($p);
            $prev = $p;
        }
        return $out;
    }

    /**
     * `(\R)(\h+)` → each tab of the blank run after a line break becomes four spaces; 1-3 spaces right before a tab
     * (`(?:(?<! ) {1,3})?\t`) go with it. A whitespace token that follows a token holding "\n" counts as starting a line.
     */
    private function fixSpace(string $p, bool $lineStart): string
    {
        if (\strpos($p, "\t") === false) {
            return $this->tabs ? self::tabify($p, $lineStart) : $p;
        }
        $n = \strlen($p);
        $out = '';
        $i = 0;
        $atRun = $lineStart;
        while ($i < $n) {
            $c = \ord($p[$i]);
            if ($c === 10 || $c === 13 || $c === 11 || $c === 12) {
                $out .= $p[$i];
                $i++;
                $atRun = true;
                continue;
            }
            if (!$atRun) {
                $out .= $p[$i];
                $i++;
                continue;
            }
            // a blank run after a line break: spaces before a tab
            $sp = \strspn($p, ' ', $i);
            if ($i + $sp < $n && \ord($p[$i + $sp]) === 9) {
                $out .= ($sp >= 4 ? \substr($p, $i, $sp) : '') . '    ';
                $i += $sp + 1;
                continue;
            }
            $out .= \substr($p, $i, $sp);
            $i += $sp;
            $atRun = false;
        }
        return $this->tabs ? self::tabify($out, $lineStart) : $out;
    }

    /** getExpectedIndent for a tab indent: in every blank run after a line break, each four spaces become a tab. */
    private static function tabify(string $p, bool $lineStart): string
    {
        $n = \strlen($p);
        $out = '';
        $i = 0;
        $atRun = $lineStart;
        while ($i < $n) {
            $c = \ord($p[$i]);
            if ($c === 10 || $c === 13 || $c === 11 || $c === 12) {
                $out .= $p[$i];
                $i++;
                $atRun = true;
                continue;
            }
            if (!$atRun) {
                $out .= $p[$i];
                $i++;
                continue;
            }
            $sp = \strspn($p, ' ', $i);
            $out .= \str_repeat("\t", \intdiv($sp, 4)) . \str_repeat(' ', $sp % 4);
            $i += $sp;
            $atRun = false;
        }
        return $out;
    }

    /**
     * `^(?:(?<! ) {1,3})?\t` per line (`/m`, LF only), then while that matched anywhere `^(\ +)?\t` until no line starts
     * with blanks and a tab: the first tab of a line eats up to three spaces before it, every other leading tab is four
     * spaces. Nothing changes when no line starts with up to three spaces and a tab.
     */
    private function fixComment(string $c): string
    {
        if (\strpos($c, "\t") === false) {
            return $this->tabs ? self::tabifyComment($c) : $c;
        }
        $lines = \explode("\n", $c);
        $cnt = \count($lines);
        $hit = false;
        for ($l = 1; $l < $cnt; $l++) {
            $line = $lines[$l];
            $sp = \strspn($line, ' ');
            if ($sp <= 3 && $sp < \strlen($line) && \ord($line[$sp]) === 9) {
                $lines[$l] = '    ' . \substr($line, $sp + 1);
                $hit = true;
            }
        }
        if (!$hit) {
            return $this->tabs ? self::tabifyComment(\implode("\n", $lines)) : $c;
        }
        for ($l = 1; $l < $cnt; $l++) {
            $line = $lines[$l];
            $lead = \strspn($line, " \t");
            if ($lead > 0 && \strpos(\substr($line, 0, $lead), "\t") !== false) {
                $lines[$l] = \str_replace("\t", '    ', \substr($line, 0, $lead)) . \substr($line, $lead);
            }
        }
        $c = \implode("\n", $lines);
        return $this->tabs ? self::tabifyComment($c) : $c;
    }

    /** `^(?:    )+` per line (/m): each four-space unit becomes a tab */
    private static function tabifyComment(string $c): string
    {
        $lines = \explode("\n", $c);
        $cnt = \count($lines);
        for ($l = 1; $l < $cnt; $l++) {
            $line = $lines[$l];
            $k = \intdiv(\strspn($line, ' '), 4);
            if ($k > 0) {
                $lines[$l] = \str_repeat("\t", $k) . \substr($line, $k * 4);
            }
        }
        return \implode("\n", $lines);
    }
}
