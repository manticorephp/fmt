<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer (priority 2, risky). strategy `enforce`: `declare(strict_types=1);` is inserted behind the open tag, an existing
 * one gets its casing and value fixed; `add_when_missing` leaves an existing value; `remove` deletes the `strict_types` declaration (the whole `declare(..);` when it is the only
 * option). Monolithic PHP files only (one open tag, no inline HTML but a shebang). Whole-file FlatTokens port. The deprecated option `preserve_existing_declaration` is refused.
 */
final class DeclareStrictTypes extends FlatRule
{
    public function __construct(private string $strategy, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'declare_strict_types';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(Source $s): bool
    {
        $tags = 0;
        $html = 0;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return false;
            }
            if ($k === \__McTokId::T_OPEN_TAG) {
                $tags++;
            } elseif ($k === \__McTokId::T_INLINE_HTML) {
                $html++;
            }
        }
        if ($tags !== 1) {
            return false;
        }
        return $html === 0 || ($html === 1 && $s->kind[0] === \__McTokId::T_INLINE_HTML && \preg_match('/^#!.+$/', $s->text(0)) === 1);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $open = $t->k[0] === \__McTokId::T_INLINE_HTML ? 1 : 0;
        $decl = $this->declaration($t);
        if ($this->strategy === 'remove') {
            if ($decl !== null) {
                $this->remove($t, $decl);
            }
            return;
        }
        if ($decl === null) {
            $this->insert($t, $open);
            return;
        }
        foreach ($decl['seq'] as $q) {
            if ($t->k[$q] === \__McTokId::T_STRING) {
                self::setCode($t, $q, \__McTokId::T_STRING, \strtolower($t->content($q)));
                continue;
            }
            if ($t->k[$q] === \__McTokId::T_LNUMBER && $this->strategy === 'enforce') {
                self::setCode($t, $q, \__McTokId::T_LNUMBER, '1');
                break;
            }
        }
    }

    /** @return array{declare:int,open:int,close:int,seq:int[]}|null */
    private function declaration(FlatTokens $t): ?array
    {
        $m = $t->count();
        for ($i = 0; $i < $m; $i++) {
            if ($t->i[$i] === -1 || $t->k[$i] !== \__McTokId::T_DECLARE) {
                continue;
            }
            $open = $t->meaningful($i, 1);
            $close = $t->blockEnd($open, 41);
            for ($j = $open; $j <= $close; $j++) {
                if ($t->i[$j] === -1 || $t->k[$j] !== \__McTokId::T_STRING || \strtolower($t->content($j)) !== 'strict_types') {
                    continue;
                }
                $eq = $t->meaningful($j, 1);
                if ($eq < 0 || $eq > $close || !self::is($t, $eq, 61)) {
                    continue;
                }
                $num = $t->meaningful($eq, 1);
                if ($num < 0 || $num > $close || !self::is($t, $num, \__McTokId::T_LNUMBER)) {
                    continue;
                }
                return ['declare' => $i, 'open' => $open, 'close' => $close, 'seq' => [$j, $eq, $num]];
            }
        }
        return null;
    }

    /** @param array{declare:int,open:int,close:int,seq:int[]} $d */
    private function remove(FlatTokens $t, array $d): void
    {
        $start = $d['seq'][0];
        $end = $d['seq'][2];
        $other = false;
        $i = $t->meaningful($d['open'], 1);
        while ($i >= 0 && $i < $d['close']) {
            if ($t->k[$i] === \__McTokId::T_STRING && $i !== $d['seq'][0] && $i !== $d['seq'][1] && $i !== $d['seq'][2]) {
                $other = true;
                break;
            }
            $i = $t->meaningful($i, 1);
        }
        if (!$other) {
            $semi = $t->meaningful($d['close'], 1);
            if ($semi < 0) {
                return;
            }
            for ($i = $semi; $i >= $d['declare']; $i--) {
                if ($t->isEmpty($i)) {
                    continue;
                }
                self::clearMerge($t, $i);
            }
            return;
        }
        for ($i = $end; $i >= $start; $i--) {
            if ($t->isEmpty($i)) {
                continue;
            }
            self::clearMerge($t, $i);
        }
        $prev = $t->meaningful($start, -1);
        if ($prev >= 0 && self::is($t, $prev, 44)) {
            self::clearMerge($t, $prev);
            $next = $t->meaningful($end, 1);
            if ($next >= 0) {
                self::removeWs($t, $next, -1, " \t");
            }
            return;
        }
        $next = $t->meaningful($end, 1);
        if ($next >= 0 && self::is($t, $next, 44)) {
            self::clearMerge($t, $next);
            $after = $t->meaningful($next, 1);
            if ($after >= 0) {
                self::removeWs($t, $after, -1, " \t");
            }
        }
    }

    private function insert(FlatTokens $t, int $open): void
    {
        $seq = [FlatTokens::code(\__McTokId::T_DECLARE, 'declare'), FlatTokens::code(40, '('), FlatTokens::code(\__McTokId::T_STRING, 'strict_types'), FlatTokens::code(61, '='),
            FlatTokens::code(\__McTokId::T_LNUMBER, '1'), FlatTokens::code(41, ')'), FlatTokens::code(59, ';')];
        $next = $open + \count($seq) + 1;
        $t->insertAt($open + 1, $seq);
        $content = $t->content($open);
        if (!\str_contains($content, ' ') || \str_contains($content, "\n")) {
            self::setCode($t, $open, $t->k[$open], \trim($content) . ' ');
        }
        if ($t->count() === $next) {
            return;
        }
        if (self::wsOnly($t, $next, " \t\n\r\0\x0B")) {
            $t->c[$next] = $this->lineEnding . \ltrim($t->c[$next], " \t");
        } else {
            $t->insertAt($next, [FlatTokens::ws($this->lineEnding)]);
        }
    }
}
