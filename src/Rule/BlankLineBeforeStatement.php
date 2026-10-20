<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer (priority -21). A configured statement keyword (`return`, `throw`, `try`, ... or a
 * PHPDoc) that directly follows a `;` / `}` gets a blank line before it (above the comments that sit right on top of it). Only whitespace changes:
 * ported on a whole-file FlatTokens, last token to first, jumping below each handled statement as upstream does.
 */
final class BlankLineBeforeStatement extends FlatRule
{
    private const MAP = ['break' => \__McTokId::T_BREAK, 'case' => \__McTokId::T_CASE, 'continue' => \__McTokId::T_CONTINUE, 'declare' => \__McTokId::T_DECLARE,
        'default' => \__McTokId::T_DEFAULT, 'do' => \__McTokId::T_DO, 'exit' => \__McTokId::T_EXIT, 'for' => \__McTokId::T_FOR, 'foreach' => \__McTokId::T_FOREACH,
        'goto' => \__McTokId::T_GOTO, 'if' => \__McTokId::T_IF, 'include' => \__McTokId::T_INCLUDE, 'include_once' => \__McTokId::T_INCLUDE_ONCE, 'phpdoc' => -1,
        'require' => \__McTokId::T_REQUIRE, 'require_once' => \__McTokId::T_REQUIRE_ONCE, 'return' => \__McTokId::T_RETURN, 'switch' => \__McTokId::T_SWITCH,
        'throw' => \__McTokId::T_THROW, 'try' => \__McTokId::T_TRY, 'while' => \__McTokId::T_WHILE, 'yield' => \__McTokId::T_YIELD, 'yield_from' => \__McTokId::T_YIELD_FROM];

    /** @var array<int, bool> */
    private array $ids = [];
    private bool $phpdoc = false;

    /** @param string[] $statements */
    public function __construct(array $statements, private string $le)
    {
        parent::__construct();
        $this->gapsOnly = true;
        foreach ($statements as $st) {
            $id = self::MAP[$st];
            if ($id === -1) {
                $this->phpdoc = true;
            } else {
                $this->ids[$id] = true;
            }
        }
    }

    public function name(): string
    {
        return 'blank_line_before_statement';
    }

    public function priority(): int
    {
        return -21;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i <= $s->n; $i++) {
            $p = $s->kind[$i - 1];
            if (($p !== 59 && $p !== 125) || $s->ct[$i - 1] !== 0) {
                continue;
            }
            if (($i < $s->n && $s->ct[$i] === 0 && isset($this->ids[$s->kind[$i]])) || ($this->phpdoc && $s->gapBefore[$i] !== '' && \str_contains($s->gapBefore[$i], '/**'))) {
                return true;
            }
        }
        return false;
    }

    private function isFix(FlatTokens $t, int $q): bool
    {
        if ($t->i[$q] !== -1) {
            return isset($this->ids[$t->k[$q]]);
        }
        return $this->phpdoc && $t->k[$q] === FlatTokens::COMMENT && Trivia::isDoc($t->c[$q]);
    }

    private static function nl(FlatTokens $t, int $q): int
    {
        return \substr_count($t->i[$q] !== -1 ? $t->content($q) : $t->c[$q], "\n");
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if (!$this->isFix($t, $q)) {
                continue;
            }
            $k = $t->k[$q];
            if ($t->i[$q] !== -1) {
                if ($k === \__McTokId::T_WHILE && self::doWhile($t, $q)) {
                    continue;
                }
                if ($k === \__McTokId::T_CASE && self::enumCase($t, $q)) {
                    continue;
                }
            }
            $ins = $this->insertIndex($t, $q);
            $prev = $t->nonWhitespace($ins, -1);
            if ($prev < 0) {
                return;
            }
            if ($this->shouldAdd($t, $prev)) {
                $this->insertBlank($t, $ins);
            }
            $q = $prev;
        }
    }

    private static function doWhile(FlatTokens $t, int $q): bool
    {
        $e = $t->meaningful($q, -1);
        if (!self::is($t, $e, 125)) {
            return false;
        }
        $st = $t->blockStart($e, 123);
        return $st >= 0 && self::is($t, $t->meaningful($st, -1), \__McTokId::T_DO);
    }

    private static function enumCase(FlatTokens $t, int $q): bool
    {
        $has = false;
        $m = $t->count();
        for ($j = 0; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_ENUM) {
                $has = true;
                break;
            }
        }
        if (!$has) {
            return false;
        }
        $p = $q;
        while (true) {
            $found = -1;
            for ($j = $p - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 125 || $t->k[$j] === \__McTokId::T_ENUM || $t->k[$j] === \__McTokId::T_SWITCH)) {
                    $found = $j;
                    break;
                }
            }
            if ($found < 0) {
                return false;
            }
            $p = $found;
            if ($t->k[$p] === 125) {
                $p = $t->blockStart($p, 123);
                if ($p < 0) {
                    return false;
                }
            } else {
                break;
            }
        }
        return $t->k[$p] === \__McTokId::T_ENUM;
    }

    private function insertIndex(FlatTokens $t, int $q): int
    {
        while ($q > 0) {
            if ($t->isWs($q - 1) && self::nl($t, $q - 1) > 1) {
                break;
            }
            $p = $t->nonWhitespace($q, -1);
            if ($p < 0 || !$t->isComment($p)) {
                break;
            }
            if (!$t->isWs($p - 1)) {
                break;
            }
            if (self::nl($t, $p - 1) !== 1) {
                break;
            }
            $q = $p;
        }
        return $q;
    }

    private function shouldAdd(FlatTokens $t, int $p): bool
    {
        if ($t->isComment($p)) {
            for ($j = $p - 1; $j >= 0; $j--) {
                $c = $t->i[$j] !== -1 ? $t->content($j) : $t->c[$j];
                if (\str_contains($c, "\n")) {
                    return false;
                }
                if ($t->isWs($j) || $t->isComment($j)) {
                    continue;
                }
                return $t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === 125);
            }
        }
        return $t->i[$p] !== -1 && ($t->k[$p] === 59 || $t->k[$p] === 125);
    }

    private function insertBlank(FlatTokens $t, int $q): void
    {
        $p = $q - 1;
        if ($t->isWs($p)) {
            $n = self::nl($t, $p);
            if ($n === 0) {
                $t->c[$p] = \rtrim($t->c[$p], " \t") . $this->le . $this->le;
            } elseif ($n === 1) {
                $t->c[$p] = $this->le . $t->c[$p];
            }
        } else {
            $t->insertAt($q, [FlatTokens::ws($this->le . $this->le)]);
        }
    }
}
