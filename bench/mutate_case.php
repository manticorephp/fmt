<?php

declare(strict_types=1);

// usage: php bench/mutate_case.php <src-corpus> <dst-dir> [seed]
// Text-rule dirt: random case on keywords, true/false/null, self/static/parent, casts (long names too), `case x:` -> `case x;`,
// `elseif` -> `else if`. Only the bytes of tokens change; a file that no longer lints is kept clean.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set("display_errors", "0");

function pick(int $n): int
{
    return mt_rand(0, $n - 1);
}

function rcase(string $t): string
{
    switch (mt_rand(0, 2)) {
        case 0:
            return strtoupper($t);
        case 1:
            return ucfirst($t);
    }
    $o = '';
    for ($i = 0; $i < strlen($t); ++$i) {
        $o .= mt_rand(0, 1) ? strtoupper($t[$i]) : $t[$i];
    }

    return $o;
}

function mutate(string $code): string
{
    $toks = token_get_all($code);
    $kw = [T_ABSTRACT, T_ARRAY, T_AS, T_BREAK, T_CALLABLE, T_CASE, T_CATCH, T_CLASS, T_CLONE, T_CONST, T_CONTINUE, T_DECLARE, T_DEFAULT, T_DO,
        T_ECHO, T_ELSE, T_ELSEIF, T_EMPTY, T_EXIT, T_EXTENDS, T_FINAL, T_FINALLY, T_FN, T_FOR, T_FOREACH, T_FUNCTION, T_GLOBAL, T_GOTO, T_IF,
        T_IMPLEMENTS, T_INCLUDE, T_INCLUDE_ONCE, T_INSTANCEOF, T_INSTEADOF, T_INTERFACE, T_ISSET, T_LIST, T_LOGICAL_AND, T_LOGICAL_OR,
        T_LOGICAL_XOR, T_NAMESPACE, T_NEW, T_PRINT, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_REQUIRE, T_REQUIRE_ONCE, T_RETURN, T_STATIC,
        T_SWITCH, T_THROW, T_TRAIT, T_TRY, T_UNSET, T_USE, T_VAR, T_WHILE, T_YIELD, T_YIELD_FROM, T_ENUM, T_MATCH, T_READONLY];
    $casts = [T_INT_CAST => ['(Int)', '(INTEGER)', '(integer)', '( int )'], T_BOOL_CAST => ['(BOOL)', '(Boolean)', '(boolean)', "(\tbool)"],
        T_DOUBLE_CAST => ['(FLOAT)', '(Double)', '(double)', '(float )'], T_STRING_CAST => ['(STRING)', '(Binary)', '(binary)', '( string)'],
        T_ARRAY_CAST => ['(ARRAY)', '(Array)'], T_OBJECT_CAST => ['(OBJECT)', '(Object)'], T_UNSET_CAST => ['(UNSET)']];
    $out = [];
    $caseOpen = false;
    $depth = 0;
    foreach ($toks as $i => $t) {
        if (!is_array($t)) {
            if ('{' === $t) {
                ++$depth;
            } elseif ('}' === $t) {
                --$depth;
            }
            if ($caseOpen && ':' === $t) {
                $caseOpen = false;
                if (mt_rand(0, 99) < 50) {
                    $t = ';';
                }
            } elseif (';' === $t) {
                $caseOpen = false;
            }
            $out[] = $t;
            continue;
        }
        [$id, $text] = $t;
        if (T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id) {
            ++$depth;
        }
        if (T_USE === $id && 0 === $depth && mt_rand(0, 99) < 30) {
            $text .= pick(2) ? ' function' : ' const'; // imports of other kinds, for blank_line_between_import_groups
        }
        if (T_CASE === $id || T_DEFAULT === $id) {
            $caseOpen = true;
        }
        if (T_STRING === $id && in_array(strtolower($text), ['true', 'false', 'null', 'self', 'parent'], true) && mt_rand(0, 99) < 60) {
            $text = rcase($text);
        } elseif (in_array($id, $kw, true) && mt_rand(0, 99) < 15) {
            $text = rcase($text);
        } elseif (isset($casts[$id]) && mt_rand(0, 99) < 60) {
            $text = $casts[$id][mt_rand(0, count($casts[$id]) - 1)];
        } elseif (T_ELSEIF === $id && mt_rand(0, 99) < 50) {
            $text = 'else if';
        }
        $out[] = $text;
    }

    return implode('', $out);
}

[$self, $src, $dst, $seed] = $argv + [null, null, null, '13'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_case.php <src> <dst> [seed]\n");
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
