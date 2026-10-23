<?php

declare(strict_types=1);

namespace Fmt;

/** Plain ANSI colors. The decision (`--color`, NO_COLOR, FORCE_COLOR, TERM, TTY) is a pure function; paint() is a no-op when off. */
final class Ansi
{
    public const RED = '31';
    public const GREEN = '32';
    public const YELLOW = '33';
    public const CYAN = '36';
    public const BOLD = '1';
    public const DIM = '2';

    /**
     * Precedence: --color=always|never, then FORCE_COLOR (non-empty, not "0") on, NO_COLOR (non-empty) off, TERM=dumb off, else whether the stream is a TTY.
     * @param string $mode auto|always|never
     */
    public static function decide(string $mode, bool $tty, ?string $noColor, ?string $forceColor, ?string $term): bool
    {
        if ($mode === 'always') {
            return true;
        }
        if ($mode === 'never') {
            return false;
        }
        if ($forceColor !== null && $forceColor !== '' && $forceColor !== '0') {
            return true;
        }
        if ($noColor !== null && $noColor !== '') {
            return false;
        }
        if ($term === 'dumb') {
            return false;
        }
        return $tty;
    }

    /** decide() with the process environment */
    public static function auto(string $mode, bool $tty): bool
    {
        return self::decide($mode, $tty, self::env('NO_COLOR'), self::env('FORCE_COLOR'), self::env('TERM'));
    }

    private static function env(string $name): ?string
    {
        $v = \getenv($name);
        return $v === false ? null : (string) $v;
    }

    public static function paint(bool $on, string $code, string $text): string
    {
        return $on && $text !== '' ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
    }
}
