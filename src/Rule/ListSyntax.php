<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ListNotation\ListSyntaxFixer (priority 2). `short`: `list($a)` -> `[$a]` (the `list` token goes with its whitespace merged);
 * `long`: `[$a] = ..` -> `list($a) = ..`. Whole-file FlatTokens port, last token first.
 */
final class ListSyntax extends FlatRule
{
    public function __construct(private bool $short)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'list_syntax';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($this->short ? ($s->kind[$i] === \__McTokId::T_LIST && $s->ct[$i] === 0) : $s->ct[$i] === CT::DESTRUCTURING_BRACKET_OPEN) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $want = $this->short ? \__McTokId::T_LIST : CT::DESTRUCTURING_BRACKET_OPEN;
        for ($q = $t->count() - 1; $q >= 0; $q--) {
            if (!self::is($t, $q, $want)) {
                continue;
            }
            if ($this->short) {
                $open = self::nextOf($t, $q, 40);
                $close = $t->blockEnd($open, 41);
                self::setCode($t, $open, CT::DESTRUCTURING_BRACKET_OPEN, '[');
                self::setCode($t, $close, CT::DESTRUCTURING_BRACKET_CLOSE, ']');
                self::clearMerge($t, $q);
            } else {
                $close = self::nextOfAny($t, $q, [CT::DESTRUCTURING_BRACKET_CLOSE, 91]);
                if ($close < 0 || $t->k[$close] !== CT::DESTRUCTURING_BRACKET_CLOSE) {
                    continue;
                }
                self::setCode($t, $q, 40, '(');
                self::setCode($t, $close, 41, ')');
                $t->insertAt($q, [FlatTokens::code(\__McTokId::T_LIST, 'list')]);
            }
        }
    }
}
