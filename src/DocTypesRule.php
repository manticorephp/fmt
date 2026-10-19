<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\Doc\TypeExpression;

/** Port of PhpCsFixer\AbstractPhpdocTypesFixer: every non-composite type of every typed annotation goes through normalize(). */
abstract class DocTypesRule extends DocRule
{
    abstract protected function normalize(string $type): string;

    /** the cheap test: false when no type of this docblock can change */
    abstract protected function candidate(string $doc): bool;

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '@') || !$this->candidate($doc)) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $as = $d->getAnnotationsOfType(Annotation::TAGS_WITH_TYPES);
        if ($as === []) {
            return $doc;
        }
        foreach ($as as $a) {
            $t = $a->getTypeExpression();
            if ($t === null) {
                continue;
            }
            $new = $t->mapTypes(function (TypeExpression $type): TypeExpression {
                if (!$type->isCompositeType()) {
                    return new TypeExpression($this->normalize($type->toString()));
                }
                return $type;
            });
            $a->setTypes([$new->toString()]);
        }
        return $d->getContent();
    }
}
