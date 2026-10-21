<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocTypesRule;
use Fmt\Preg;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocArrayTypeFixer (priority 2, risky): `T[]` -> `array<T>` (`T[][]` -> `array<array<T>>`, a parenthesised type loses its parentheses) in phpdoc types. */
final class PhpdocArrayType extends DocTypesRule
{
    public function name(): string
    {
        return 'phpdoc_array_type';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(string $doc): bool
    {
        return \str_contains($doc, '[');
    }

    protected function normalize(string $type): string
    {
        if (Preg::match('/^\??\s*[\'"]/', $type)) {
            return $type;
        }
        $prefix = '';
        if (\str_starts_with($type, '?')) {
            $prefix = '?';
            $type = \substr($type, 1);
        }
        return $prefix . Preg::replaceCallback('/^(.+?)((?:\h*\[\h*\])+)$/', static function (array $m): string {
            $inner = $m[1];
            $level = \substr_count($m[2], '[');
            if (\str_starts_with($inner, '(') && \str_ends_with($inner, ')')) {
                $inner = \substr($inner, 1, -1);
            }
            return \str_repeat('array<', $level) . $inner . \str_repeat('>', $level);
        }, $type);
    }
}
