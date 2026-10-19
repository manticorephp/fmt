<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitFqcnAnnotationFixer (priority -9): `@covers Foo` -> `@covers \Foo` (also `@uses`,
 * `@coversDefaultClass`, `@expectedException`) in a PHPUnit test class: from the last docblock before its `{` to its `}`
 * (PhpUnitTestCaseAnalyzer: `class ... extends`, a name ending in Test / TestCase / TestInterface up to the `{`).
 */
final class PhpUnitFqcnAnnotation extends GapRule
{
    public function name(): string
    {
        return 'php_unit_fqcn_annotation';
    }

    public function priority(): int
    {
        return -9;
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
        $class = false;
        $str = false;
        for ($t = 0; $t < $s->n && !($class && $str); $t++) {
            $k = $s->kind[$t];
            if ($k === \__McTokId::T_CLASS && $s->ct[$t] === 0) {
                $class = true;
            } elseif ($k === \__McTokId::T_STRING && $s->ct[$t] === 0) {
                $str = true;
            }
        }
        return $class && $str;
    }

    public function fixFile(Source $s): void
    {
        for ($t = $s->n - 1; $t > 0; $t--) {
            if ($s->kind[$t] !== \__McTokId::T_CLASS || $s->ct[$t] !== 0 || !self::isPhpUnitClass($s, $t)) {
                continue;
            }
            $open = -1;
            for ($x = $t + 1; $x < $s->n; $x++) {
                if ($s->kind[$x] === 123 && $s->ct[$x] === 0) {
                    $open = $x;
                    break;
                }
            }
            $end = $open >= 0 ? $s->pair[$open] : -1;
            if ($end < 0) {
                $s->abort = true;
                return;
            }
            // getPrevTokenOfKind(T_DOC_COMMENT) before the `{`: the last docblock of the gaps up to it
            $g = $open;
            $from = -1;
            for (; $g >= 0; $g--) {
                $from = DocRule::lastDoc($s, $g);
                if ($from >= 0) {
                    break;
                }
            }
            $start = $g >= 0 ? $g : $open + 1;
            for ($x = $start; $x <= $end; $x++) {
                DocRule::mapDocs($s, $x, static fn (string $d): string => self::fixDoc($d), $x === $g ? $from : 0);
            }
        }
    }

    private static function isPhpUnitClass(Source $s, int $t): bool
    {
        for ($x = $t + 1; $x < $s->n; $x++) {
            if ($s->kind[$x] === 123 && $s->ct[$x] === 0) {
                return false;
            }
            if ($s->kind[$x] === \__McTokId::T_EXTENDS) {
                break;
            }
        }
        if ($x >= $s->n) {
            return false;
        }
        for ($x = $t + 1; $x < $s->n; $x++) {
            if ($s->kind[$x] === 123 && $s->ct[$x] === 0) {
                break;
            }
            if ($s->kind[$x] === \__McTokId::T_STRING && $s->ct[$x] === 0 && Preg::test('/(?:Test|TestCase)(?:Interface)?$/', $s->text($x))) {
                return true;
            }
        }
        return false;
    }

    private static function fixDoc(string $d): string
    {
        if (!\str_contains($d, '@covers') && !\str_contains($d, '@uses') && !\str_contains($d, '@expectedException')) {
            return $d;
        }
        return Preg::replace('~^(\s*\*\s*@(?:expectedException|covers|coversDefaultClass|uses)\h+)(?!(?:self|static)::)(\w.*)$~m', '$1\\\\$2', $d);
    }
}
