<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\HeredocToNowdocFixer (priority 0). A heredoc whose body is empty, or one text token with no backslash
 * escape other than `\\` and `\$`, becomes a nowdoc: the start marker is quoted with `'` (a `"` quote goes), `\\` -> `\` and `\$` -> `$` in the body.
 * Text rule over the start-marker and body tokens (a nowdoc lexes to the same token kinds).
 */
final class HeredocToNowdoc extends GapRule
{
    public function name(): string
    {
        return 'heredoc_to_nowdoc';
    }

    public function priority(): int
    {
        return 0;
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
            if ($s->kind[$i] === \__McTokId::T_START_HEREDOC) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_START_HEREDOC) {
                continue;
            }
            $start = $s->text($i);
            if (\str_contains($start, "'")) {
                continue;
            }
            if ($i + 1 < $n && $s->kind[$i + 1] === \__McTokId::T_END_HEREDOC) {
                $s->setText($i, self::convert($start));
                continue;
            }
            if ($i + 2 >= $n || $s->kind[$i + 1] !== \__McTokId::T_ENCAPSED_AND_WHITESPACE || $s->kind[$i + 2] !== \__McTokId::T_END_HEREDOC) {
                continue;
            }
            $content = $s->text($i + 1);
            // odd number of backslashes, not followed by dollar
            if (Preg::match('/(?<!\\\)(?:\\\{2})*\\\(?![$\\\])/', $content)) {
                continue;
            }
            $s->setText($i, self::convert($start));
            $s->setText($i + 1, \str_replace(['\\\\', '\$'], ['\\', '$'], $content));
        }
    }

    private static function convert(string $start): string
    {
        return Preg::replace('/^([Bb]?<<<)(\h*)"?([^\s"]+)"?/', '$1$2\'$3\'', $start);
    }
}
