<?php

declare(strict_types=1);

// usage: php bench/mutate_sym.php <src-corpus> <dst-dir> [seed] [family,...]
// Dirt for the @Symfony non-phpdoc rules: one mutator per family (see FAMILIES), each applied to a file only when the result still lints.
// A mutator gets the token list ([id|null, text] pairs; id null = single char) and edits it in place with mt_rand chances.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function rcase(string $t): string
{
    switch (mt_rand(0, 2)) {
        case 0:
            return strtoupper($t);
        case 1:
            return ucfirst(strtolower($t));
    }
    $o = '';
    for ($i = 0, $n = strlen($t); $i < $n; ++$i) {
        $o .= mt_rand(0, 1) ? strtoupper($t[$i]) : $t[$i];
    }

    return $o;
}

function chance(int $pct): bool
{
    return mt_rand(0, 99) < $pct;
}

/** @param list<array{0:?int,1:string}> $t */
function nextSig(array $t, int $i): int
{
    for (++$i, $n = count($t); $i < $n; ++$i) {
        if (!in_array($t[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }

    return -1;
}

/** @param list<array{0:?int,1:string}> $t */
function prevSig(array $t, int $i): int
{
    for (--$i; $i >= 0; --$i) {
        if (!in_array($t[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }

    return -1;
}

define('NATIVE_FN', array_flip(get_defined_functions()['internal']));
define('NATIVE_CLASS', array_flip(array_map('strtolower', array_filter(get_declared_classes(), fn ($c) => (new ReflectionClass($c))->isInternal()))));

const FAMILIES = ['case', 'misc1', 'space2', 'comma', 'comment', 'tags', 'empty', 'incr'];

function m_case(array &$t): void
{
    $magic = [T_LINE, T_FILE, T_DIR, T_FUNC_C, T_CLASS_C, T_METHOD_C, T_NS_C, T_TRAIT_C];
    $methods = ['__call', '__callstatic', '__clone', '__construct', '__debuginfo', '__destruct', '__get', '__invoke', '__isset', '__serialize',
        '__set', '__set_state', '__sleep', '__tostring', '__unserialize', '__unset', '__wakeup'];
    $types = ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'object', 'parent', 'self', 'static', 'string', 'void', 'false', 'mixed', 'null', 'never', 'true'];
    foreach ($t as $i => [$id, $x]) {
        if (null === $id) {
            continue;
        }
        $l = strtolower($x);
        if (in_array($id, $magic, true) && chance(50)) {
            $t[$i][1] = rcase($x);
        } elseif (T_CLASS === $id && '::' === ($t[prevSig($t, $i)][1] ?? '') && chance(50)) {
            $t[$i][1] = rcase($x);
        } elseif (T_LNUMBER === $id && preg_match('/^0[boxBOX]/', $x) && chance(50)) {
            $t[$i][1] = '0'.(chance(50) ? strtoupper($x[1]) : $x[1]).(chance(50) ? strtolower(substr($x, 2)) : substr($x, 2));
        } elseif (T_STRING === $id && isset(NATIVE_FN[$l]) && '(' === ($t[nextSig($t, $i)][1] ?? '') && chance(60)) {
            $t[$i][1] = rcase($x);
        } elseif (T_STRING === $id && isset(NATIVE_CLASS[$l]) && chance(60)) {
            $t[$i][1] = rcase($x);
        } elseif (T_STRING === $id && in_array($l, $methods, true) && chance(60)) {
            $t[$i][1] = rcase($x);
        } elseif (in_array($l, $types, true) && in_array($id, [T_STRING, T_ARRAY, T_CALLABLE, T_STATIC], true) && chance(60)) {
            $t[$i][1] = rcase($x);
        }
    }
}

function m_misc1(array &$t): void
{
    $out = [];
    foreach ($t as $i => [$id, $x]) {
        if (T_EXIT === $id && chance(50)) {
            $x = chance(50) ? rcase('die') : rcase('exit');
        } elseif (T_IS_NOT_EQUAL === $id && chance(50)) {
            $x = '<>';
        } elseif (T_CONSTANT_ENCAPSED_STRING === $id && chance(8) && '"' !== $x[0] || T_CONSTANT_ENCAPSED_STRING === $id && chance(4)) {
            $x = (chance(50) ? 'b' : 'B').$x;
        } elseif (T_VARIABLE === $id && chance(3) && in_array($t[prevSig($t, $i)][1] ?? '', ['(', '=', '&&', '||', ','], true)) {
            $x = ['!!', '! !', '! /*c*/ !', "!\n!", '!!!'][mt_rand(0, 4)].$x;
        } elseif (T_NAMESPACE === $id && '\\' !== ($t[nextSig($t, $i)][1] ?? '') && T_WHITESPACE === ($t[$i - 1][0] ?? 0) && chance(60)) {
            $out[count($out) - 1][1] = [' ', "\n  ", "\n\n\t", '  '][mt_rand(0, 3)];
        } elseif (T_OPEN_TAG === $id && 0 === $i && "<?php\n" === $x && chance(50)) {
            $x = '<?php ';
        }
        $out[] = [$id, $x];
    }
    $t = $out;
}

function m_space2(array &$t): void
{
    $depth = 0;
    $out = [];
    $declare = false;
    $pending = [];
    foreach ($t as $i => [$id, $x]) {
        if (isset($pending[$i])) {
            $x = $pending[$i];
        }
        if (null === $id) {
            if ('{' === $x) {
                ++$depth;
            } elseif ('}' === $x) {
                --$depth;
            } elseif ($declare && '(' === $x && chance(70)) {
                $x = [' ( ', ' (', '( ', "(\n    "][mt_rand(0, 3)];
            } elseif ($declare && ')' === $x) {
                $declare = false;
                if (chance(70)) {
                    $x = [' )', "\n)", '  )'][mt_rand(0, 2)];
                }
            }
            $out[] = [$id, $x];
            continue;
        }
        if (T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id) {
            ++$depth;
        }
        if (T_DECLARE === $id) {
            $declare = true;
            if (chance(60)) {
                $x .= ' ';
            }
        } elseif (T_COMMENT === $id && chance(60)) {
            if (str_starts_with($x, '//') && strlen($x) > 3) {
                $x = '//'.ltrim(substr($x, 2));
            } elseif (str_starts_with($x, '#') && !str_starts_with($x, '#[') && strlen($x) > 2) {
                $x = '#'.ltrim(substr($x, 1));
            } elseif (str_starts_with($x, '/*') && !str_starts_with($x, '/**') && !str_contains($x, "\n") && strlen($x) > 5) {
                $x = '/*'.trim(substr($x, 2, -2)).(chance(50) ? '' : ' ').'*/';
            }
        } elseif (T_DOUBLE_ARROW === $id && chance(25)) {
            $x = [" =>\n        ", "\n        => ", "\n   =>\n   "][mt_rand(0, 2)];
        } elseif (T_USE === $id && 0 === $depth && chance(60)) {
            // `use A\B;` -> `use A\B as B;`
            $j = $i + 1;
            $last = null;
            $ok = true;
            for (; $j < count($t) && ';' !== $t[$j][1]; ++$j) {
                if (in_array($t[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $last = $t[$j][1];
                } elseif (!in_array($t[$j][0], [T_WHITESPACE, T_NS_SEPARATOR], true) && !in_array($t[$j][0], [T_NAME_QUALIFIED], true)) {
                    $ok = false;
                }
            }
            if ($ok && null !== $last && $j < count($t)) {
                $short = ltrim(substr($last, (int) strrpos($last, '\\')), '\\');
                $pending[$j] = ' as '.$short.';';
            }
        }
        $out[] = [$id, $x];
    }
    $t = $out;
}

function m_comma(array &$t): void
{
    // trailing commas into one-line calls, arrays and lists (the rule removes them again), sometimes doubled with blanks / comments
    $stack = [];
    $out = [];
    $sig = null; // previous significant token
    $sigId = null;
    foreach ($t as $i => [$id, $x]) {
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $out[] = [$id, $x];
            continue;
        }
        if (null === $id && '(' === $x) {
            $call = in_array($sigId, [T_STRING, T_VARIABLE, T_ARRAY, T_ISSET, T_UNSET, T_LIST, T_CLASS], true) && !in_array($t[prevSig($t, prevSig($t, $i))][0] ?? 0, [T_FUNCTION, T_FN], true) || in_array($sig, [')', ']'], true);
            $stack[] = $call;
        } elseif (null === $id && '[' === $x) {
            $stack[] = in_array($sig, ['=', '(', ',', '=>', '[', '.', ':', '?'], true) || in_array($sigId, [T_RETURN, T_DOUBLE_ARROW, T_YIELD], true);
        } elseif (null === $id && ('{' === $x)) {
            $stack[] = false;
        } elseif (null === $id && (')' === $x || ']' === $x)) {
            $ok = array_pop($stack);
            if ($ok && !in_array($sig, ['(', '[', ',', null], true) && T_ELLIPSIS !== $sigId && chance(35)) {
                $out[] = [null, [',', ' ,', ', ', ' , '][mt_rand(0, 3)].(chance(20) ? ',' : '')];
            }
        } elseif (null === $id && '}' === $x) {
            array_pop($stack);
        } elseif (in_array($id, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $stack[] = false;
        }
        $out[] = [$id, $x];
        $sig = null === $id ? $x : null;
        $sigId = $id;
    }
    $t = $out;
}

function m_comment(array &$t): void
{
    foreach ($t as $i => [$id, $x]) {
        if (T_COMMENT !== $id || !chance(40)) {
            continue;
        }
        if (str_starts_with($x, '//')) {
            $core = rtrim($x, "\r\n");
            $nl = substr($x, strlen($core));
            $text = trim(substr($core, 2));
            if (str_contains($text, '*/') || str_contains($text, '?>')) {
                continue;
            }
            $t[$i][1] = [
                '#'.substr($core, 2),
                '/* '.$text.' */',
                '/*'.$text.'*/',
                '/**'.$text.'*/',
                "/*\n * ".$text."\n */",
                '/* '.$text.' */  ',
                "/**\n * ".$text."\n */",
                '//'.$text,
                '/* ** '.$text.' ** */',
                "/*\n\n*/",
                '/* */',
                '//',
                '#',
                '/**/',
                '// ',
                '/*  *  */',
            ][mt_rand(0, 15)].$nl;
        } elseif (str_starts_with($x, '/*') && !str_starts_with($x, '/**') && !str_contains($x, "\n") && chance(50)) {
            $t[$i][1] = [rtrim($x), $x.'  ', '#'.trim(substr($x, 2, -2)), '//'.trim(substr($x, 2, -2))."\n"][mt_rand(0, 3)];
        }
    }
}

function m_tags(array &$t): void
{
    // `print` after a statement, `echo` with commas / without, close tags after expressions
    foreach ($t as $i => [$id, $x]) {
        if (T_ECHO === $id && chance(50)) {
            $t[$i][1] = 'print';
            // echo a, b; has no print equivalent: the file will not lint, the family reverts
        } elseif (T_PRINT === $id && chance(50)) {
            $t[$i][1] = 'echo';
        }
    }
}

function m_empty(array &$t): void
{
    // empty statements, empty blocks / loops, trailing `return;`, `$this?->`; only where statements are legal (a brace stack by owner)
    $stack = [];
    $out = [];
    $sig = null;
    $sigId = null;
    $fn = false;
    $stmts = ['$z;', '1;', "'x';", ';', '; ;', '{ }', '{ { $z; } }', 'while ($z);', 'while ($z) { }', 'for ($i = 0; $i < 2; $i++) {}', 'foreach ($a as $b) { }', 'foreach ($a as $b) {

}', 'if ($z) { ; }', '{ ; }', 'foo;', 'include( "x" );', 'require_once ( $y ) ;', 'require   $z;', '$q = include ( $w ) ;', 'include$z ;', 'require ("a") ;', 'for (;;) { break; }', 'for ( ; ; ) {}', 'do { } while (true);', 'for (;;)\n{ break; }', 'do { foo(); } while ( true ) ;', 'if ($z) { return 1; } else { foo(); }', 'if ($z) return; else foo();', 'if ($z) { throw new E(); } elseif ($y) { return; } else { bar(); }', 'if ($z) { foo(); } else { }', 'if ($z) { foo(); } else { /* c */ }', 'if ($z) { foo(); } else ;', 'if ($z) { continue; } else { baz(); }', 'if ($z) { return; } else { function q() {} }', 'if ($z) { return; } else if ($y) { foo(); }', 'if ($a) { return; } elseif ($b) { exit; } else { x(); }', 'if ($z) { break; } else { foo(); bar(); }', 'if ($z): return; else: foo(); endif;'];
    foreach ($t as $i => [$id, $x]) {
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $out[] = [$id, $x];
            continue;
        }
        if (T_FUNCTION === $id || T_FN === $id) {
            $fn = true;
        }
        if (null === $id && '{' === $x) {
            if ($fn) {
                $stack[] = 'f';
                $fn = false;
            } elseif (')' === $sig || in_array($sigId, [T_ELSE, T_TRY, T_DO, T_FINALLY], true) || ':' === $sig) {
                $stack[] = 's';
            } elseif (in_array($sigId, [T_STRING, T_CLASS], true) || in_array($sigId, [T_EXTENDS, T_IMPLEMENTS], true)) {
                $stack[] = 'c';
                if (chance(60) && T_STRING === $sigId) {
                    $out[] = [null, '{'];
                    $out[] = [null, [' public $mz = null; protected $my  =  NULL, $mx = 1, $mw = \\null;', "\n    var \$mv\n        = null;\n", ' static $mu = null, $mt = null /* c */, $ms = null;', ' public $mr = [null], $mq = null;'][mt_rand(0, 3)]];
                    $sig = '{';
                    $sigId = null;
                    continue;
                }
            } else {
                $stack[] = 'x';
            }
        } elseif (T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id) {
            $stack[] = 'x';
        } elseif (null === $id && '}' === $x) {
            $top = array_pop($stack);
            if ('f' === $top && ';' === $sig && chance(15) && !in_array($t[$i + 1][1] ?? '', ['', ' '], true) | true) {
                $out[] = [null, ' return; '];
            }
        } elseif (null === $id && ';' === $x) {
            $out[] = [$id, $x];
            if (in_array(end($stack), ['s', 'f'], true) && chance(6)) {
                $out[] = [null, ' '.$stmts[mt_rand(0, count($stmts) - 1)]];
            }
            $sig = $x;
            $sigId = $id;
            continue;
        } elseif (T_VARIABLE === $id && 'c' === end($stack) && in_array($sigId, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR, T_STATIC], true) && in_array($t[nextSig($t, $i)][1] ?? '', [';', ','], true) && chance(60)) {
            $x .= [' = null', ' = NULL', '  =  \\null', ' /* c */ = null', "\n        = Null", ' = null /* d */'][mt_rand(0, 5)];
        } elseif (T_OBJECT_OPERATOR === $id && T_VARIABLE === ($t[prevSig($t, $i)][0] ?? 0) && '$this' === ($t[prevSig($t, $i)][1] ?? '') && chance(50)) {
            $x = '?->';
            $id = T_NULLSAFE_OBJECT_OPERATOR;
        }
        $out[] = [$id, $x];
        $sig = null === $id ? $x : null;
        $sigId = $id;
    }
    $t = $out;
}

function m_incr(array &$t): void
{
    $out = [];
    $n = count($t);
    for ($i = 0; $i < $n; ++$i) {
        [$id, $x] = $t[$i];
        $p = prevSig($t, $i);
        $nx = nextSig($t, $i);
        if (T_VARIABLE === $id && $nx >= 0 && in_array($t[$nx][0], [T_INC, T_DEC], true) && ';' === ($t[nextSig($t, $nx)][1] ?? '') && in_array($t[$p][1] ?? '', [';', '{', '}'], true) && chance(40)) {
            // `$a++;` -> `++$a;`
            $out[] = $t[$nx];
            $out[] = [$id, $x];
            $i = $nx;
            continue;
        }
        if (in_array($id, [T_INC, T_DEC], true) && $p >= 0 && T_VARIABLE === $t[$p][0] && $nx >= 0 && ';' === $t[$nx][1] && chance(35)) {
            $op = T_INC === $id ? '+=' : '-=';
            $x = [' '.$op.' 1', $op.'1', ' '.$op."\n    1", ' '.$op.' /* c */ 1', "\n".$op.' 1'][mt_rand(0, 4)];
        } elseif (in_array($id, [T_INC, T_DEC], true) && $nx >= 0 && T_VARIABLE === $t[$nx][0] && isset($t[$nx + 1]) && ';' === ($t[nextSig($t, $nx)][1] ?? '') && in_array($t[$p][1] ?? '', [';', '{', '}'], true) && chance(40)) {
            // `++$a;` -> `$a++;` (the variable first, then the operator)
            for ($j = $i + 1; $j <= $nx; ++$j) {
                $out[] = $t[$j];
            }
            $out[] = [$id, $x];
            $i = $nx;
            continue;
        }
        $out[] = [$id, $x];
    }
    $t = $out;
}

function mutate(string $code, array $fams): string
{
    foreach ($fams as $f) {
        $toks = [];
        foreach (token_get_all($code) as $x) {
            $toks[] = is_array($x) ? [$x[0], $x[1]] : [null, $x];
        }
        ('m_'.$f)($toks);
        $new = implode('', array_column($toks, 1));
        try {
            token_get_all($new, TOKEN_PARSE);
            $code = $new;
        } catch (ParseError $e) {
        }
    }

    return $code;
}

[$self, $src, $dst, $seed, $fams] = $argv + [null, null, null, '17', null];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_sym.php <src> <dst> [seed] [family,...]\n");
    exit(2);
}
$fams = null === $fams ? FAMILIES : explode(',', $fams);
mt_srand((int) $seed);
rmTree($dst);
$changed = 0;
foreach (phpFiles($src) as $rel) {
    $code = file_get_contents($src.'/'.$rel);
    $new = mutate($code, $fams);
    $changed += $new !== $code;
    @mkdir(dirname($dst.'/'.$rel), 0777, true);
    file_put_contents($dst.'/'.$rel, $new);
}
echo "$changed files mutated -> $dst\n";
