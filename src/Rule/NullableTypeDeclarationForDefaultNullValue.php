<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\NullableTypeDeclarationForDefaultNullValueFixer (priority 3), use_nullable_type_declaration=true. A typed
 * parameter whose default is `null` (any case) gets a `?` (`T $a = null` -> `?T $a = null`), a union `|null` (`A|B` -> `A|B|null`), an intersection `(A&B)|null`;
 * `mixed`, `null` and already-nullable types stay. Whole-file FlatTokens port, functions last to first, parameters last to first.
 */
final class NullableTypeDeclarationForDefaultNullValue extends FlatRule
{
    private TypeWalk $tw;

    public function name(): string
    {
        return 'nullable_type_declaration_for_default_null_value';
    }

    public function priority(): int
    {
        return 3;
    }

    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 3; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_STRING || $s->ct[$i] !== 0 || $s->end[$i] - $s->start[$i] !== 4 || $s->kind[$i - 1] !== 61 || $s->ct[$i - 1] !== 0
                || $s->kind[$i - 2] !== \__McTokId::T_VARIABLE || \strtolower($s->text($i)) !== 'null') {
                continue;
            }
            // the type chain in front of the variable
            $j = $i - 3;
            if ($s->kind[$j] === \__McTokId::T_ELLIPSIS || $s->kind[$j] === 38 || $s->kind[$j] === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
                || $s->kind[$j] === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                $j--;
            }
            $typed = false;
            $null = false;
            for (; $j > 0; $j--) {
                $k = $s->kind[$j];
                $c = $s->ct[$j];
                if ($c === CT::NULLABLE_TYPE) {
                    $null = true;
                    $typed = true;
                } elseif ($k === \__McTokId::T_STRING && $c === 0) {
                    $typed = true;
                    $null = $null || \strtolower($s->text($j)) === 'null';
                    if (\strtolower($s->text($j)) === 'mixed') {
                        $null = true;
                    }
                } elseif ($k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_ARRAY || $k === \__McTokId::T_CALLABLE || $k === \__McTokId::T_STATIC || $c === CT::TYPE_ALTERNATION
                    || $c === CT::TYPE_INTERSECTION || $c === CT::ARRAY_TYPEHINT || $c === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN
                    || $c === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE) {
                    $typed = true;
                } else {
                    break;
                }
            }
            if ($typed && !$null) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->tw = new TypeWalk($t);
        for ($q = $t->count() - 1; $q >= 0; $q--) {
            if ($t->i[$q] === -1 || !($t->k[$q] === \__McTokId::T_FUNCTION || $t->k[$q] === \__McTokId::T_FN)) {
                continue;
            }
            $this->fixArgs($t, $q);
        }
    }

    private function fixArgs(FlatTokens $t, int $q): void
    {
        $open = self::nextOf($t, $q, 40);
        if ($open < 0) {
            return;
        }
        $close = $t->blockEnd($open, 41);
        if ($close < 0 || self::is($t, $t->meaningful($open, 1), 41)) {
            return;
        }
        $ranges = [];
        $p = $open + 1;
        $as = $p;
        for (; $p < $close; $p++) {
            if ($t->i[$p] === -1) {
                continue;
            }
            $cl = TypeWalk::closer($t->k[$p]);
            if ($cl >= 0) {
                $p = $t->blockEnd($p, $cl);
                continue;
            }
            if ($t->k[$p] === 44) {
                if ($t->meaningful($p, 1) === $close) {
                    break;
                }
                $ranges[$as] = $p - 1;
                $as = $p + 1;
            }
        }
        $ranges[$as] = $p - 1;
        $infos = [];
        foreach ($ranges as $a => $b) {
            $info = $this->info($t, $a, $b);
            $infos[$info['name']] = $info;
        }
        foreach (\array_reverse($infos) as $info) {
            if ($info['type'] === null || $info['default'] === null || \strtolower($info['default']) !== 'null') {
                continue;
            }
            $name = $info['type'];
            $nullable = false;
            if (\str_starts_with($name, '?')) {
                $name = \substr($name, 1);
                $nullable = true;
            } else {
                $nullable = \in_array('null', \array_map('trim', \explode('|', \strtolower($name))), true);
            }
            if (\in_array(\strtolower($name), ['mixed', 'null'], true)) {
                continue;
            }
            if (\str_contains($name, '|') || \str_contains($name, '&')) {
                if ($nullable) {
                    continue;
                }
                $end = $info['te'];
                if (\str_contains($name, '&') && !\str_contains($name, '|')) {
                    $end += 2;
                    $t->insertAt($info['ts'], [FlatTokens::code(CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN, '(')]);
                    $t->insertAt($end, [FlatTokens::code(CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE, ')')]);
                }
                $t->insertAt($end + 1, [FlatTokens::code(CT::TYPE_ALTERNATION, '|'), FlatTokens::code(\__McTokId::T_STRING, 'null')]);
            } elseif (!$nullable) {
                $t->insertAt($info['ts'], [FlatTokens::code(CT::NULLABLE_TYPE, '?')]);
            }
        }
    }

    /** @return array{name:string,type:?string,ts:int,te:int,default:?string} ArgumentsAnalyzer::getArgumentInfo */
    private function info(FlatTokens $t, int $a, int $b): array
    {
        $name = null;
        $type = null;
        $ts = 0;
        $te = 0;
        $def = null;
        for ($q = $a; $q <= $b; $q++) {
            $k = $t->k[$q];
            if ($k === \__McTokId::T_ATTRIBUTE) {
                $q = $t->blockEnd($q, CT::ATTRIBUTE_CLOSE);
                continue;
            }
            if ($k === CT::PROPERTY_HOOK_BRACE_OPEN) {
                $q = $t->blockEnd($q, CT::PROPERTY_HOOK_BRACE_CLOSE);
                continue;
            }
            if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT || $k === \__McTokId::T_ELLIPSIS || $k === \__McTokId::T_FINAL
                || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE
                || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET || $k === 38
                || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                continue;
            }
            if ($k === \__McTokId::T_VARIABLE) {
                if ($name !== null) {
                    continue;
                }
                $name = $t->content($q);
                continue;
            }
            if ($k === 61) {
                continue;
            }
            if ($name !== null) {
                $def = ($def ?? '') . $t->content($q);
            } else {
                $ts = $ts > 0 ? $ts : $q;
                $te = $q;
                $type = ($type ?? '') . $t->content($q);
            }
        }
        if ($name === null) {
            return ['name' => '', 'type' => null, 'ts' => 0, 'te' => 0, 'default' => null];
        }
        return ['name' => $name, 'type' => $type, 'ts' => $ts, 'te' => $te, 'default' => $def];
    }
}
