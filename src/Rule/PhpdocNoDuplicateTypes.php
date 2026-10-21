<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\Doc\TypeExpression;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocNoDuplicateTypesFixer (priority 0): `@param int|int $a` -> `int`, in every typed tag and the parameter types of `@method`. */
final class PhpdocNoDuplicateTypes extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_no_duplicate_types';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @param string[] $p */
    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        // duplicates live in a composite type: a union or an intersection
        if (\strpbrk($doc, '|&') === false || !\str_contains($doc, '@')) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $as = $d->getAnnotationsOfType(Annotation::TAGS_WITH_TYPES);
        if ($as === []) {
            return $doc;
        }
        foreach ($as as $a) {
            $te = $a->getTypeExpression();
            if ($te !== null) {
                $a->setTypes($te->removeDuplicateTypes()->getTypes());
            }
            $line = $d->getLine($a->start);
            if ($line === null) {
                continue;
            }
            $line->set(Preg::replaceCallback('/\*\h*@method\h+' . TypeExpression::REGEX_TYPES . '\h+\K(?&callable)/', static function (array $m): string {
                $te = new TypeExpression((string) $m[0], null);
                return \implode('|', $te->removeDuplicateTypes()->getTypes());
            }, $line->get()));
        }
        return $d->getContent();
    }
}
