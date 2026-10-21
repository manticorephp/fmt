<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NativeNames;
use Fmt\NsAnalysis;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ConstantNotation\NativeConstantInvocationFixer (priority 1, risky). A constant of the built-in set (`get_defined_constants` of the oracle php,
 * NativeNames::CONSTANTS) or of `include`, not in `exclude` and not imported with `use const`, gets a leading `\` (case-sensitive, except null / false / true); with `strict` a `\` in front
 * of any other constant is removed. `isConstantInvocation` is TokensAnalyzer's, evaluated on the untouched Source (the walk is right to left, so the tokens it reads are unchanged).
 */
final class NativeConstantInvocation extends FlatRule
{
    /** @var array<string, bool> */
    private array $escape = [];
    /** @var array<string, bool> lower-cased null / false / true */
    private array $escapeCi = [];

    /**
     * @param string[] $include
     * @param string[] $exclude
     */
    public function __construct(bool $fixBuiltIn, array $include, array $exclude, private string $scope, private bool $strict)
    {
        parent::__construct();
        $names = [];
        foreach ($include as $i) {
            $names[$i] = true;
        }
        if ($fixBuiltIn) {
            foreach (\explode("\n", NativeNames::CONSTANTS) as $c) {
                $names[$c] = true;
            }
        }
        $ex = [];
        $exLower = [];
        foreach ($exclude as $e) {
            $ex[$e] = true;
            $exLower[\strtolower($e)] = true;
        }
        foreach ($names as $n => $_) {
            $n = (string) $n;
            if (isset($ex[$n])) {
                continue;
            }
            $l = \strtolower($n);
            if ($l === 'null' || $l === 'false' || $l === 'true') {
                if (!isset($exLower[$l])) {
                    $this->escapeCi[$l] = true;
                }
                continue;
            }
            $this->escape[$n] = true;
        }
    }

    public function name(): string
    {
        return 'native_constant_invocation';
    }

    public function priority(): int
    {
        return 1;
    }

    private static function ek(Source $s, int $j): int
    {
        return $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
    }

    private function listed(string $name): bool
    {
        return isset($this->escape[$name]) || isset($this->escapeCi[\strtolower($name)]);
    }

    /** a constant that gets a `\` or loses one (the exact tests on the Source), anywhere in the file: a superset of the scopes */
    protected function candidate(Source $s): bool
    {
        $uses = $this->constUses($s);
        $n = $s->n;
        for ($i = 1; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_STRING || $s->ct[$i] !== 0) {
                continue;
            }
            $text = $s->text($i);
            $prevNs = $s->kind[$i - 1] === \__McTokId::T_NS_SEPARATOR;
            if ($this->listed($text)) {
                if ($prevNs || isset($uses[$text]) || !self::isConstantInvocation($s, $i)) {
                    continue;
                }
                return true;
            }
            if (!$this->strict || !$prevNs || ($i >= 2 && $s->kind[$i - 2] === \__McTokId::T_STRING) || !self::isConstantInvocation($s, $i)) {
                continue;
            }
            return true;
        }
        return false;
    }

    /** @return array<string, bool> */
    private function constUses(Source $s): array
    {
        $out = [];
        foreach (NsAnalysis::singleImports($s) as $im) {
            if ($im[0] === 2) {
                $out[$im[2]] = true;
            }
        }
        return $out;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        if ($this->scope === 'all') {
            $this->fix($s, $t, 0, $t->count() - 1);
            return;
        }
        $scopes = NsAnalysis::scopes($s);
        for ($x = \count($scopes) - 1; $x >= 0; $x--) {
            [$name, $a, $b] = $scopes[$x];
            if ($name === '') {
                continue;
            }
            $from = $t->pos[$a] ?? -1;
            $to = $t->pos[$b] ?? -1;
            if ($from < 0 || $to < 0) {
                continue;
            }
            $this->fix($s, $t, $from, $to);
        }
    }

    private function fix(Source $s, FlatTokens $t, int $start, int $end): void
    {
        $uses = $this->constUses($s);
        for ($index = $end; $index > $start; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING || $t->i[$index] < 0) {
                continue;
            }
            if (!self::isConstantInvocation($s, $t->i[$index])) {
                continue;
            }
            $content = $t->content($index);
            $prev = $t->meaningful($index, -1);
            if (!$this->listed($content)) {
                if (!$this->strict) {
                    continue;
                }
                if (!self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                    continue;
                }
                $pp = $t->meaningful($prev, -1);
                if (self::is($t, $pp, \__McTokId::T_STRING)) {
                    continue;
                }
                self::clearMerge($t, $prev);
                continue;
            }
            if (isset($uses[$content])) {
                continue;
            }
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                continue;
            }
            $t->insertAt($index, [FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\')]);
        }
    }

    /** TokensAnalyzer::isConstantInvocation for the T_STRING at $i of the untouched Source */
    private static function isConstantInvocation(Source $s, int $i): bool
    {
        $n = $s->n;
        $nx = $i + 1;
        if ($nx >= $n) {
            return false;
        }
        $nk = self::ek($s, $nx);
        if ($nk === 40 || $nk === 123 || $nk === \__McTokId::T_DOUBLE_COLON || $nk === \__McTokId::T_ELLIPSIS || $nk === \__McTokId::T_NS_SEPARATOR || $nk === CT::RETURN_REF
            || $nk === CT::TYPE_ALTERNATION || $nk === CT::TYPE_INTERSECTION || $nk === \__McTokId::T_VARIABLE) {
            return false;
        }
        $pi = $i - 1;
        if ($nk === \__McTokId::T_AS) {
            if ($pi < 0 || self::ek($s, $pi) !== 40) {
                return false;
            }
        }
        $pk = $pi >= 0 ? self::ek($s, $pi) : -1;
        if ($pk === \__McTokId::T_CLASS || $pk === \__McTokId::T_INTERFACE || $pk === \__McTokId::T_TRAIT || $pk === \__McTokId::T_ENUM) {
            return false;
        }
        if ($pk === \__McTokId::T_AS || $pk === \__McTokId::T_CONST || $pk === \__McTokId::T_DOUBLE_COLON || $pk === \__McTokId::T_FUNCTION || $pk === \__McTokId::T_GOTO
            || $pk === CT::GROUP_IMPORT_BRACE_OPEN || $pk === CT::TYPE_COLON || $pk === CT::TYPE_ALTERNATION || $pk === CT::TYPE_INTERSECTION
            || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
            return false;
        }
        if ($pk === \__McTokId::T_CASE) {
            $hasEnum = false;
            for ($j = 0; $j < $n; $j++) {
                if ($s->kind[$j] === \__McTokId::T_ENUM && $s->ct[$j] === 0) {
                    $hasEnum = true;
                    break;
                }
            }
            if ($hasEnum) {
                $found = -1;
                for ($j = $i - 1; $j >= 0; $j--) {
                    $jk = self::ek($s, $j);
                    if ($jk === \__McTokId::T_SWITCH || $jk === \__McTokId::T_ENUM) {
                        $found = $jk;
                        break;
                    }
                }
                if ($found !== \__McTokId::T_SWITCH) {
                    return false;
                }
            }
        }
        while ($pi >= 0 && ($pk === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::ARRAY_TYPEHINT)) {
            $pi--;
            $pk = $pi >= 0 ? self::ek($s, $pi) : -1;
        }
        if ($pk === CT::CONST_IMPORT || $pk === \__McTokId::T_EXTENDS || $pk === CT::FUNCTION_IMPORT || $pk === \__McTokId::T_IMPLEMENTS || $pk === \__McTokId::T_INSTANCEOF
            || $pk === \__McTokId::T_INSTEADOF || $pk === \__McTokId::T_NAMESPACE || $pk === \__McTokId::T_NEW || $pk === CT::NULLABLE_TYPE || $pk === CT::TYPE_COLON
            || $pk === \__McTokId::T_USE || $pk === CT::USE_TRAIT || $pk === CT::TYPE_INTERSECTION || $pk === CT::TYPE_ALTERNATION || $pk === \__McTokId::T_CONST
            || $pk === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE) {
            return false;
        }
        $nAmp = $nk === 38 || $nk === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $nk === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
        if ($nAmp && $s->text($nx) === '&' && $nx + 1 < $n && $s->kind[$nx + 1] === \__McTokId::T_VARIABLE) {
            $c = -1;
            for ($j = $pi - 1; $j >= 0; $j--) {
                $jk = self::ek($s, $j);
                if ($jk === 59 || $jk === 123 || $jk === 125 || $jk === \__McTokId::T_FUNCTION || $jk === \__McTokId::T_OPEN_TAG || $jk === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                    $c = $jk;
                    break;
                }
            }
            if ($c === \__McTokId::T_FUNCTION) {
                return false;
            }
        }
        if ($pk === 44) {
            $ck = $pk;
            $ci = $pi;
            while ($ci >= 0 && ($ck === 44 || $ck === \__McTokId::T_AS || $ck === CT::NAMESPACE_OPERATOR || $ck === \__McTokId::T_NS_SEPARATOR || $ck === \__McTokId::T_STRING)) {
                $ci--;
                $ck = $ci >= 0 ? self::ek($s, $ci) : -1;
            }
            if ($ck === \__McTokId::T_EXTENDS || $ck === CT::GROUP_IMPORT_BRACE_OPEN || $ck === \__McTokId::T_IMPLEMENTS || $ck === \__McTokId::T_USE || $ck === CT::USE_TRAIT) {
                return false;
            }
        }
        if ($pk === 91 && $nk === 93) {
            $c2 = $nx + 1 < $n ? $nx + 1 : -1;
            if ($c2 >= 0) {
                $ck = self::ek($s, $c2);
                if ($ck === 34 || $ck === \__McTokId::T_CURLY_OPEN || $ck === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES || $ck === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $ck === \__McTokId::T_VARIABLE) {
                    return false;
                }
            }
        }
        if (NsAnalysis::isAttribute($s, $i)) {
            return false;
        }
        if ($nk === 58) {
            $a = $nx - 1;
            if ($a >= 0 && $s->kind[$a] === \__McTokId::T_STRING) {
                $b = $a - 1;
                if ($b >= 0) {
                    $bk = self::ek($s, $b);
                    if ($bk === 58 || $bk === 59 || $bk === 123 || $bk === 125 || $bk === \__McTokId::T_OPEN_TAG) {
                        return false;
                    }
                }
            }
        }
        while ($pi >= 0 && ($pk === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::TYPE_ALTERNATION)) {
            $pi--;
            $pk = $pi >= 0 ? self::ek($s, $pi) : -1;
        }
        if ($pk === 40) {
            $pp = $pi - 1;
            if ($pp >= 0 && self::ek($s, $pp) === \__McTokId::T_CATCH) {
                return false;
            }
        }
        return true;
    }
}
