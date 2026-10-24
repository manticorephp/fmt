<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer (priority -17). At a return-type colon: the whitespace
 * token before it goes (none) or becomes one space (one) unless a comment precedes it; behind it the whitespace token is REPLACED
 * by one space (a line break included) or a space is inserted.
 */
final class ReturnTypeDeclaration extends GapRule
{
    public function __construct(private bool $oneBefore = false) {}

    public function name(): string
    {
        return 'return_type_declaration';
    }

    public function priority(): int
    {
        return -17;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [58];
    }

    public function beforeKinds(): array
    {
        return [58];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i < $s->n && $s->kind[$i] === 58 && $s->ct[$i] === CT::TYPE_COLON) {
            if ($gap === '') {
                if ($this->oneBefore) {
                    $gap = ' ';
                }
            } elseif (!Gap::hasComment($gap)) {
                $gap = $this->oneBefore ? ' ' : '';
            } elseif ($this->oneBefore && Gap::trail($gap) === '') {
                $gap .= ' ';
            }
        }
        if ($i > 0 && $s->kind[$i - 1] === 58 && $s->ct[$i - 1] === CT::TYPE_COLON) {
            $gap = Gap::withLead($gap, ' ');
        }
        return $gap;
    }
}
