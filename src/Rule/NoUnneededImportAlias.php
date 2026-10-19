<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\NoUnneededImportAliasFixer (priority 1). `use A\B as B;` loses ` as B`: the `as` and the alias go,
 * the whitespace around them stays (merged tokens, same text; the trailing blank before `;` is another rule's job).
 */
final class NoUnneededImportAlias extends EditRule
{
    public function name(): string
    {
        return 'no_unneeded_import_alias';
    }

    public function priority(): int
    {
        return 1;
    }

    public function prepare(Source $s): bool
    {
        $as = false;
        $use = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $as = $as || $k === \__McTokId::T_AS;
            $use = $use || $k === \__McTokId::T_USE;
        }
        return $as && $use;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 2; $i > 0; $i--) {
            if ($s->kind[$i] !== \__McTokId::T_AS || $s->ct[$i] !== 0 || $s->kind[$i + 1] !== \__McTokId::T_STRING || $s->ct[$i + 1] !== 0
                || $s->kind[$i - 1] !== \__McTokId::T_STRING || $s->ct[$i - 1] !== 0) {
                continue;
            }
            if ($s->text($i - 1) !== $s->text($i + 1)) {
                continue;
            }
            $j = $i - 1;
            do {
                $j--;
                if ($j < 0) {
                    break;
                }
                $k = $s->kind[$j];
                $c = $s->ct[$j];
            } while ($c === 0 && ($k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STRING || $k === \__McTokId::T_AS || $k === 44));
            if ($j < 0) {
                continue;
            }
            if ($s->ct[$j] === CT::FUNCTION_IMPORT || $s->ct[$j] === CT::CONST_IMPORT) {
                $j--;
            }
            if ($j < 0 || !(($s->kind[$j] === \__McTokId::T_USE && $s->ct[$j] === 0) || $s->ct[$j] === CT::GROUP_IMPORT_BRACE_OPEN)) {
                continue;
            }
            $s->edit(Source::tokAt($i), Source::tokAt($i + 1) + 1, $s->gapBefore[$i + 1]);
        }
    }
}
