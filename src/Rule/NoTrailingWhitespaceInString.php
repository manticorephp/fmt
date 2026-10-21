<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\NoTrailingWhitespaceInStringFixer (priority 0, risky). Horizontal whitespace before a line break inside a string literal /
 * interpolated text / inline HTML is removed (the last inline HTML also before the end of the file); the line break that opens inline HTML moves into the close tag.
 * Whole-file FlatTokens port, last token first.
 */
final class NoTrailingWhitespaceInString extends FlatRule
{
    public function name(): string
    {
        return 'no_trailing_whitespace_in_string';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $k === \__McTokId::T_INLINE_HTML) {
                $x = $s->text($i);
                if (($i === $s->n - 1 || \strpbrk($x, "\r\n\x0b\x0c\x85\xC2\xE2") !== false) && Trivia::pmatch('/\\h(?:\\R|$)/', $x)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $last = true;
        for ($q = $t->count() - 1; $q >= 0; $q--, $last = false) {
            $k = $t->k[$q];
            if ($t->i[$q] === -1 || !($k === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $k === \__McTokId::T_INLINE_HTML)) {
                continue;
            }
            $html = $k === \__McTokId::T_INLINE_HTML;
            $re = $html && $last ? '/\h+(?=\R|$)/' : '/\h+(?=\R)/';
            $old = $t->content($q);
            $content = Preg::replace($re, '', $old);
            if ($old === $content) {
                continue;
            }
            if (!$html || $q === 0) {
                $this->update($t, $q, $content);
                continue;
            }
            $prev = $q - 1;
            if ($t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_CLOSE_TAG && $t->content($prev) === '?>' && Preg::match('/^\R/', $content, $m)) {
                self::setCode($t, $prev, \__McTokId::T_CLOSE_TAG, $t->content($prev) . $m[0]);
                $content = \substr($content, \strlen($m[0]));
            }
            $this->update($t, $q, $content);
        }
    }

    private function update(FlatTokens $t, int $q, string $content): void
    {
        if ($content === '') {
            $t->clearAt($q);
            return;
        }
        self::setCode($t, $q, $t->k[$q], $content);
    }
}
