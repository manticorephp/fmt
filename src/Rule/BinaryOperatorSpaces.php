<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer (priority -32) for the non-aligning strategies
 * single_space, at_least_single_space, no_space (per operator, `default` + `operators`). The align* strategies are a string
 * rewrite of the whole file upstream and are not implemented (the factory refuses them).
 *
 * The whitespace token before / after an operator is the last / first trivia piece of the gap. An existing whitespace
 * token is only rewritten when it is the whole gap: upstream refuses when the neighbour past it is a comment, and that neighbour
 * is a piece of the same gap. So a gap with a comment can only gain a blank (single / at-least), never lose one. An operator's
 * before-part runs before the previous operator's after-part (upstream walks backwards); `no_space` leaves an empty
 * whitespace token behind that still counts as one.
 */
final class BinaryOperatorSpaces extends GapRule
{
    public const SINGLE = 1;
    public const AT_LEAST = 2;
    public const NONE = 3;

    /** @var array<int, int> kind => mode, operators with one text per kind */
    private array $byKind = [];
    private int $modeNe = 0;
    private int $modeNeAlt = 0;
    private UnaryOperatorSpaces $unary;

    /** @param array<string, int> $modes operator text (lower case) => SINGLE | AT_LEAST | NONE; an absent operator is left alone */
    public function __construct(array $modes)
    {
        $this->unary = new UnaryOperatorSpaces(false);
        $kinds = [
            '=' => 61, '*' => 42, '/' => 47, '%' => 37, '<' => 60, '>' => 62, '|' => 124, '^' => 94, '+' => 43, '-' => 45, '&' => 38,
            '&=' => \__McTokId::T_AND_EQUAL, '&&' => \__McTokId::T_BOOLEAN_AND, '||' => \__McTokId::T_BOOLEAN_OR,
            '.=' => \__McTokId::T_CONCAT_EQUAL, '/=' => \__McTokId::T_DIV_EQUAL, '=>' => \__McTokId::T_DOUBLE_ARROW,
            '==' => \__McTokId::T_IS_EQUAL, '>=' => \__McTokId::T_IS_GREATER_OR_EQUAL, '===' => \__McTokId::T_IS_IDENTICAL,
            '!==' => \__McTokId::T_IS_NOT_IDENTICAL, '<=' => \__McTokId::T_IS_SMALLER_OR_EQUAL,
            'and' => \__McTokId::T_LOGICAL_AND, 'or' => \__McTokId::T_LOGICAL_OR, 'xor' => \__McTokId::T_LOGICAL_XOR,
            '-=' => \__McTokId::T_MINUS_EQUAL, '%=' => \__McTokId::T_MOD_EQUAL, '*=' => \__McTokId::T_MUL_EQUAL,
            '|=' => \__McTokId::T_OR_EQUAL, '+=' => \__McTokId::T_PLUS_EQUAL, '<<' => \__McTokId::T_SL, '<<=' => \__McTokId::T_SL_EQUAL,
            '>>' => \__McTokId::T_SR, '>>=' => \__McTokId::T_SR_EQUAL, '^=' => \__McTokId::T_XOR_EQUAL, '**' => \__McTokId::T_POW,
            '**=' => \__McTokId::T_POW_EQUAL, '<=>' => \__McTokId::T_SPACESHIP, '??' => \__McTokId::T_COALESCE,
            '??=' => \__McTokId::T_COALESCE_EQUAL,
        ];
        foreach ($kinds as $text => $k) {
            if (isset($modes[$text])) {
                $this->byKind[$k] = $modes[$text];
            }
        }
        if (isset($modes['&'])) {
            $this->byKind[\__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG] = $modes['&'];
            $this->byKind[\__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG] = $modes['&'];
        }
        $this->modeNe = $modes['!='] ?? 0;
        $this->modeNeAlt = $modes['<>'] ?? 0;
        if ($this->modeNe !== 0 || $this->modeNeAlt !== 0) {
            $this->byKind[\__McTokId::T_IS_NOT_EQUAL] = 1; // resolved by text in modeAt
        }
    }

    public function name(): string
    {
        return 'binary_operator_spaces';
    }

    public function priority(): int
    {
        return -32;
    }

    public function needsMask(): int
    {
        return 0;
    }

    /** @return int[] */
    public function afterKinds(): array
    {
        return $this->kinds();
    }

    /** @return int[] */
    public function beforeKinds(): array
    {
        return $this->kinds();
    }

    /** @return int[] */
    private function kinds(): array
    {
        $out = [];
        foreach ($this->byKind as $k => $_) {
            $out[] = $k;
        }
        return $out;
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
        $before = $i >= 1 && $i < $s->n ? $this->modeAt($s, $i) : 0;
        $after = $i >= 2 && $i <= $s->n ? $this->modeAt($s, $i - 1) : 0;
        if ($before === 0 && $after === 0) {
            return $gap;
        }
        if ($gap !== '' && Gap::hasComment($gap)) {
            if ($before !== self::NONE && $before !== 0 && !Gap::isSpace(\substr($gap, -1))) {
                $gap .= ' ';
            }
            if ($after !== self::NONE && $after !== 0 && !Gap::isSpace($gap[0])) {
                $gap = ' ' . $gap;
            }
            return $gap;
        }
        $cleared = false;
        foreach ([$before, $after] as $mode) {
            $present = $cleared || $gap !== '';
            if ($mode === self::SINGLE) {
                if (!$present || ($gap !== ' ' && \strpos($gap, "\n") === false)) {
                    $gap = ' ';
                }
            } elseif ($mode === self::AT_LEAST) {
                if (!$present) {
                    $gap = ' ';
                }
            } elseif ($mode === self::NONE && $gap !== '' && \strpos($gap, "\n") === false) {
                $gap = '';
                $cleared = true;
            }
        }
        return $gap;
    }

    private function modeAt(Source $s, int $i): int
    {
        $k = $s->kind[$i];
        $c = $s->ct[$i];
        if ($c !== 0 && $c !== CT::RETURN_REF) {
            return 0;
        }
        if ($c === CT::RETURN_REF) {
            $m = $this->byKind[38] ?? 0;
            return $m !== 0 && !$this->unary->isPredecessor($s, $i) ? $m : 0;
        }
        $m = $this->byKind[$k] ?? 0;
        if ($m === 0) {
            return 0;
        }
        if ($k === 43 || $k === 45 || $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
            || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
            return $this->unary->isPredecessor($s, $i) ? 0 : $m;
        }
        if ($k === \__McTokId::T_IS_NOT_EQUAL) {
            return $s->text($i) === '<>' ? $this->modeNeAlt : $this->modeNe;
        }
        if ($k === 61 && $i >= 3 && $s->kind[$i - 1] === \__McTokId::T_STRING && $s->ct[$i - 1] === 0
            && $s->kind[$i - 2] === 40 && $s->ct[$i - 2] === 0 && $s->kind[$i - 3] === \__McTokId::T_DECLARE) {
            return 0;
        }
        return $m;
    }
}
