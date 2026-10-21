<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/** Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitMockShortWillReturnFixer (priority 0, risky). `->will($this->returnValue(1))` -> `->willReturn(1)` (returnArgument / returnCallback / returnSelf / returnValueMap too). */
final class PhpUnitMockShortWillReturn extends PhpUnitClassRule
{
    public function name(): string
    {
        return 'php_unit_mock_short_will_return';
    }

    public function priority(): int
    {
        return 0;
    }

    private static function target(string $lower): string
    {
        return match ($lower) {
            'returnargument' => 'willReturnArgument',
            'returncallback' => 'willReturnCallback',
            'returnself' => 'willReturnSelf',
            'returnvalue' => 'willReturn',
            'returnvaluemap' => 'willReturnMap',
            default => '',
        };
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $start; $index < $end; $index++) {
            if ($t->i[$index] === -1 || !($t->k[$index] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$index] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                continue;
            }
            $will = $t->meaningful($index, 1);
            if ($will < 0 || $t->k[$will] !== \__McTokId::T_STRING || \strtolower($t->content($will)) !== 'will') {
                continue;
            }
            $open = $t->meaningful($will, 1);
            if (!self::is($t, $open, 40)) {
                continue;
            }
            $ref = $t->meaningful($open, 1);
            $op = $ref >= 0 ? $t->meaningful($ref, 1) : -1;
            $remove = $op >= 0 ? $t->meaningful($op, 1) : -1;
            if ($remove < 0 || !PhpUnitConstruct::sameClassCall($t, $remove)) {
                continue;
            }
            $to = self::target(\strtolower($t->content($remove)));
            if ($to === '') {
                continue;
            }
            $oBrace = $t->meaningful($remove, 1);
            $afterOpen = $t->meaningful($oBrace, 1);
            if ($afterOpen >= 0 && $t->i[$afterOpen] !== -1 && $t->k[$afterOpen] === CT::FIRST_CLASS_CALLABLE) {
                continue;
            }
            $cBrace = $t->blockEnd($oBrace, 41);
            self::setCode($t, $will, \__McTokId::T_STRING, $to);
            self::clearMerge($t, $ref);
            self::clearMerge($t, $op);
            self::clearMerge($t, $remove);
            self::clearMerge($t, $oBrace);
            self::clearMerge($t, $cBrace);
            $comma = $t->meaningful($cBrace, 1);
            if (self::is($t, $comma, 44)) {
                self::clearMerge($t, $comma);
            }
        }
    }
}
