<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatBlocks;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer (priority 0), always_move_variable=false. In a comparison (`==` `!=` `<>` per `equal`,
 * `===` `!==` per `identical`, `<` `<=` `>` `>=` per `less_and_greater`; true = Yoda `1 === $a`, false = `$a === 1`, null = untouched) the two sides swap when
 * exactly one of them is a variable chain, the other being the one that should be on the left. Each side is re-lexed as a fragment and fixed recursively.
 * Whole-file FlatTokens port, last token to first, jumping below each swap.
 */
final class YodaStyle extends FlatRule
{
    /** @var array<int, bool> effective id => yoda? for the candidate operators */
    private array $conf = [];
    /** @var array<int, array{0:int,1:string}> operator id => swapped operator */
    private array $swap = [];
    private FlatBlocks $b;

    public function __construct(?bool $equal, ?bool $identical, ?bool $lessGreater)
    {
        parent::__construct();
        $this->b = new FlatBlocks();
        if ($equal !== null) {
            $this->conf[\__McTokId::T_IS_EQUAL] = $equal;
            $this->conf[\__McTokId::T_IS_NOT_EQUAL] = $equal;
        }
        if ($identical !== null) {
            $this->conf[\__McTokId::T_IS_IDENTICAL] = $identical;
            $this->conf[\__McTokId::T_IS_NOT_IDENTICAL] = $identical;
        }
        if ($lessGreater !== null) {
            $this->conf[\__McTokId::T_IS_SMALLER_OR_EQUAL] = $lessGreater;
            $this->conf[\__McTokId::T_IS_GREATER_OR_EQUAL] = $lessGreater;
            $this->conf[60] = $lessGreater;
            $this->conf[62] = $lessGreater;
            $this->swap[\__McTokId::T_IS_SMALLER_OR_EQUAL] = [\__McTokId::T_IS_GREATER_OR_EQUAL, '>='];
            $this->swap[\__McTokId::T_IS_GREATER_OR_EQUAL] = [\__McTokId::T_IS_SMALLER_OR_EQUAL, '<='];
            $this->swap[60] = [62, '>'];
            $this->swap[62] = [60, '<'];
        }
    }

