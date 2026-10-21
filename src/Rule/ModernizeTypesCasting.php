<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\CastNotation\ModernizeTypesCastingFixer (priority 31, risky). `intval($x)` -> `(int) $x` (also floatval / doubleval / strval / boolval;
 * parentheses kept around a multi-token argument, added in front of `[` `{` `**`). Whole-file FlatTokens port.
 */
final class ModernizeTypesCasting extends FlatRule
{
    public function name(): string
    {
        return 'modernize_types_casting';
    }

    public function priority(): int
    {
        return 31;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['intval', 'floatval', 'doubleval', 'strval', 'boolval']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $map = ['intval' => [\__McTokId::T_INT_CAST, '(int)'], 'floatval' => [\__McTokId::T_DOUBLE_CAST, '(float)'], 'doubleval' => [\__McTokId::T_DOUBLE_CAST, '(float)'],
            'strval' => [\__McTokId::T_STRING_CAST, '(string)'], 'boolval' => [\__McTokId::T_BOOL_CAST, '(bool)']];
        foreach ($map as $fn => $new) {
            $cur = 0;
            while (true) {
                $b = $this->findCall($s, $t, $fn, $cur, $t->count() - 1);
                if ($b === null) {
                    break;
                }
                [$name, $open, $close] = $b;
                $cur = $open;
                if (\count(self::callArguments($t, $open, $close)) !== 1) {
                    continue;
                }
                $paramEnd = $close;
                $comma = $t->meaningful($paramEnd, -1);
                if (self::is($t, $comma, 44)) {
                    self::removeWs($t, $comma, 1);
                    $t->clearAt($comma);
                    $paramEnd = $comma;
                }
                $count = 0;
                for ($p = $open + 1; $p < $paramEnd; $p++) {
                    if (!($t->k[$p] === FlatTokens::WS && $t->c[$p] !== "")) { // a cleared item is Token("") upstream: not T_WHITESPACE
                        $count++;
                    }
                }
                $preserve = $count > 1;
                $after = $t->meaningful($close, 1);
                $wrap = $after >= 0 && ($t->k[$after] === 91 || $t->k[$after] === 123 || $t->k[$after] === \__McTokId::T_POW) && $t->i[$after] !== -1;
                $prev = $t->meaningful($name, -1);
                if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                    self::removeWs($t, $prev, 1);
                    $t->clearAt($prev);
                }
                $repl = [FlatTokens::code($new[0], $new[1]), FlatTokens::ws(' ')];
                if ($wrap) {
                    \array_unshift($repl, FlatTokens::code(40, '('));
                }
                if (!$preserve) {
                    self::removeWs($t, $close, -1);
                    $t->clearAt($close);
                    self::removeWs($t, $open, -1);
                    self::removeWs($t, $open, 1);
                    $t->clearAt($open);
                } else {
                    self::removeWs($t, $name, 1);
                }
                if ($wrap) {
                    $t->insertAt($close, [FlatTokens::code(41, ')')]);
                }
                self::overrideRange($t, $name, $name, $repl);
                $cur = $name;
            }
        }
    }
}
