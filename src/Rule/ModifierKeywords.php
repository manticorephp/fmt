<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\ModifierKeywordsFixer (priority 56). Per classy element (option `elements`; property
 * includes promoted properties), last first: the modifiers before it are put in the order abstract/final, visibility, set-visibility,
 * static/readonly (a missing visibility becomes `public`, `var` becomes `public`), each followed by exactly one space. An element whose
 * modifiers are already in place costs a backward walk over the Source; the others run upstream's moves on a FlatTokens window from the
 * first modifier to the element, rendered back as one edit.
 */
final class ModifierKeywords extends EditRule
{
    private FlatTokens $t;
    /** @var array<int, bool> E_* types to fix */
    private array $types = [];

    /** @param string[] $elements */
    public function __construct(array $elements = ['const', 'method', 'property'])
    {
        $this->t = new FlatTokens();
        foreach ($elements as $e) {
            if ($e === 'const') {
                $this->types[Analyzer::E_CONST] = true;
            } elseif ($e === 'method') {
                $this->types[Analyzer::E_METHOD] = true;
            } elseif ($e === 'property') {
                $this->types[Analyzer::E_PROPERTY] = true;
                $this->types[Analyzer::E_PROMOTED] = true;
            }
        }
    }

    public function name(): string
    {
        return 'modifier_keywords';
    }

    public function priority(): int
    {
        return 56;
    }

    private static function eff(Source $s, int $i): int
    {
        return $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
    }

    private static function isAmp(int $k): bool
    {
        return $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }

    private static function isType(int $k): bool
    {
        return $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR || $k === CT::NULLABLE_TYPE || $k === CT::ARRAY_TYPEHINT
            || $k === CT::TYPE_ALTERNATION || $k === CT::TYPE_INTERSECTION || $k === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN
            || $k === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE;
    }

    /** 0 = no modifier, 1 abstract/final, 2 static, 3 readonly, 4 set-visibility, 5 visibility (incl. var, promotion CTs) */
    private static function role(int $k): int
    {
        if ($k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_FINAL) {
            return 1;
        }
        if ($k === \__McTokId::T_STATIC) {
            return 2;
        }
        if ($k === \__McTokId::T_READONLY) {
            return 3;
        }
        if ($k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET) {
            return 4;
        }
        if ($k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_VAR
            || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED
            || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE) {
            return 5;
        }
        return 0;
    }

