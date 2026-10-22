<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\NsScan;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\StringableForToStringFixer (priority 37). A class with a `__toString` method that does not implement
 * Stringable (by `\Stringable`, `Stringable`, or the short name of a `use ...\Stringable` seen so far in this namespace) gets `implements \Stringable`
 * (or `\Stringable, ` first in its list). Whole-file FlatTokens port: the use declarations are located at load time and compared with the live index,
 * which an earlier insertion shifts (upstream's stale start indexes).
 */
final class StringableForToString extends FlatRule
{
    public function name(): string
    {
        return 'stringable_for_to_string';
    }

    public function priority(): int
    {
        return 37;
    }

    protected function candidate(Source $s): bool
    {
        $c = false;
        $str = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $c = $c || ($k === \__McTokId::T_CLASS && $s->ct[$i] === 0);
            $str = $str || $k === \__McTokId::T_STRING;
        }
        return $c && $str;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, string> $names flat index of a `use` token => lower-case short name of a Stringable import */
        $names = [];
        foreach (NsScan::uses($s, true) as $d) {
            $full = \strtolower($d[1]);
            if (isset($names[$t->pos[$d[5]]])) {
                continue;
            }
            $names[$t->pos[$d[5]]] = ($full === 'stringable' || $full === '\stringable') ? \strtolower($d[2]) : '';
        }
        $stringable = ['stringable'];
        for ($index = 1; $index < $t->count(); ++$index) {
            if (self::is($t, $index, \__McTokId::T_NAMESPACE)) {
                $stringable = [];
                continue;
            }
            if (self::is($t, $index, \__McTokId::T_USE)) {
                if (isset($names[$index]) && $names[$index] !== '') {
                    $stringable[] = $names[$index];
                }
                continue;
            }
            if (!self::is($t, $index, \__McTokId::T_CLASS)) {
                continue;
            }
            $start = self::nextOf($t, $index, 123);
            if ($start < 0) {
                return;
            }
            $end = $t->blockEnd($start, 125);
            if (!$this->hasToString($t, $start, $end)) {
                continue;
            }
            if ($this->implementsStringable($t, $index, $start, $stringable)) {
                continue;
            }
            $this->add($t, $index);
        }
    }

    private function hasToString(FlatTokens $t, int $start, int $end): bool
    {
        $index = $start;
        while ($index < $end) {
            ++$index;
            if (self::is($t, $index, 123)) {
                $index = $t->blockEnd($index, 125);
                continue;
            }
            if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $name = $t->meaningful($index, 1);
            if ($name >= 0 && self::is($t, $name, \__McTokId::T_STRING) && \strtolower($t->content($name)) === '__tostring') {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $stringable */
    private function implementsStringable(FlatTokens $t, int $classIndex, int $open, array $stringable): bool
    {
        $implements = self::nextOfAny($t, $classIndex, [123, \__McTokId::T_IMPLEMENTS]);
        $interfaces = [];
        $interface = '';
        for ($index = $t->meaningful($implements, 1); $index >= 0 && $index < $open; $index = $t->meaningful($index, 1)) {
            if (self::is($t, $index, 44)) {
                $interfaces[] = \strtolower($interface);
                $interface = '';
                continue;
            }
            $interface .= $t->content($index);
        }
        if ($interface !== '') {
            $interfaces[] = \strtolower($interface);
        }
        if ($interfaces === []) {
            return false;
        }
        if (\in_array('\stringable', $interfaces, true)) {
            return true;
        }
        foreach ($stringable as $name) {
            if (\in_array($name, $interfaces, true)) {
                return true;
            }
        }
        return false;
    }

    private function add(FlatTokens $t, int $classIndex): void
    {
        $implements = self::nextOfAny($t, $classIndex, [123, \__McTokId::T_IMPLEMENTS]);
        if (self::is($t, $implements, 123)) {
            $prev = $t->meaningful($implements, -1);
            $t->insertSlices([$prev + 1 => [FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_IMPLEMENTS, 'implements'), FlatTokens::ws(' '),
                FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\'), FlatTokens::code(\__McTokId::T_STRING, 'Stringable')]]);
            return;
        }
        $after = $t->meaningful($implements, 1);
        $t->insertSlices([$after => [FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\'), FlatTokens::code(\__McTokId::T_STRING, 'Stringable'),
            FlatTokens::code(44, ','), FlatTokens::ws(' ')]]);
    }
}
