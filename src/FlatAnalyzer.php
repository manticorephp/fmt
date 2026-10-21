<?php

declare(strict_types=1);

namespace Fmt;

/** TokensAnalyzer / AlternativeSyntaxAnalyzer over a FlatTokens (the live item list of a FlatRule): operators and the `:` of alternative syntax. */
final class FlatAnalyzer
{
    /** @var array<int, bool> */
    private array $binary = [];
    /** @var array<int, bool> */
    private array $predPrev = [];

    public function __construct()
    {
        foreach ([\__McTokId::T_AND_EQUAL, \__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR, \__McTokId::T_CONCAT_EQUAL, \__McTokId::T_DIV_EQUAL, \__McTokId::T_DOUBLE_ARROW,
            \__McTokId::T_IS_EQUAL, \__McTokId::T_IS_GREATER_OR_EQUAL, \__McTokId::T_IS_IDENTICAL, \__McTokId::T_IS_NOT_EQUAL, \__McTokId::T_IS_NOT_IDENTICAL,
            \__McTokId::T_IS_SMALLER_OR_EQUAL, \__McTokId::T_LOGICAL_AND, \__McTokId::T_LOGICAL_OR, \__McTokId::T_LOGICAL_XOR, \__McTokId::T_MINUS_EQUAL, \__McTokId::T_MOD_EQUAL,
            \__McTokId::T_MUL_EQUAL, \__McTokId::T_OR_EQUAL, \__McTokId::T_PLUS_EQUAL, \__McTokId::T_POW, \__McTokId::T_POW_EQUAL, \__McTokId::T_SL, \__McTokId::T_SL_EQUAL,
            \__McTokId::T_SR, \__McTokId::T_SR_EQUAL, \__McTokId::T_XOR_EQUAL, \__McTokId::T_SPACESHIP, \__McTokId::T_COALESCE, \__McTokId::T_COALESCE_EQUAL] as $k) {
            $this->binary[$k] = true;
        }
        foreach ([93, 125, 41, 34, 96, CT::ARRAY_BRACKET_CLOSE, CT::ARRAY_INDEX_BRACE_CLOSE, CT::DYNAMIC_PROP_BRACE_CLOSE, CT::DYNAMIC_VAR_BRACE_CLOSE, \__McTokId::T_CLASS_C,
            \__McTokId::T_CONSTANT_ENCAPSED_STRING, \__McTokId::T_DEC, \__McTokId::T_DIR, \__McTokId::T_DNUMBER, \__McTokId::T_FILE, \__McTokId::T_FUNC_C, \__McTokId::T_INC,
            \__McTokId::T_LINE, \__McTokId::T_LNUMBER, \__McTokId::T_METHOD_C, \__McTokId::T_NS_C, \__McTokId::T_STRING, \__McTokId::T_TRAIT_C, \__McTokId::T_VARIABLE] as $k) {
            $this->predPrev[$k] = true;
        }
    }

    private static function isAmp(int $k): bool
    {
        return $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }

    public function isUnarySuccessor(FlatTokens $t, int $q): bool
    {
        $k = $t->k[$q];
        if (!($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC)) {
            return false;
        }
        $p = $t->meaningful($q, -1);
        if ($p < 0) {
            return false;
        }
        $pk = $t->k[$p];
        return $pk === 93 || $pk === \__McTokId::T_STRING || $pk === \__McTokId::T_VARIABLE || $pk === CT::ARRAY_INDEX_BRACE_CLOSE || $pk === CT::DYNAMIC_PROP_BRACE_CLOSE
            || $pk === CT::DYNAMIC_VAR_BRACE_CLOSE;
    }

    public function isUnaryPredecessor(FlatTokens $t, int $q): bool
    {
        $k = $t->k[$q];
        if ($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) {
            return !$this->isUnarySuccessor($t, $q);
        }
        if ($k === 33 || $k === 126 || $k === 64 || $k === \__McTokId::T_ELLIPSIS) {
            return true;
        }
        $amp = self::isAmp($k);
        if (!($k === 43 || $k === 45 || $amp || $k === CT::RETURN_REF)) {
            return false;
        }
        $p = $t->meaningful($q, -1);
        if ($p < 0) {
            return true;
        }
        if (!isset($this->predPrev[$t->k[$p]])) {
            return true;
        }
        if (!$amp || $t->k[$p] !== \__McTokId::T_STRING) {
            return false;
        }
        for ($j = $q - 1; $j >= 0; $j--) {
            if ($t->i[$j] === -1) {
                continue;
            }
            $x = $t->k[$j];
            if ($x === 59 || $x === 123 || $x === 125 || $x === \__McTokId::T_DOUBLE_ARROW || $x === \__McTokId::T_FN || $x === \__McTokId::T_FUNCTION
                || $x === \__McTokId::T_OPEN_TAG || $x === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return $x === \__McTokId::T_FN || $x === \__McTokId::T_FUNCTION;
            }
        }
        return false;
    }

    public function isBinaryOperator(FlatTokens $t, int $q): bool
    {
        $k = $t->k[$q];
        if ($k === \__McTokId::T_INLINE_HTML || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $k === CT::TYPE_INTERSECTION) {
            return false;
        }
        $c = $t->content($q);
        if ($c === '+' || $c === '-' || $c === '&') {
            return !$this->isUnaryPredecessor($t, $q);
        }
        if ($k >= 256) {
            return isset($this->binary[$k]);
        }
        return $k === 61 || $k === 42 || $k === 47 || $k === 37 || $k === 60 || $k === 62 || $k === 124 || $k === 94 || $k === 46;
    }

    /** AlternativeSyntaxAnalyzer::belongsToAlternativeSyntax for the `:` at item $q */
    public function belongsToAlternativeSyntax(FlatTokens $t, int $q): bool
    {
        if (!($t->i[$q] !== -1 && $t->k[$q] === 58)) {
            return false;
        }
        $p = $t->meaningful($q, -1);
        if ($p >= 0 && $t->k[$p] === \__McTokId::T_ELSE) {
            return true;
        }
        if (!($p >= 0 && $t->k[$p] === 41)) {
            return false;
        }
        $open = $t->blockStart($p, 40);
        $b = $t->meaningful($open, -1);
        if ($b < 0) {
            return false;
        }
        $k = $t->k[$b];
        return $k === \__McTokId::T_DECLARE || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH
            || $k === \__McTokId::T_IF || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_WHILE;
    }
}
