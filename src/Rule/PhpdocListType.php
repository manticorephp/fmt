<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocTypesRule;
use Fmt\Preg;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocListTypeFixer (priority 1, risky): `array<T>` -> `list<T>` (a key-less array type) in phpdoc types. */
final class PhpdocListType extends DocTypesRule
{
    public function name(): string
    {
        return 'phpdoc_list_type';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(string $doc): bool
    {
        return \stripos($doc, 'array<') !== false;
    }

    protected function normalize(string $type): string
    {
        return Preg::replace('/\barray(?=<(?:[^,<]|<[^>]+>)+(>|{|\())/i', 'list', $type);
    }
}
