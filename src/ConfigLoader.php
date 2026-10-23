<?php

declare(strict_types=1);

namespace Fmt;

/**
 * `.manticore-fmt.json` / `.yaml` / `.yml` (+ `.dist`): the first of these found in the directory wins, as upstream picks
 * `.php-cs-fixer.php` before `.php-cs-fixer.dist.php`. Keys are matched without case, `_` and `-`
 * (`line_ending` == `lineEnding`).
 */
final class ConfigLoader
{
    private const NAMES = ['.manticore-fmt.json', '.manticore-fmt.yaml', '.manticore-fmt.yml',
        '.manticore-fmt.dist.json', '.manticore-fmt.dist.yaml', '.manticore-fmt.dist.yml'];

    /** The config file of a directory, '' when there is none. */
    public static function discover(string $dir): string
    {
        foreach (self::NAMES as $n) {
            $f = ($dir === '' ? '.' : \rtrim($dir, '/')) . '/' . $n;
            if (\is_file($f)) {
                return $f;
            }
        }
        return '';
    }

    /** @throws ConfigException */
    public static function load(string $file): Config
    {
        $src = @\file_get_contents($file);
        if ($src === false) {
            throw new ConfigException('Cannot read config file "' . $file . '".');
        }
        if (\str_starts_with($src, "\xEF\xBB\xBF")) {
            $src = \substr($src, 3);
        }
        try {
            if (\str_ends_with(\strtolower($file), '.json')) {
                $data = \json_decode($src, true);
                if ($data === null && \trim($src) !== 'null') {
                    $off = JsonLint::errorOffset($src);
                    $where = '';
                    if ($off >= 0) {
                        $pre = \substr($src, 0, $off);
                        $nl = \strrpos($pre, "\n");
                        $where = ' at line ' . (\substr_count($pre, "\n") + 1) . ' column ' . ($off - ($nl === false ? 0 : $nl + 1) + 1);
                    }
                    throw new \InvalidArgumentException('invalid JSON' . $where . ': ' . \json_last_error_msg());
                }
            } else {
                $data = Yaml::parse($src);
            }
        } catch (\InvalidArgumentException $e) {
            throw new ConfigException('Cannot parse config file "' . $file . '": ' . $e->getMessage());
        }
        if ($data === null) {
            $data = [];
        }
        if (!\is_array($data) || ($data !== [] && \array_is_list($data))) {
            throw new ConfigException('Config file "' . $file . '" must be a map of options (rules, paths, ...), not a list or a single value.');
        }
        $c = new Config();
        $c->file = $file;
        $dir = \dirname($file);
        /** @var array<string, string> $seen */
        $seen = [];
        foreach ($data as $key => $v) {
            $k = self::norm((string) $key);
            if (isset($seen[$k])) {
                throw new ConfigException('Config file "' . $file . '": "' . $key . '" repeats "' . $seen[$k] . '" (keys are matched without case, "_" and "-").');
            }
            $seen[$k] = (string) $key;
            $what = $file . ': "' . $key . '"';
            if ($k === 'rules') {
                if (!\is_array($v)) {
                    throw new ConfigException($what . ' must be a map of rule => true | false | options.');
                }
                /** @var array<string, mixed> $r */
                $r = [];
                foreach ($v as $name => $val) {
                    if (\is_int($name)) {
                        throw new ConfigException($what . ': missing value for "' . (\is_string($val) ? $val : '?') . '" (write `name: true`).');
                    }
                    $r[(string) $name] = $val;
                }
                $c->rules = $r;
                $c->rulesGiven = true;
            } elseif ($k === 'risky' || $k === 'allowrisky' || $k === 'riskyallowed') {
                $c->risky = self::bool($what, $v);
            } elseif ($k === 'indent') {
                $c->indent = self::indent($what, $v);
            } elseif ($k === 'lineending') {
                $c->lineEnding = self::lineEnding($what, $v);
            } elseif ($k === 'paths' || $k === 'in') {
                $c->paths = self::joined($dir, self::strings($what, $v));
            } elseif ($k === 'exclude') {
                $c->exclude = self::strings($what, $v);
            } elseif ($k === 'name') {
                $c->name = self::strings($what, $v);
            } elseif ($k === 'notname') {
                $c->notName = self::strings($what, $v);
            } elseif ($k === 'path' || $k === 'include') {
                $c->path = self::strings($what, $v);
            } elseif ($k === 'notpath') {
                $c->notPath = self::strings($what, $v);
            } elseif ($k === 'ignoredotfiles') {
                $c->ignoreDotFiles = self::bool($what, $v);
            } elseif ($k === 'cache' || $k === 'usingcache') {
                if (\is_array($v)) {
                    foreach ($v as $ck => $cv) {
                        $n = self::norm((string) $ck);
                        if ($n === 'enabled') {
                            $c->cache = self::bool($what . '.enabled', $cv);
                        } elseif ($n === 'file') {
                            $c->cacheFile = self::abs($dir, self::str($what . '.file', $cv));
                        } else {
                            throw new ConfigException($what . ': unknown key "' . $ck . '" (enabled, file).');
                        }
                    }
                } elseif (\is_string($v) && !\in_array(\strtolower($v), ['yes', 'no', 'true', 'false', 'on', 'off'], true)) {
                    $c->cache = true;
                    $c->cacheFile = self::abs($dir, $v);
                } else {
                    $c->cache = self::bool($what, $v);
                }
            } elseif ($k === 'cachefile') {
                $c->cacheFile = self::abs($dir, self::str($what, $v));
            } elseif ($k === 'parallel') {
                if (\is_array($v)) {
                    foreach ($v as $pk => $pv) {
                        $n = self::norm((string) $pk);
                        if ($n === 'workers' || $n === 'maxprocesses') {
                            $c->workers = self::int($what . '.' . $pk, $pv);
                        } elseif ($n === 'chunk' || $n === 'filesperprocess' || $n === 'chunksize') {
                            $c->chunkSize = self::int($what . '.' . $pk, $pv);
                        } else {
                            throw new ConfigException($what . ': unknown key "' . $pk . '" (workers, chunk).');
                        }
                    }
                } elseif (\is_float($v)) {
                    throw new ConfigException($what . ' must be a non-negative integer, true or false.');
                } elseif (\is_int($v)) {
                    $c->workers = $v;
                } elseif (self::bool($what, $v) === false) {
                    $c->workers = 1;
                }
            } elseif ($k === 'format') {
                $c->format = self::str($what, $v);
            } elseif ($k === 'allowunimplemented') {
                $c->allowUnimplemented = self::bool($what, $v);
            } elseif ($k === 'pathmode') {
                $c->pathMode = self::str($what, $v);
            } else {
                throw new ConfigException('Config file "' . $file . '": unknown key "' . $key . '" (allowed: rules, risky, indent, lineEnding, paths, exclude, name, notName, path, notPath, ignoreDotFiles, cache, parallel, format, allowUnimplemented, pathMode).');
            }
        }
        return $c;
    }

