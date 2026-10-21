<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/** Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitMockFixer (priority 0, risky). `->getMock($class)` -> `->createMock($class)`, `->getMockWithoutInvokingTheOriginalConstructor()` -> `->createMock()`, a 2-argument `->getMock()` -> `->createPartialMock()` (target 5.5+ / newest). */
final class PhpUnitMock extends PhpUnitClassRule
{
    private bool $partial;

    public function __construct(string $target)
    {
        parent::__construct();
        $this->partial = $target !== '5.4';
    }

    public function name(): string
    {
        return 'php_unit_mock';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $start; $index < $end; $index++) {
            if ($t->i[$index] === -1 || !($t->k[$index] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$index] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                continue;
            }
            $index = $t->meaningful($index, 1);
            if ($index < 0 || $t->k[$index] !== \__McTokId::T_STRING) {
                continue;
            }
            $name = \strtolower($t->content($index));
            if ($name === 'getmockwithoutinvokingtheoriginalconstructor') {
                self::setCode($t, $index, \__McTokId::T_STRING, 'createMock');
            } elseif ($name === 'getmock') {
                $open = $t->meaningful($index, 1);
                $close = $t->blockEnd($open, 41);
                $count = \count(self::callArguments($t, $open, $close));
                if ($count === 1) {
                    self::setCode($t, $index, \__McTokId::T_STRING, 'createMock');
                } elseif ($count === 2 && $this->partial) {
                    self::setCode($t, $index, \__McTokId::T_STRING, 'createPartialMock');
                }
            }
        }
    }
}
