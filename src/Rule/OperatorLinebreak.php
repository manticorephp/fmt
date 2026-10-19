<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\OperatorLinebreakFixer (priority 0), a file rule. The operator token stays between the same
 * two code tokens; what upstream moves (clear + insert) is the trivia around it, so each operator is processed on a FlatTokens
 * window [previous code token .. next code token] with upstream's algorithm and the trivia is stored back as gaps. Operators are
 * visited from the end; a `?` directly before a `:` is handled together with it.
 */
final class OperatorLinebreak extends GapRule
{
    /** @var array<int, bool> lexer kinds of the operators (custom id 0) */
    private array $ops = [];

    public function __construct(private bool $onlyBooleans = false, private bool $beginning = true)
    {
        foreach ([\__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR, \__McTokId::T_LOGICAL_AND, \__McTokId::T_LOGICAL_OR, \__McTokId::T_LOGICAL_XOR] as $k) {
            $this->ops[$k] = true;
        }
        if (!$onlyBooleans) {
            foreach ([37, 38, 42, 43, 45, 46, 47, 58, 60, 61, 62, 63, 94, 124, \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, \__McTokId::T_AND_EQUAL, \__McTokId::T_CONCAT_EQUAL, \__McTokId::T_DIV_EQUAL,
                \__McTokId::T_DOUBLE_ARROW, \__McTokId::T_IS_EQUAL, \__McTokId::T_IS_GREATER_OR_EQUAL, \__McTokId::T_IS_IDENTICAL,
                \__McTokId::T_IS_NOT_EQUAL, \__McTokId::T_IS_NOT_IDENTICAL, \__McTokId::T_IS_SMALLER_OR_EQUAL, \__McTokId::T_MINUS_EQUAL,
                \__McTokId::T_MOD_EQUAL, \__McTokId::T_MUL_EQUAL, \__McTokId::T_OR_EQUAL, \__McTokId::T_DOUBLE_COLON, \__McTokId::T_PLUS_EQUAL,
                \__McTokId::T_POW, \__McTokId::T_POW_EQUAL, \__McTokId::T_SL, \__McTokId::T_SL_EQUAL, \__McTokId::T_SR, \__McTokId::T_SR_EQUAL,
                \__McTokId::T_XOR_EQUAL, \__McTokId::T_COALESCE, \__McTokId::T_SPACESHIP, \__McTokId::T_PIPE, \__McTokId::T_OBJECT_OPERATOR,
                \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $k) {
                $this->ops[$k] = true;
            }
        }
    }

    public function name(): string
    {
        return 'operator_linebreak';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        // every operator window needs a line break around the operator: a file without a gap holding one is untouched
        $n = $s->n;
        for ($j = 1; $j < $n; $j++) {
            if (($s->gapFlags[$j] & (Gap::G_NL | Gap::G_CR | Gap::G_ODD)) !== 0) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $ft = new FlatTokens();
        for ($j = $n - 1; $j >= 2; $j--) {
            // upstream acts only when trivia next to the operator (the gaps before it, behind it, and before a `?` of `?:`) holds a "\n"
            if ((($s->gapFlags[$j - 1] | $s->gapFlags[$j] | $s->gapFlags[$j + 1]) & Gap::G_NL) === 0) {
                continue;
            }
            if ($s->ct[$j] !== 0 || !isset($this->ops[$s->kind[$j]])) {
                continue;
            }
            $k = $s->kind[$j];
            if ($k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                if (self::isReference($s, $j)) {
                    continue;
                }
            } elseif ($k === 58 && !TernaryOperatorSpaces::isOp($s, $j)) {
                continue; // a switch / alternative syntax / goto label colon
            }
            $lo = $j;
            if ($k === 58 && $s->kind[$j - 1] === 63 && $s->ct[$j - 1] === 0) {
                $lo = $j - 1; // `?:` is one operator
            }
            // the side upstream moves the operator away from must hold a line break: behind it for `beginning`, ahead of it for `end`
            if (($s->gapFlags[$this->beginning ? $j + 1 : $lo] & Gap::G_NL) !== 0) {
                $this->fixOperator($s, $ft, $lo, $j);
            }
            $j = $lo;
        }
    }

    /** ReferenceAnalyzer::isReference for the `&` token at $j */
    private static function isReference(Source $s, int $j): bool
    {
        $p = $j - 1;
        $pc = $s->ct[$p];
        $pk = $s->kind[$p];
        if ($pc === CT::ARRAY_TYPEHINT || ($pc === 0 && ($pk === 61 || $pk === \__McTokId::T_AS || $pk === \__McTokId::T_CALLABLE || $pk === \__McTokId::T_DOUBLE_ARROW))) {
            return true;
        }
        if ($pc === 0 && $pk === \__McTokId::T_STRING) {
            $p--;
            if ($p < 0) {
                return false;
            }
            $pc = $s->ct[$p];
            $pk = $s->kind[$p];
        }
        return $pc === CT::NULLABLE_TYPE || ($pc === 0 && ($pk === 40 || $pk === 44 || $pk === \__McTokId::T_NS_SEPARATOR));
    }

    private function fixOperator(Source $s, FlatTokens $ft, int $minOp, int $maxOp): void
    {
        $n = $s->n;
        if ($minOp < 1 || $maxOp + 1 >= $n) {
            return;
        }
        $ft->load($s, $minOp - 1, $maxOp + 1);
        $size = $ft->count();
        // view positions of the operator items
        $opLo = -1;
        $opHi = -1;
        for ($q = 0; $q < $size; $q++) {
            if ($ft->i[$q] === $minOp) {
                $opLo = $q;
            }
            if ($ft->i[$q] === $maxOp) {
                $opHi = $q;
            }
        }
        $indexStart = 1;
        $indexEnd = $size - 2;
        if (!self::multiline($ft, $indexStart, $indexEnd)) {
            return;
        }
        $ops = $minOp === $maxOp ? [$opLo] : [$opLo, $opHi];
        if ($this->beginning) {
            if (!self::multiline($ft, $opHi, $indexEnd)) {
                return;
            }
            $this->moveToBeginning($ft, $ops, $opLo, $opHi, $size - 1);
        } else {
            if (!self::multiline($ft, $indexStart, $opLo)) {
                return;
            }
            $this->moveToEnd($ft, $ops, $opLo, $opHi);
        }
        $ft->store($s);
    }

    private static function multiline(FlatTokens $ft, int $a, int $b): bool
    {
        for ($q = $a; $q <= $b; $q++) {
            if (\strpos($ft->content($q), "\n") !== false) {
                return true;
            }
        }
        return false;
    }

    /** @param int[] $ops */
    private function moveToBeginning(FlatTokens $ft, array $ops, int $opLo, int $opHi, int $nextIndex): void
    {
        $prevIndex = $ft->nonEmptySibling($opLo, -1);
        for ($q = $nextIndex - 1; $q > $opHi; $q--) {
            if ($ft->isWs($q) && Gap::pieceHasR($ft->c[$q])) {
                $isWsBefore = $ft->isWs($prevIndex);
                $inserts = $this->replacementsAndClear($ft, $ops, -1);
                if ($isWsBefore) {
                    $inserts[] = [FlatTokens::WS, ' ', -1];
                }
                $ft->insertAt($nextIndex, $inserts);
                return;
            }
        }
    }

    /** @param int[] $ops */
    private function moveToEnd(FlatTokens $ft, array $ops, int $opLo, int $opHi): void
    {
        $prevIndex = 0;
        $nextIndex = $ft->nonEmptySibling($opHi, 1);
        for ($q = $prevIndex + 1; $q < $opHi; $q++) {
            if ($ft->isWs($q) && Gap::pieceHasR($ft->c[$q])) {
                $isWsAfter = $ft->isWs($nextIndex);
                $inserts = $this->replacementsAndClear($ft, $ops, 1);
                if ($isWsAfter) {
                    \array_unshift($inserts, [FlatTokens::WS, ' ', -1]);
                }
                $ft->insertAt($prevIndex + 1, $inserts);
                return;
            }
        }
    }

    /**
     * @param int[] $ops
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private function replacementsAndClear(FlatTokens $ft, array $ops, int $dir): array
    {
        $out = [];
        foreach ($ops as $q) {
            $out[] = $ft->item($q);
            if ($ft->isWs($q + $dir)) {
                $ft->clearAt($q + $dir);
            }
            $ft->clearAt($q);
        }
        return $out;
    }
}
