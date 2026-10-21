<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitStrictFixer (priority 0, risky). `$this->assertEquals(a, b)` -> `assertSame` (also assertNotEquals and the deprecated assertAttribute* pair) for a
 * 2 or 3 argument call; the search runs to the end of the file, as upstream's.
 */
final class PhpUnitStrict extends PhpUnitClassRule
{
    /** @param string[] $assertions */
    public function __construct(private array $assertions)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_strict';
    }

    public function priority(): int
    {
        return 0;
    }

    private static function after(string $m): string
    {
        return match ($m) {
            'assertAttributeEquals' => 'assertAttributeSame',
            'assertAttributeNotEquals' => 'assertAttributeNotSame',
            'assertEquals' => 'assertSame',
            default => 'assertNotSame',
        };
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $m = $t->count();
        foreach ($this->assertions as $before) {
            $after = self::after($before);
            for ($index = $start; $index < $end; $index++) {
                $mi = -1;
                for ($j = $index + 1; $j < $m; $j++) {
                    if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_STRING && $t->content($j) === $before) {
                        $mi = $j;
                        break;
                    }
                }
                if ($mi < 0) {
                    break;
                }
                if (!PhpUnitConstruct::sameClassCall($t, $mi)) {
                    continue;
                }
                $open = $t->meaningful($mi, 1);
                $count = \count(self::callArguments($t, $open, $t->blockEnd($open, 41)));
                if ($count === 2 || $count === 3) {
                    self::setCode($t, $mi, \__McTokId::T_STRING, $after);
                }
                $index = $mi;
            }
        }
    }
}
