<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\EmptyLoopBodyFixer (priority 39). style=braces: `while (f());` -> `while (f()){}` (the `;` becomes `{}`;
 * a `while` that ends a do-while is left); style=semicolon: `{}` (only whitespace inside) behind a `for` / `foreach` / `while` header becomes `;`.
 */
final class EmptyLoopBody extends EditRule
{
    public function __construct(private bool $braces)
    {
    }

    public function name(): string
    {
        return 'empty_loop_body';
    }

    public function priority(): int
    {
        return 39;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_WHILE) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = $n - 1; $i > 0; $i--) {
            $k = $s->kind[$i];
            if (($k !== \__McTokId::T_FOR && $k !== \__McTokId::T_FOREACH && $k !== \__McTokId::T_WHILE) || $s->ct[$i] !== 0) {
                continue;
            }
            $o = $i + 1;
            while ($o < $n && !($s->kind[$o] === 40 && $s->ct[$o] === 0)) {
                $o++;
            }
            $e = $o < $n ? $s->pair[$o] : -1;
            if ($e < 0 || $e + 1 >= $n) {
                continue;
            }
            $x = $e + 1;
            if ($this->braces) {
                if ($k === \__McTokId::T_WHILE && self::doWhile($s, $i)) {
                    continue;
                }
                if ($s->kind[$x] === 59 && $s->ct[$x] === 0) {
                    if ($k === \__McTokId::T_WHILE && $i > 0 && $s->kind[$i - 1] !== 123 && $s->kind[$s->structure()->stmtStart[$i - 1]] === \__McTokId::T_DO) {
                        $s->abort = true; // `do stmt; while (x){}` does not lint: upstream's lint step leaves the file as it is
                        return;
                    }
                    $s->edit(Source::tokAt($x), Source::tokAt($x) + 1, '{}');
                }
                continue;
            }
            if ($s->kind[$x] !== 123 || $s->ct[$x] !== 0 || $x + 1 >= $n) {
                continue;
            }
            $g = $s->gapBefore[$x + 1];
            if ($s->kind[$x + 1] !== 125 || $s->ct[$x + 1] !== 0 || ($g !== '' && Gap::hasComment($g))) {
                continue;
            }
            $s->edit(Source::tokAt($x), Source::tokAt($x) + 1, ';');
            $s->edit(Source::tokAt($x + 1), Source::tokAt($x + 1) + 1, '');
        }
    }

    /** TokensAnalyzer::isWhilePartOfDoWhile */
    private static function doWhile(Source $s, int $i): bool
    {
        $e = $i - 1;
        if ($e < 0 || $s->kind[$e] !== 125 || $s->ct[$e] !== 0) {
            return false;
        }
        $o = $s->pair[$e];
        return $o > 0 && $s->kind[$o - 1] === \__McTokId::T_DO;
    }
}
