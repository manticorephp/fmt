<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocNoEmptyReturnFixer (priority 4): a `@return null` / `@return void` annotation goes; an emptied docblock goes with it. */
final class PhpdocNoEmptyReturn extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_no_empty_return';
    }

    public function priority(): int
    {
        return 4;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '@return')) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $as = $d->getAnnotationsOfType(['return']);
        if ($as === []) {
            return $doc;
        }
        foreach ($as as $a) {
            $types = $a->getNormalizedTypes();
            if (\count($types) === 1 && ($types[0] === 'null' || $types[0] === 'void')) {
                $a->remove();
            }
        }
        return $d->getContent();
    }
}