    public function name(): string
    {
        return 'yoda_style';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->ct[$i] === 0 && isset($this->conf[$s->kind[$i]])) {
                return true;
            }
        }
        return false;
    }

    private ?Source $top = null;

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->top = $s;
        $this->fixTokens($t);
        $this->top = null;
    }

    private function fixTokens(FlatTokens $t): void
    {
        $src = $this->top;
        $this->top = null; // only the whole-file list reports an unlintable result
        for ($i = $t->count() - 1; $i > 1; $i--) {
            if ($t->i[$i] === -1 || !isset($this->conf[$t->k[$i]])) {
                continue;
            }
            $yoda = $this->conf[$t->k[$i]];
            $info = $this->compareInfo($t, $i, $yoda);
            if ($info === null) {
                continue;
            }
            if ($src !== null && $yoda) {
                $p = $t->meaningful($info[0], -1);
                if ($p >= 0 && $t->i[$p] !== -1 && ($t->k[$p] === 38 || $t->k[$p] === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $t->k[$p] === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
                    $src->abort = true; // `&$a === 1` -> `&1 === $a` does not lint: upstream leaves the file
                    return;
                }
            }
            $i = $this->fixCompare($t, $info[0], $info[1], $i, $info[2], $info[3]);
        }
    }

    private static function lowerAssign(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === 61 || $k === \__McTokId::T_AND_EQUAL || $k === \__McTokId::T_CONCAT_EQUAL || $k === \__McTokId::T_DIV_EQUAL || $k === \__McTokId::T_MINUS_EQUAL
            || $k === \__McTokId::T_MOD_EQUAL || $k === \__McTokId::T_MUL_EQUAL || $k === \__McTokId::T_OR_EQUAL || $k === \__McTokId::T_PLUS_EQUAL || $k === \__McTokId::T_POW_EQUAL
            || $k === \__McTokId::T_SL_EQUAL || $k === \__McTokId::T_SR_EQUAL || $k === \__McTokId::T_XOR_EQUAL || $k === \__McTokId::T_COALESCE_EQUAL;
    }

    private static function lower(FlatTokens $t, int $q): bool
    {
        if (self::lowerAssign($t, $q)) {
            return true;
        }
        if ($t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === \__McTokId::T_BOOLEAN_AND || $k === \__McTokId::T_BOOLEAN_OR || $k === \__McTokId::T_CASE || $k === \__McTokId::T_DOUBLE_ARROW || $k === \__McTokId::T_ECHO
            || $k === \__McTokId::T_GOTO || $k === \__McTokId::T_LOGICAL_AND || $k === \__McTokId::T_LOGICAL_OR || $k === \__McTokId::T_LOGICAL_XOR || $k === \__McTokId::T_OPEN_TAG
            || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === \__McTokId::T_PRINT || $k === \__McTokId::T_RETURN || $k === \__McTokId::T_THROW || $k === \__McTokId::T_COALESCE
            || $k === \__McTokId::T_YIELD || $k === \__McTokId::T_YIELD_FROM || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE || $k === \__McTokId::T_INCLUDE
            || $k === \__McTokId::T_INCLUDE_ONCE || $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
            || $k === 124 || $k === 94 || $k === 63 || $k === 58 || $k === 44 || $k === 59;
    }

    private function end(FlatTokens $t, int $q): int
    {
        $q++;
        $m = $t->count();
        while ($q < $m) {
            if ($t->k[$q] === FlatTokens::WS || $t->k[$q] === FlatTokens::COMMENT) {
                $q++;
                continue;
            }
            if (self::lower($t, $q)) {
                break;
            }
            if (!$this->b->isStart($t, $q) && !$this->b->isEnd($t, $q)) {
                $q++;
                continue;
            }
            if (!$this->b->isStart($t, $q)) {
                break;
            }
            $e = $this->b->end($t, $q);
            if ($e < 0) {
                break;
            }
            $q = $e + 1;
        }
        $p = $t->meaningful($q, -1);
        return $p >= 0 && $t->i[$p] !== -1 && $t->k[$p] === \__McTokId::T_CLOSE_TAG ? $t->meaningful($p, -1) : $p;
    }

    private function start(FlatTokens $t, int $q): int
    {
        $q--;
        $nonBlock = false;
        while ($q >= 0) {
            if ($t->k[$q] === FlatTokens::WS || $t->k[$q] === FlatTokens::COMMENT) {
                $q--;
                continue;
            }
            if ($t->k[$q] === CT::NAMED_ARGUMENT_COLON) {
                break;
            }
            if (self::lower($t, $q)) {
                break;
            }
            $isS = $this->b->isStart($t, $q);
            $isE = $this->b->isEnd($t, $q);
            if (!$isS && !$isE) {
                $q--;
                $nonBlock = true;
                continue;
            }
            if ($isS || ($nonBlock && $t->k[$q] === 125)) {
                break;
            }
            $o = $this->b->start($t, $q);
            if ($o < 0) {
                break;
            }
            $q = $o - 1;
        }
        return $t->meaningful($q, 1);
    }

    /** @return array{0:int,1:int,2:int,3:int}|null left start, left end, right start, right end */
    private function compareInfo(FlatTokens $t, int $q, bool $yoda): ?array
    {
        $rs = $t->meaningful($q, 1);
        $re = $this->end($t, $q);
        if ($rs < 0 || $re < 0) {
            return null;
        }
        if (!$yoda && self::lowerAssign($t, $t->meaningful($re, 1))) {
            return null;
        }
        $ls = $this->start($t, $q);
        $le = $t->meaningful($q, -1);
        if ($ls < 0 || $le < 0 || self::isList($t, $ls, $le) || self::isList($t, $rs, $re)) {
            return null;
        }
        $lv = $this->isVariable($t, $ls, $le);
        $rv = $this->isVariable($t, $rs, $re);
        if (!($lv xor $rv)) {
            return null;
        }
        $lv = $lv && !self::is($t, $ls, 40);
        $rv = $rv && !self::is($t, $rs, 40);
        return ($yoda && !$lv) || (!$yoda && !$rv) ? null : [$ls, $le, $rs, $re];
    }

    private static function isList(FlatTokens $t, int $a, int $b): bool
    {
        for ($i = $a; $i <= $b; $i++) {
            if ($t->i[$i] !== -1 && ($t->k[$i] === \__McTokId::T_LIST || $t->k[$i] === CT::DESTRUCTURING_BRACKET_OPEN || $t->k[$i] === CT::DESTRUCTURING_BRACKET_CLOSE)) {
                return true;
            }
        }
        return false;
    }

    private function isVariable(FlatTokens $t, int $start, int $end): bool
    {
        if ($start === $end) {
            return self::is($t, $start, \__McTokId::T_VARIABLE);
        }
        if (self::is($t, $start, 40)) {
            return true;
        }
        $index = $start;
        while (self::is($t, $index, 40) && $t->blockEnd($index, 41) === $end) {
            $index = $t->meaningful($index, 1);
            $end = $t->meaningful($end, -1);
        }
        $expectString = false;
        while ($index <= $end) {
            if ($t->i[$index] === -1) { // whitespace, comment, an emptied item
                $index++;
                continue;
            }
            if ($index === $end) {
                return $t->k[$index] === ($expectString ? \__McTokId::T_STRING : \__McTokId::T_VARIABLE);
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_LIST || $k === CT::DESTRUCTURING_BRACKET_OPEN || $k === CT::DESTRUCTURING_BRACKET_CLOSE) {
                return false;
            }
            $ni = $t->meaningful($index, 1);
            $nk = $ni >= 0 ? $t->k[$ni] : -1;
            if ($k === \__McTokId::T_STRING && $nk === \__McTokId::T_DOUBLE_COLON) {
                $index = $t->meaningful($ni, 1);
                continue;
            }
            if ($k === \__McTokId::T_NS_SEPARATOR && $nk === \__McTokId::T_STRING) {
                $index = $ni;
                continue;
            }
            if ($k === \__McTokId::T_STRING && $nk === \__McTokId::T_NS_SEPARATOR) {
                $index = $ni;
                continue;
            }
            if (($k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE) && ($nk === \__McTokId::T_OBJECT_OPERATOR || $nk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                $index = $t->meaningful($ni, 1);
                $expectString = true;
                continue;
            }
            if ($k === ($expectString ? \__McTokId::T_STRING : \__McTokId::T_VARIABLE) && ($nk === 91 || $nk === CT::ARRAY_INDEX_BRACE_OPEN)) {
                $index = $t->blockEnd($ni, $nk === 91 ? 93 : CT::ARRAY_INDEX_BRACE_CLOSE);
                if ($index === $end) {
                    return true;
                }
                $index = $t->meaningful($index, 1);
                $x = $index >= 0 ? $t->k[$index] : -1;
                if (!($x === 91 || $x === CT::ARRAY_INDEX_BRACE_OPEN || $x === \__McTokId::T_OBJECT_OPERATOR || $x === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                    return false;
                }
                $index = $t->meaningful($index, 1);
                $expectString = true;
                continue;
            }
            if ($expectString && $k === CT::DYNAMIC_PROP_BRACE_OPEN) {
                $index = $t->blockEnd($index, CT::DYNAMIC_PROP_BRACE_CLOSE);
                if ($index === $end) {
                    return true;
                }
                $index = $t->meaningful($index, 1);
                $x = $index >= 0 ? $t->k[$index] : -1;
                if (!($x === \__McTokId::T_OBJECT_OPERATOR || $x === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                    return false;
                }
                $index = $t->meaningful($index, 1);
                $expectString = true;
                continue;
            }
            break;
        }
        return !$this->isConstant($t, $start, $end);
    }

    private function isConstant(FlatTokens $t, int $i, int $end): bool
    {
        $arr = false;
        $num = false;
        $nothing = false;
        for (; $i <= $end; $i++) {
            if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                continue;
            }
            if ($nothing) {
                return false;
            }
            $k = $t->k[$i];
            if ($k === \__McTokId::T_STRING && $i + 1 <= $end && $t->i[$i + 1] !== -1 && $t->k[$i + 1] === \__McTokId::T_CONSTANT_ENCAPSED_STRING
                && ($t->content($i) === 'b' || $t->content($i) === 'B')) {
                continue; // the tokenizer splits the binary flag of b'x' off (issue #371); Zend has one string token
            }
            if ($arr) {
                if ($k === 40 || $k === 41 || $k === CT::ARRAY_BRACKET_CLOSE) {
                    continue;
                }
                return false;
            }
            if ($k === \__McTokId::T_ARRAY || $k === CT::ARRAY_BRACKET_OPEN) {
                $arr = true;
                continue;
            }
            if ($num && !($k === \__McTokId::T_LNUMBER || $k === \__McTokId::T_DNUMBER)) {
                return false;
            }
            if ($k === 45) {
                $num = true;
                continue;
            }
            if ($k === \__McTokId::T_LNUMBER || $k === \__McTokId::T_DNUMBER || $k === \__McTokId::T_CONSTANT_ENCAPSED_STRING
                || ($k === \__McTokId::T_STRING && ($t->content($i) === 'true' || $t->content($i) === 'false' || $t->content($i) === 'null'))) {
                $nothing = true;
                continue;
            }
            return false;
        }
        return true;
    }

    private static function content(FlatTokens $t, int $q): string
    {
        return $t->i[$q] !== -1 ? $t->content($q) : $t->c[$q];
    }

    /**
     * The swap, written over the range in place (right side, the operator part, left side): the cleared placeholders upstream leaves behind
     * contribute no text and nothing below the range looks at them, so the list is not spliced (a splice of a whole-file list per comparison is
     * quadratic). Only when the new content is longer than the range does the list grow.
     */
    private function fixCompare(FlatTokens $t, int $sl, int $el, int $op, int $sr, int $er): int
    {
        $k = $t->k[$op];
        if (isset($this->swap[$k])) {
            $t->k[$op] = $this->swap[$k][0];
            $t->i[$op] = FlatTokens::NEW;
            $t->c[$op] = $this->swap[$k][1];
        }
        $right = $this->part($t, $sr, $er);
        $left = $this->part($t, $sl, $el);
        $new = $right;
        for ($q = $el + 1; $q < $sr; $q++) {
            if (!$t->isEmpty($q)) {
                $new[] = [$t->k[$q], $t->c[$q], $t->i[$q]];
            }
        }
        foreach ($left as $it) {
            $new[] = $it;
        }
        $cnt = \count($new);
        $span = $er - $sl + 1;
        if ($cnt > $span) {
            $ph = [];
            for ($x = $span; $x < $cnt; $x++) {
                $ph[] = FlatTokens::ws('');
            }
            $t->insertAt($er + 1, $ph);
        }
        foreach ($new as $j => $it) {
            $t->k[$sl + $j] = $it[0];
            $t->c[$sl + $j] = $it[1];
            $t->i[$sl + $j] = $it[2];
        }
        for ($j = $cnt; $j < $span; $j++) {
            $t->clearAt($sl + $j);
        }
        return $sl;
    }

    /** @return array<int, array{0:int,1:string,2:int}> */
    private function part(FlatTokens $t, int $a, int $b): array
    {
        $has = false;
        for ($i = $a; $i <= $b; $i++) {
            if ($t->i[$i] !== -1 && isset($this->conf[$t->k[$i]])) {
                $has = true;
                break;
            }
        }
        if (!$has) { // nothing to fix inside: the re-lexed fragment would be these same items
            $out = [];
            for ($i = $a; $i <= $b; $i++) {
                if (!$t->isEmpty($i)) {
                    $out[] = [$t->k[$i], $t->c[$i], $t->i[$i]];
                }
            }
            return $out;
        }
        $code = '';
        for ($i = $a; $i <= $b; $i++) {
            $code .= self::content($t, $i);
        }
        $frag = Source::parse('<?php ' . $code . ';');
        $ft = new FlatTokens();
        $ft->load($frag, 0, $frag->n - 1, true);
        $this->fixTokens($ft);
        $items = [];
        $m = $ft->count();
        for ($q = 1; $q < $m - 1; $q++) {
            if ($ft->isEmpty($q)) {
                continue;
            }
            $items[] = $ft->i[$q] !== -1 ? [$ft->k[$q], $ft->content($q), FlatTokens::NEW] : [$ft->k[$q], $ft->c[$q], -1];
        }
        // the last item (`;`) is cleared by upstream; the trailing trivia of the fragment stays out (none: the fragment ends with `;`)
        return $items;
    }
}
