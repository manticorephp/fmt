<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer (priority 40). Superfluous `;` go (behind `;`, `{`, the open tag, and behind a `}` that
 * ends a block / control structure / declaration), a noop statement `1;` / `$a;` / `foo;` with its `;`, and the `1` of `break 1;` / `continue 1;`.
 * Upstream clears tokens and merges the whitespace around them, which as text is just the token's bytes gone, so the rule deletes token units; its
 * walk (a cleared token is invisible to "previous meaningful", the index quirks around `for` and `break`) runs on a deleted-set over the Source.
 */
final class NoEmptyStatement extends EditRule
{
    /** @var array<int, bool> */
    private array $del = [];

    public function name(): string
    {
        return 'no_empty_statement';
    }

    public function priority(): int
    {
        return 40;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === 59) {
                return true;
            }
        }
        return false;
    }

    private function prev(Source $s, int $i): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!isset($this->del[$j])) {
                return $j;
            }
        }
        return -1;
    }

    private function next(Source $s, int $i): int
    {
        for ($j = $i + 1; $j < $s->n; $j++) {
            if (!isset($this->del[$j])) {
                return $j;
            }
        }
        return $s->n;
    }

    private function plain(Source $s, int $j, int $k): bool
    {
        return $j >= 0 && $j < $s->n && $s->kind[$j] === $k && $s->ct[$j] === 0;
    }

    public function fixFile(Source $s): void
    {
        $this->del = [];
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_BREAK || $k === \__McTokId::T_CONTINUE) && $s->ct[$i] === 0) {
                $i = $this->next($s, $i);
                if ($i < $n && $s->kind[$i] === \__McTokId::T_LNUMBER && $s->ct[$i] === 0 && $s->text($i) === '1') {
                    $this->del[$i] = true;
                }
                continue;
            }
            if ($k === \__McTokId::T_FOR && $s->ct[$i] === 0) {
                $o = $this->next($s, $i);
                $c = $o < $n ? $s->pair[$o] : -1;
                if ($c < 0) {
                    return;
                }
                // upstream continues at the index after `)` and the loop steps once more: with no trivia behind `)` the next code token is jumped
                $i = $c + ($c + 1 < $n && $s->gapBefore[$c + 1] === '' ? 1 : 0);
                continue;
            }
            if ($k !== 59 || $s->ct[$i] !== 0) {
                continue;
            }
            $p = $this->prev($s, $i);
            if ($p < 0) {
                continue;
            }
            $pk = $s->kind[$p];
            if ($s->ct[$p] === 0 && ($pk === 123 || $pk === 59) || $pk === \__McTokId::T_OPEN_TAG) {
                $this->del[$i] = true;
                continue;
            }
            if ($pk === 125 && $s->ct[$p] === 0) {
                if ($this->afterBrace($s, $i, $p)) {
                    $this->del[$i] = true;
                }
                continue;
            }
            $nx = $this->next($s, $i);
            if ($nx < $n && $s->ct[$nx] === CT::PROPERTY_HOOK_BRACE_CLOSE) {
                continue;
            }
            $pp = $this->prev($s, $p);
            if ($pp < 0 || $s->ct[$p] !== 0) {
                continue;
            }
            $ppk = $s->kind[$pp];
            $ppOk = $s->ct[$pp] === 0 ? ($ppk === 59 || $ppk === 123 || $ppk === 125 || $ppk === \__McTokId::T_OPEN_TAG) : false;
            if ($ppOk && ($pk === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $pk === \__McTokId::T_DNUMBER || $pk === \__McTokId::T_LNUMBER
                || $pk === \__McTokId::T_STRING || $pk === \__McTokId::T_VARIABLE)) {
                $this->del[$i] = true;
                $this->del[$p] = true;
            }
        }
        foreach ($this->del as $j => $_) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, '');
        }
        $this->del = [];
    }

    private function afterBrace(Source $s, int $semi, int $close): bool
    {
        $o = $s->pair[$close];
        if ($o < 0) {
            return false;
        }
        $b = $this->prev($s, $o);
        if ($b < 0) {
            return false;
        }
        $bk = $s->kind[$b];
        $plain = $s->ct[$b] === 0;
        if ($plain && ($bk === \__McTokId::T_ELSE || $bk === \__McTokId::T_FINALLY || $bk === \__McTokId::T_NAMESPACE || $bk === \__McTokId::T_OPEN_TAG
            || $bk === 59 || $bk === 123 || $bk === 125)) {
            return true;
        }
        if ($bk === \__McTokId::T_OPEN_TAG) {
            return true;
        }
        if ($plain && $bk === \__McTokId::T_STRING) {
            $c = $this->prev($s, $b);
            while ($c >= 0 && $s->ct[$c] === 0 && ($s->kind[$c] === 44 || $s->kind[$c] === \__McTokId::T_STRING || $s->kind[$c] === \__McTokId::T_NS_SEPARATOR
                || $s->kind[$c] === \__McTokId::T_EXTENDS || $s->kind[$c] === \__McTokId::T_IMPLEMENTS)) {
                $c = $this->prev($s, $c);
            }
            if ($c < 0) {
                return false;
            }
            $ck = $s->kind[$c];
            return $s->ct[$c] === 0 && ($ck === \__McTokId::T_NAMESPACE
                || (($ck === \__McTokId::T_CLASS || $ck === \__McTokId::T_INTERFACE || $ck === \__McTokId::T_TRAIT || $ck === \__McTokId::T_ENUM) && !Analyzer::isAnonymousClass($s, $c)));
        }
        if (!($plain && $bk === 41)) {
            return false;
        }
        $op = $s->pair[$b];
        $bo = $op >= 0 ? $this->prev($s, $op) : -1;
        if ($bo < 0 || $s->ct[$bo] !== 0) {
            return false;
        }
        $k = $s->kind[$bo];
        if ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_WHILE
            || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_DECLARE) {
            return true;
        }
        if ($k === \__McTokId::T_STRING) {
            $bs = $this->prev($s, $bo);
            return $bs >= 0 && $s->kind[$bs] === \__McTokId::T_FUNCTION && $s->ct[$bs] === 0;
        }
        return false;
    }
}
