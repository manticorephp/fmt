<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\NoMixedEchoPrintFixer (priority -10). use=echo: a statement-leading `print` (behind `;`, `{`, `}`, `)`, the open tag,
 * `else`) becomes `echo`; use=print: an `echo` whose arguments (up to `;` / `?>`, blocks jumped) have no comma becomes `print`. The kind changes with the text.
 */
final class NoMixedEchoPrint extends GapRule
{
    public function __construct(private bool $toPrint) {}

    public function name(): string
    {
        return 'no_mixed_echo_print';
    }

    public function priority(): int
    {
        return -10;
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
        $want = $this->toPrint ? \__McTokId::T_ECHO : \__McTokId::T_PRINT;
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === $want) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $want = $this->toPrint ? \__McTokId::T_ECHO : \__McTokId::T_PRINT;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] !== $want || $s->ct[$i] !== 0) {
                continue;
            }
            if ($this->toPrint) {
                $end = $i + 1;
                while ($end < $n && !($s->kind[$end] === 59 || $s->kind[$end] === \__McTokId::T_CLOSE_TAG)) {
                    $end++;
                }
                $ok = true;
                for ($j = $i + 1; $j < $end; $j++) {
                    if ($s->kind[$j] === 40 && $s->ct[$j] === 0 || $s->ct[$j] === CT::ARRAY_BRACKET_OPEN) {
                        $j = $s->pair[$j];
                    }
                    if ($j < $n && $s->kind[$j] === 44 && $s->ct[$j] === 0) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $s->setText($i, 'print');
                    $s->setKind($i, \__McTokId::T_PRINT);
                }
                continue;
            }
            if ($i === 0) {
                continue;
            }
            $p = $i - 1;
            $k = $s->kind[$p];
            if ($k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_ELSE || ($s->ct[$p] === 0 && ($k === 59 || $k === 123 || $k === 125 || $k === 41))) {
                $s->setText($i, 'echo');
                $s->setKind($i, \__McTokId::T_ECHO);
            }
        }
    }
}
