<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Operator\NoUselessNullsafeOperatorFixer (priority 0). `$this?->a` -> `$this->a` (any case of `$this`). The kind changes with the text. */
final class NoUselessNullsafeOperator extends GapRule
{
    public function name(): string
    {
        return 'no_useless_nullsafe_operator';
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
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 1; $i > 0; $i--) {
            if ($s->kind[$i] !== \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $s->ct[$i] !== 0) {
                continue;
            }
            $p = $i - 1;
            if ($s->kind[$p] === \__McTokId::T_VARIABLE && $s->ct[$p] === 0 && $s->end[$p] - $s->start[$p] === 5 && \strtolower($s->text($p)) === '$this') {
                $s->setText($i, '->');
                $s->setKind($i, \__McTokId::T_OBJECT_OPERATOR);
            }
        }
    }
}
