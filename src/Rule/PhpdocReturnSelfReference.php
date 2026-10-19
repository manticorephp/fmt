<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocReturnSelfReferenceFixer (priority 10): `@return this` / `@self` / `$static` ... ->
 * `$this` / `self` / `static` in the docblock of a method (the docblock right before its modifiers).
 */
final class PhpdocReturnSelfReference extends GapRule
{
    /** @param array<string, string> $replacements lowercase from => to */
    public function __construct(private array $replacements)
    {
    }

    public function name(): string
    {
        return 'phpdoc_return_self_reference';
    }

    public function priority(): int
    {
        return 10;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function needsMask(): int
    {
        return Gap::G_DOC;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function prepare(Source $s): bool
    {
        $fn = false;
        $classy = false;
        for ($t = 0; $t < $s->n && !($fn && $classy); $t++) {
            $k = $s->kind[$t];
            if ($k === \__McTokId::T_FUNCTION) {
                $fn = true;
            } elseif (($k === \__McTokId::T_CLASS && $s->ct[$t] === 0) || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_ENUM) {
                $classy = true;
            }
        }
        if (!$fn || !$classy) {
            return false;
        }
        for ($g = 0; $g <= $s->n; $g++) {
            if (($s->gapFlags[$g] & Gap::G_DOC) !== 0 && \str_contains($s->gapBefore[$g], '@return')) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        foreach (Analyzer::classyElements($s) as $index => $el) {
            if ($el[0] !== Analyzer::E_METHOD) {
                continue;
            }
            while ($index > 0) {
                $k = $s->kind[$index - 1];
                if ($k !== \__McTokId::T_STATIC && $k !== \__McTokId::T_FINAL && $k !== \__McTokId::T_ABSTRACT && $k !== \__McTokId::T_PRIVATE
                    && $k !== \__McTokId::T_PROTECTED && $k !== \__McTokId::T_PUBLIC) {
                    break;
                }
                $index--;
            }
            // getPrevNonWhitespace: the last comment of the gap
            $p = Gap::pieces($s->gapBefore[$index]);
            for ($q = \count($p) - 1; $q >= 0; $q--) {
                if (!Gap::isSpace($p[$q])) {
                    break;
                }
            }
            if ($q < 0 || !Gap::isDoc($p[$q])) {
                continue;
            }
            $rep = $this->replacements;
            DocRule::mapDocs($s, $index, static fn (string $d): string => self::fixDoc($d, $rep), $q);
        }
    }

    /** @param array<string, string> $rep */
    private static function fixDoc(string $doc, array $rep): string
    {
        if (!\str_contains($doc, '@return')) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $rs = $d->getAnnotationsOfType(['return']);
        if ($rs === []) {
            return $doc;
        }
        $types = $rs[0]->getTypes();
        if ($types === []) {
            return $doc;
        }
        $new = [];
        foreach ($types as $t) {
            $new[] = $rep[\strtolower($t)] ?? $t;
        }
        if ($new === $types) {
            return $doc;
        }
        $rs[0]->setTypes($new);
        return $d->getContent();
    }
}
