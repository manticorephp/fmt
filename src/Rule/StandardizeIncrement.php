<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\StandardizeIncrementFixer (priority 16). `$i += 1;` -> `++$i;` and `$i -= 1` -> `--$i` where an expression ends right
 * behind the `1` (`;` `)` `]` `,` `:` close tag, dynamic brace closers): from the start of the variable chain the old tokens up to the `1` are cleared
 * (comments and pure line-break whitespace stay) and `++` / `--` is inserted before it. Whole-file FlatTokens port, from the last token to the first.
 */
final class StandardizeIncrement extends FlatRule
{
    private IncrementOps $ops;

    public function __construct()
    {
        parent::__construct();
        $this->ops = new IncrementOps();
    }

    public function name(): string
    {
        return 'standardize_increment';
    }

    public function priority(): int
    {
        return 16;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i + 2 < $s->n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_PLUS_EQUAL || $k === \__McTokId::T_MINUS_EQUAL) && $s->kind[$i + 1] === \__McTokId::T_LNUMBER && $s->end[$i + 1] - $s->start[$i + 1] === 1
                && $s->code[$s->start[$i + 1]] === '1') {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if ($t->i[$q] === -1) {
                continue;
            }
            $k = $t->k[$q];
            if (!($k === 59 || $k === 41 || $k === 93 || $k === 44 || $k === 58 || $k === CT::DYNAMIC_PROP_BRACE_CLOSE || $k === CT::DYNAMIC_VAR_BRACE_CLOSE
                || $k === \__McTokId::T_CLOSE_TAG)) {
                continue;
            }
            $num = $t->meaningful($q, -1);
            if (!self::is($t, $num, \__McTokId::T_LNUMBER) || $t->content($num) !== '1') {
                continue;
            }
            $op = $t->meaningful($num, -1);
            if (!(self::is($t, $op, \__McTokId::T_PLUS_EQUAL) || self::is($t, $op, \__McTokId::T_MINUS_EQUAL))) {
                continue;
            }
            $start = $this->ops->findStart($t, $op);
            if ($start < 0) {
                continue;
            }
            $plus = $t->k[$op] === \__McTokId::T_PLUS_EQUAL;
            $from = $t->meaningful($op, -1) + 1;
            for ($i = $from; $i <= $num; $i++) {
                if ($t->isComment($i)) {
                    continue;
                }
                if ($t->k[$i] === FlatTokens::WS && $t->i[$i] === -1 && $t->c[$i] !== '' && \strspn($t->c[$i], "\n\r") === \strlen($t->c[$i])) {
                    continue;
                }
                $t->clearAt($i);
            }
            $t->insertAt($start, [FlatTokens::code($plus ? \__McTokId::T_INC : \__McTokId::T_DEC, $plus ? '++' : '--')]);
        }
    }
}
