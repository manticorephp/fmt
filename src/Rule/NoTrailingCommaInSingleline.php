<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\NoTrailingCommaInSinglelineFixer (priority 0). `f(1, 2,)`, `[1,]`, `[$a,] = $b`, `use A\{B,}` on one line (no
 * line break anywhere from the opener to the closer, tokens included) lose their trailing comma(s); the whitespace behind the token before the
 * commas goes with them. The merged gap = the gaps of the removed commas and the closer, minus its first whitespace token.
 */
final class NoTrailingCommaInSingleline extends EditRule
{
    public function __construct(private bool $arguments, private bool $array, private bool $destructuring, private bool $groupImport)
    {
    }

    public function name(): string
    {
        return 'no_trailing_comma_in_singleline';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === 44 && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($e = $n - 1; $e > 0; $e--) {
            $c = $s->ct[$e];
            if ($c === 0 ? $s->kind[$e] !== 41 : ($c !== CT::ARRAY_BRACKET_CLOSE && $c !== CT::DESTRUCTURING_BRACKET_CLOSE && $c !== CT::GROUP_IMPORT_BRACE_CLOSE)) {
                continue;
            }
            $m = $e - 1;
            if ($s->kind[$m] !== 44 || $s->ct[$m] !== 0) {
                continue;
            }
            $o = $s->pair[$e];
            if ($o < 0 || self::multiline($s, $o, $e) || !$this->shouldClear($s, $o)) {
                continue;
            }
            $first = $m;
            while ($first > 0 && $s->kind[$first - 1] === 44 && $s->ct[$first - 1] === 0) {
                $first--;
            }
            $g = '';
            for ($k = $first; $k < $m; $k++) {
                $g .= $s->gapBefore[$k];
            }
            $g .= $s->gapBefore[$m] . $s->gapBefore[$e];
            // gaps of the commas between are interleaved with the comma tokens: g = gap(first) gap(first+1) ... gap(m) gap(e)
            $s->edit(Source::gapAt($first), Source::gapAt($e) + 1, Trivia::removeTrailing($g));
            $e = $first;
        }
    }

    private static function multiline(Source $s, int $o, int $e): bool
    {
        for ($k = $o + 1; $k <= $e; $k++) {
            if (($s->gapFlags[$k] & 1) !== 0) { // Gap::G_NL
                return true;
            }
        }
        for ($k = $o; $k <= $e; $k++) {
            $kd = $s->kind[$k];
            if ($kd === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $kd === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $kd === \__McTokId::T_INLINE_HTML
                || $kd === \__McTokId::T_START_HEREDOC || $kd === \__McTokId::T_END_HEREDOC || $kd === \__McTokId::T_OPEN_TAG || $kd === \__McTokId::T_CLOSE_TAG
                || $kd === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                if (\str_contains($s->text($k), "\n")) {
                    return true;
                }
            }
        }
        return false;
    }

    private function shouldClear(Source $s, int $o): bool
    {
        $c = $s->ct[$o];
        if ($c === CT::ARRAY_BRACKET_OPEN) {
            return $this->array;
        }
        if ($c === CT::DESTRUCTURING_BRACKET_OPEN) {
            return $this->destructuring;
        }
        if ($c === CT::GROUP_IMPORT_BRACE_OPEN) {
            return $this->groupImport;
        }
        $b = $o - 1;
        $k = $s->kind[$b];
        $bc = $s->ct[$b];
        if ($bc === 0) {
            if ($k === \__McTokId::T_ARRAY) {
                return $this->array;
            }
            if ($k === \__McTokId::T_LIST) {
                return $this->destructuring;
            }
            if ($k === \__McTokId::T_UNSET || $k === \__McTokId::T_ISSET || $k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_CLASS) {
                return $this->arguments;
            }
            if ($k === \__McTokId::T_STRING) {
                return !\Fmt\NsAnalysis::isAttribute($s, $b) && $this->arguments;
            }
            if ($k === 41 || $k === 93) {
                return $this->arguments;
            }
            return false;
        }
        return ($bc === CT::DYNAMIC_VAR_BRACE_CLOSE || $bc === CT::ARRAY_INDEX_BRACE_CLOSE) && $this->arguments;
    }
}
