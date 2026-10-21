<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\FunctionNotation\NoUselessPrintfFixer (priority 10, risky). A statement `printf('x')` with the format only becomes `print 'x'` (the `print ` is inserted at the end, as upstream's insertSlices). Whole-file FlatTokens port. */
final class NoUselessPrintf extends FlatRule
{
    public function name(): string
    {
        return 'no_useless_printf';
    }

    public function priority(): int
    {
        return 10;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['printf']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, bool> $indices */
        $indices = [];
        for ($index = $t->count() - 1; $index > 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING || \strtolower($t->content($index)) !== 'printf') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $open = self::nextOf($t, $index, 40);
            $first = $t->meaningful($open, 1);
            if ($first >= 0 && $t->i[$first] !== -1 && ($t->k[$first] === \__McTokId::T_ELLIPSIS || $t->k[$first] === CT::FIRST_CLASS_CALLABLE)) {
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
            self::clearMerge($t, $index);
            $prev = $t->meaningful($index, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                self::clearMerge($t, $prev);
            }
            $indices[$index] = true;
        }
        \krsort($indices);
        foreach ($indices as $at => $_) {
            $t->insertAt($at, [FlatTokens::code(\__McTokId::T_PRINT, 'print'), FlatTokens::ws(' ')]);
        }
    }
}
