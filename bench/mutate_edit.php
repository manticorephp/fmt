<?php

declare(strict_types=1);

// usage: php bench/mutate_edit.php <src-corpus> <dst-dir> [seed]
// Edit-rule dirt (tokens inserted / deleted / merged): braces of one-statement control bodies dropped, `new X()` -> `new X`,
// short arrays -> `array(..)`, multiline trailing commas dropped, `?T` -> `T|null` / `null|T`, consecutive imports / trait uses /
// same-modifier properties merged into one statement, `use \X`, modifiers swapped, `public` -> `var`. A file that no longer lints is kept clean.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function p(int $pct): bool
{
    return mt_rand(0, 99) < $pct;
}

/** @param list<array{0:int|string,1:string}> $t */
function txt(array $t, int $i): string
{
    return $t[$i][1];
}

function id(array $t, int $i): int|string
{
    return $t[$i][0];
}

function trivia(array $t, int $i): bool
{
    return isset($t[$i]) && in_array($t[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function nextCode(array $t, int $i): int
{
    for (++$i; isset($t[$i]) && trivia($t, $i); ++$i) {
    }

    return $i;
}

function prevCode(array $t, int $i): int
{
    for (--$i; $i >= 0 && trivia($t, $i); --$i) {
    }

    return $i;
}

function match_(array $t, int $i): int
{
    $o = id($t, $i);
    $c = ['(' => ')', '[' => ']', '{' => '}'][$o] ?? null;
    $d = 0;
    for ($j = $i; isset($t[$j]); ++$j) {
        $x = id($t, $j);
        if ($x === $o || ('{' === $o && (T_CURLY_OPEN === $x || T_DOLLAR_OPEN_CURLY_BRACES === $x))) {
            ++$d;
        } elseif ($x === $c) {
            if (0 === --$d) {
                return $j;
            }
        }
    }

    return -1;
}

const MODS = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY];

function mutate(string $code): string
{
    $t = [];
    foreach (token_get_all($code) as $x) {
        $t[] = is_array($x) ? [$x[0], $x[1]] : [$x, $x];
    }
    $n = count($t);
    for ($i = 0; $i < $n; ++$i) {
        $k = id($t, $i);
        // 1. drop the braces of a one-statement body
        if (in_array($k, [T_IF, T_ELSEIF, T_ELSE, T_WHILE, T_FOR, T_FOREACH], true) && p(50)) {
            $b = nextCode($t, $i);
            if (T_ELSE !== $k && '(' === id($t, $b)) {
                $b = nextCode($t, match_($t, $b));
            }
            if (isset($t[$b]) && '{' === id($t, $b)) {
                $e = match_($t, $b);
                $semi = 0;
                $nested = false;
                $last = -1;
                for ($j = $b + 1; $j < $e; ++$j) {
                    $x = id($t, $j);
                    if (';' === $x) {
                        ++$semi;
                        $last = $j;
                    } elseif ('{' === $x || T_CURLY_OPEN === $x || ':' === $x || T_CLOSE_TAG === $x || T_IF === $x) {
                        $nested = true;
                    }
                }
                if (1 === $semi && !$nested && prevCode($t, $e) === $last && $e > 0) {
                    $t[$b][1] = '';
                    $t[$e][1] = '';
                }
            }
        }
        // 2. new X() -> new X
        if (T_NEW === $k && p(40)) {
            $j = nextCode($t, $i);
            if (in_array(id($t, $j), [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STATIC], true)) {
                $o = nextCode($t, $j);
                if ('(' === id($t, $o) && ')' === id($t, nextCode($t, $o)) && !in_array(id($t, nextCode($t, nextCode($t, $o))), [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, '[', T_DOUBLE_COLON], true)) {
                    for ($q = $o; $q <= nextCode($t, $o); ++$q) {
                        $t[$q][1] = '';
                    }
                }
            }
        }
        // 3. [..] literal -> array(..)
        if ('[' === $k && p(30)) {
            $pv = prevCode($t, $i);
            if ($pv >= 0 && in_array(id($t, $pv), ['=', '(', ',', T_DOUBLE_ARROW, T_RETURN, T_COALESCE, '?', ':'], true)) {
                $e = match_($t, $i);
                if ($e > 0 && '=' !== id($t, nextCode($t, $e))) {
                    $t[$i][1] = 'array(';
                    $t[$e][1] = ')';
                }
            }
        }
        // 4. drop a trailing comma before a line break and `]` / `)`
        if (',' === $k && p(50)) {
            $j = nextCode($t, $i);
            if (isset($t[$j]) && in_array(id($t, $j), [']', ')'], true) && T_WHITESPACE === id($t, $i + 1) && str_contains(txt($t, $i + 1), "\n")) {
                $t[$i][1] = '';
            }
        }
        // 5. ?T -> T|null / null|T
        if ('?' === $k && p(40)) {
            $pv = prevCode($t, $i);
            $j = nextCode($t, $i);
            if ($pv >= 0 && in_array(id($t, $pv), ['(', ',', ':', T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY, T_VAR, T_STATIC, T_CONST], true)
                && in_array(id($t, $j), [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY, T_CALLABLE], true)
                && in_array(id($t, nextCode($t, $j)), [T_VARIABLE, '{', ';', '=', T_ELLIPSIS, '&', T_DOUBLE_ARROW, T_STRING], true)
                && 'null' !== strtolower(txt($t, $j)) && 'mixed' !== strtolower(txt($t, $j))) {
                $t[$i][1] = '';
                if (p(50)) {
                    $t[$j][1] .= '|null';
                } else {
                    $t[$j][1] = 'null|'.$t[$j][1];
                }
            }
        }
        // 6. use \X ; 7. two consecutive `use` statements of the same shape -> one
        if (T_USE === $k) {
            $j = nextCode($t, $i);
            if (T_NAME_QUALIFIED === id($t, $j) && p(25)) {
                $t[$j][1] = '\\'.$t[$j][1];
            }
            $end = $i;
            while (isset($t[$end]) && ';' !== id($t, $end) && '{' !== id($t, $end)) {
                ++$end;
            }
            $nx = nextCode($t, $end);
            if (isset($t[$end], $t[$nx]) && ';' === id($t, $end) && T_USE === id($t, $nx) && p(35)) {
                $j2 = nextCode($t, $nx);
                $sameKind = in_array(txt($t, $j), ['function', 'const'], true) === in_array(txt($t, $j2), ['function', 'const'], true)
                    && (!in_array(txt($t, $j), ['function', 'const'], true) || txt($t, $j) === txt($t, $j2));
                if ($sameKind && !str_contains(implode('', array_column(array_slice($t, $i, $end - $i), 1)), '{')) {
                    $t[$end][1] = ',';
                    $t[$nx][1] = '';
                    if (in_array(txt($t, $j2), ['function', 'const'], true)) {
                        $t[$j2][1] = '';
                    }
                }
            }
        }
        // 8. properties with the same modifiers: `public $a; public $b;` -> `public $a, $b;`; 9. swap two modifiers; 10. var
        if (in_array($k, MODS, true)) {
            $j = $i;
            $mods = [];
            while (isset($t[$j]) && (in_array(id($t, $j), MODS, true) || trivia($t, $j))) {
                if (!trivia($t, $j)) {
                    $mods[] = $j;
                }
                ++$j;
            }
            if (count($mods) >= 2 && p(30)) {
                $a = $mods[0];
                $b = $mods[1];
                [$t[$a][1], $t[$b][1]] = [$t[$b][1], $t[$a][1]];
                [$t[$a][0], $t[$b][0]] = [$t[$b][0], $t[$a][0]];
            } elseif (1 === count($mods) && T_PUBLIC === id($t, $i) && T_VARIABLE === id($t, $j) && p(20)) {
                $t[$i][1] = 'var';
            }
            $prefix = '';
            foreach ($mods as $m) {
                $prefix .= strtolower(txt($t, $m)).' ';
            }
            // a property type between the modifiers and the variable
            while (isset($t[$j]) && in_array(id($t, $j), [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY, T_CALLABLE, '?', '|', T_WHITESPACE], true)) {
                $prefix .= trivia($t, $j) ? ' ' : txt($t, $j);
                ++$j;
            }
            if (T_VARIABLE === id($t, $j)) {
                $e = $j;
                while (isset($t[$e]) && ';' !== id($t, $e) && !in_array(id($t, $e), ['{', '(', '[', T_FUNCTION, T_FN], true)) {
                    ++$e;
                }
                $nx = nextCode($t, $e);
                if (isset($t[$e]) && ';' === id($t, $e) && isset($t[$nx]) && p(40)) {
                    $q = $nx;
                    $prefix2 = '';
                    while (isset($t[$q]) && (in_array(id($t, $q), MODS, true) || T_VAR === id($t, $q) || trivia($t, $q))) {
                        if (!trivia($t, $q)) {
                            $prefix2 .= strtolower(txt($t, $q)).' ';
                        }
                        ++$q;
                    }
                    while (isset($t[$q]) && in_array(id($t, $q), [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY, T_CALLABLE, '?', '|', T_WHITESPACE], true)) {
                        $prefix2 .= trivia($t, $q) ? ' ' : txt($t, $q);
                        ++$q;
                    }
                    if ($prefix2 === $prefix && T_VARIABLE === id($t, $q) && $q > $nx) {
                        $t[$e][1] = ',';
                        for ($r = $nx; $r < $q; ++$r) {
                            $t[$r][1] = trivia($t, $r) && $r === $q - 1 ? ' ' : (trivia($t, $r) ? $t[$r][1] : '');
                        }
                    }
                }
            }
            $i = $j - 1;
        }
    }

    return implode('', array_column($t, 1));
}

[$self, $src, $dst, $seed] = $argv + [null, null, null, '17'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_edit.php <src> <dst> [seed]\n");
    exit(2);
}
mt_srand((int) $seed);
rmTree($dst);
$bad = 0;
foreach (phpFiles($src) as $rel) {
    $code = file_get_contents($src.'/'.$rel);
    $new = mutate($code);
    try {
        token_get_all($new, TOKEN_PARSE);
    } catch (ParseError $e) {
        ++$bad;
        $new = $code;
    }
    @mkdir(dirname($dst.'/'.$rel), 0777, true);
    file_put_contents($dst.'/'.$rel, $new);
}
echo count(phpFiles($dst))." files -> $dst ($bad kept clean: mutation did not lint)\n";
