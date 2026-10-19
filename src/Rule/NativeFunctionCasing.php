<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\NativeNames;
use Fmt\NsAnalysis;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\NativeFunctionCasingFixer (priority 0). A call of a global function (`STRLEN($a)`, not a method, not behind
 * `new` / `::` / `->` / `function`, not a first-class callable, not a function declared in the current namespace or imported from elsewhere, not an attribute)
 * whose lower-cased name is a function of the running PHP gets that name. The name list is NativeNames (the oracle php's get_defined_functions).
 */
final class NativeFunctionCasing extends TextRule
{
    /** @var array<string, string> lower => canonical */
    private array $map = [];
    private bool $ready = false;
    /** @var array<int, array{0:string,1:int,2:int}> */
    private array $scopes = [];
    /** @var array<int, int> declared function name token indexes */
    private array $decl = [];
    /** @var array<int, array{0:int,1:string,2:string,3:int,4:int}> */
    private array $imports = [];
    private bool $analysed = false;

    public function name(): string
    {
        return 'native_function_casing';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_STRING];
    }

    public function prepare(Source $s): bool
    {
        $this->analysed = false;
        return true;
    }

    private function analyse(Source $s): void
    {
        $this->analysed = true;
        $this->scopes = NsAnalysis::scopes($s);
        $this->decl = [];
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) && $s->ct[$i] === 0) {
                while ($i < $n && !(($s->kind[$i] === 40 || $s->kind[$i] === 123) && $s->ct[$i] === 0)) {
                    $i++;
                }
                if ($i < $n && $s->kind[$i] === 40) {
                    $i = $s->pair[$i];
                    while ($i < $n && !($s->kind[$i] === 123 && $s->ct[$i] === 0)) {
                        $i++;
                    }
                }
                $i = $i < $n ? $s->pair[$i] : $n;
                continue;
            }
            if ($k !== \__McTokId::T_FUNCTION || $s->ct[$i] !== 0) {
                continue;
            }
            $i++;
            if ($i < $n && $s->ct[$i] === CT::RETURN_REF) {
                $i++;
            }
            if ($i < $n && $s->kind[$i] === \__McTokId::T_STRING && $s->ct[$i] === 0) {
                $this->decl[] = $i;
            }
        }
        $this->imports = [];
        foreach (NsAnalysis::singleImports($s) as $im) {
            if ($im[0] === 1) {
                $this->imports[] = $im;
            }
        }
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_STRING || $s->ct[$i] !== 0 || $i + 1 >= $s->n || $s->kind[$i + 1] !== 40 || $s->ct[$i + 1] !== 0) {
            return null;
        }
        $t = $s->text($i);
        $lower = \strtolower($t);
        if ($lower === $t) {
            return null; // every internal function name is lower case
        }
        if (!$this->ready) {
            $this->ready = true;
            foreach (\explode("\n", NativeNames::FUNCTIONS) as $f) {
                $this->map[$f] = $f;
            }
        }
        if (!isset($this->map[$lower])) {
            return null;
        }
        return $this->isGlobalCall($s, $i, $lower) ? $this->map[$lower] : null;
    }

    private function isGlobalCall(Source $s, int $i, string $lower): bool
    {
        $n = $s->n;
        $p = $i - 1;
        $nsSep = false;
        if ($p >= 0 && $s->kind[$p] === \__McTokId::T_NS_SEPARATOR && $s->ct[$p] === 0) {
            $nsSep = true;
            $p--;
        }
        if ($p >= 0) {
            $pk = $s->kind[$p];
            $pc = $s->ct[$p];
            if ($pc === CT::NAMESPACE_OPERATOR || $pc === CT::RETURN_REF || ($pc === 0 && ($pk === \__McTokId::T_DOUBLE_COLON || $pk === \__McTokId::T_FUNCTION
                || $pk === \__McTokId::T_NEW || $pk === \__McTokId::T_STRING || $pk === \__McTokId::T_OBJECT_OPERATOR
                || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $pk === \__McTokId::T_ATTRIBUTE))) {
                return false;
            }
        }
        $o = $i + 1;
        if ($o + 1 < $n && $s->ct[$o + 1] === CT::FIRST_CLASS_CALLABLE) {
            return false;
        }
        if ($nsSep) {
            return true;
        }
        if ($lower === 'set') {
            $pk = $p >= 0 ? $s->kind[$p] : 0;
            $pc = $p >= 0 ? $s->ct[$p] : 0;
            if (!($pc === CT::PROPERTY_HOOK_BRACE_OPEN || ($pc === 0 && ($pk === 59 || $pk === 125)))) {
                return true;
            }
            $c = $s->pair[$o];
            $a = $c + 1;
            if ($a < $n && (($s->kind[$a] === 123 && $s->ct[$a] === 0) || ($s->kind[$a] === \__McTokId::T_DOUBLE_ARROW && $s->ct[$a] === 0))) {
                return false;
            }
        }
        if (!$this->analysed) {
            $this->analyse($s);
        }
        $ss = 0;
        $se = $n - 1;
        $global = false;
        foreach ($this->scopes as [$name, $a, $b]) {
            $ss = $a;
            $se = $b;
            if ($i >= $a && $i <= $b) {
                $global = $name === '';
                break;
            }
        }
        if (!$global) {
            foreach ($this->decl as $d) {
                if ($d >= $ss && $d <= $se && \strtolower($s->text($d)) === $lower) {
                    return false;
                }
            }
        }
        foreach ($this->imports as [$type, $full, $short, $u, $e]) {
            if ($u < $ss || $e > $se || \strtolower($short) !== $lower) {
                continue;
            }
            return $short === \ltrim($full, '\\');
        }
        return !NsAnalysis::isAttribute($s, $i);
    }
}
