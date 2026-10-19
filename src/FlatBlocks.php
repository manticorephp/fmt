<?php

declare(strict_types=1);

namespace Fmt;

/** Tokens::detectBlockType / findBlockStart / findBlockEnd over a FlatTokens (effective ids), for the edge definitions of Tokens::getBlockEdgeDefinitions. */
final class FlatBlocks
{
    /** @var array<int, int> closer id => opener id */
    private array $open = [];
    /** @var array<int, int> opener id => closer id */
    private array $close = [];

    public function __construct()
    {
        $pairs = [[123, 125], [40, 41], [91, 93], [CT::ARRAY_BRACKET_OPEN, CT::ARRAY_BRACKET_CLOSE], [CT::DYNAMIC_PROP_BRACE_OPEN, CT::DYNAMIC_PROP_BRACE_CLOSE],
            [CT::DYNAMIC_VAR_BRACE_OPEN, CT::DYNAMIC_VAR_BRACE_CLOSE], [CT::ARRAY_INDEX_BRACE_OPEN, CT::ARRAY_INDEX_BRACE_CLOSE],
            [CT::GROUP_IMPORT_BRACE_OPEN, CT::GROUP_IMPORT_BRACE_CLOSE], [CT::DESTRUCTURING_BRACKET_OPEN, CT::DESTRUCTURING_BRACKET_CLOSE],
            [CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN, CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE],
            [CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN, CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE],
            [CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_OPEN, CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE], [\__McTokId::T_DOLLAR_OPEN_CURLY_BRACES, CT::DOLLAR_CLOSE_CURLY_BRACES],
            [CT::PROPERTY_HOOK_BRACE_OPEN, CT::PROPERTY_HOOK_BRACE_CLOSE], [\__McTokId::T_ATTRIBUTE, CT::ATTRIBUTE_CLOSE]];
        foreach ($pairs as [$o, $c]) {
            $this->open[$c] = $o;
            $this->close[$o] = $c;
        }
    }

    /** the code item at $q is a block opener */
    public function isStart(FlatTokens $t, int $q): bool
    {
        return $t->i[$q] !== -1 && isset($this->close[$t->k[$q]]);
    }

    /** the code item at $q is a block closer */
    public function isEnd(FlatTokens $t, int $q): bool
    {
        return $t->i[$q] !== -1 && isset($this->open[$t->k[$q]]);
    }

    /** the matching closer of the opener at $q, -1 when there is none */
    public function end(FlatTokens $t, int $q): int
    {
        return $t->blockEnd($q, $this->close[$t->k[$q]]);
    }

    /** the matching opener of the closer at $q */
    public function start(FlatTokens $t, int $q): int
    {
        return $t->blockStart($q, $this->open[$t->k[$q]]);
    }
}
