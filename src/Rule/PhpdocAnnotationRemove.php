<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer (priority 10), and the proxies phpdoc_no_access (`access`),
 * phpdoc_no_package (`package`, `subpackage`). Annotations of the listed tags are removed; an emptied docblock is removed with it.
 */
final class PhpdocAnnotationRemove extends DocRule
{
    /** @param string[] $annotations */
    public function __construct(private string $ruleName, private array $annotations, private bool $caseSensitive) {}

    public function name(): string
    {
        return $this->ruleName;
    }

    public function priority(): int
    {
        return 10;
    }

    public function prepare(Source $s): bool
    {
        return $this->annotations !== [];
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        $hit = false;
        foreach ($this->annotations as $a) {
            if ($this->caseSensitive ? \str_contains($doc, '@' . $a) : \stripos($doc, '@' . $a) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $remove = [];
        if ($this->caseSensitive) {
            $remove = $d->getAnnotationsOfType($this->annotations);
        } else {
            $lower = [];
            foreach ($this->annotations as $a) {
                $lower[] = \strtolower($a);
            }
            foreach ($d->getAnnotations() as $an) {
                if (\in_array(\strtolower($an->getTag()->getName()), $lower, true)) {
                    $remove[] = $an;
                }
            }
        }
        if ($remove === []) {
            return $doc;
        }
        foreach ($remove as $an) {
            $an->remove();
        }
        return $d->getContent();
    }
}
