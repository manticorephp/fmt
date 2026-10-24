<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocSeparationFixer (priority -3): a blank line after the description, none between
 * annotations of one group, one between groups (unlisted tags too unless skip_unlisted_annotations).
 */
final class PhpdocSeparation extends DocRule
{
    /** @param string[][] $groups */
    public function __construct(private array $groups, private bool $skipUnlisted) {}

    public function name(): string
    {
        return 'phpdoc_separation';
    }

    public function priority(): int
    {
        return -3;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '@') || \strpbrk($doc, "\r\n") === false) {
            return $doc; // one line holds one annotation at most and no description before it
        }
        $d = new DocBlock($doc);
        foreach ($d->lines as $index => $line) {
            if ($line->containsATag()) {
                break;
            }
            if ($line->containsUsefulContent()) {
                $next = $d->getLine($index + 1);
                if ($next !== null && $next->containsATag()) {
                    $line->addBlank();
                    break;
                }
            }
        }
        $as = $d->getAnnotations();
        foreach ($as as $index => $a) {
            $next = $as[$index + 1] ?? null;
            if ($next === null) {
                break;
            }
            $together = $this->shouldBeTogether($a, $next);
            if ($together === 1) {
                for ($pos = $a->end + 1; $pos < $next->start; $pos++) {
                    $d->lines[$pos]->remove();
                }
            } elseif ($together === 0 || !$this->skipUnlisted) {
                $pos = $a->end;
                $final = $next->start - 1;
                if ($pos === $final) {
                    $d->lines[$pos]->addBlank();
                    continue;
                }
                for ($pos++; $pos < $final; $pos++) {
                    $d->lines[$pos]->remove();
                }
            }
        }
        return $d->getContent();
    }

    /** 1 together, 0 apart, -1 unknown (upstream's null) */
    private function shouldBeTogether(Annotation $first, Annotation $second): int
    {
        $a = self::tagName($first);
        $b = self::tagName($second);
        if ($a === null || $b === null) {
            return -1;
        }
        if ($a === $b) {
            return 1;
        }
        foreach ($this->groups as $group) {
            $ina = self::inGroup($a, $group);
            $inb = self::inGroup($b, $group);
            if ($ina) {
                return $inb ? 1 : 0;
            }
            if ($inb) {
                return 0;
            }
        }
        return -1;
    }

    private static function tagName(Annotation $a): ?string
    {
        if (Preg::match('/@([a-zA-Z0-9_\\\-]+(?=\s|$|\())/', $a->getContent(), $m)) {
            return (string) $m[1];
        }
        return null;
    }

    /** @param string[] $group */
    private static function inGroup(string $tag, array $group): bool
    {
        foreach ($group as $t) {
            $t = \str_replace('*', '\*', $t);
            $t = \preg_quote($t, '/');
            $t = \str_replace('\\\\\*', '.*?', $t);
            if (Preg::test("/^{$t}$/", $tag)) {
                return true;
            }
        }
        return false;
    }
}
