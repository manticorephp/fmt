<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NativeNames;
use Fmt\NsAnalysis;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\NativeFunctionInvocationFixer (priority 1, risky). A global function call whose name is in the include set (`@compiler_optimized`: upstream's
 * list; `@internal`: the internal functions of the oracle php, NativeNames; `@all`; plain names) and not in `exclude` gets a leading `\`; with `strict` a `\` before any other global call is
 * removed (in `scope=namespaced`, everything in the global namespace too). Whole-file FlatTokens port, namespace by namespace from the last.
 */
final class NativeFunctionInvocation extends FlatRule
{
    /** @var array<string, bool> */
    private array $include = [];
    /** @var array<string, bool> */
    private array $exclude = [];
    private bool $all = false;

    /**
     * @param string[] $include
     * @param string[] $exclude
     */
    public function __construct(array $include, array $exclude, private string $scope, private bool $strict)
    {
        parent::__construct();
        foreach ($exclude as $e) {
            $this->exclude[\strtolower($e)] = true;
        }
        $this->all = \in_array('@all', $include, true);
        if ($this->all) {
            return;
        }
        if (\in_array('@internal', $include, true)) {
            foreach (\explode("\n", NativeNames::FUNCTIONS) as $f) {
                $this->include[\strtolower($f)] = true;
            }
        } elseif (\in_array('@compiler_optimized', $include, true)) {
            foreach (['array_key_exists', 'array_slice', 'assert', 'boolval', 'call_user_func', 'call_user_func_array', 'chr', 'count', 'defined', 'doubleval', 'floatval', 'func_get_args',
                'func_num_args', 'get_called_class', 'get_class', 'gettype', 'in_array', 'intval', 'is_array', 'is_bool', 'is_double', 'is_float', 'is_int', 'is_integer', 'is_long', 'is_null',
                'is_object', 'is_real', 'is_resource', 'is_scalar', 'is_string', 'ord', 'sizeof', 'sprintf', 'strlen', 'strval', 'constant', 'define', 'dirname', 'extension_loaded',
                'function_exists', 'is_callable', 'ini_get'] as $f) {
                $this->include[$f] = true;
            }
        }
        foreach ($include as $add) {
            if ($add[0] !== '@') {
                $this->include[\strtolower($add)] = true;
            }
        }
    }

    public function name(): string
    {
        return 'native_function_invocation';
    }

    public function priority(): int
    {
        return 1;
    }

    private function filter(string $name): bool
    {
        $l = \strtolower($name);
        if (isset($this->exclude[$l])) {
            return false;
        }
        return $this->all || isset($this->include[$l]);
    }

    /** a call that gets a `\` (in the filter, none before it) or loses one (strict, `\` before it): a superset (the global-call test is left to the real pass) */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 1; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_STRING || $s->kind[$i + 1] !== 40) {
                continue;
            }
            $prevNs = $s->kind[$i - 1] === \__McTokId::T_NS_SEPARATOR;
            if ($prevNs ? $this->strict || $this->filter($s->text($i)) : $this->filter($s->text($i))) {
                if ($prevNs && !$this->strict) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        if ($this->scope === 'all') {
            $this->fix($s, $t, 0, $t->count() - 1, false);
            return;
        }
        $scopes = NsAnalysis::scopes($s);
        for ($x = \count($scopes) - 1; $x >= 0; $x--) {
            [$name, $a, $b] = $scopes[$x];
            $from = $t->pos[$a] ?? -1;
            $to = $t->pos[$b] ?? -1;
            if ($from < 0 || $to < 0) {
                continue;
            }
            $this->fix($s, $t, $from, $to, $name === '');
        }
    }

    private function fix(Source $s, FlatTokens $t, int $start, int $end, bool $tryToRemove): void
    {
        /** @var array<int, bool> $insert */
        $insert = [];
        for ($index = $start; $index < $end; $index++) {
            if (!$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $prev = $t->meaningful($index, -1);
            if (!$this->filter($t->content($index)) || $tryToRemove) {
                if (!$this->strict) {
                    continue;
                }
                if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                    self::clearMerge($t, $prev);
                }
                continue;
            }
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                continue;
            }
            $insert[$index] = true;
        }
        /** @var array<int, array<int, array{0:int,1:string,2:int}>> $slices */
        $slices = [];
        foreach ($insert as $at => $_) {
            $slices[$at] = [FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\')];
        }
        $t->insertSlices($slices);
    }
}
