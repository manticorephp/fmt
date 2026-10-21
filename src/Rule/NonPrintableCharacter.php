<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\NonPrintableCharacterFixer (priority 0, risky). Zero-width space / word joiner are removed, figure space / narrow no-break space / no-break space become a
 * space (or, in string literals and text parts with use_escape_sequences_in_strings, `\u{200b}` style escapes; a nowdoc / single-quoted string is turned into a heredoc / double-quoted one for
 * that). Names with a replaced space stay as they are, a `/* *\/` comment is only changed when it still ends with its closer. Whole-file FlatTokens port: comments are items of the flat list.
 */
final class NonPrintableCharacter extends FlatRule
{
    public function __construct(private bool $escape)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'non_printable_character';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        return \preg_match('/\xE2\x80\x8B|\xE2\x80\x87|\xE2\x80\xAF|\xE2\x81\xA0|\xC2\xA0/', $s->code) === 1;
    }

    /** @return array<string, string> */
    private function replacements(): array
    {
        return ["\xE2\x80\x8B" => '', "\xE2\x80\x87" => ' ', "\xE2\x80\xAF" => ' ', "\xE2\x81\xA0" => '', "\xC2\xA0" => ' '];
    }

    /** @return array<string, string> */
    private function escapes(): array
    {
        return ["\xE2\x80\x8B" => '\u{200b}', "\xE2\x80\x87" => '\u{2007}', "\xE2\x80\xAF" => '\u{202f}', "\xE2\x81\xA0" => '\u{2060}', "\xC2\xA0" => '\u{a0}'];
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $replacements = $this->replacements();
        $escapes = $this->escapes();
        $m = $t->count();
        for ($index = 0; $index < $m; $index++) {
            $k = $t->k[$index];
            $isComment = $k === FlatTokens::COMMENT;
            $code = $t->i[$index] !== -1;
            if (!$isComment && !($code && ($k === \__McTokId::T_STRING_VARNAME || $k === \__McTokId::T_INLINE_HTML || $k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_CONSTANT_ENCAPSED_STRING
                || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE))) {
                continue;
            }
            $content = $code ? $t->content($index) : $t->c[$index];
            if ($this->escape && $code && ($k === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE)) {
                $has = false;
                foreach ($escapes as $ch => $_) {
                    if (\str_contains($content, $ch)) {
                        $has = true;
                        break;
                    }
                }
                if (!$has) {
                    continue;
                }
                $prev = $index - 1;
                $changed = false;
                $swap = false;
                if ($prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_START_HEREDOC) {
                    $pc = $t->content($prev);
                    if (\str_contains($pc, "'")) {
                        self::setCode($t, $prev, \__McTokId::T_START_HEREDOC, \str_replace("'", '', $pc));
                        $changed = true;
                    }
                } elseif (\str_starts_with($content, "'")) {
                    $changed = true;
                    $swap = true;
                }
                if ($swap) {
                    $content = \str_replace("\\'", "'", $content);
                }
                if ($changed) {
                    $content = Preg::replace('/(\\\\{1,2})/', '\\\\\\\\', $content);
                    $content = \str_replace('$', '\$', $content);
                }
                if ($swap) {
                    $content = \str_replace('"', '\"', $content);
                    $content = Preg::replace('/^\'(.*)\'$/s', '"$1"', $content);
                }
                self::setCode($t, $index, $k, \strtr($content, $escapes));
                continue;
            }
            $new = \strtr($content, $replacements);
            if ($code && ($k === \__McTokId::T_STRING_VARNAME || $k === \__McTokId::T_VARIABLE) && \str_contains($new, ' ')) {
                continue;
            }
            if ($isComment && \str_starts_with($new, '/*') && \strpos($new, '*/') !== \strlen($new) - 2) {
                continue;
            }
            if ($isComment) {
                $t->c[$index] = $new;
            } else {
                self::setCode($t, $index, $k, $new);
            }
        }
    }
}
