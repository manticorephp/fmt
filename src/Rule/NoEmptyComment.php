<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;
use Fmt\Structure;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\NoEmptyCommentFixer (priority 2). A comment with no text (`//`, `#`, `/* ** *\/`) is removed; a run of `//` (or `#`)
 * comments on consecutive lines goes together only when every one of them is empty. Whole-file FlatTokens port (the removal merges the whitespace around it).
 */
final class NoEmptyComment extends FlatRule
{
    private const HASH = 1;
    private const SLASH = 2;
    private const BLOCK = 3;

    public function name(): string
    {
        return 'no_empty_comment';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(Source $s): bool
    {
        // a comment block never spans a code token, so the block walk of upstream runs per gap on its pieces; ASCII-only prefilter
        // (a comment of nothing but Unicode spaces is not found), the surgery itself is run() on the whole file
        for ($i = 1; $i <= $s->n; $i++) {
            if (($s->gapFlags[$i] & Gap::G_COMMENT) === 0 || \preg_match('~(?://|#)[ \t]*(?:\r|\n|\z)|/\*[ \t\r\n*]*\*/~', $s->gapBefore[$i]) !== 1) {
                continue;
            }
            $p = Gap::pieces($s->gapBefore[$i]);
            $m = \count($p);
            for ($q = 0; $q < $m; $q++) {
                if (Gap::isSpace($p[$q]) || Trivia::isDoc($p[$q])) {
                    continue;
                }
                $type = self::type($p[$q]);
                $empty = self::emptyComment($p[$q]);
                if ($type !== self::BLOCK) {
                    $j = $q + 1;
                    for (; $j < $m; $j++) {
                        if (!Gap::isSpace($p[$j])) {
                            if ($type !== self::type($p[$j])) {
                                break;
                            }
                            if ($empty) {
                                $empty = self::emptyComment($p[$j]);
                            }
                            continue;
                        }
                        if (\preg_match_all('/\R/u', $p[$j]) > 1) {
                            break;
                        }
                    }
                    $q = $j - 1;
                }
                if ($empty) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function type(string $c): int
    {
        if ($c[0] === '#') {
            return self::HASH;
        }
        return isset($c[1]) && $c[1] === '*' ? self::BLOCK : self::SLASH;
    }

    private static function emptyComment(string $c): bool
    {
        switch (self::type($c)) {
            case self::HASH:
                return Trivia::pmatch('|^#\s*$|', $c);
            case self::BLOCK:
                return Trivia::pmatch('|^/\*[\s\*]*\*+/$|', $c);
        }
        return Trivia::pmatch('|^//\s*$|', $c);
    }

    /**
     * Two words that touch after the removal lex as ONE identifier (`public/**\/const` -> `publicconst`). Upstream's lint step (the tokenizer's parser) then
     * accepts or rejects the file; there is no parser here, so the contexts seen in practice are modelled: a statement keyword before an operand still
     * parses (`echo1;`, `useFoo\Bar;` outside a class, `namespaceFoo;`), a modifier fused with a type inside a parameter list does (`publicarray $a`), anything
     * else (class-body modifiers and `use`, `final classX`, `extends`, ...) does not.
     */
    private static function glueRejected(Source $s, FlatTokens $t, int $l, int $r): bool
    {
        $a = $t->content($l);
        $b = $t->content($r);
        if ($a === '' || $b === '' || \preg_match('/[A-Za-z0-9_\x80-\xff]$/', $a) !== 1 || \preg_match('/^[A-Za-z0-9_\x80-\xff]/', $b) !== 1) {
            return false;
        }
        $la = \strtolower($a);
        $lb = \strtolower($b);
        $li = $t->i[$l];
        $ri = $t->i[$r];
        if ($li < 0 || $ri < 0) {
            return false;
        }
        // the token after the fused word and the qualified name it starts (`useFoo\Bar` is one name)
        $n = $ri + 1;
        while ($n + 1 < $s->n && $s->kind[$n] === \__McTokId::T_NS_SEPARATOR && $s->gapBefore[$n] === '' && $s->kind[$n + 1] === \__McTokId::T_STRING && $s->gapBefore[$n + 1] === '') {
            $n += 2;
        }
        if ($la === 'else') {
            return $lb !== 'if';
        }
        $st = $s->structure();
        $o = $st->parent[$li];
        $ow = $o >= 0 ? $st->owner[$o] : 0;
        if ($la === 'echo' || $la === 'print' || $la === 'return' || $la === 'throw' || $la === 'new' || $la === 'clone' || $la === 'yield' || $la === 'namespace'
            || $la === 'include' || $la === 'include_once' || $la === 'require' || $la === 'require_once' || $la === 'goto' || $la === 'use') {
            if ($la === 'use' && ($ow === Structure::OW_CLASS_BODY || $lb === 'function' || $lb === 'const')) {
                return true;
            }
            return !self::validNext($s, $n);
        }
        if ($la === 'public' || $la === 'protected' || $la === 'private' || $la === 'static' || $la === 'readonly' || $la === 'final' || $la === 'abstract' || $la === 'var') {
            if ($ow !== Structure::OW_PARAMS || $n >= $s->n) {
                return true;
            }
            $k = $s->kind[$n];
            return !($k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_ELLIPSIS || $k === 38 || $k === 124 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
                || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG || $s->ct[$n] === CT::TYPE_ALTERNATION || $s->ct[$n] === CT::TYPE_INTERSECTION);
        }
        return true;
    }

    /** the token after a fused identifier continues an expression (an operator, a bracket, `;`), not a second operand */
    private static function validNext(Source $s, int $n): bool
    {
        if ($n >= $s->n) {
            return true;
        }
        $k = $s->kind[$n];
        if ($s->ct[$n] !== 0) {
            return $s->ct[$n] === CT::ARRAY_BRACKET_CLOSE || $s->ct[$n] === CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN;
        }
        if ($k < 256) {
            return $k !== 36 && $k !== 34 && $k !== 39 && $k !== 96 && $k !== 92 && $k !== 123;
        }
        return $k === \__McTokId::T_DOUBLE_COLON || $k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $k === \__McTokId::T_CLOSE_TAG
            || $k === \__McTokId::T_IS_EQUAL || $k === \__McTokId::T_IS_NOT_EQUAL || $k === \__McTokId::T_IS_IDENTICAL || $k === \__McTokId::T_IS_NOT_IDENTICAL
            || $k === \__McTokId::T_IS_SMALLER_OR_EQUAL || $k === \__McTokId::T_IS_GREATER_OR_EQUAL || $k === \__McTokId::T_BOOLEAN_AND || $k === \__McTokId::T_BOOLEAN_OR
            || $k === \__McTokId::T_COALESCE || $k === \__McTokId::T_SPACESHIP || $k === \__McTokId::T_INC || $k === \__McTokId::T_DEC || $k === \__McTokId::T_INSTANCEOF
            || $k === \__McTokId::T_LOGICAL_AND || $k === \__McTokId::T_LOGICAL_OR || $k === \__McTokId::T_LOGICAL_XOR || $k === \__McTokId::T_SL || $k === \__McTokId::T_SR
            || $k === \__McTokId::T_POW || $k === \__McTokId::T_PIPE;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $count = $t->count();
        for ($q = 1; $q < $count; $q++) {
            if (!$t->isComment($q) || Trivia::isDoc($t->c[$q])) {
                continue;
            }
            $type = self::type($t->c[$q]);
            $empty = self::emptyComment($t->c[$q]);
            $start = $q;
            if ($type === self::BLOCK) {
                $end = $q;
            } else {
                $j = $q + 1;
                for (; $j < $count; $j++) {
                    if ($t->isComment($j)) {
                        if ($type !== self::type($t->c[$j])) {
                            break;
                        }
                        if ($empty) {
                            $empty = self::emptyComment($t->c[$j]);
                        }
                        continue;
                    }
                    if (!$t->isWs($j) || \preg_match_all('/\R/u', $t->c[$j]) > 1) {
                        break;
                    }
                }
                $end = $j - 1;
            }
            $q = $end;
            if (!$empty) {
                continue;
            }
            for ($i = $start; $i <= $end; $i++) {
                self::clearMerge($t, $i);
            }
            $l = $t->nonEmptySibling($start, -1);
            $r = $t->nonEmptySibling($end, 1);
            if ($l >= 0 && $r >= 0 && $t->isCode($l) && $t->isCode($r) && self::glueRejected($s, $t, $l, $r)) {
                $s->abort = true; // the neighbours fuse into one word (`public/**/const`): upstream's lint step rejects the result and leaves the file
                return;
            }
        }
    }
}
