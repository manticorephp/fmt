<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\Doc\TypeExpression;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocTypesOrderFixer (priority 0): union / intersection members sorted, `null` first or last. */
final class PhpdocTypesOrder extends DocRule
{
    /** @param string $nullAdjustment always_first | always_last | none */
    public function __construct(private bool $alpha, private string $nullAdjustment, private bool $caseSensitive)
    {
    }

    public function name(): string
    {
        return 'phpdoc_types_order';
    }

    public function priority(): int
    {
        return 0;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (\strpbrk($doc, '|&') === false || !\str_contains($doc, '@')) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $as = $d->getAnnotationsOfType(Annotation::TAGS_WITH_TYPES);
        if ($as === []) {
            return $doc;
        }
        foreach ($as as $a) {
            $t = $a->getTypeExpression();
            if ($t !== null) {
                $a->setTypes($this->sortTypes($t));
            }
            $line = $d->lines[$a->start];
            $line->set(Preg::replaceCallback('/\*\h*@method\h+' . TypeExpression::REGEX_TYPES . '\h+\K(?&callable)/',
                fn (array $m): string => \implode('|', $this->sortTypes(new TypeExpression((string) $m[0]))), $line->get()));
        }
        return $d->getContent();
    }

    /** @return string[] */
    private function sortTypes(TypeExpression $t): array
    {
        $na = $this->nullAdjustment;
        $alpha = $this->alpha;
        $cs = $this->caseSensitive;
        return $t->sortTypes(static function (TypeExpression $x, TypeExpression $y) use ($na, $alpha, $cs): int {
            $a = Preg::replace('/^\(*\??\\\\?/', '', $x->toString());
            $b = Preg::replace('/^\(*\??\\\\?/', '', $y->toString());
            $la = \strtolower($a);
            $lb = \strtolower($b);
            if ($na !== 'none') {
                if ($la === 'null' && $lb !== 'null') {
                    return $na === 'always_last' ? 1 : -1;
                }
                if ($la !== 'null' && $lb === 'null') {
                    return $na === 'always_last' ? -1 : 1;
                }
            }
            if ($alpha) {
                return $cs ? $a <=> $b : \strcasecmp($a, $b);
            }
            return 0;
        })->getTypes();
    }
}
