<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\FunctionToConstantFixer (priority 2, risky). `phpversion()` -> `PHP_VERSION`, `pi()` -> `M_PI`, `php_sapi_name()` -> `PHP_SAPI`,
 * `get_called_class()` / `get_class($this)` -> `static::class`, `get_class()` -> `self::class` (the `functions` option). Whole-file FlatTokens port.
 */
final class FunctionToConstant extends FlatRule
{
    /** @param string[] $functions */
    public function __construct(private array $functions)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'function_to_constant';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(Source $s): bool
    {
        $names = [];
        foreach ($this->functions as $f) {
            $names[] = $f === 'get_class_this' ? 'get_class' : $f;
        }
        return self::hasCallOf($s, $names);
    }

    /** @return array<int, array{0:int,1:string}>|null */
    private function replacement(string $key): ?array
    {
        if (!\in_array($key, $this->functions, true)) {
            return null;
        }
        return match ($key) {
            'get_called_class', 'get_class_this' => [[\__McTokId::T_STATIC, 'static'], [\__McTokId::T_DOUBLE_COLON, '::'], [CT::CLASS_CONSTANT, 'class']],
            'get_class' => [[\__McTokId::T_STRING, 'self'], [\__McTokId::T_DOUBLE_COLON, '::'], [CT::CLASS_CONSTANT, 'class']],
            'php_sapi_name' => [[\__McTokId::T_STRING, 'PHP_SAPI']],
            'phpversion' => [[\__McTokId::T_STRING, 'PHP_VERSION']],
            'pi' => [[\__McTokId::T_STRING, 'M_PI']],
            default => null,
        };
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 4; $q > 0; $q--) {
            if ($t->i[$q] === -1 || $t->k[$q] !== \__McTokId::T_STRING) {
                continue;
            }
            $lower = \strtolower($t->content($q));
            $open = -1;
            $close = -1;
            $repl = null;
            if ($lower === 'get_class') {
                if (!\in_array('get_class', $this->functions, true) && !\in_array('get_class_this', $this->functions, true)) {
                    continue;
                }
                if (!$this->isGlobalCall($s, $t, $q)) {
                    continue;
                }
                $open = $t->meaningful($q, 1);
                $close = $t->blockEnd($open, 41);
                if ($close === $t->meaningful($open, 1)) {
                    $repl = $this->replacement('get_class');
                } elseif (\in_array('get_class_this', $this->functions, true)) {
                    $isThis = false;
                    for ($i = $open + 1; $i < $close; $i++) {
                        $k = $t->k[$i];
                        if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT || ($t->i[$i] !== -1 && $k === 41)) {
                            continue;
                        }
                        if ($k === \__McTokId::T_VARIABLE && \strtolower($t->content($i)) === '$this') {
                            $isThis = true;
                            continue;
                        }
                        if ($isThis === false && $k === 40 && $t->i[$i] !== -1) {
                            continue;
                        }
                        $isThis = false;
                        break;
                    }
                    if ($isThis) {
                        $repl = $this->replacement('get_class_this');
                    }
                }
            } else {
                if (!\in_array($lower, $this->functions, true) || $lower === 'get_class_this') {
                    continue;
                }
                if (!$this->isGlobalCall($s, $t, $q)) {
                    continue;
                }
                $open = $t->meaningful($q, 1);
                $close = $t->meaningful($open, 1);
                if (!self::is($t, $close, 41)) {
                    continue;
                }
                $repl = $this->replacement($lower);
            }
            if ($repl === null) {
                continue;
            }
            for ($i = $close; $i >= $open; $i--) {
                if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                    continue;
                }
                self::clearMerge($t, $i);
            }
            $r0 = $repl[0][0];
            if ($r0 === \__McTokId::T_STATIC || ($r0 === \__McTokId::T_STRING && $repl[0][1] === 'self')) {
                $prev = $t->meaningful($q, -1);
                if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                    $t->clearAt($prev);
                }
            }
            $t->clearAt($q);
            $items = [];
            foreach ($repl as $r) {
                $items[] = FlatTokens::code($r[0], $r[1]);
            }
            $t->insertAt($q, $items);
        }
    }
}
