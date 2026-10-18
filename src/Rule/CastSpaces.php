<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\CastNotation\CastSpacesFixer (priority -10). Whitespace inside the cast token goes
 * (fixText); the gap right after it becomes one space ('single') or loses its leading whitespace piece ('none').
 * Triggered by the cast kinds only (afterKinds), so the empty gap `(int)$x` is reached without a gap flag.
 */
final class CastSpaces extends GapRule
{
    private const CASTS = [381, 382, 383, 384, 385, 386, 387, 388];

    public function __construct(private bool $single = true)
    {
    }

    public function name(): string
    {
        return 'cast_spaces';
    }

    public function priority(): int
    {
        return -10;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return self::CASTS;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_TEXT;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i === 0 || $i > $s->n || !self::isCast($s->kind[$i - 1])) {
            return $gap;
        }
        if ($gap === '') {
            return $this->single ? ' ' : '';
        }
        $c = \ord($gap[0]);
        if ($c === 35 || $c === 47) {
            return $this->single ? ' ' . $gap : $gap; // a comment follows the cast directly
        }
        $ws = \strcspn($gap, '#/');
        if (!$this->single) {
            return $ws === \strlen($gap) ? '' : \substr($gap, $ws);
        }
        if (\strspn($gap, " \t") === $ws) {
            return ' ' . \substr($gap, $ws);
        }
        return $gap;
    }

    public function usesText(Source $s): bool
    {
        return true;
    }

    public function textKinds(): array
    {
        return self::CASTS;
    }

    public function fixText(Source $s, int $i): ?string
    {
        if (!self::isCast($s->kind[$i])) {
            return null; // the Engine also asks the open tag
        }
        $t = $s->text($i);
        if (\strpbrk($t, " \t\n\r\0\x0b") === false) {
            return null;
        }
        return \strtr($t, [' ' => '', "\t" => '', "\n" => '', "\r" => '', "\0" => '', "\x0b" => '']);
    }

    private static function isCast(int $k): bool
    {
        return $k >= 381 && $k <= 388;
    }
}
