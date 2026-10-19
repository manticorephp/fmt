<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\TypeDeclarationSpacesFixer (priority 0). Exactly one space (any whitespace token, a line break included,
 * becomes ' '; one is inserted when none) after the type of a function / closure / arrow-function argument, a property, and with `constant` a class constant.
 * Upstream visits the closures / functions / arrow functions first (last to first), then the class elements (last to first), with the indexes taken up front:
 * the flat list shifts under an insertion exactly as upstream's. Whole-file FlatTokens port (TypeWalk collectors).
 */
final class TypeDeclarationSpaces extends FlatRule
{
    public function __construct(private bool $function, private bool $property, private bool $constant)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'type_declaration_spaces';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        $cls = false;
        for ($i = 1; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                $cls = true;
            }
            if ($this->constant && $k === \__McTokId::T_CONST && $s->ct[$i] === 0) {
                return true;
            }
            if ($k !== \__McTokId::T_VARIABLE) {
                continue;
            }
            $j = $i;
            while ($j > 1) {
                $pk = $s->kind[$j - 1];
                if ($pk === \__McTokId::T_ELLIPSIS || $pk === 38 || $pk === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $pk === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                    $j--;
                    continue;
                }
                break;
            }
            $te = $j - 1;
            if ($te < 1) {
                continue;
            }
            if ($s->kind[$te] === 44 && $s->ct[$te] === 0 && $s->gapBefore[$j] !== ' ' && self::inPropertyList($s, $te)) {
                return true; // upstream's property type of `protected $a, $b` runs up to the comma
            }
            $tk = $s->kind[$te];
            $tc = $s->ct[$te];
            if (($tk === \__McTokId::T_STRING || $tk === \__McTokId::T_ARRAY || $tk === \__McTokId::T_CALLABLE || $tk === \__McTokId::T_STATIC || $tc === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE
                || $tc === CT::ARRAY_TYPEHINT) && $s->gapBefore[$j] !== ' ') {
                return true;
            }
        }
        return false;
    }

    /** the comma at $c belongs to a property declaration list (`public int $a, $b;`): walking back over balanced groups reaches a visibility modifier */
    private static function inPropertyList(Source $s, int $c): bool
    {
        for ($j = $c - 1; $j > 0; $j--) {
            $k = $s->kind[$j];
            if ($s->ct[$j] === 0 && ($k === 41 || $k === 93 || $k === 125) && $s->pair[$j] >= 0) {
                if ($k === 125) {
                    return false;
                }
                $j = $s->pair[$j];
                continue;
            }
            if ($s->ct[$j] === 0 && ($k === 59 || $k === 123 || $k === 40 || $k === 91)) {
                $m = $j + 1;
                while ($m < $c && ($s->kind[$m] === \__McTokId::T_ATTRIBUTE)) {
                    $m = $s->pair[$m] + 1;
                }
                $mk = $s->kind[$m];
                return $s->ct[$j] === 0 && $k !== 40 && $k !== 91 && ($mk === \__McTokId::T_PUBLIC || $mk === \__McTokId::T_PROTECTED || $mk === \__McTokId::T_PRIVATE
                    || $mk === \__McTokId::T_STATIC || $mk === \__McTokId::T_VAR || $mk === \__McTokId::T_READONLY || $mk === \__McTokId::T_FINAL
                    || $mk === \__McTokId::T_PUBLIC_SET || $mk === \__McTokId::T_PROTECTED_SET || $mk === \__McTokId::T_PRIVATE_SET);
            }
        }
        return false;
    }

    private static function ensure(FlatTokens $t, int $q): void
    {
        if ($t->isWs($q)) {
            $t->c[$q] = ' ';
            return;
        }
        $t->insertAt($q, [FlatTokens::ws(' ')]);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $tw = new TypeWalk($t);
        // [flat index, element type] in upstream's order: the closures / functions / arrow functions (ascending in the list), then the class elements
        $els = [];
        $classy = [];
        foreach (Analyzer::classyElements($s) as $idx => [$type, $cls]) {
            if ($type === Analyzer::E_METHOD) {
                $classy[$idx] = 'method';
            } elseif ($type === Analyzer::E_PROPERTY) {
                $classy[$idx] = 'property';
            } elseif ($type === Analyzer::E_CONST) {
                $classy[$idx] = 'const';
            }
        }
        foreach ($classy as $idx => $ty) {
            $els[] = [$t->pos[$idx], $ty];
        }
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_FN || ($k === \__McTokId::T_FUNCTION && !isset($classy[$i])))) {
                $els[] = [$t->pos[$i], 'method'];
            }
        }
        for ($e = \count($els) - 1; $e >= 0; $e--) {
            [$q, $ty] = $els[$e];
            if ($ty === 'property' && $this->property) {
                $this->property($t, $tw, $q);
            } elseif ($ty === 'method' && $this->function) {
                $args = $tw->arguments($q);
                foreach (\array_reverse($args) as $a) {
                    if ($a !== null) {
                        self::ensure($t, $a[2] + 1);
                    }
                }
            } elseif ($ty === 'const' && $this->constant) {
                $this->constant($t, $q);
            }
        }
    }

    private function property(FlatTokens $t, TypeWalk $tw, int $q): void
    {
        $p = $q;
        do {
            $p = $t->meaningful($p, -1);
            if ($p < 0) {
                return;
            }
            $k = $t->k[$p];
        } while (!($k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_VAR
            || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PUBLIC_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PRIVATE_SET));
        $ty = $tw->propertyType($q);
        if ($ty === null) {
            return;
        }
        self::ensure($t, $ty[2] + 1);
    }

    private function constant(FlatTokens $t, int $q): void
    {
        $eq = self::nextOf($t, $q, 61);
        if ($eq < 0) {
            return;
        }
        $name = $t->meaningful($eq, -1);
        if (!self::is($t, $name, \__McTokId::T_STRING)) {
            return;
        }
        $te = $t->meaningful($name, -1);
        if ($te < 0 || self::is($t, $te, \__McTokId::T_CONST)) {
            return;
        }
        self::ensure($t, $te + 1);
    }
}
