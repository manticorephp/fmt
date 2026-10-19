<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\CastNotation\NoUnsetCastFixer (priority 0). `$a = (unset) $b;` -> `$a = null;`: the cast and the variable are cleared
 * with their whitespace merged, `null` goes in behind the first whitespace after `=` (a space is added when none follows it). Whole-file FlatTokens port.
 */
final class NoUnsetCast extends FlatRule
{
    public function name(): string
    {
        return 'no_unset_cast';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_UNSET_CAST) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if (!self::is($t, $q, \__McTokId::T_UNSET_CAST)) {
                continue;
            }
            $a = $t->meaningful($q, -1);
            if (!self::is($t, $a, 61)) {
                continue;
            }
            $v = $t->meaningful($q, 1);
            if (!self::is($t, $v, \__McTokId::T_VARIABLE)) {
                continue;
            }
            $e = $t->meaningful($v, 1);
            if (!(self::is($t, $e, 59) || self::is($t, $e, \__McTokId::T_CLOSE_TAG))) {
                continue;
            }
            $nextIsWs = $t->isWs($a + 1);
            self::clearMerge($t, $q);
            self::clearMerge($t, $v);
            $a++;
            if (!$nextIsWs) {
                $t->insertAt($a, [FlatTokens::ws(' ')]);
            }
            $a++;
            $t->insertAt($a, [FlatTokens::code(\__McTokId::T_STRING, 'null')]);
        }
    }
}
