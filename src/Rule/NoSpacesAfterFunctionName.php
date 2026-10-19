<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\NoSpacesAfterFunctionNameFixer (priority 3). A plain `(` loses the whitespace
 * token before it when the token ahead is function-like (a name that is not a definition, a variable, a language construct, a
 * closing `)` / `]` / index brace). A comment in the gap makes the comment the "previous non-whitespace", which never matches.
 */
final class NoSpacesAfterFunctionName extends GapRule
{
    /** @var array<int, bool> */
    private array $functiony = [];
    /** @var array<int, bool> */
    private array $stmtLike = [];

    public function __construct()
    {
        foreach ([\__McTokId::T_ARRAY, \__McTokId::T_ECHO, \__McTokId::T_EMPTY, \__McTokId::T_EVAL, \__McTokId::T_EXIT, \__McTokId::T_INCLUDE,
            \__McTokId::T_INCLUDE_ONCE, \__McTokId::T_ISSET, \__McTokId::T_LIST, \__McTokId::T_PRINT, \__McTokId::T_REQUIRE,
            \__McTokId::T_REQUIRE_ONCE, \__McTokId::T_UNSET, \__McTokId::T_VARIABLE] as $k) {
            $this->functiony[$k] = true;
        }
        foreach ([\__McTokId::T_ECHO, \__McTokId::T_PRINT, \__McTokId::T_INCLUDE, \__McTokId::T_INCLUDE_ONCE, \__McTokId::T_REQUIRE,
            \__McTokId::T_REQUIRE_ONCE] as $k) {
            $this->stmtLike[$k] = true;
        }
    }

    public function name(): string
    {
        return 'no_spaces_after_function_name';
    }

    public function priority(): int
    {
        return 3;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function beforeKinds(): array
    {
        return [40];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($gap === '' || $i === 0 || $i >= $s->n || $s->kind[$i] !== 40 || $s->ct[$i] !== 0 || Gap::hasComment($gap)) {
            return $gap;
        }
        $p = $i - 1;
        $c = $s->ct[$p];
        $k = $s->kind[$p];
        if ($c !== 0) {
            return $c === CT::DYNAMIC_VAR_BRACE_CLOSE || $c === CT::ARRAY_INDEX_BRACE_CLOSE ? '' : $gap;
        }
        if (isset($this->stmtLike[$k])) {
            $after = $s->pair[$i] + 1;
            if ($after < $s->n && $s->kind[$after] !== 59) {
                return $gap;
            }
        }
        if (isset($this->functiony[$k]) || $k === 41 || $k === 93) {
            return '';
        }
        if ($k === \__McTokId::T_STRING && !($p > 0 && $s->kind[$p - 1] === \__McTokId::T_FUNCTION && $s->ct[$p - 1] === 0)) {
            return '';
        }
        return $gap;
    }
}
