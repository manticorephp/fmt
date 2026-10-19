<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NsAnalysis;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\IncrementStyleFixer (priority 15). style=pre: a statement-like `$i++` (behind `;` `{` `}` `)` or the open tag, before `;` / `)`)
 * becomes `++$i`; style=post the reverse. The old operator is cleared (its whitespace stays) and a copy is inserted at the start / behind the end of the
 * variable chain. Whole-file FlatTokens port, from the last token to the first.
 */
final class IncrementStyle extends FlatRule
{
    private IncrementOps $ops;

    public function __construct(private bool $pre)
    {
        parent::__construct();
        $this->ops = new IncrementOps();
    }

    public function name(): string
    {
        return 'increment_style';
    }

    public function priority(): int
    {
        return 15;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * style=pre on the Source: the old operator is deleted (its whitespace stays: gap + gap, two whitespace tokens upstream when both are blanks) and
     * a copy goes in front of the first token of the variable chain, where upstream's flat insertAt puts it.
     */
    public function fixFile(Source $s): void
    {
        if (!$this->pre) {
            parent::fixFile($s);
            return;
        }
        $n = $s->n;
        for ($i = 1; $i < $n - 1; $i++) {
            $k = $s->kind[$i];
            if (($k !== \__McTokId::T_INC && $k !== \__McTokId::T_DEC) || $s->ct[$i] !== 0) {
                continue;
            }
            $p = $s->kind[$i - 1];
            $pc = $s->ct[$i - 1];
            if (!(($pc === 0 && ($p === 93 || $p === \__McTokId::T_STRING || $p === \__McTokId::T_VARIABLE)) || $pc === CT::ARRAY_INDEX_BRACE_CLOSE
                || $pc === CT::DYNAMIC_PROP_BRACE_CLOSE || $pc === CT::DYNAMIC_VAR_BRACE_CLOSE)) {
                continue;
            }
            $nx = $s->kind[$i + 1];
            if ($s->ct[$i + 1] !== 0 || ($nx !== 59 && $nx !== 41)) {
                continue;
            }
            $start = self::findStartSrc($s, $i);
            if ($start <= 0) {
                continue;
            }
            $b = $s->kind[$start - 1];
            if (!($b === \__McTokId::T_OPEN_TAG || ($s->ct[$start - 1] === 0 && ($b === 59 || $b === 123 || $b === 125 || $b === 41)))) {
                continue;
            }
            $g1 = $s->gapBefore[$i];
            $g2 = $s->gapBefore[$i + 1];
            $split = $g1 !== '' && $g2 !== '' && \strspn($g1, " \t\r\n") === \strlen($g1) && \strspn($g2, " \t\r\n") === \strlen($g2) ? \strlen($g1) : -1;
            $s->edit(Source::gapAt($i), Source::gapAt($i + 1) + 1, $g1 . $g2, $split);
            $s->edit(Source::tokAt($start), Source::tokAt($start), $s->text($i));
        }
    }

    /** AbstractIncrementOperatorFixer::findStart over Source tokens (the argument is the operator; "previous meaningful" is the previous token) */
    private static function findStartSrc(Source $s, int $i): int
    {
        $ends = NsAnalysis::blockEndIds();
        do {
            $i--;
            if ($i < 0) {
                return -1;
            }
            $id = $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
            if (isset($ends[$id])) {
                $i = $s->pair[$i];
                if ($i < 0) {
                    return -1;
                }
            }
        } while (!($s->ct[$i] === 0 && ($s->kind[$i] === 36 || $s->kind[$i] === \__McTokId::T_VARIABLE)));
        $p = $i - 1;
        if ($p < 0) {
            return $i;
        }
        $pk = $s->kind[$p];
        if ($s->ct[$p] === 0) {
            if ($pk === 36) {
                return self::findStartSrc($s, $i);
            }
            if ($pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
                return self::findStartSrc($s, $p);
            }
            if ($pk === \__McTokId::T_DOUBLE_COLON) {
                $pp = $p - 1;
                if ($pp < 0 || !($s->ct[$pp] === 0 && ($s->kind[$pp] === \__McTokId::T_STATIC || $s->kind[$pp] === \__McTokId::T_STRING))) {
                    return self::findStartSrc($s, $p);
                }
                $j = $p;
                while ($j > 0 && $s->gapBefore[$j] === '' && $s->ct[$j - 1] === 0
                    && ($s->kind[$j - 1] === \__McTokId::T_NS_SEPARATOR || $s->kind[$j - 1] === \__McTokId::T_STATIC || $s->kind[$j - 1] === \__McTokId::T_STRING)) {
                    $j--;
                }
                return $j;
            }
        }
        return $i;
    }

    private static function leads(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === 59 || $k === 123 || $k === 125 || $k === \__McTokId::T_OPEN_TAG || $k === 41;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q >= 0; $q--) {
            if ($t->i[$q] === -1 || !($t->k[$q] === \__McTokId::T_INC || $t->k[$q] === \__McTokId::T_DEC)) {
                continue;
            }
            $item = FlatTokens::code($t->k[$q], $t->content($q));
            if ($this->pre && $this->ops->isSuccessor($t, $q)) {
                $nx = $t->meaningful($q, 1);
                if (!(self::is($t, $nx, 59) || self::is($t, $nx, 41))) {
                    continue;
                }
                $start = $this->ops->findStart($t, $q);
                if ($start < 0) {
                    continue;
                }
                if (self::leads($t, $t->meaningful($start, -1))) {
                    $t->clearAt($q);
                    $t->insertAt($start, [$item]);
                }
            } elseif (!$this->pre && !$this->ops->isSuccessor($t, $q)) {
                $p = $t->meaningful($q, -1);
                if (!self::leads($t, $p)) {
                    continue;
                }
                $end = $this->ops->findEnd($t, $q);
                $nx = $t->meaningful($end, 1);
                if (self::is($t, $nx, 59) || self::is($t, $nx, 41)) {
                    $t->clearAt($q);
                    $t->insertAt($t->nonWhitespace($end, 1), [$item]);
                }
            }
        }
    }
}
