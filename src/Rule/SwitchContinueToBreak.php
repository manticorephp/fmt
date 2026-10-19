<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\SwitchContinueToBreakFixer (priority 0). A `continue` that acts as `break` (directly in a switch with no
 * argument or `0` / `1`, or whose numeric argument targets a switch level) becomes `break`. Not for files with alternative syntax. The kind changes.
 */
final class SwitchContinueToBreak extends GapRule
{
    /** @var int[] */
    private array $levels = [];
    private Source $s;

    public function name(): string
    {
        return 'switch_continue_to_break';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $sw = false;
        $co = false;
        for ($i = 0; $i < $s->n; $i++) {
            switch ($s->kind[$i]) {
                case \__McTokId::T_SWITCH:
                    $sw = true;
                    break;
                case \__McTokId::T_CONTINUE:
                    $co = true;
                    break;
                case \__McTokId::T_ENDDECLARE:
                case \__McTokId::T_ENDFOR:
                case \__McTokId::T_ENDFOREACH:
                case \__McTokId::T_ENDIF:
                case \__McTokId::T_ENDSWITCH:
                case \__McTokId::T_ENDWHILE:
                    return false;
            }
        }
        return $sw && $co;
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $this->levels = [];
        $n = $s->n;
        for ($i = 1; $i < $n - 1; $i++) {
            $i = $this->doFix($i, 0, false);
        }
    }

    private function nextOf(int $i, int $kind): int
    {
        $s = $this->s;
        for ($j = $i + 1; $j < $s->n; $j++) {
            if ($s->kind[$j] === $kind && $s->ct[$j] === 0) {
                return $j;
            }
        }
        return $s->n;
    }

    private function doFix(int $i, int $depth, bool $inSwitch): int
    {
        $s = $this->s;
        if ($i >= $s->n || $s->ct[$i] !== 0) {
            return $i;
        }
        $k = $s->kind[$i];
        if ($k === \__McTokId::T_FOREACH || $k === \__McTokId::T_FOR || $k === \__McTokId::T_WHILE) {
            $i = $this->nextOf($i, 40);
            $i = $this->nextOf($i, 41);
            for ($j = $i + 1; $j < $s->n; $j++) {
                if ($s->ct[$j] === 0 && ($s->kind[$j] === 123 || $s->kind[$j] === 59 || $s->kind[$j] === \__McTokId::T_CLOSE_TAG)) {
                    $i = $j;
                    break;
                }
            }
            if ($i >= $s->n || $s->kind[$i] !== 123 || $s->ct[$i] !== 0) {
                return $i;
            }
            return $this->fixInLoop($i, $depth + 1);
        }
        if ($k === \__McTokId::T_DO) {
            return $this->fixInLoop($this->nextOf($i, 123), $depth + 1);
        }
        if ($k === \__McTokId::T_SWITCH) {
            return $this->fixInSwitch($i, $depth + 1);
        }
        if ($k === \__McTokId::T_CONTINUE) {
            return $this->fixContinue($i, $inSwitch, $depth);
        }
        return $i;
    }

    private function fixInSwitch(int $sw, int $depth): int
    {
        $s = $this->s;
        $this->levels[] = $depth;
        $open = $this->nextOf($sw, 123);
        $close = $open < $s->n ? $s->pair[$open] : $s->n;
        for ($i = $open + 1; $i < $close; $i++) {
            $i = $this->doFix($i, $depth, true);
        }
        \array_pop($this->levels);
        return $close;
    }

    private function fixInLoop(int $open, int $depth): int
    {
        $s = $this->s;
        $count = 1;
        while (true) {
            $open++;
            if ($open >= $s->n) {
                return $open;
            }
            if ($s->ct[$open] === 0 && $s->kind[$open] === 123) {
                $count++;
                continue;
            }
            if ($s->ct[$open] === 0 && $s->kind[$open] === 125) {
                $count--;
                if ($count === 0) {
                    break;
                }
                continue;
            }
            $open = $this->doFix($open, $depth, false);
        }
        return $open;
    }

    private function fixContinue(int $c, bool $inSwitch, int $depth): int
    {
        $s = $this->s;
        $f = $c + 1;
        if ($f >= $s->n) {
            return $f;
        }
        if ($inSwitch && $s->kind[$f] === 59 && $s->ct[$f] === 0) {
            $this->toBreak($c);
            return $f;
        }
        if ($s->kind[$f] !== \__McTokId::T_LNUMBER || $s->ct[$f] !== 0) {
            return $f;
        }
        $a = $f + 1;
        if ($a >= $s->n || $s->kind[$a] !== 59 || $s->ct[$a] !== 0) {
            return $a;
        }
        $jump = \str_replace('_', '', $s->text($f));
        if (\strlen($jump) > 2 && $jump[1] === 'x') {
            $j = \hexdec($jump);
        } elseif (\strlen($jump) > 2 && $jump[1] === 'b') {
            $j = \bindec($jump);
        } elseif (\strlen($jump) > 1 && $jump[0] === '0') {
            $j = \octdec($jump);
        } elseif (\preg_match('#^\d+$#', $jump) === 1) {
            $j = (float) $jump;
        } else {
            return $a;
        }
        if ($j > \PHP_INT_MAX) {
            return $a;
        }
        $j = (int) $j;
        if ($inSwitch && ($j === 1 || $j === 0)) {
            $this->toBreak($c);
            return $a;
        }
        if (\in_array($depth - $j + 1, $this->levels, true)) {
            $this->toBreak($c);
        }
        return $a;
    }

    private function toBreak(int $c): void
    {
        $this->s->setText($c, 'break');
        $this->s->setKind($c, \__McTokId::T_BREAK);
    }
}
