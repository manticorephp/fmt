<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\DocRule;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocNoUselessInheritdocFixer (priority 6): `{@inheritdoc}` is dropped from the docblocks of a
 * class / interface that extends / implements nothing (and, inside it, uses no trait). Upstream's walk over tokens and trivia is kept:
 * a nested `class` (anonymous) is visited as a classy of its own; a class without a `{` after it makes upstream throw (file left).
 */
final class PhpdocNoUselessInheritdoc extends GapRule
{
    public function name(): string
    {
        return 'phpdoc_no_useless_inheritdoc';
    }

    public function priority(): int
    {
        return 6;
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
        $doc = false;
        $classy = false;
        for ($g = 0; $g <= $s->n; $g++) {
            if (($s->gapFlags[$g] & Gap::G_DOC) !== 0 && \stripos($s->gapBefore[$g], 'inheritdoc') !== false) {
                $doc = true;
                break;
            }
        }
        if (!$doc) {
            return false;
        }
        for ($t = 0; $t < $s->n; $t++) {
            $k = $s->kind[$t];
            if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE) {
                $classy = true;
                break;
            }
        }
        return $classy;
    }

    public function fixFile(Source $s): void
    {
        for ($t = 0; $t < $s->n && !$s->abort; $t++) {
            $k = $s->kind[$t];
            if (($k === \__McTokId::T_CLASS && $s->ct[$t] === 0) || $k === \__McTokId::T_INTERFACE) {
                $t = $this->fixClassy($s, $t);
            }
        }
    }

    private static function plainOpenBrace(Source $s, int $from): int
    {
        for ($t = $from + 1; $t < $s->n; $t++) {
            if ($s->kind[$t] === 123 && $s->ct[$t] === 0) {
                return $t;
            }
        }
        return -1;
    }

    private function fixClassy(Source $s, int $t): int
    {
        $open = self::plainOpenBrace($s, $t);
        if ($open < 0 || $s->pair[$open] < 0) {
            $s->abort = true; // upstream's findBlockEnd gets null / an unbalanced brace and throws: the file stays as it is
            return $s->n;
        }
        $end = $s->pair[$open];
        $ext = false;
        for ($x = $t; $x < $open; $x++) {
            if ($s->kind[$x] === \__McTokId::T_EXTENDS || $s->kind[$x] === \__McTokId::T_IMPLEMENTS) {
                $ext = true;
                break;
            }
        }
        if (!$ext) {
            // getPrevNonWhitespace of the classy keyword: the last comment of its gap
            $p = Gap::pieces($s->gapBefore[$t]);
            for ($q = \count($p) - 1; $q >= 0; $q--) {
                if ($p[$q] !== '' && !Gap::isSpace($p[$q])) {
                    if (Gap::isDoc($p[$q])) {
                        DocRule::mapDocs($s, $t, static fn (string $d): string => self::fixDoc($d), $q);
                    }
                    break;
                }
            }
            if ($s->kind[$t] === \__McTokId::T_CLASS) {
                for ($x = $open + 1; $x < $s->n; $x++) {
                    if ($s->ct[$x] === CT::USE_TRAIT) {
                        $ext = $x < $end;
                        break;
                    }
                }
            }
        }
        $fix = !$ext;
        for ($x = $open + 1; $x <= $end; $x++) {
            if ($fix) {
                DocRule::mapDocs($s, $x, static fn (string $d): string => self::fixDoc($d));
            }
            if ($x < $end && $s->kind[$x] === \__McTokId::T_CLASS && $s->ct[$x] === 0) {
                $x = $this->fixClassy($s, $x);
                if ($s->abort) {
                    return $s->n;
                }
            }
        }
        return $end;
    }

    private static function fixDoc(string $d): string
    {
        if (\stripos($d, 'inheritdoc') === false) {
            return $d;
        }
        return Preg::replaceCallback('#(\h*(?:@{*|{*\h*@)\h*inheritdoc\h*)([^}]*)((?:}*)\h*)#i', static fn (array $m): string => ' ' . $m[2], $d);
    }
}
