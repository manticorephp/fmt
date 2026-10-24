<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer (priority 0). A multiline block (a line break in the
 * whitespace directly inside it, nested blocks skipped, not right after a heredoc end) of a configured kind gets `,` right after its last
 * element when the text from that element to the closer holds a line break. Upstream's stale `$endIndex` after the insert: when nothing
 * separates the element and the closer, the comma is followed by a space.
 */
final class TrailingCommaInMultiline extends EditRule
{
    public function __construct(
        private bool $arrays = true,
        private bool $arguments = false,
        private bool $parameters = false,
        private bool $match = false,
        private bool $destructuring = false,
        private bool $afterHeredoc = false,
    ) {}

    public function name(): string
    {
        return 'trailing_comma_in_multiline';
    }

    public function priority(): int
    {
        return 0;
    }

    private static function eff(Source $s, int $i): int
    {
        return $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = $n - 1; $i >= 0; $i--) {
            $c = $s->ct[$i];
            if ($c === CT::DESTRUCTURING_BRACKET_OPEN) {
                if ($this->destructuring) {
                    $this->fixBlock($s, $i);
                }
                continue;
            }
            if ($c === CT::ARRAY_BRACKET_OPEN) {
                if ($this->arrays) {
                    $this->fixBlock($s, $i);
                }
                continue;
            }
            if ($c !== 0 || $s->kind[$i] !== 40 || $i === 0) {
                continue;
            }
            $p = $i - 1;
            $pk = self::eff($s, $p);
            if ($pk === \__McTokId::T_ARRAY) {
                if ($this->arrays) {
                    $this->fixBlock($s, $i);
                }
                continue;
            }
            if ($pk === \__McTokId::T_LIST) {
                if ($this->destructuring || $this->arguments) {
                    $this->fixBlock($s, $i);
                }
                continue;
            }
            if ($this->match && $pk === \__McTokId::T_MATCH) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($s->kind[$j] === 123 && $s->ct[$j] === 0) {
                        $this->fixBlock($s, $j);
                        break;
                    }
                }
                continue;
            }
            $ppk = $p > 0 ? self::eff($s, $p - 1) : -1;
            if ($this->arguments && ($pk === 93 || $pk === \__McTokId::T_CLASS || $pk === \__McTokId::T_STRING || $pk === \__McTokId::T_VARIABLE
                || $pk === \__McTokId::T_STATIC || $pk === \__McTokId::T_ISSET || $pk === \__McTokId::T_UNSET || $pk === \__McTokId::T_LIST)
                && $ppk !== \__McTokId::T_FUNCTION) {
                $this->fixBlock($s, $i);
                continue;
            }
            if ($this->parameters && (($pk === \__McTokId::T_STRING && $ppk === \__McTokId::T_FUNCTION) || $pk === \__McTokId::T_FN || $pk === \__McTokId::T_FUNCTION)) {
                $this->fixBlock($s, $i);
            }
        }
    }

    /** a whitespace piece with "\n" in gap $g (the first piece does not count right after a heredoc end) */
    private static function gapBreaks(Source $s, int $g): bool
    {
        $gap = $s->gapBefore[$g];
        if (\strpos($gap, "\n") === false) {
            return false;
        }
        $p = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
        $afterHeredoc = $s->kind[$g - 1] === \__McTokId::T_END_HEREDOC;
        foreach ($p as $k => $x) {
            if (($k > 0 || !$afterHeredoc) && Gap::isSpace($x) && \strpos($x, "\n") !== false) {
                return true;
            }
        }
        return false;
    }

    private function fixBlock(Source $s, int $start): void
    {
        $end = $s->pair[$start];
        if ($end <= $start) {
            return;
        }
        $multi = false;
        for ($j = $start + 1; $j <= $end; $j++) {
            if (self::gapBreaks($s, $j)) {
                $multi = true;
                break;
            }
            if ($j < $end && $s->pair[$j] > $j && $s->kind[$j] !== \__McTokId::T_CURLY_OPEN) {
                $j = $s->pair[$j]; // nested block: the gaps inside it do not count, the one after its closer does
            }
        }
        if (!$multi) {
            return;
        }
        $be = $end - 1;
        if (\strpos($s->text($be), "\n") === false && \strpos($s->gapBefore[$end], "\n") === false && \strpos($s->text($end), "\n") === false) {
            return;
        }
        $bk = self::eff($s, $be);
        if ($be === $start || $bk === 44 || $bk === CT::FIRST_CLASS_CALLABLE || (!$this->afterHeredoc && $bk === \__McTokId::T_END_HEREDOC)) {
            return;
        }
        $s->edit(Source::gapAt($end), Source::gapAt($end), $s->gapBefore[$end] === '' ? ', ' : ',');
    }
}
