<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/** Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitDataProviderReturnTypeFixer (priority 9, risky). A data provider without a return type gets `: iterable`, one with a plain type other than `iterable` gets that type replaced. */
final class PhpUnitDataProviderReturnType extends PhpUnitClassRule
{
    public function name(): string
    {
        return 'php_unit_data_provider_return_type';
    }

    public function priority(): int
    {
        return 9;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $providers = $this->dataProviders($t, $start, $end);
        for ($x = \count($providers) - 1; $x >= 0; $x--) {
            $nameIdx = $providers[$x][0];
            $open = self::nextOf($t, $nameIdx, 40);
            $close = $t->blockEnd($open, 41);
            $colon = $t->meaningful($close, 1);
            if ($colon >= 0 && $t->i[$colon] !== -1 && $t->k[$colon] === CT::USE_LAMBDA) {
                $lu = self::nextOf($t, $colon, 40);
                $colon = $t->meaningful($t->blockEnd($lu, 41), 1);
            }
            if ($colon < 0 || $t->i[$colon] === -1 || $t->k[$colon] !== CT::TYPE_COLON) {
                $t->insertAt($close + 1, [FlatTokens::code(CT::TYPE_COLON, ':'), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_STRING, 'iterable')]);
                continue;
            }
            $type = '';
            $ts = $t->meaningful($colon, 1);
            $te = $ts;
            $body = -1;
            $m = $t->count();
            for ($j = $colon + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === 59 || $t->k[$j] === \__McTokId::T_DOUBLE_ARROW)) {
                    $body = $j;
                    break;
                }
            }
            for ($i = $ts; $i < $body; $i++) {
                if ($t->k[$i] === \Fmt\FlatTokens::WS || $t->k[$i] === \Fmt\FlatTokens::COMMENT) {
                    continue;
                }
                $type .= $t->content($i);
                $te = $i;
            }
            $name = \str_starts_with($type, '?') ? \substr($type, 1) : $type;
            if ($name === 'iterable') {
                continue;
            }
            $ts2 = $t->meaningful($ts - 1, 1);
            $code = '';
            for ($i = $ts2; $i <= $te; $i++) {
                $code .= $t->i[$i] === -1 ? $t->c[$i] : $t->content($i);
            }
            if ($code !== $name) {
                continue;
            }
            self::overrideRange($t, $ts2, $te, [FlatTokens::code(\__McTokId::T_STRING, 'iterable')]);
        }
    }
}
