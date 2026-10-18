<?php

declare(strict_types=1);

namespace Fmt;

final class Files
{
    /**
     * Every *.php under the given files/directories, sorted per directory; missing paths are skipped.
     * @param string[] $paths
     * @return string[]
     */
    public static function collect(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            if (\is_dir($p)) {
                self::walk(\rtrim($p, '/'), $out);
            } elseif (\is_file($p)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @param string[] $out */
    private static function walk(string $dir, array &$out): void
    {
        $names = \scandir($dir);
        if ($names === false) {
            return;
        }
        \sort($names);
        foreach ($names as $nm) {
            if ($nm === '.' || $nm === '..') {
                continue;
            }
            $full = $dir . '/' . $nm;
            if (\is_dir($full)) {
                self::walk($full, $out);
            } elseif (\str_ends_with($nm, '.php')) {
                $out[] = $full;
            }
        }
    }
}
