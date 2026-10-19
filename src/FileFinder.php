<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Upstream's Finder::create() defaults (`*.php`, `vendor` pruned at any depth, dot files and VCS directories ignored, symlinked
 * directories not followed), configurable per config file. Explicit file roots are never filtered. The result is sorted.
 */
final class FileFinder
{
    private const VCS = ['.git', '.svn', '.hg', '.bzr', 'CVS', '_darcs'];

    /**
     * @param string[] $cliPaths paths from the command line (empty = the config's)
     * @return string[] sorted file paths
     * @throws ConfigException
     */
    public static function find(Config $c, array $cliPaths): array
    {
        if ($c->pathMode !== 'override' && $c->pathMode !== 'intersection') {
            throw new ConfigException('The path-mode "' . $c->pathMode . '" is not defined, supported are "override" and "intersection".');
        }
        if ($cliPaths === []) {
            if ($c->paths === []) {
                throw new ConfigException('No paths: give paths on the command line or "paths" in the config file.');
            }
            return self::walkAll($c->paths, $c);
        }
        if ($c->pathMode === 'override' || $c->paths === []) {
            return self::walkAll($cliPaths, new Config());
        }
        $inConfig = self::walkAll($c->paths, $c);
        $roots = [];
        foreach ($cliPaths as $p) {
            $rp = \realpath($p);
            if ($rp !== false) {
                $roots[] = \is_dir($rp) ? \rtrim($rp, '/') . '/' : $rp;
            }
        }
        $out = [];
        foreach ($inConfig as $f) {
            $rf = \realpath($f);
            if ($rf === false) {
                continue;
            }
            foreach ($roots as $r) {
                if ($rf === $r || ($r[\strlen($r) - 1] === '/' && \str_starts_with($rf, $r))) {
                    $out[] = $f;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * @param string[] $roots
     * @return string[]
     */
    private static function walkAll(array $roots, Config $c): array
    {
        /** @var array<string, bool> $seen */
        $seen = [];
        /** @var string[] $out */
        $out = [];
        foreach ($roots as $root) {
            if (\is_dir($root)) {
                $files = [];
                self::walk(\rtrim($root, '/') === '' ? '/' : \rtrim($root, '/'), '', $c, $files);
                foreach ($files as $f) {
                    if (!isset($seen[$f])) {
                        $seen[$f] = true;
                        $out[] = $f;
                    }
                }
            } elseif (\is_file($root)) {
                if (!isset($seen[$root])) {
                    $seen[$root] = true;
                    $out[] = $root;
                }
            }
        }
        \sort($out);
        return $out;
    }

    /** @param string[] $out */
    private static function walk(string $base, string $rel, Config $c, array &$out): void
    {
        $dir = $rel === '' ? $base : $base . '/' . $rel;
        $names = \scandir($dir);
        if ($names === false) {
            return;
        }
        \sort($names);
        foreach ($names as $nm) {
            if ($nm === '.' || $nm === '..') {
                continue;
            }
            $r = $rel === '' ? $nm : $rel . '/' . $nm;
            $full = $base . '/' . $r;
            if (\is_dir($full) && !\is_link($full)) {
                if (\in_array($nm, self::VCS, true) || ($c->ignoreDotFiles && $nm[0] === '.') || self::excluded($r, $c->exclude)) {
                    continue;
                }
                self::walk($base, $r, $c, $out);
                continue;
            }
            if (!\is_file($full) || ($c->ignoreDotFiles && $nm[0] === '.')) {
                continue;
            }
            if (!self::accepts($nm, $r, $c)) {
                continue;
            }
            $out[] = $full;
        }
    }

    /** @param string[] $exclude */
    private static function excluded(string $rel, array $exclude): bool
    {
        foreach ($exclude as $e) {
            $e = \trim($e, '/');
            if ($e === '') {
                continue;
            }
            if (Glob::isGlob($e)) {
                if (Glob::matches($e, $rel)) {
                    return true;
                }
            } elseif ($rel === $e || \str_ends_with($rel, '/' . $e)) {
                return true;
            }
        }
        return false;
    }

    private static function accepts(string $name, string $rel, Config $c): bool
    {
        $ok = $c->name === [];
        foreach ($c->name as $p) {
            if (Glob::matches($p, $name)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return false;
        }
        foreach ($c->notName as $p) {
            if (Glob::matches($p, $name)) {
                return false;
            }
        }
        if ($c->path !== []) {
            $hit = false;
            foreach ($c->path as $p) {
                if (Glob::pathMatches($p, $rel)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        foreach ($c->notPath as $p) {
            if (Glob::pathMatches($p, $rel)) {
                return false;
            }
        }
        return true;
    }
}
