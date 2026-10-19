<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocAnnotationWithoutDotFixer (priority 17): annotation descriptions lose their final dot. */
final class PhpdocAnnotationWithoutDot extends DocRule
{
    private const TAGS = ['throws', 'return', 'param', 'internal', 'deprecated', 'var', 'type'];

    public function name(): string
    {
        return 'phpdoc_annotation_without_dot';
    }

    public function priority(): int
    {
        return 17;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '@') || (!\str_contains($doc, '.') && !\str_contains($doc, "\xe3\x80\x82"))) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $annotations = $d->getAnnotations();
        if ($annotations === []) {
            return $doc;
        }
        foreach ($annotations as $a) {
            $tag = $a->getTag();
            if (!$tag->valid() || !\in_array($tag->getName(), self::TAGS, true)) {
                continue;
            }
            $after = $d->getLine($a->end + 1);
            if ($after !== null) {
                $t = \ltrim($after->get());
                if ($t === '' || !\str_starts_with($t, '*')) {
                    continue;
                }
            }
            $content = $a->getContent();
            if (!Preg::test('/[.。]\h*$/', $content) || Preg::test('/[.。](?!\h*$)/', $content)) {
                continue;
            }
            $end = $d->lines[$a->end];
            $end->set(Preg::replace('/(?<![.。])[.。]\h*(\H+)$/', '\1', $end->get()));
            $start = $d->lines[$a->start];
            $opt = $a->supportTypes() ? '(?:' . \preg_quote(\implode('|', $a->getTypes()), '/') . '\s+(?:\$\w+\s+)?)' : '';
            $start->set(Preg::replaceCallback('/^(\s*\*\s*@\w+\s+' . $opt . ')(\p{Lu}?(?=\p{Ll}|\p{Zs}))(.*)$/',
                static fn (array $m): string => $m[1] . \mb_strtolower((string) $m[2]) . $m[3], $start->get(), 1));
        }
        return $d->getContent();
    }
}
