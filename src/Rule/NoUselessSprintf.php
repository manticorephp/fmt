<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\FunctionNotation\NoUselessSprintfFixer (priority 42, risky). `sprintf('x')` -> `'x'`. Whole-file FlatTokens port. */
final class NoUselessSprintf extends FlatRule
{
    public function name(): string
    {
        return 'no_useless_sprintf';
    }

    public function priority(): int
    {
        return 42;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['sprintf']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if ($t->i[$q] === -1 || $t->k[$q] !== \__McTokId::T_STRING || \strtolower($t->content($q)) !== 'sprintf') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $q)) {
                continue;
            }
            $open = self::nextOf($t, $q, 40);
            $first = $t->meaningful($open, 1);
            if (self::is($t, $first, \__McTokId::T_ELLIPSIS)) {
                continue;
            }
            $close = $t->blockEnd($open, 41);
            if (\count(self::callArguments($t, $open, $close)) !== 1) {
                continue;
            }
            self::clearMerge($t, $close);
            $prev = $t->meaningful($close, -1);
            if (self::is($t, $prev, 44)) {
                self::clearMerge($t, $prev);
            }
            self::clearMerge($t, $open);
            self::clearMerge($t, $q);
            $prev = $t->meaningful($q, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                self::clearMerge($t, $prev);
            }
        }
    }
}
