<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\CompactNullableTypeDeclarationFixer (priority 0). The whitespace token after a type `?`
 * goes when the token behind it is a type name (a comment in between makes the comment the token at +2, which never matches).
 */
final class CompactNullableTypeDeclaration extends GapRule
{
    public function name(): string
    {
        return 'compact_nullable_type_declaration';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [63];
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
        if ($gap === '' || $i === 0 || $i >= $s->n || $s->ct[$i - 1] !== CT::NULLABLE_TYPE || Gap::hasComment($gap)) {
            return $gap;
        }
        $c = $s->ct[$i];
        $k = $s->kind[$i];
        if ($c === CT::ARRAY_TYPEHINT || ($c === 0 && ($k === \__McTokId::T_CALLABLE || $k === \__McTokId::T_NS_SEPARATOR
            || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_STRING))) {
            return '';
        }
        return $gap;
    }
}
