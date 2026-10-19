<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\NativeNames;
use Fmt\NsAnalysis;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\ClassReferenceNameCasingFixer (priority 0). A reference to an internal class (`new \exception()`, `exception $e`) gets
 * the class's own case, per namespace scope: in a namespace only the fully-qualified `\x` form, in the global one any; not a name that is imported in the scope
 * (single imports only), not a method / constant / declaration name, not a call, assignment or lone constant. The class list is NativeNames (the oracle php).
 */
final class ClassReferenceNameCasing extends TextRule
{
    /** @var array<string, string> lower => canonical */
    private array $map = [];
    private bool $ready = false;
    /** @var array<int, array{0:string,1:int,2:int}> */
    private array $scopes = [];
    /** @var array<int, array<string, bool>> scope index => lower-cased short names of its single imports */
    private array $uses = [];
    /** @var array<int, bool> */
    private array $before = [];
    /** @var array<int, bool> */
    private array $after = [];
    /** @var array<int, bool> */
    private array $notBefore = [];

    public function __construct()
    {
        $this->before = NsAnalysis::blockStartIds();
        $this->before[44] = true;
        $this->after = NsAnalysis::blockEndIds();
        $this->after[44] = true;
        foreach ([CT::USE_TRAIT, \__McTokId::T_AS, \__McTokId::T_CASE, \__McTokId::T_CLASS, \__McTokId::T_CONST, \__McTokId::T_DOUBLE_ARROW, \__McTokId::T_DOUBLE_COLON,
            \__McTokId::T_FUNCTION, \__McTokId::T_INTERFACE, \__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_TRAIT, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR,
            \__McTokId::T_ENUM] as $k) {
            $this->notBefore[$k] = true;
        }
    }

    public function name(): string
    {
        return 'class_reference_name_casing';
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
        $this->scopes = NsAnalysis::scopes($s);
        $this->uses = [];
        if ($this->scopes === []) {
            return false;
        }
        $imports = NsAnalysis::singleImports($s);
        foreach ($this->scopes as $si => [$name, $a, $b]) {
            $u = [];
            foreach ($imports as $im) {
                if ($im[3] >= $a && $im[3] <= $b) {
                    $u[\strtolower($im[2])] = true;
                }
            }
            $this->uses[$si] = $u;
        }
        return true;
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_STRING || $s->ct[$i] !== 0 || $i === 0 || $i + 1 >= $s->n) {
            return null;
        }
        $n = $i + 1;
        $nk = $s->kind[$n];
        $nc = $s->ct[$n];
        if ($nc === 0 && $nk === \__McTokId::T_NS_SEPARATOR) {
            return null;
        }
        $p = $i - 1;
        $nsSep = $s->ct[$p] === 0 && $s->kind[$p] === \__McTokId::T_NS_SEPARATOR;
        $scope = -1;
        foreach ($this->scopes as $si => [$name, $a, $b]) {
            if ($i >= $a && $i < $b) {
                $scope = $si;
                break;
            }
        }
        if ($scope < 0) {
            return null;
        }
        $global = $this->scopes[$scope][0] === '';
        if (!$nsSep && !$global) {
            return null;
        }
        if ($nsSep) {
            $p--;
            if ($p < 0 || ($s->kind[$p] === \__McTokId::T_STRING && $s->ct[$p] === 0)) {
                return null;
            }
        } elseif (isset($this->notBefore[$s->ct[$p] !== 0 ? $s->ct[$p] : $s->kind[$p]])) {
            return null;
        }
        $pid = $s->ct[$p] !== 0 ? $s->ct[$p] : $s->kind[$p];
        $nid = $nc !== 0 ? $nc : $nk;
        if (isset($this->before[$pid]) && isset($this->after[$nid])) {
            return null;
        }
        if ($pid !== \__McTokId::T_NEW && ($nc === 0 && ($nk === 40 || $nk === 59 || $nk === 61 || $nk === \__McTokId::T_CLOSE_TAG))) {
            return null;
        }
        $t = $s->text($i);
        $lower = \strtolower($t);
        if (!$this->ready) {
            $this->ready = true;
            foreach (\explode("\n", NativeNames::CLASSES) as $c) {
                $this->map[\strtolower($c)] = $c;
            }
        }
        $canon = $this->map[$lower] ?? null;
        if ($canon === null || $canon === $t || isset($this->uses[$scope][$lower])) {
            return null;
        }
        return $canon;
    }
}
