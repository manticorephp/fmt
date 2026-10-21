<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertFixer (priority -9, risky). `assertTrue(is_null($a))` -> `assertNull($a)`, `assertFalse(file_exists($f))` ->
 * `assertFileNotExists($f)`, `assertTrue(is_string($a))` -> `assertInternalType('string', $a)`, `assertTrue($a instanceof Foo)` -> `assertInstanceOf(Foo::class, $a)`, `assertSame(3, count($a))`
 * -> `assertCount(3, $a)`, `assertTrue(str_contains($a, $b))` -> `assertStringContainsString($b, $a)`; the `target` option limits the functions. Whole-file FlatTokens port: upstream's
 * generator is walked lazily, from the end of the class to its start.
 */
final class PhpUnitDedicateAssert extends PhpUnitClassRule
{
    /** @var string[] */
    private array $functions = [];

    public function __construct(string $target)
    {
        parent::__construct();
        $this->functions = ['array_key_exists', 'file_exists', 'is_null', 'str_ends_with', 'str_starts_with'];
        $newest = $target === 'newest';
        if ($newest || $target === '3.5' || $target === '5.0' || $target === '5.6') {
            foreach (['empty', 'is_array', 'is_bool', 'is_boolean', 'is_callable', 'is_double', 'is_float', 'is_int', 'is_integer', 'is_long', 'is_numeric', 'is_object', 'is_real',
                'is_scalar', 'is_string'] as $f) {
                $this->functions[] = $f;
            }
        }
        if ($newest || $target === '5.0' || $target === '5.6') {
            $this->functions[] = 'is_infinite';
            $this->functions[] = 'is_nan';
        }
        if ($newest || $target === '5.6') {
            $this->functions[] = 'is_dir';
            $this->functions[] = 'is_readable';
            $this->functions[] = 'is_writable';
        }
        if ($newest) {
            $this->functions[] = 'str_contains';
        }
    }

    public function name(): string
    {
        return 'php_unit_dedicate_assert';
    }

    public function priority(): int
    {
        return -9;
    }

