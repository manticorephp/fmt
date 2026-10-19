<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Upstream's type collectors (FunctionsAnalyzer::getFunctionArguments / getFunctionReturnType, ArgumentsAnalyzer, a property's type, a
 * fixer's collectTypeAnalysis) and Tokens::overrideRange over a whole-file FlatTokens, with upstream's integer indexes: the type rules
 * (nullable_type_declaration, ordered_types) port their walks on it line by line. A type is [name as written without trivia, first item,
 * last item].
 */
final class TypeWalk
{
    public function __construct(public FlatTokens $t)
    {
    }

    public function k(int $q): int
    {
        return $q >= 0 && $q < $this->t->count() ? $this->t->k[$q] : -1;
    }

    public function nextOfKind(int $q, int $id): int
    {
        $t = $this->t;
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->isCode($j) && $t->k[$j] === $id) {
                return $j;
            }
        }
        return -1;
    }

    /** @return array{0:string,1:int,2:int}|null the type of the property at $q: from after its last modifier to the variable */
    public function propertyType(int $q): ?array
    {
        $t = $this->t;
        $end = $q;
        do {
            $q = $t->meaningful($q, -1);
            if ($q < 0) {
                return null;
            }
            $k = $t->k[$q];
        } while (!($k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_STATIC
            || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_VAR || $k === \__McTokId::T_READONLY
            || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET));
        $ts = $t->meaningful($q, 1);
        $type = '';
        $te = $ts;
        for ($i = $ts; $i >= 0 && $i < $end; $i++) {
            if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                continue;
            }
            $type .= $t->content($i);
            $te = $i;
        }
        return $type === '' ? null : [$type, $ts, $te];
    }

    /** @return array{0:string,1:int,2:int}|null FunctionsAnalyzer::getFunctionReturnType */
    public function returnType(int $q): ?array
    {
        $t = $this->t;
        $open = $this->nextOfKind($q, 40);
        if ($open < 0) {
            return null;
        }
        $close = $t->blockEnd($open, 41);
        $colon = $t->meaningful($close, 1);
        if ($this->k($colon) === CT::USE_LAMBDA) {
            $lo = $this->nextOfKind($colon, 40);
            $colon = $t->meaningful($t->blockEnd($lo, 41), 1);
        }
        if ($this->k($colon) !== CT::TYPE_COLON) {
            return null;
        }
        $ts = $t->meaningful($colon, 1);
        $m = $t->count();
        $body = $colon + 1;
        while ($body < $m && !($t->isCode($body) && ($t->k[$body] === 123 || $t->k[$body] === 59 || $t->k[$body] === \__McTokId::T_DOUBLE_ARROW))) {
            $body++;
        }
        $type = '';
        $te = $ts;
        for ($i = $ts; $i < $body; $i++) {
            if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                continue;
            }
            $type .= $t->content($i);
            $te = $i;
        }
        return [$type, $ts, $te];
    }

    public static function closer(int $k): int
    {
        return match ($k) {
            40 => 41, 123 => 125, 91 => 93,
            CT::ARRAY_BRACKET_OPEN => CT::ARRAY_BRACKET_CLOSE, CT::DYNAMIC_PROP_BRACE_OPEN => CT::DYNAMIC_PROP_BRACE_CLOSE,
            CT::DYNAMIC_VAR_BRACE_OPEN => CT::DYNAMIC_VAR_BRACE_CLOSE, CT::ARRAY_INDEX_BRACE_OPEN => CT::ARRAY_INDEX_BRACE_CLOSE,
            CT::GROUP_IMPORT_BRACE_OPEN => CT::GROUP_IMPORT_BRACE_CLOSE, CT::DESTRUCTURING_BRACKET_OPEN => CT::DESTRUCTURING_BRACKET_CLOSE,
            CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN => CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE,
            CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN => CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE,
            CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_OPEN => CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE,
            \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES => CT::DOLLAR_CLOSE_CURLY_BRACES, CT::PROPERTY_HOOK_BRACE_OPEN => CT::PROPERTY_HOOK_BRACE_CLOSE,
            \__McTokId::T_ATTRIBUTE => CT::ATTRIBUTE_CLOSE,
            default => -1,
        };
    }

    /** @return array<string, array{0:string,1:int,2:int}|null> FunctionsAnalyzer::getFunctionArguments: name => type analysis */
    public function arguments(int $q): array
    {
        $t = $this->t;
        $open = $this->nextOfKind($q, 40);
        $out = [];
        if ($open < 0) {
            return $out;
        }
        $close = $t->blockEnd($open, 41);
        if ($this->k($t->meaningful($open, 1)) === 41) {
            return $out;
        }
        $ranges = [];
        $p = $open + 1;
        $as = $p;
        for (; $p < $close; $p++) {
            if (!$t->isCode($p)) {
                continue;
            }
            $cl = self::closer($t->k[$p]);
            if ($cl >= 0) {
                $p = $t->blockEnd($p, $cl);
                continue;
            }
            if ($t->k[$p] === 44) {
                if ($t->meaningful($p, 1) === $close) {
                    break;
                }
                $ranges[$as] = $p - 1;
                $as = $p + 1;
            }
        }
        $ranges[$as] = $p - 1;
        foreach ($ranges as $a => $b) {
            $info = $this->argumentInfo($a, $b);
            $out[$info[0]] = $info[1];
        }
        return $out;
    }

    /** @return array{0:string,1:array{0:string,1:int,2:int}|null} ArgumentsAnalyzer::getArgumentInfo: name ('' when none), type */
    public function argumentInfo(int $a, int $b): array
    {
        $t = $this->t;
        $name = null;
        $type = null;
        $ts = 0;
        $te = 0;
        for ($q = $a; $q <= $b; $q++) {
            $k = $t->k[$q];
            if ($k === \__McTokId::T_ATTRIBUTE) {
                $q = $t->blockEnd($q, CT::ATTRIBUTE_CLOSE);
                continue;
            }
            if ($k === CT::PROPERTY_HOOK_BRACE_OPEN) {
                $q = $t->blockEnd($q, CT::PROPERTY_HOOK_BRACE_CLOSE);
                continue;
            }
            if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT || $k === \__McTokId::T_ELLIPSIS || $k === \__McTokId::T_FINAL
                || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED
                || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET
                || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET || $k === 38
                || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                continue;
            }
            if ($k === \__McTokId::T_VARIABLE) {
                if ($name !== null) {
                    continue;
                }
                $name = $t->content($q);
                continue;
            }
            if ($k === 61) {
                continue;
            }
            if ($name === null) {
                $ts = $ts > 0 ? $ts : $q;
                $te = $q;
                $type = ($type ?? '') . $t->content($q);
            }
        }
        if ($name === null) {
            return ['', null];
        }
        return [$name, $type === null ? null : [$type, $ts, $te]];
    }

    /** @param array<int, array{0:int,1:string,2:int}> $items */
    public function overrideRange(int $a, int $b, array $items): void
    {
        $t = $this->t;
        $span = $b - $a + 1;
        $cnt = \count($items);
        if ($cnt > $span) {
            $ph = [];
            while ($cnt > $span) {
                $ph[] = FlatTokens::code(0, '__PLACEHOLDER__');
                $span++;
            }
            $t->insertAt($b + 1, $ph);
        }
        foreach ($items as $j => $it) {
            $t->k[$a + $j] = $it[0];
            $t->c[$a + $j] = $it[1];
            $t->i[$a + $j] = $it[2];
        }
        for ($j = $a + $cnt; $j <= $b; $j++) {
            $t->clearAt($j);
        }
    }
}
