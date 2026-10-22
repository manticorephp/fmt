<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\FlatNamespaces;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\MultilineStringToHeredocFixer (priority 16). A quoted string spanning lines becomes a heredoc (single-quoted: nowdoc)
 * with marker EOD (`_` appended while a body line starts with it); the `\"` and `\\` escapes of a double-quoted one are normalised. Whole-file FlatTokens port: the walk
 * is a live iteration, so the replaced / inserted items shift the index it continues from, as upstream's.
 */
final class MultilineStringToHeredoc extends FlatRule
{
    public function name(): string
    {
        return 'multiline_string_to_heredoc';
    }

    public function priority(): int
    {
        return 16;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if (($s->kind[$i] === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $s->kind[$i] === \__McTokId::T_ENCAPSED_AND_WHITESPACE)
                && \strpbrk($s->text($i), "\n\r") !== false) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $complexStart = -1;
        for ($index = 0; $index < $t->count(); ++$index) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($complexStart < 0) {
                if ($k === \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
                    $this->convert($t, $index, $index);
                } elseif ($k === 34 && ($t->content($index) === '"' || $t->content($index) === 'b"' || $t->content($index) === 'B"')) {
                    $complexStart = $index;
                }
            } elseif ($k === 34 && $t->content($index) === '"') {
                $this->convert($t, $complexStart, $index);
                $complexStart = -1;
            }
        }
    }

    private function convert(FlatTokens $t, int $start, int $end): void
    {
        $marker = 'EOD';
        $constant = null;
        if (self::is($t, $start, \__McTokId::T_CONSTANT_ENCAPSED_STRING)) {
            $content = $t->content($start);
            if (\strtolower(\substr($content, 0, 1)) === 'b') {
                $content = \substr($content, 1);
            }
            $single = \str_starts_with($content, '\'');
            $content = \substr($content, 1, -1);
            if ($single) {
                $content = Preg::replace('~\\\([\\\\\'])~', '$1', $content);
            } else {
                $content = Preg::replace('~(\\\\\\\)|\\\(")~', '$1$2', $content);
            }
            $constant = $content . "\n";
        } else {
            $content = FlatNamespaces::partial($t, $start + 1, $end - 1);
            $single = false;
        }
        if (!\str_contains($content, "\n") && !\str_contains($content, "\r")) {
            return;
        }
        while (Preg::match('~(^|[\r\n])\s*' . \preg_quote($marker, '~') . '(?!\w)~', $content)) {
            $marker .= '_';
        }
        $quoting = $single ? '\'' : '';
        $startToken = FlatTokens::code(\__McTokId::T_START_HEREDOC, '<<<' . $quoting . $marker . $quoting . "\n");
        $endToken = FlatTokens::code(\__McTokId::T_END_HEREDOC, $marker);
        if ($constant !== null) {
            self::overrideRange($t, $start, $end, [$startToken, FlatTokens::code(\__McTokId::T_ENCAPSED_AND_WHITESPACE, $constant), $endToken]);
            return;
        }
        for ($i = $start + 1; $i < $end; ++$i) {
            if (self::is($t, $i, \__McTokId::T_ENCAPSED_AND_WHITESPACE)) {
                self::setCode($t, $i, \__McTokId::T_ENCAPSED_AND_WHITESPACE, Preg::replace('~(\\\\\\\)|\\\(")~', '$1$2', $t->content($i)));
            }
        }
        self::setCode($t, $start, \__McTokId::T_START_HEREDOC, $startToken[1]);
        self::setCode($t, $end, \__McTokId::T_END_HEREDOC, $endToken[1]);
        if (self::is($t, $end - 1, \__McTokId::T_ENCAPSED_AND_WHITESPACE)) {
            self::setCode($t, $end - 1, \__McTokId::T_ENCAPSED_AND_WHITESPACE, $t->content($end - 1) . "\n");
        } else {
            $t->insertAt($end, [FlatTokens::code(\__McTokId::T_ENCAPSED_AND_WHITESPACE, "\n")]);
        }
    }
}