    private static function norm(string $k): string
    {
        return \strtolower(\str_replace(['_', '-'], '', $k));
    }

    private static function abs(string $dir, string $p): string
    {
        return $p !== '' && $p[0] === '/' ? $p : ($dir === '.' ? $p : $dir . '/' . $p);
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private static function joined(string $dir, array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            $out[] = self::abs($dir, $p);
        }
        return $out;
    }

    private static function bool(string $what, mixed $v): bool
    {
        if (\is_bool($v)) {
            return $v;
        }
        if (\is_int($v) && ($v === 0 || $v === 1)) {
            return $v === 1;
        }
        if (\is_string($v)) {
            $l = \strtolower($v);
            if ($l === 'yes' || $l === 'true' || $l === 'on') {
                return true;
            }
            if ($l === 'no' || $l === 'false' || $l === 'off') {
                return false;
            }
        }
        throw new ConfigException($what . ' must be true or false.');
    }

    private static function str(string $what, mixed $v): string
    {
        if (!\is_string($v)) {
            throw new ConfigException($what . ' must be a string.');
        }
        return $v;
    }

    private static function int(string $what, mixed $v): int
    {
        if (!\is_int($v) || $v < 0) {
            throw new ConfigException($what . ' must be a non-negative integer.');
        }
        return $v;
    }

    /** @return string[] */
    private static function strings(string $what, mixed $v): array
    {
        if (\is_string($v)) {
            return [$v];
        }
        if (!\is_array($v)) {
            throw new ConfigException($what . ' must be a list of strings.');
        }
        $out = [];
        foreach ($v as $s) {
            if (!\is_string($s)) {
                throw new ConfigException($what . ' must be a list of strings.');
            }
            $out[] = $s;
        }
        return $out;
    }

    /** "    ", "\t", "tab", "tabs", or a number of spaces */
    public static function indent(string $what, mixed $v): string
    {
        if (\is_int($v) && $v > 0 && $v <= 16) {
            return \str_repeat(' ', $v);
        }
        if (\is_string($v)) {
            $l = \strtolower($v);
            if ($l === 'tab' || $l === 'tabs') {
                return "\t";
            }
            if ($l === 'spaces' || $l === 'space') {
                return '    ';
            }
            if ($v !== '' && \strspn($v, ' ') === \strlen($v) || $v === "\t") {
                return $v;
            }
            if ($v === '\t') {
                return "\t";
            }
        }
        throw new ConfigException($what . ' must be a run of spaces, "\t", "tab" or a number of spaces.');
    }

    /** "\n", "\r\n", "lf", "crlf", "cr" */
    public static function lineEnding(string $what, mixed $v): string
    {
        if (\is_string($v)) {
            $l = \strtolower($v);
            if ($v === "\n" || $v === "\r\n" || $v === "\r") {
                return $v;
            }
            if ($l === 'lf' || $l === '\n') {
                return "\n";
            }
            if ($l === 'crlf' || $l === '\r\n') {
                return "\r\n";
            }
            if ($l === 'cr' || $l === '\r') {
                return "\r";
            }
        }
        throw new ConfigException($what . ' must be "\n", "\r\n", "lf", "crlf" or "cr".');
    }
}
