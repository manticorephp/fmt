<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/** Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitSetUpTearDownVisibilityFixer (priority 0, risky). A public `setUp()` / `tearDown()` becomes protected, one without a visibility gets `protected `. */
final class PhpUnitSetUpTearDownVisibility extends PhpUnitClassRule
{
    public function name(): string
    {
        return 'php_unit_set_up_tear_down_visibility';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $counter = 0;
        /** @var array<int, bool> $slices */
        $slices = [];
        for ($index = $start + 1; $index < $end; $index++) {
            if ($counter === 2) {
                break;
            }
            if (self::is($t, $index, 123)) {
                $index = $t->blockEnd($index, 125);
                continue;
            }
            if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $nameIdx = $t->meaningful($index, 1);
            $name = \strtolower($t->content($nameIdx));
            if ($name !== 'setup' && $name !== 'teardown') {
                continue;
            }
            $counter++;
            $visibility = 0;
            for ($i = $index; $i >= 0; $i--) {
                $i = $t->meaningful($i, -1);
                if ($i < 0) {
                    break;
                }
                $k = $t->i[$i] === -1 ? -1 : $t->k[$i];
                if ($k === \__McTokId::T_STATIC || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_ABSTRACT) {
                    continue;
                }
                if ($k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC) {
                    $visibility = $k;
                    continue;
                }
                break;
            }
            if ($visibility === \__McTokId::T_PUBLIC) {
                for ($j = $index - 1; $j >= 0; $j--) {
                    if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_PUBLIC) {
                        self::setCode($t, $j, \__McTokId::T_PROTECTED, 'protected');
                        break;
                    }
                }
                continue;
            }
            if ($visibility === 0) {
                $slices[$index] = true;
            }
        }
        \krsort($slices);
        foreach ($slices as $at => $_) {
            $t->insertAt($at, [FlatTokens::code(\__McTokId::T_PROTECTED, 'protected'), FlatTokens::ws(' ')]);
        }
    }
}