    /** @return array{0:string,1:string,2:int,3:bool}|null [positive, negative ('' = none), argument count, swap] for a function with its own assertion, null for the type functions */
    private static function fix(string $f): ?array
    {
        return match ($f) {
            'array_key_exists' => ['assertArrayHasKey', 'assertArrayNotHasKey', 2, false],
            'empty' => ['assertEmpty', 'assertNotEmpty', 1, false],
            'file_exists' => ['assertFileExists', 'assertFileNotExists', 1, false],
            'is_dir' => ['assertDirectoryExists', 'assertDirectoryNotExists', 1, false],
            'is_infinite' => ['assertInfinite', 'assertFinite', 1, false],
            'is_nan' => ['assertNan', '', 1, false],
            'is_null' => ['assertNull', 'assertNotNull', 1, false],
            'is_readable' => ['assertIsReadable', 'assertNotIsReadable', 1, false],
            'is_writable' => ['assertIsWritable', 'assertNotIsWritable', 1, false],
            'str_contains' => ['assertStringContainsString', 'assertStringNotContainsString', 2, true],
            'str_ends_with' => ['assertStringEndsWith', 'assertStringEndsNotWith', 2, true],
            'str_starts_with' => ['assertStringStartsWith', 'assertStringStartsNotWith', 2, true],
            default => null,
        };
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $end; $index > $start; $index--) {
            $index = self::prevString($t, $index);
            if ($index < 0) {
                return;
            }
            $lower = \strtolower($t->content($index));
            if (!\str_starts_with($lower, 'assert')) {
                continue;
            }
            $open = $t->meaningful($index, 1);
            if (!self::is($t, $open, 40) || !PhpUnitConstruct::sameClassCall($t, $index)) {
                continue;
            }
            $close = $t->blockEnd($open, 41);
            if ($lower === 'asserttrue' || $lower === 'assertfalse') {
                $this->trueFalse($t, $index, $lower, $open);
                continue;
            }
            if ($lower === 'assertsame' || $lower === 'assertnotsame' || $lower === 'assertequals' || $lower === 'assertnotequals') {
                $this->sameEquals($t, $index, $lower, $open);
            }
        }
    }

    private function trueFalse(FlatTokens $t, int $call, string $lower, int $open): void
    {
        $nsIdx = -1;
        $test = $t->meaningful($open, 1);
        $k = $test >= 0 && $t->i[$test] !== -1 ? $t->k[$test] : -1;
        if ($k !== \__McTokId::T_EMPTY && $k !== \__McTokId::T_STRING) {
            if ($this->instanceOf($t, $call, $lower, $test)) {
                return;
            }
            if ($k !== \__McTokId::T_NS_SEPARATOR) {
                return;
            }
            $nsIdx = $test;
            $test = $t->meaningful($test, 1);
        }
        $tOpen = $t->meaningful($test, 1);
        if (!self::is($t, $tOpen, 40)) {
            return;
        }
        $tClose = $t->blockEnd($tOpen, 41);
        $after = $t->meaningful($tClose, 1);
        if (!(self::is($t, $after, 41) || self::is($t, $after, 44))) {
            return;
        }
        $content = \strtolower($t->content($test));
        if (!\in_array($content, $this->functions, true)) {
            return;
        }
        $args = self::callArguments($t, $tOpen, $tClose);
        $positive = $lower === 'asserttrue';
        $fix = self::fix($content);
        if ($fix !== null) {
            if ($fix[2] !== \count($args)) {
                return;
            }
            $name = $positive ? $fix[0] : $fix[1];
            if ($name === '') {
                return;
            }
            self::setCode($t, $call, \__McTokId::T_STRING, $name);
            $this->removeCall($t, $nsIdx, $test, $tOpen, $tClose);
            if ($fix[3]) {
                $this->swap($t, $args);
            }
            return;
        }
        if (\count($args) !== 1) {
            return;
        }
        $type = \substr($content, 3);
        self::setCode($t, $call, \__McTokId::T_STRING, $positive ? 'assertInternalType' : 'assertNotInternalType');
        self::setCode($t, $test, \__McTokId::T_CONSTANT_ENCAPSED_STRING, "'" . $type . "'");
        self::setCode($t, $tOpen, 44, ',');
        self::clearMerge($t, $tClose);
        $comma = $t->meaningful($tClose, -1);
        if (self::is($t, $comma, 44)) {
            self::removeWs($t, $comma, 1);
            $t->clearAt($comma);
        }
        $ws = $tOpen + 1;
        if (!($t->k[$ws] === FlatTokens::WS && \strspn($t->c[$ws], " \t\n\r\0\x0B") === \strlen($t->c[$ws]))) {
            $t->insertAt($ws, [FlatTokens::ws(' ')]);
        }
        if ($nsIdx >= 0) {
            self::clearMerge($t, $nsIdx);
        }
    }

    private function instanceOf(FlatTokens $t, int $call, string $lower, int $test): bool
    {
        $positiveAssertion = $lower === 'asserttrue';
        if (self::is($t, $test, 33)) {
            $var = $t->meaningful($test, 1);
            $positiveCondition = false;
        } else {
            $var = $test;
            $positiveCondition = true;
        }
        if (!self::is($t, $var, \__McTokId::T_VARIABLE)) {
            return false;
        }
        $io = $t->meaningful($var, 1);
        if (!self::is($t, $io, \__McTokId::T_INSTANCEOF)) {
            return false;
        }
        $classEnd = $t->meaningful($io, 1);
        $parts = [];
        do {
            $parts[] = $classEnd;
            $classEnd = $t->meaningful($classEnd, 1);
        } while ($classEnd >= 0 && $t->i[$classEnd] !== -1 && ($t->k[$classEnd] === \__McTokId::T_STRING || $t->k[$classEnd] === \__McTokId::T_NS_SEPARATOR || $t->k[$classEnd] === \__McTokId::T_VARIABLE));
        if ($classEnd >= 0 && (self::is($t, $classEnd, 44) || self::is($t, $classEnd, 41))) {
            $isVar = $t->k[$parts[0]] === \__McTokId::T_VARIABLE;
            /** @var array<int, array{0:int,1:string,2:int}> $new key => item */
            $new = [];
            $at = $test - 1;
            foreach ($parts as $p) {
                $new[++$at] = [$t->k[$p], $t->content($p), FlatTokens::NEW];
            }
            if (!$isVar) {
                $new[++$at] = FlatTokens::code(\__McTokId::T_DOUBLE_COLON, '::');
                $new[++$at] = FlatTokens::code(CT::CLASS_CONSTANT, 'class');
            }
            $new[++$at] = FlatTokens::code(44, ',');
            $new[++$at] = FlatTokens::ws(' ');
            $new[++$at] = [$t->k[$var], $t->content($var), FlatTokens::NEW];
            for ($i = $classEnd - 1; $i >= $test; $i--) {
                if ($t->k[$i] !== FlatTokens::COMMENT) {
                    self::clearMerge($t, $i);
                }
            }
            $name = ($positiveAssertion && $positiveCondition) || (!$positiveAssertion && !$positiveCondition) ? 'assertInstanceOf' : 'assertNotInstanceOf';
            \krsort($new);
            foreach ($new as $pos => $item) {
                $t->insertAt($pos, [$item]);
            }
            self::setCode($t, $call, \__McTokId::T_STRING, $name);
        }
        return true;
    }

    private function sameEquals(FlatTokens $t, int $call, string $lower, int $open): void
    {
        $expected = $t->meaningful($open, 1);
        if (self::is($t, $expected, \__McTokId::T_VARIABLE)) {
            if (!self::is($t, $t->meaningful($expected, 1), 44)) {
                return;
            }
        } elseif (!(self::is($t, $expected, \__McTokId::T_LNUMBER) || self::is($t, $expected, \__McTokId::T_VARIABLE))) {
            return;
        }
        $comma = $t->meaningful($expected, 1);
        if (!self::is($t, $comma, 44)) {
            return;
        }
        $cc = $t->meaningful($comma, 1);
        $ns = -1;
        if (self::is($t, $cc, \__McTokId::T_NS_SEPARATOR)) {
            $ns = $cc;
            $cc = $t->meaningful($cc, 1);
        }
        if (!self::is($t, $cc, \__McTokId::T_STRING)) {
            return;
        }
        $lc = \strtolower($t->content($cc));
        if ($lc !== 'count' && $lc !== 'sizeof') {
            return;
        }
        $ccOpen = $t->meaningful($cc, 1);
        if (!self::is($t, $ccOpen, 40)) {
            return;
        }
        $ccClose = $t->blockEnd($ccOpen, 41);
        $after = $t->meaningful($ccClose, 1);
        if (!(self::is($t, $after, 41) || self::is($t, $after, 44))) {
            return;
        }
        $this->removeCall($t, $ns, $cc, $ccOpen, $ccClose);
        self::setCode($t, $call, \__McTokId::T_STRING, \strpos($lower, 'not', 6) === false ? 'assertCount' : 'assertNotCount');
    }

    private function removeCall(FlatTokens $t, int $ns, int $call, int $open, int $close): void
    {
        self::clearMerge($t, $call);
        if ($ns >= 0) {
            self::clearMerge($t, $ns);
        }
        self::clearMerge($t, $open);
        $comma = $t->meaningful($close, -1);
        if (self::is($t, $comma, 44)) {
            self::removeWs($t, $comma, 1);
            $t->clearAt($comma);
        }
        self::clearMerge($t, $close);
    }

    /** @param array<int, int> $args */
    private function swap(FlatTokens $t, array $args): void
    {
        $starts = \array_keys($args);
        $a = $starts[0];
        $b = $starts[1];
        $first = $this->cloneAndClear($t, $a, $args[$a]);
        $second = $this->cloneAndClear($t, $b, $args[$b]);
        if ($first[0][0] !== FlatTokens::WS || $first[0][1] === '') {
            \array_unshift($first, FlatTokens::ws(' '));
        }
        $t->insertAt($b, $first);
        if ($second[0][0] === FlatTokens::WS && $second[0][1] !== '') {
            \array_shift($second);
        }
        $t->insertAt($a, $second);
    }

    /** @return array<int, array{0:int,1:string,2:int}> */
    private function cloneAndClear(FlatTokens $t, int $from, int $to): array
    {
        $out = [];
        for ($i = $from; $i <= $to; $i++) {
            $c = $t->i[$i] === -1 ? $t->c[$i] : $t->content($i);
            if ($c === '') {
                continue;
            }
            $out[] = ImplodeCall::cloneItem($t, $i);
            $t->clearAt($i);
        }
        return $out;
    }
}
