<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\UnaryOperatorSpacesFixer (priority 0) with TokensAnalyzer::isUnarySuccessorOperator /
 * isUnaryPredecessorOperator. A predecessor operator (`!`, `~`, `@`, `...`, `++$a`, unary `+ - &`) loses the whitespace token
 * after it, a successor (`$a++`) the whitespace token before it unless a comment sits there. Both only empty a gap's
 * whitespace, so the rule is a gap function triggered by the operator kinds; an empty gap is never touched.
 */
final class UnaryOperatorSpaces extends GapRule
{
    /** @var array<int, bool> effective kinds (custom kind when there is one) after which `+ - &` are binary */
    private array $binaryPrev = [];

    public function __construct(private bool $onlyDecInc = false)
    {
        foreach ([93, 125, 41, 34, 96, CT::ARRAY_BRACKET_CLOSE, CT::ARRAY_INDEX_BRACE_CLOSE, CT::DYNAMIC_PROP_BRACE_CLOSE,
            CT::DYNAMIC_VAR_BRACE_CLOSE, \__McTokId::T_CLASS_C, \__McTokId::T_CONSTANT_ENCAPSED_STRING, \__McTokId::T_DEC,
            \__McTokId::T_DIR, \__McTokId::T_DNUMBER, \__McTokId::T_FILE, \__McTokId::T_FUNC_C, \__McTokId::T_INC,
            \__McTokId::T_LINE, \__McTokId::T_LNUMBER, \__McTokId::T_METHOD_C, \__McTokId::T_NS_C, \__McTokId::T_STRING,
            \__McTokId::T_TRAIT_C, \__McTokId::T_VARIABLE] as $k) {
            $this->binaryPrev[$k] = true;
        }
    }

    public function name(): string
    {
        return 'unary_operator_spaces';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    /** @return int[] */
    public function afterKinds(): array
    {
        $k = [\__McTokId::T_INC, \__McTokId::T_DEC];
        if (!$this->onlyDecInc) {
            \array_push($k, 33, 126, 64, \__McTokId::T_ELLIPSIS, 43, 45, 38, \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG);
        }
        return $k;
    }

    public function beforeKinds(): array
    {
        return [\__McTokId::T_INC, \__McTokId::T_DEC];
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
        if ($gap === '') {
            return $gap;
        }
        // upstream walks the tokens backwards: the successor operator after the gap goes first
        if ($i < $s->n && !Gap::hasComment($gap) && self::isIncDec($s, $i) && $this->isSuccessor($s, $i)) {
            return '';
        }
        if ($i > 0 && $this->isPredecessor($s, $i - 1)) {
            $c = \ord($gap[0]);
            if ($c !== 35 && $c !== 47) {
                return \substr($gap, \strcspn($gap, '#/'));
            }
        }
        return $gap;
    }

    private static function isIncDec(Source $s, int $i): bool
    {
        $k = $s->kind[$i];
        return ($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) && $s->ct[$i] === 0;
    }

    private function isSuccessor(Source $s, int $i): bool
    {
        $p = $i - 1;
        $c = $s->ct[$p];
        if ($c !== 0) {
            return $c === CT::ARRAY_INDEX_BRACE_CLOSE || $c === CT::DYNAMIC_PROP_BRACE_CLOSE || $c === CT::DYNAMIC_VAR_BRACE_CLOSE;
        }
        $k = $s->kind[$p];
        return $k === 93 || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE;
    }

    private function isPredecessor(Source $s, int $i): bool
    {
        $k = $s->kind[$i];
        $c = $s->ct[$i];
        if (($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) && $c === 0) {
            return !$this->isSuccessor($s, $i);
        }
        if ($this->onlyDecInc) {
            return false;
        }
        if ($c === 0 && ($k === 33 || $k === 126 || $k === 64 || $k === \__McTokId::T_ELLIPSIS)) {
            return true;
        }
        $amp = $c === 0 && ($k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG);
        if (!(($c === 0 && ($k === 43 || $k === 45)) || $c === CT::RETURN_REF || $amp) || $i === 0) {
            return false;
        }
        $p = $i - 1;
        $pk = $s->ct[$p] !== 0 ? $s->ct[$p] : $s->kind[$p];
        if (!isset($this->binaryPrev[$pk])) {
            return true;
        }
        if (!$amp || $s->kind[$p] !== \__McTokId::T_STRING || $s->ct[$p] !== 0) {
            return false;
        }
        for ($j = $p - 1; $j >= 0; $j--) {
            if ($s->ct[$j] !== 0) {
                continue;
            }
            $q = $s->kind[$j];
            if ($q === \__McTokId::T_FN || $q === \__McTokId::T_FUNCTION) {
                return true;
            }
            if ($q === 59 || $q === 123 || $q === 125 || $q === \__McTokId::T_DOUBLE_ARROW || $q === \__McTokId::T_OPEN_TAG || $q === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return false;
            }
        }
        return false;
    }
}
