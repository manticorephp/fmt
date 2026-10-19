<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\EmptyLoopConditionFixer (priority 1). style=while: `for (;;)` -> `while (true)` and
 * `do { } while (true);` -> `while (true) { }`; style=for: `while (true)` -> `for (;;)` (also the do-while form). Upstream clears the old tokens with
 * their whitespace merged into the whitespace before them (comments stay) and inserts the new ones, so the whitespace behind the old header ends up
 * before the new one: ported on a whole-file FlatTokens.
 */
final class EmptyLoopCondition extends FlatRule
{
    public function __construct(private bool $toWhile)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'empty_loop_condition';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i + 3 < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_WHILE && $s->ct[$i] === 0 && $s->kind[$i + 1] === 40 && $s->kind[$i + 2] === \__McTokId::T_STRING && $s->kind[$i + 3] === 41
                && $s->end[$i + 2] - $s->start[$i + 2] === 4) {
                return true;
            }
            if ($this->toWhile && $k === \__McTokId::T_FOR && $s->ct[$i] === 0 && $s->kind[$i + 1] === 40 && $s->kind[$i + 2] === 59 && $s->kind[$i + 3] === 59) {
                return true;
            }
        }
        return false;
    }

    private static function isForEmpty(FlatTokens $t, int $q, int $open, int $end): bool
    {
        if (!self::is($t, $q, \__McTokId::T_FOR)) {
            return false;
        }
        $i = $t->meaningful($open, 1);
        if (!self::is($t, $i, 59)) {
            return false;
        }
        $i = $t->meaningful($i, 1);
        return self::is($t, $i, 59) && $end === $t->meaningful($i, 1);
    }

    private static function isWhileEmpty(FlatTokens $t, int $q, int $open, int $end): bool
    {
        if (!self::is($t, $q, \__McTokId::T_WHILE)) {
            return false;
        }
        $i = $t->meaningful($open, 1);
        return self::is($t, $i, \__McTokId::T_STRING) && $t->content($i) === 'true' && $end === $t->meaningful($i, 1);
    }

    private static function doIndex(FlatTokens $t, int $q): int
    {
        $e = $t->meaningful($q, -1);
        if (!self::is($t, $e, 125)) {
            return -1;
        }
        $st = $t->blockStart($e, 123);
        if ($st < 0) {
            return -1;
        }
        $d = $t->meaningful($st, -1);
        return self::is($t, $d, \__McTokId::T_DO) ? $d : -1;
    }

    private static function clearRange(FlatTokens $t, int $a, int $b): void
    {
        for ($i = $a; $i <= $b; $i++) {
            if (!$t->isComment($i)) {
                self::clearMerge($t, $i);
            }
        }
    }

    /** @return array<int, array{0:int,1:string,2:int}> */
    private function replacement(): array
    {
        if ($this->toWhile) {
            return [FlatTokens::code(\__McTokId::T_WHILE, 'while'), FlatTokens::ws(' '), FlatTokens::code(40, '('), FlatTokens::code(\__McTokId::T_STRING, 'true'),
                FlatTokens::code(41, ')')];
        }
        return [FlatTokens::code(\__McTokId::T_FOR, 'for'), FlatTokens::code(40, '('), FlatTokens::code(59, ';'), FlatTokens::code(59, ';'), FlatTokens::code(41, ')')];
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if ($t->i[$q] === -1) {
                continue;
            }
            $k = $t->k[$q];
            if (!($k === \__McTokId::T_WHILE || ($this->toWhile && $k === \__McTokId::T_FOR))) {
                continue;
            }
            $open = self::nextOf($t, $q, 40);
            if ($open < 0) {
                continue;
            }
            $end = $t->blockEnd($open, 41);
            if ($end < 0) {
                continue;
            }
            if ($this->toWhile) {
                if (self::isForEmpty($t, $q, $open, $end)) {
                    if (self::is($t, $t->meaningful($end, 1), 58)) {
                        $s->abort = true; // `while (true): ... endfor;` does not lint: upstream leaves the file
                        return;
                    }
                    self::clearRange($t, $q, $end);
                    $t->insertAt($q, $this->replacement());
                } elseif (self::isWhileEmpty($t, $q, $open, $end)) {
                    $d = self::doIndex($t, $q);
                    if ($d >= 0) {
                        self::clearRange($t, $q, $t->meaningful($end, 1));
                        $t->clearAt($d);
                        $t->insertAt($d, $this->replacement());
                    }
                }
                continue;
            }
            if (!self::isWhileEmpty($t, $q, $open, $end)) {
                continue;
            }
            $d = self::doIndex($t, $q);
            $si = $t->i[$q];
            if (self::is($t, $t->meaningful($end, 1), 58) || ($d < 0 && $si > 0 && $s->kind[$si - 1] !== 123
                && $s->kind[$s->structure()->stmtStart[$si - 1]] === \__McTokId::T_DO)) {
                $s->abort = true; // `for (;;): ... endwhile;` / `do stmt; for (;;);` do not lint: upstream leaves the file
                return;
            }
            if ($d < 0) {
                self::clearRange($t, $q, $end);
                $t->insertAt($q, $this->replacement());
            } else {
                self::clearRange($t, $q, $t->meaningful($end, 1));
                $t->clearAt($d);
                $t->insertAt($d, $this->replacement());
            }
        }
    }
}
