<?php

declare(strict_types=1);

namespace Fmt;

/** Upstream's fixer metadata (src/FixerTable.php, generated): names, risky flag, deprecation successors, option definitions. */
final class Fixers
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $table = null;
    /** @var string[]|null */
    private static ?array $impl = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$table === null) {
            /** @var array<string, array<string, mixed>> $t */
            $t = \json_decode(FixerTable::JSON, true);
            self::$table = $t;
        }
        return self::$table;
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function isRisky(string $name): bool
    {
        $f = self::all()[$name] ?? null;
        return $f !== null && $f['risky'] === true;
    }

    /** @return string[]|null successors of a deprecated fixer, null when it is not deprecated */
    public static function successors(string $name): ?array
    {
        $f = self::all()[$name] ?? null;
        if ($f === null || $f['succ'] === null) {
            return null;
        }
        /** @var string[] $s */
        $s = $f['succ'];
        return $s;
    }

    /** @var array<string, bool> tests only: names that answer "not implemented" (every upstream rule has an implementation now) */
    private static array $hidden = [];

    public static function hide(string $name, bool $on): void
    {
        if ($on) {
            self::$hidden[$name] = true;
        } else {
            unset(self::$hidden[$name]);
        }
    }

    /** Whether Rules::make knows the rule (the registry: see Rules). */
    public static function implemented(string $name): bool
    {
        if (isset(self::$hidden[$name])) {
            return false;
        }
        return \in_array($name, self::implementedNames(), true);
    }

    /** @return string[] */
    public static function implementedNames(): array
    {
        if (self::$impl === null) {
            self::$impl = [];
            $real = [];
            foreach (\array_keys(self::all()) as $n) {
                if (Rules::make((string) $n, "\n") !== null) {
                    $real[(string) $n] = true;
                    self::$impl[] = (string) $n;
                }
            }
            // a deprecated proxy runs through its successors (Aliases::expand): implemented when they all are
            foreach (\array_keys(self::all()) as $n) {
                if (!isset($real[(string) $n]) && Aliases::isProxy((string) $n)) {
                    self::$impl[] = (string) $n;
                }
            }
        }
        return self::$impl;
    }
}