    public function fixFile(Source $s): void
    {
        $els = null;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                $els = Analyzer::classyElements($s);
                break;
            }
        }
        if ($els === null) {
            return;
        }
        foreach ($els as $idx => $info) {
            $type = $info[0];
            if (!isset($this->types[$type])) {
                continue;
            }
            $prop = $type === Analyzer::E_PROPERTY || $type === Analyzer::E_PROMOTED;
            $absFinal = -1;
            $vis = -1;
            $visSet = -1;
            $static = -1;
            $ro = -1;
            $typeIdx = -1;
            $p = $idx - 1;
            while ($p >= 0) {
                $e = self::eff($s, $p);
                $r = self::role($e);
                if ($r === 0 && !self::isAmp($e) && !($prop && self::isType($e))) {
                    break;
                }
                if ($r === 1) {
                    $absFinal = $p;
                } elseif ($r === 2) {
                    $static = $p;
                } elseif ($r === 3) {
                    $ro = $p;
                } elseif ($r === 4) {
                    $visSet = $p;
                } elseif ($prop && self::isType($e)) {
                    $typeIdx = $p;
                } elseif (!self::isAmp($e)) {
                    $vis = $p;
                }
                $p--;
            }
            if ($type === Analyzer::E_PROPERTY && $p >= 0 && $s->kind[$p] === 44 && $s->ct[$p] === 0) {
                continue;
            }
            if (!$this->needsFix($s, $idx, $typeIdx, $static >= 0 ? $static : $ro, $visSet, $vis, $absFinal)) {
                continue;
            }
            $this->fixWindow($s, $p + 1, $idx, $prop, $type === Analyzer::E_PROMOTED);
        }
    }

    /** isKeywordPlacedProperly over the Source: exactly one space after the keyword, then $cmp (or `&` glued to $cmp) */
    private static function placed(Source $s, int $kw, int $cmp): bool
    {
        if ($s->gapBefore[$kw + 1] !== ' ') {
            return false;
        }
        return $kw + 1 === $cmp || ($kw + 2 === $cmp && self::isAmp(self::eff($s, $kw + 1)) && $s->gapBefore[$kw + 2] === '');
    }

    private function needsFix(Source $s, int $idx, int $typeIdx, int $swap, int $visSet, int $vis, int $absFinal): bool
    {
        $at = $typeIdx >= 0 ? $typeIdx : $idx;
        foreach ([$swap, $visSet] as $kw) {
            if ($kw >= 0) {
                if (!self::placed($s, $kw, $at)) {
                    return true;
                }
                $at = $kw;
            }
        }
        if ($vis < 0 || $s->kind[$vis] === \__McTokId::T_VAR || !self::placed($s, $vis, $at)) {
            return true;
        }
        return $absFinal >= 0 && !self::placed($s, $absFinal, $vis);
    }

    /** upstream's moves on the window [first modifier .. element], window-local indexes */
    private function fixWindow(Source $s, int $from, int $idx, bool $prop, bool $promoted): void
    {
        $t = $this->t;
        $t->load($s, $from, $idx);
        $index = $t->count() - 1;
        $absFinal = -1;
        $vis = -1;
        $visSet = -1;
        $static = -1;
        $ro = -1;
        $typeIdx = -1;
        $p = $t->meaningful($index, -1);
        while ($p >= 0) {
            $e = $t->k[$p];
            $r = self::role($e);
            if ($r === 1) {
                $absFinal = $p;
            } elseif ($r === 2) {
                $static = $p;
            } elseif ($r === 3) {
                $ro = $p;
            } elseif ($r === 4) {
                $visSet = $p;
            } elseif ($prop && self::isType($e)) {
                $typeIdx = $p;
            } elseif (!self::isAmp($e)) {
                $vis = $p;
            }
            $p = $t->meaningful($p, -1);
        }
        if ($typeIdx >= 0) {
            $index = $typeIdx;
        }
        $swap = $static >= 0 ? $static : $ro;
        if ($swap >= 0) {
            if ($this->placedFlat($swap, $index)) {
                $index = $swap;
            } else {
                $this->move($swap, $index);
            }
        }
        if ($visSet >= 0) {
            if ($this->placedFlat($visSet, $index)) {
                $index = $visSet;
            } else {
                $this->move($visSet, $index);
            }
        }
        if ($vis < 0) {
            $t->insertAt($index, [FlatTokens::code($promoted ? CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC : \__McTokId::T_PUBLIC, 'public'), FlatTokens::ws(' ')]);
        } else {
            if ($t->k[$vis] === \__McTokId::T_VAR) {
                $t->k[$vis] = \__McTokId::T_PUBLIC;
                $t->c[$vis] = 'public';
                $t->i[$vis] = FlatTokens::NEW;
            }
            if ($this->placedFlat($vis, $index)) {
                $index = $vis;
            } else {
                $this->move($vis, $index);
            }
        }
        if ($absFinal >= 0 && !$this->placedFlat($absFinal, $index)) {
            $this->move($absFinal, $index);
        }
        $s->edit(Source::tokAt($from), Source::tokAt($idx) + 1, $t->render(), -1, $t->wsBoundaries());
    }

    private function placedFlat(int $kw, int $cmp): bool
    {
        $t = $this->t;
        if ($kw + 1 >= $t->count() || $t->content($kw + 1) !== ' ') {
            return false;
        }
        return $kw + 2 === $cmp || ($kw + 3 === $cmp && $t->isCode($kw + 2) && self::isAmp($t->k[$kw + 2]));
    }

    private function move(int $from, int $to): void
    {
        $t = $this->t;
        $t->insertAt($to, [$t->item($from), FlatTokens::ws(' ')]);
        $t->clearAt($from);
        if ($t->isWs($from + 1)) {
            $t->clearAt($from + 1);
        }
    }
}
