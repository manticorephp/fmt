<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocInlineTagNormalizerFixer (priority 0): `@{tag}`, `{ @tag x }`, `{{@tag}}` -> `{@tag x}`. */
final class PhpdocInlineTagNormalizer extends DocRule
{
    private string $regex;

    /** @param string[] $tags */
    public function __construct(private array $tags)
    {
        $q = [];
        foreach ($tags as $t) {
            $q[] = \preg_quote($t, '/');
        }
        $this->regex = '#(?:@{+|{+\h*@)\h*(' . \implode('|', $q) . ')\b([^}]*)(?:}+)#i';
    }

    public function name(): string
    {
        return 'phpdoc_inline_tag_normalizer';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        return $this->tags !== [];
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '{') || !\str_contains($doc, '@')) {
            return $doc;
        }
        return Preg::replaceCallback($this->regex, static function (array $m): string {
            $d = \trim((string) $m[2]);
            return $d === '' ? '{@' . $m[1] . '}' : '{@' . $m[1] . ' ' . $d . '}';
        }, $doc);
    }
}
