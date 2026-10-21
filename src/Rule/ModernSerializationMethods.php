<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\ModernSerializationMethodsFixer (priority 0, risky). `__sleep` -> `__serialize`, `__wakeup` -> `__unserialize(array $data)` per class that
 * does not have the new method already. Whole-file FlatTokens port over the Source's classy elements.
 */
final class ModernSerializationMethods extends FlatRule
{
    public function name(): string
    {
        return 'modern_serialization_methods';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_STRING && $s->ct[$i] === 0 && ($s->end[$i] - $s->start[$i] === 7 || $s->end[$i] - $s->start[$i] === 8)) {
                $x = $s->text($i);
                if ($x === '__sleep' || $x === '__wakeup') {
                    return true;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, array<string, int>> $byClass */
        $byClass = [];
        foreach (Analyzer::classyElements($s) as $index => $el) {
            if ($el[0] !== Analyzer::E_METHOD) {
                continue;
            }
            $q = $t->pos[$index] ?? -1;
            if ($q < 0) {
                continue;
            }
            $nameIdx = $t->meaningful($q, 1);
            if (!isset($byClass[$el[1]])) {
                $byClass[$el[1]] = [];
            }
            $byClass[$el[1]][$t->content($nameIdx)] = $nameIdx;
        }
        /** @var array<int, bool> $insert */
        $insert = [];
        foreach ($byClass as $methods) {
            if (isset($methods['__sleep']) && !isset($methods['__serialize'])) {
                self::setCode($t, $methods['__sleep'], \__McTokId::T_STRING, '__serialize');
            }
            if (isset($methods['__wakeup']) && !isset($methods['__unserialize'])) {
                self::setCode($t, $methods['__wakeup'], \__McTokId::T_STRING, '__unserialize');
                $open = self::nextOf($t, $methods['__wakeup'], 40);
                $insert[$open + 1] = true;
            }
        }
        \krsort($insert);
        foreach ($insert as $at => $_) {
            $t->insertAt($at, [FlatTokens::code(CT::ARRAY_TYPEHINT, 'array'), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_VARIABLE, '$data')]);
        }
    }
}
