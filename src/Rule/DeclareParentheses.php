<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\DeclareParenthesesFixer (priority 0). `declare ( strict_types=1 )` -> `declare(strict_types=1)`:
 * the whitespace token after `declare`, after its `(` and before its `)` goes.
 */
final class DeclareParentheses extends GapRule
{
    public function name(): string
    {
        return 'declare_parentheses';
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
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_DECLARE) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = $n - 1; $i >= 0; $i--) {
            if ($s->kind[$i] !== \__McTokId::T_DECLARE || $s->ct[$i] !== 0) {
                continue;
            }
            $s->setGap($i + 1, Trivia::removeTrailing($s->gapBefore[$i + 1]));
            $o = $i + 1;
            while ($o < $n && !($s->kind[$o] === 40 && $s->ct[$o] === 0)) {
                $o++;
            }
            if ($o >= $n) {
                continue;
            }
            $s->setGap($o + 1, Trivia::removeTrailing($s->gapBefore[$o + 1]));
            $c = $s->pair[$o];
            $s->setGap($c, Trivia::removeLeading($s->gapBefore[$c]));
        }
    }
}
