<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\CombineNestedDirnameFixer (priority 35, risky). `dirname(dirname($p))` -> `dirname($p, 2)` (levels added up, an explicit
 * `, n` level kept). Whole-file FlatTokens port, last token first.
 */
final class CombineNestedDirname extends FlatRule
{
    public function name(): string
    {
        return 'combine_nested_dirname';
    }

    public function priority(): int
    {
        return 35;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['dirname']);
    }

    /**
     * ['indices' => item indexes of the call's name / parentheses / commas, 'levels', 'end', 'second' (-1: none)]
     * @return array{indices:int[],levels:int,end:int,second:int}|null
     */
    private function info(Source $s, FlatTokens $t, int $index, int $firstEnd): ?array
    {
        if ($index < 0 || $t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING || \strtolower($t->content($index)) !== 'dirname') {
            return null;
        }
        if (!$this->isGlobalCall($s, $t, $index)) {
            return null;
        }
        $indices = [];
        $prev = $t->meaningful($index, -1);
        if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
            $indices[] = $prev;
        }
        $indices[] = $index;
        $next = $t->meaningful($index, 1);
        $indices[] = $next;
        if ($firstEnd >= 0) {
            $next = $t->meaningful($firstEnd, 1);
        } else {
            $next = $t->meaningful($next, 1);
            if (self::is($t, $next, 41)) {
                return null;
            }
            while (!(self::is($t, $next, 44) || self::is($t, $next, 41))) {
                $det = self::blockEdge($t->k[$next]);
                if ($det !== null) {
                    $next = $t->blockEnd($next, \Fmt\TypeWalk::closer($det[0]));
                }
                $next = $t->meaningful($next, 1);
            }
        }
        $indices[] = $next;
        if (self::is($t, $next, 44)) {
            $next = $t->meaningful($next, 1);
            $indices[] = $next;
        }
        if (self::is($t, $next, 41)) {
            return ['indices' => $indices, 'levels' => 1, 'end' => $next, 'second' => -1];
        }
        if (!self::is($t, $next, \__McTokId::T_LNUMBER)) {
            return null;
        }
        $second = $next;
        $levels = (int) $t->content($next);
        $next = $t->meaningful($next, 1);
        if (self::is($t, $next, 44)) {
            $indices[] = $next;
            $next = $t->meaningful($next, 1);
        }
        if (!self::is($t, $next, 41)) {
            return null;
        }
        $indices[] = $next;
        return ['indices' => $indices, 'levels' => $levels, 'end' => $next, 'second' => $second];
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index >= 0; $index--) {
            $info = $this->info($s, $t, $index, -1);
            if ($info === null) {
                continue;
            }
            $prev = $t->meaningful($info['indices'][0], -1);
            if (!self::is($t, $prev, 40)) {
                continue;
            }
            $prev = $t->meaningful($prev, -1);
            $firstEnd = $info['end'];
            $all = [$info];
            while (($info = $this->info($s, $t, $prev, $firstEnd)) !== null) {
                $all[] = $info;
                $prev = $t->meaningful($info['indices'][0], -1);
                if (!self::is($t, $prev, 40)) {
                    break;
                }
                $prev = $t->meaningful($prev, -1);
                $firstEnd = $info['end'];
            }
            if (\count($all) > 1) {
                $this->combine($t, $all);
            }
            $index = $prev;
        }
    }

    /** @param array<int, array{indices:int[],levels:int,end:int,second:int}> $all */
    private function combine(FlatTokens $t, array $all): void
    {
        $outer = $all[\count($all) - 1];
        $levels = $outer['levels'];
        for ($x = 0; $x < \count($all) - 1; $x++) {
            $levels += $all[$x]['levels'];
            foreach ($all[$x]['indices'] as $index) {
                self::removeWs($t, $index, -1);
                self::clearMerge($t, $index);
            }
        }
        if ($outer['second'] >= 0) {
            self::setCode($t, $outer['second'], \__McTokId::T_LNUMBER, (string) $levels);
        } else {
            $prev = $t->meaningful($outer['end'], -1);
            $items = [];
            if (!self::is($t, $prev, 44)) {
                $items = [FlatTokens::code(44, ','), FlatTokens::ws(' ')];
            }
            $items[] = FlatTokens::code(\__McTokId::T_LNUMBER, (string) $levels);
            $t->insertAt($outer['end'], $items);
        }
    }
}
