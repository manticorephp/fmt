<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\NoLeadingImportSlashFixer (priority -20). The `\` that starts an import (`use \A`, `use function \f`)
 * goes; when no trivia separates it from the token before (`use\A`), it becomes a single space instead. Every T_USE that is no trait /
 * closure `use` is an import (TokensAnalyzer::getImportUseIndexes, all namespaces).
 */
final class NoLeadingImportSlash extends EditRule
{
    public function name(): string
    {
        return 'no_leading_import_slash';
    }

    public function priority(): int
    {
        return -20;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 0; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_USE || $s->ct[$i] !== 0) {
                continue;
            }
            $j = $i + 1;
            if ($s->ct[$j] === CT::FUNCTION_IMPORT || $s->ct[$j] === CT::CONST_IMPORT) {
                $j++;
            }
            if ($j < $n && $s->kind[$j] === \__McTokId::T_NS_SEPARATOR) {
                $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, $s->gapBefore[$j] === '' ? ' ' : '');
            }
        }
    }
}
