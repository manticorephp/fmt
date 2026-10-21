<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitExpectationFixer (priority 0, risky). `$this->setExpectedException(Foo::class, 'msg', 7)` -> `$this->expectException(Foo::class);
 * $this->expectExceptionMessage('msg'); $this->expectExceptionCode(7);` (also setExpectedExceptionRegExp / expectExceptionMessageRegExp per target); a `null` message is dropped.
 * Upstream's quirks mirrored: the scan runs to the end of the file, a call with more than 3 arguments throws (the file stays as it is). Whole-file FlatTokens port.
 */
final class PhpUnitExpectation extends PhpUnitClassRule
{
    /** @var array<string, string> */
    private array $methodMap = [];

    public function __construct(string $target, private string $lineEnding, private string $indentCfg)
    {
        parent::__construct();
        $this->methodMap = ['setExpectedException' => 'expectExceptionMessage'];
        if ($target === 'newest' || $target === '5.6' || $target === '8.4') {
            $this->methodMap['setExpectedExceptionRegExp'] = 'expectExceptionMessageRegExp';
        }
        if ($target === 'newest' || $target === '8.4') {
            $this->methodMap['setExpectedExceptionRegExp'] = 'expectExceptionMessageMatches';
            $this->methodMap['expectExceptionMessageRegExp'] = 'expectExceptionMessageMatches';
        }
    }

    public function name(): string
    {
        return 'php_unit_expectation';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        foreach ([\__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $op) {
            if (!$this->withOperator($t, $start, $op)) {
                return;
            }
        }
    }

    private function withOperator(FlatTokens $t, int $start, int $op): bool
    {
        $m = $t->count();
        for ($index = $start; ; $index++) {
            $found = false;
            $thisIdx = -1;
            $nameIdx = -1;
            for ($j = $index; $j < $m; $j++) {
                if ($t->i[$j] === -1 || $t->k[$j] !== \__McTokId::T_VARIABLE || $t->content($j) !== '$this') {
                    continue;
                }
                $o = $t->meaningful($j, 1);
                if ($o < 0 || $t->i[$o] === -1 || $t->k[$o] !== $op) {
                    continue;
                }
                $n = $t->meaningful($o, 1);
                if ($n < 0 || $t->i[$n] === -1 || $t->k[$n] !== \__McTokId::T_STRING) {
                    continue;
                }
                $thisIdx = $j;
                $nameIdx = $n;
                $found = true;
                break;
            }
            if (!$found) {
                return true;
            }
            $index = $nameIdx;
            $name = $t->content($index);
            if (!isset($this->methodMap[$name])) {
                continue;
            }
            $newName = $this->methodMap[$name];
            $open = self::nextOf($t, $index, 40);
            $close = $t->blockEnd($open, 41);
            $comma = $t->meaningful($close, -1);
            if (self::is($t, $comma, 44)) {
                self::removeWs($t, $comma, 1);
                $t->clearAt($comma);
            }
            $args = self::callArguments($t, $open, $close);
            $cnt0 = \count($args);
            $starts = \array_keys($args);
            $replacements = ['expectException', $newName, 'expectExceptionCode'];
            $indent = $this->lineEnding . self::detectIndent($t, $thisIdx);
            $multi = false;
            for ($cnt = $cnt0 - 1; $cnt >= 1; $cnt--) {
                $argStart = $starts[$cnt];
                $argBefore = $t->meaningful($argStart, -1);
                if (!isset($replacements[$cnt])) {
                    $this->src->abort = true; // upstream throws a LogicException (unexpected index): the file stays as it is
                    return false;
                }
                if ($replacements[$cnt] === 'expectExceptionMessage') {
                    $pi = $t->meaningful($argBefore, 1);
                    $after = $t->meaningful($pi, 1);
                    if ($pi >= 0 && $t->i[$pi] !== -1 && $t->k[$pi] === \__McTokId::T_STRING && \strtolower($t->content($pi)) === 'null' && self::is($t, $after, 41)) {
                        $nx = $argBefore + 1;
                        if ($t->k[$nx] === \Fmt\FlatTokens::WS && \strspn($t->c[$nx], " \t\n\r\0\x0B") === \strlen($t->c[$nx])) {
                            self::clearMerge($t, $nx);
                        }
                        self::clearMerge($t, $argBefore);
                        self::clearMerge($t, $pi);
                        continue;
                    }
                }
                $isWs = $t->k[$argStart] === \Fmt\FlatTokens::WS && \strspn($t->c[$argStart], " \t\n\r\0\x0B") === \strlen($t->c[$argStart]);
                $multi = $multi || ($isWs && \strspn($t->c[$argStart], " \t") !== \strlen($t->c[$argStart]));
                $startItems = [FlatTokens::ws($indent), FlatTokens::code(\__McTokId::T_VARIABLE, '$this'), FlatTokens::code(\__McTokId::T_OBJECT_OPERATOR, '->'),
                    FlatTokens::code(\__McTokId::T_STRING, $replacements[$cnt]), FlatTokens::code(40, '(')];
                $beforeItems = [FlatTokens::code(41, ')'), FlatTokens::code(59, ';')];
                if ($multi) {
                    $startItems[] = FlatTokens::ws($indent . $this->indentCfg);
                    \array_unshift($beforeItems, FlatTokens::ws($indent));
                }
                if ($isWs) {
                    self::overrideRange($t, $argStart, $argStart, $startItems);
                } else {
                    $t->insertAt($argStart, $startItems);
                }
                self::overrideRange($t, $argBefore, $argBefore, $beforeItems);
            }
            $methodName = 'expectException';
            if ($t->content($index) === 'expectExceptionMessageRegExp') {
                $methodName = $newName;
            }
            self::setCode($t, $index, \__McTokId::T_STRING, $methodName);
            $m = $t->count();
        }
    }
}
