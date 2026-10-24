<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\StringImplicitBackslashesFixer (priority 15). Backslashes that do not start an escape sequence of their string
 * kind (single-quoted, double-quoted, heredoc) are escaped (`escape`) or un-escaped (`unescape`) in the text tokens, per the three options. Text rule.
 */
final class StringImplicitBackslashes extends GapRule
{
    public function __construct(private string $single, private string $double, private string $heredoc) {}

    public function name(): string
    {
        return 'string_implicit_backslashes';
    }

    public function priority(): int
    {
        return 15;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $s->kind[$i] === \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $singleQuotedReservedRegex = '[\'\\\]';
        $doubleQuotedReservedRegex = '(?:[efnrtv$"\\\0-7]|x[0-9A-Fa-f]|u{|$)';
        $heredocSyntaxReservedRegex = '(?:[efnrtv$\\\0-7]|x[0-9A-Fa-f]|u{|$)';
        $doubleQuoteOpened = false;
        $n = $s->n;
        for ($index = 0; $index < $n; $index++) {
            $k = $s->kind[$index];
            if ($k === 34 && $s->ct[$index] === 0) {
                $doubleQuoteOpened = !$doubleQuoteOpened;
            }
            if ($k !== \__McTokId::T_ENCAPSED_AND_WHITESPACE && $k !== \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $content = $s->text($index);
            if (!\str_contains($content, '\\')) {
                continue;
            }
            $isEncapsed = $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE;
            // nowdoc syntax
            if ($isEncapsed && $index > 0 && \substr(\rtrim($s->text($index - 1)), -1) === '\'') {
                continue;
            }
            $firstTwo = \strtolower(\substr($content, 0, 2));
            $isSingle = !$isEncapsed && ($content[0] === '\'' || $firstTwo === 'b\'');
            $isDouble = (!$isEncapsed && ($content[0] === '"' || $firstTwo === 'b"')) || ($isEncapsed && $doubleQuoteOpened);
            $mode = $isSingle ? $this->single : ($isDouble ? $this->double : $this->heredoc);
            if ($mode === 'ignore') {
                continue;
            }
            $escape = $mode === 'escape';
            $reserved = $isSingle ? $singleQuotedReservedRegex : ($isDouble ? $doubleQuotedReservedRegex : $heredocSyntaxReservedRegex);
            if ($escape) {
                $regex = '/(?<!\\\)\\\((?:\\\\\\\)*)(?!' . $reserved . ')/';
                $new = Preg::replace($regex, '\\\\\\\$1', $content);
            } else {
                $regex = '/(?<!\\\)\\\\\\\((?:\\\\\\\)*)(?!' . $reserved . ')/';
                $new = Preg::replace($regex, '\\\$1', $content);
            }
            if ($new !== $content) {
                $s->setText($index, $new);
            }
        }
    }
}
