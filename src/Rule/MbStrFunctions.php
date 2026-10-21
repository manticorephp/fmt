<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer (priority 2, risky; the function map of PHP 8.4+ like the oracle, mb_str_pad / mb_trim / mb_ltrim / mb_rtrim included). A global call of
 * strlen / strpos / substr / strtolower / ... with an argument count the mb function accepts is renamed to `mb_*`; so is the name in a `use function` import. Text rule.
 */
final class MbStrFunctions extends TextRule
{
    private NativeFunctionCasing $calls;

    public function __construct()
    {
        $this->calls = new NativeFunctionCasing();
    }

    public function name(): string
    {
        return 'mb_str_functions';
    }

    public function priority(): int
    {
        return 2;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_STRING];
    }

    /** @return array{0:string,1:int[]}|null [mb name, accepted argument counts] */
    private static function info(string $lower): ?array
    {
        return match ($lower) {
            'str_split' => ['mb_str_split', [1, 2, 3]],
            'stripos' => ['mb_stripos', [2, 3]],
            'stristr' => ['mb_stristr', [2, 3]],
            'strlen' => ['mb_strlen', [1]],
            'strpos' => ['mb_strpos', [2, 3]],
            'strrchr' => ['mb_strrchr', [2]],
            'strripos' => ['mb_strripos', [2, 3]],
            'strrpos' => ['mb_strrpos', [2, 3]],
            'strstr' => ['mb_strstr', [2, 3]],
            'strtolower' => ['mb_strtolower', [1]],
            'strtoupper' => ['mb_strtoupper', [1]],
            'substr' => ['mb_substr', [2, 3]],
            'substr_count' => ['mb_substr_count', [2, 3, 4]],
            'str_pad' => ['mb_str_pad', [1, 2, 3, 4]],
            'trim' => ['mb_trim', [1, 2]],
            'ltrim' => ['mb_ltrim', [1, 2]],
            'rtrim' => ['mb_rtrim', [1, 2]],
            default => null,
        };
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_STRING) {
            return null;
        }
        $len = $s->end[$i] - $s->start[$i];
        if ($len < 4 || $len > 12) {
            return null;
        }
        $lower = \strtolower($s->text($i));
        $info = self::info($lower);
        if ($info === null) {
            return null;
        }
        if ($i + 1 < $s->n && $s->kind[$i + 1] === 40 && $s->ct[$i + 1] === 0 && $this->calls->isGlobalCallAt($s, $i, $lower)) {
            $close = $s->pair[$i + 1];
            $count = $close === $i + 2 ? 0 : 1;
            for ($j = $i + 2; $j < $close; $j++) {
                $k = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                if (\Fmt\TypeWalk::closer($k) >= 0 && $s->pair[$j] > $j) {
                    $j = $s->pair[$j];
                    continue;
                }
                if ($k === 44 && $j + 1 < $close) {
                    $count++;
                }
            }
            return \in_array($count, $info[1], true) ? $info[0] : null;
        }
        $f = $i - 1;
        if ($f >= 0 && $s->kind[$f] === \__McTokId::T_NS_SEPARATOR) {
            $f--;
        }
        if ($f >= 0 && $s->ct[$f] === CT::FUNCTION_IMPORT) {
            return $info[0];
        }
        return null;
    }
}
