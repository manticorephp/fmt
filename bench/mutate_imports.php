<?php

declare(strict_types=1);

// usage: php bench/mutate_imports.php <src-corpus> <dst-dir> [seed]
// Import dirt for the name-resolving rules (global_namespace_import, fully_qualified_strict_types, no_unused_imports): in a file
// with one namespace, global symbols used fully qualified get imported (`use Exception;`, `use function strlen;`, `use const PHP_EOL;`)
// and some of their uses lose the backslash; imported classes are written fully qualified again in code and docblocks
// (`\Foo\Bar` for `Bar`), a docblock type gets the namespace prefix, `self` / a class's own name. A file that no longer lints is kept clean.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function p(int $pct): bool
{
    return mt_rand(0, 99) < $pct;
}

function mutate(string $code): string
{
    $t = token_get_all($code);
    $ns = null;
    $nsCount = 0;
    $useEnd = -1;
    $nsEnd = -1;
    $imports = []; // short => full
    foreach ($t as $k => $tok) {
        if (!is_array($tok)) {
            continue;
        }
        if (T_NAMESPACE === $tok[0]) {
            ++$nsCount;
            for ($j = $k + 1; isset($t[$j]); ++$j) {
                if (';' === $t[$j] || '{' === $t[$j]) {
                    $nsEnd = $j;
                    break;
                }
                if (is_array($t[$j]) && in_array($t[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    $ns = $t[$j][1];
                }
            }
        }
        if (T_USE === $tok[0] && $nsEnd > 0) {
            // top-level `use X\Y;` right in the namespace header
            $name = '';
            for ($j = $k + 1; isset($t[$j]) && ';' !== $t[$j]; ++$j) {
                if (is_array($t[$j]) && in_array($t[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $name = $t[$j][1];
                }
                if ('{' === $t[$j] || '(' === $t[$j]) {
                    $name = '';
                    break;
                }
            }
            if (isset($t[$j]) && ';' === $t[$j] && '' !== $name) {
                $useEnd = $j;
                $imports[substr($name, (int) strrpos('\\'.$name, '\\'))] = ltrim($name, '\\');
            }
        }
        if (in_array($tok[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION], true)) {
            break; // imports come first
        }
    }
    if (1 !== $nsCount || null === $ns || $nsEnd < 0) {
        return $code;
    }
    $global = ['class' => [], 'function' => [], 'const' => []];
    foreach ($t as $k => $tok) {
        if (!is_array($tok) || T_NAME_FULLY_QUALIFIED !== $tok[0] || 1 !== substr_count($tok[1], '\\')) {
            continue;
        }
        $next = $t[$k + 1] ?? null;
        $name = substr($tok[1], 1);
        if ('(' === $next) {
            $global['function'][strtolower($name)] = $name;
        } elseif (strtoupper($name) === $name && !in_array($next, [T_DOUBLE_COLON], true)) {
            $global['const'][$name] = $name;
        } else {
            $global['class'][strtolower($name)] = $name;
        }
    }
    $add = [];
    $deq = [];
    foreach ($global as $kind => $names) {
        foreach ($names as $lower => $name) {
            if (p(50) && !isset($imports[$name])) {
                $add[] = 'use '.('class' === $kind ? '' : $kind.' ').$name.';';
                $deq[$kind][$lower] = true;
            }
        }
    }
    if (p(30)) {
        $add[] = 'use '.['DateTime', 'Exception', 'Countable', 'ArrayObject'][mt_rand(0, 3)].';';
    }
    $out = '';
    foreach ($t as $k => $tok) {
        $text = is_array($tok) ? $tok[1] : $tok;
        if (is_array($tok) && T_NAME_FULLY_QUALIFIED === $tok[0] && 1 === substr_count($text, '\\') && p(60)) {
            $name = substr($text, 1);
            $next = $t[$k + 1] ?? null;
            $kind = '(' === $next ? 'function' : (strtoupper($name) === $name ? 'const' : 'class');
            if (isset($deq[$kind][strtolower($name)]) || isset($deq[$kind][$name])) {
                $text = $name;
            }
        } elseif (is_array($tok) && T_STRING === $tok[0] && isset($imports[$text]) && p(25) && str_contains($imports[$text], '\\')) {
            $prev = $t[$k - 1] ?? null;
            if (!(is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_USE], true))) {
                $text = '\\'.$imports[$text];
            }
        } elseif (is_array($tok) && T_DOC_COMMENT === $tok[0] && p(40)) {
            $text = preg_replace_callback('/(@(?:param|return|var|throws|see)\s+)([A-Z][A-Za-z0-9_]*)\b/', static function ($m) use ($imports, $ns) {
                if (isset($imports[$m[2]]) && p(60)) {
                    return $m[1].'\\'.$imports[$m[2]];
                }
                if (p(20)) {
                    return $m[1].'\\'.$ns.'\\'.$m[2];
                }

                return $m[0];
            }, $text);
        }
        $out .= $text;
        if ($k === ($useEnd > 0 ? $useEnd : $nsEnd) && [] !== $add && ';' === $tok) {
            $out .= "\n".implode("\n", $add);
        }
    }

    return $out;
}

[$self, $src, $dst, $seed] = $argv + [null, null, null, '11'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_imports.php <src-corpus> <dst-dir> [seed]\n");
    exit(2);
}
$src = realpath($src);
$changed = 0;
foreach (phpFiles($src) as $rel) {
    $code = file_get_contents($src.'/'.$rel);
    mt_srand((int) $seed + crc32($rel));
    $out = mutate($code);
    if ($out !== $code) {
        try {
            token_get_all($out, TOKEN_PARSE);
            ++$changed;
        } catch (ParseError) {
            $out = $code;
        }
    }
    @mkdir(dirname($dst.'/'.$rel), 0777, true);
    file_put_contents($dst.'/'.$rel, $out);
}
echo "$changed files changed\n";
