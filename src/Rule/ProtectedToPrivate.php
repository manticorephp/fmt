<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\ProtectedToPrivateFixer (priority 66). In a final class that extends nothing and uses no trait (and
 * in an enum) `protected` methods, properties, promoted properties and constants become `private` (`protected(set)` -> `private(set)`);
 * a `final` constant is left. The token's kind changes with its text.
 */
final class ProtectedToPrivate extends GapRule
{
    /** @var array<int, bool> */
    private array $modifier = [];

    public function __construct()
    {
        foreach ([\__McTokId::T_PUBLIC, \__McTokId::T_PROTECTED, \__McTokId::T_PRIVATE, \__McTokId::T_FINAL, \__McTokId::T_ABSTRACT, \__McTokId::T_NS_SEPARATOR,
            \__McTokId::T_STRING, CT::NULLABLE_TYPE, CT::ARRAY_TYPEHINT, \__McTokId::T_STATIC, CT::TYPE_ALTERNATION, CT::TYPE_INTERSECTION, \__McTokId::T_READONLY,
            \__McTokId::T_PRIVATE_SET, \__McTokId::T_PROTECTED_SET] as $k) {
            $this->modifier[$k] = true;
        }
    }

    public function name(): string
    {
        return 'protected_to_private';
    }

    public function priority(): int
    {
        return 66;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $prot = false;
        $cls = false;
        $fin = false;
        $enum = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            $prot = $prot || $k === \__McTokId::T_PROTECTED || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $k === \__McTokId::T_PROTECTED_SET;
            $cls = $cls || ($k === \__McTokId::T_CLASS && $c === 0);
            $fin = $fin || $k === \__McTokId::T_FINAL;
            $enum = $enum || $k === \__McTokId::T_ENUM;
        }
        return $prot && ($cls && $fin || $enum);
    }

    public function fixFile(Source $s): void
    {
        $cand = [];
        $hasTrait = null;
        foreach (Analyzer::classyElements($s) as $idx => [$type, $cls]) {
            if (!isset($cand[$cls])) {
                $cand[$cls] = $this->isClassCandidate($s, $cls, $hasTrait);
            }
            if (!$cand[$cls] || ($type !== Analyzer::E_METHOD && $type !== Analyzer::E_PROPERTY && $type !== Analyzer::E_PROMOTED && $type !== Analyzer::E_CONST)) {
                continue;
            }
            $p = $idx;
            $prot = -1;
            $promo = -1;
            $set = -1;
            $final = false;
            do {
                $p--;
                if ($p < 0) {
                    break;
                }
                $k = $s->kind[$p];
                $c = $s->ct[$p];
                if ($c === 0 && $k === \__McTokId::T_PROTECTED) {
                    $prot = $p;
                } elseif ($c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED) {
                    $promo = $p;
                } elseif ($c === 0 && $k === \__McTokId::T_PROTECTED_SET) {
                    $set = $p;
                } elseif ($c === 0 && $k === \__McTokId::T_FINAL) {
                    $final = true;
                }
            } while (isset($this->modifier[$c !== 0 ? $c : $k]));
            if ($final && $type === Analyzer::E_CONST) {
                continue;
            }
            if ($prot >= 0) {
                $s->setText($prot, 'private');
                $s->setKind($prot, \__McTokId::T_PRIVATE);
            }
            if ($promo >= 0) {
                $s->setText($promo, 'private');
                $s->ct[$promo] = CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE;
                $s->setKind($promo, \__McTokId::T_PRIVATE);
            }
            if ($set >= 0) {
                $s->setText($set, 'private(set)');
                $s->setKind($set, \__McTokId::T_PRIVATE_SET);
            }
        }
    }

    private function isClassCandidate(Source $s, int $ci, ?bool &$hasTrait): bool
    {
        $k = $s->kind[$ci];
        if ($k === \__McTokId::T_ENUM && $s->ct[$ci] === 0) {
            return true;
        }
        if ($k !== \__McTokId::T_CLASS || $s->ct[$ci] !== 0 || Analyzer::isAnonymousClass($s, $ci)) {
            return false;
        }
        $final = false;
        for ($j = $ci - 1; $j >= 0; $j--) {
            $jk = $s->kind[$j];
            if ($s->ct[$j] !== 0 || !($jk === \__McTokId::T_FINAL || $jk === \__McTokId::T_ABSTRACT || $jk === \__McTokId::T_READONLY)) {
                break;
            }
            $final = $final || $jk === \__McTokId::T_FINAL;
        }
        if (!$final) {
            return false;
        }
        $name = $ci + 1;
        $ext = $name + 1;
        if ($ext < $s->n && $s->kind[$ext] === \__McTokId::T_EXTENDS) {
            return false;
        }
        if ($hasTrait === null) {
            $hasTrait = false;
            for ($i = 0; $i < $s->n; $i++) {
                if ($s->ct[$i] === CT::USE_TRAIT) {
                    $hasTrait = true;
                    break;
                }
            }
        }
        if (!$hasTrait) {
            return true;
        }
        $o = $name + 1;
        while ($o < $s->n && !($s->kind[$o] === 123 && $s->ct[$o] === 0)) {
            $o++;
        }
        $c = $o < $s->n ? $s->pair[$o] : $s->n;
        for ($i = $o; $i < $c; $i++) {
            if ($s->ct[$i] === CT::USE_TRAIT) {
                return false;
            }
        }
        return true;
    }
}
