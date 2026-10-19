<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\MagicMethodCasingFixer (priority 0). A magic method name gets its canonical case where it is
 * defined inside a class body (`function __TOSTRING(`), called (`->__INVOKE(`) or, for `__callStatic` / `__set_state`, called
 * statically. A whole-file walk (upstream's brace depth of the class), rewriting token texts only.
 */
final class MagicMethodCasing extends GapRule
{
    private const NAMES = ['__call' => '__call', '__callstatic' => '__callStatic', '__clone' => '__clone', '__construct' => '__construct',
        '__debuginfo' => '__debugInfo', '__destruct' => '__destruct', '__get' => '__get', '__invoke' => '__invoke', '__isset' => '__isset',
        '__serialize' => '__serialize', '__set' => '__set', '__set_state' => '__set_state', '__sleep' => '__sleep', '__tostring' => '__toString',
        '__unserialize' => '__unserialize', '__unset' => '__unset', '__wakeup' => '__wakeup'];

    public function name(): string
    {
        return 'magic_method_casing';
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
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_STRING && $s->end[$i] - $s->start[$i] >= 5 && $s->code[$s->start[$i]] === '_') {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $inClass = 0;
        $kinds = $s->kind;
        for ($i = 1; $i < $n - 1; $i++) {
            $k = $kinds[$i];
            if ($inClass === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)
                && $s->ct[$i] === 0) {
                $inClass = 1;
                for ($i++; $i < $n; $i++) {
                    if ($kinds[$i] === 123 && $s->ct[$i] === 0) {
                        break;
                    }
                }
                continue;
            }
            if ($inClass !== 0 && $s->ct[$i] === 0) {
                if ($k === 123) {
                    $inClass++;
                    continue;
                }
                if ($k === 125) {
                    $inClass--;
                    continue;
                }
            }
            if ($k !== \__McTokId::T_STRING || $s->ct[$i] !== 0 || $s->code[$s->start[$i]] !== '_') {
                continue;
            }
            $t = $s->text($i);
            if (!\str_starts_with($t, '__')) {
                continue;
            }
            $low = \strtolower($t);
            $good = self::NAMES[$low] ?? null;
            if ($good === null || $good === $t) {
                continue;
            }
            $p = $s->kind[$i - 1];
            $pc = $s->ct[$i - 1];
            $paren = $i + 1 < $n && $kinds[$i + 1] === 40;
            if ($pc === 0 && $p === \__McTokId::T_FUNCTION) {
                if ($paren && $inClass !== 0) {
                    $s->setText($i, $good);
                }
                continue;
            }
            if ($pc === 0 && ($p === \__McTokId::T_OBJECT_OPERATOR || $p === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                if ($paren) {
                    $s->setText($i, $good);
                }
                continue;
            }
            if (($low === '__callstatic' || $low === '__set_state') && $pc === 0 && $p === \__McTokId::T_DOUBLE_COLON && $paren) {
                $s->setText($i, $good);
            }
        }
    }
}
