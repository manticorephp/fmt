<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\GetClassToClassKeywordFixer (priority 1, risky; PHP >= 8.0 like the oracle). `get_class($a)` -> `$a::class` for a single
 * variable argument other than `$this`. Whole-file FlatTokens port: the calls are collected last first, cleared, then every slice is inserted.
 */
final class GetClassToClassKeyword extends FlatRule
{
    public function name(): string
    {
        return 'get_class_to_class_keyword';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['get_class']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, int[]> $clear */
        $clear = [];
        for ($index = $t->count() - 1; $index > 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING || \strtolower($t->content($index)) !== 'get_class') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $open = $t->meaningful($index, 1);
            $close = $t->blockEnd($open, 41);
            if ($close === $t->meaningful($open, 1)) {
                continue;
            }
            $count = 0;
            $vars = [];
            $isThis = false;
            for ($i = $open + 1; $i < $close; $i++) {
                $k = $t->k[$i];
                if (!($k === FlatTokens::WS || $k === FlatTokens::COMMENT || ($t->i[$i] !== -1 && ($k === 40 || $k === 41)))) {
                    $count++;
                }
                if ($t->i[$i] === -1 || $k !== \__McTokId::T_VARIABLE) {
                    continue;
                }
                if (\strtolower($t->content($i)) === '$this') {
                    $isThis = true;
                    break;
                }
                $vars[] = $i;
            }
            if ($isThis) {
                continue;
            }
            if ($count > 1 || \count($vars) !== 1) {
                continue;
            }
            $clear[$index] = [$open, $vars[0], $close];
        }
        $slices = [];
        foreach ($clear as $index => $items) {
            $slices[$index] = $t->content($items[1]);
            for ($i = $items[0]; $i <= $items[2]; $i++) {
                if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                    continue;
                }
                self::clearMerge($t, $i);
            }
            $prev = $t->meaningful($index, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                $t->clearAt($prev);
            }
            $t->clearAt($index);
        }
        foreach ($slices as $index => $var) {
            $t->insertAt($index, [FlatTokens::code(\__McTokId::T_VARIABLE, $var), FlatTokens::code(\__McTokId::T_DOUBLE_COLON, '::'), FlatTokens::code(CT::CLASS_CONSTANT, 'class')]);
        }
    }
}
