<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocVarAnnotationCorrectOrderFixer (priority 0): `@var $x Type` -> `@var Type $x`. */
final class PhpdocVarAnnotationCorrectOrder extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_var_annotation_correct_order';
    }

    public function priority(): int
    {
        return 0;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '$') || (\stripos($doc, '@var') === false && \stripos($doc, '@type') === false)) {
            return $doc;
        }
        return Preg::replace('/(@(?:type|var)\s*)(\$\S+)(\h+)([^\$](?:[^<\s]|<[^>]*>)*)(\s|\*)/i', '$1$4$3$2$5', $doc);
    }
}
