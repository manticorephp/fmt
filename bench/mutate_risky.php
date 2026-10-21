<?php

declare(strict_types=1);

// usage: php bench/mutate_risky.php <src-corpus> <dst-dir> [seed] [family,...]
// Dirt for the risky rules: one mutator per family (see FAMILIES), each applied to a file only when the result still lints.
// A mutator gets the token list ([id|null, text] pairs; id null = single char) and edits it in place with mt_rand chances.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function chance(int $pct): bool
{
    return mt_rand(0, 99) < $pct;
}

function pick(array $a): mixed
{
    return $a[mt_rand(0, count($a) - 1)];
}

const SKIP = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

/** @param list<array{0:?int,1:string}> $t */
function nextSig(array $t, int $i): int
{
    for (++$i, $n = count($t); $i < $n; ++$i) {
        if (!in_array($t[$i][0], SKIP, true)) {
            return $i;
        }
    }

    return -1;
}

/** @param list<array{0:?int,1:string}> $t */
function prevSig(array $t, int $i): int
{
    for (--$i; $i >= 0; --$i) {
        if (!in_array($t[$i][0], SKIP, true)) {
            return $i;
        }
    }

    return -1;
}

function sp(): string
{
    return pick(['', '', ' ', "\n    ", ' /* c */ ']);
}

define('FAMILIES', ['logical', 'dirconst', 'isnull', 'cast', 'sprintf', 'strings', 'settype', 'elvis', 'suppress', 'calls', 'alias', 'ops', 'pow', 'phpunit', 'ptest']);

/**
 * Statement-level dirt: after a `;` that ends a line (not a `for` header) one of $pool's statements goes in ($pct percent). Candidates that do not lint are dropped with the file's family.
 * @param list<array{0:?int,1:string}> $t
 * @param list<string> $pool
 */
function inject(array &$t, array $pool, int $pct): void
{
    $out = [];
    foreach ($t as $i => [$id, $x]) {
        $out[] = [$id, $x];
        if (null === $id && ';' === $x && isset($t[$i + 1]) && T_WHITESPACE === $t[$i + 1][0] && str_contains($t[$i + 1][1], "\n") && chance($pct)) {
            $out[] = [T_WHITESPACE, "\n"];
            $out[] = [null, pick($pool)];
        }
    }
    $t = $out;
}

function m_settype(array &$t): void
{
    inject($t, ['settype($a, "integer");', '\\settype($b,"string",);', '$r = settype($c, "int");', 'SetType( $d , "NULL" );', 'settype($e->f, "bool");', 'settype(/* a */ $g /* b */, /* c */ "double" /* d */);',
        'settype($h, $i);', 'if ($z) { settype($m, "array"); }', 'settype($x, "object");'], 4);
}

function m_elvis(array &$t): void
{
    inject($t, ['$a = $a ? $a : 1;', '$b = $b[1] ? $b[1] : 2;', '$c = $c->d ? $c->d : 3;', '$d = foo($x) ? foo($x) : 4;', '$e = ($e) ? $e : 5;',
        '$g = $g ? /* c */ $g : 7;', '$h = $h++ ? $h++ : 8;', '$k = $a ? $a : ($b ? $b : 11);', '$l = [$a ? $a : 1, $b["x"] ? $b["x"] : 2];', '$n = $a . $b ? $a . $b : 3;'], 4);
}

function m_suppress(array &$t): void
{
    inject($t, ['@trigger_error("x", E_USER_DEPRECATED);', 'trigger_error("x", E_USER_DEPRECATED);', '\\trigger_error("x", E_USER_DEPRECATED,);', '@mkdir($d);', '@\\unlink($f);',
        '$a = @file_get_contents("x");', 'echo phpversion(), pi(), php_sapi_name(), \\phpversion();', 'echo get_class(), get_class($this), get_called_class();', '$o = get_class( ($this) );'], 4);
}

function m_calls(array &$t): void
{
    inject($t, ['$a = implode($b);', '$a = implode($b, ",");', '$a = implode($b[1] + 1, "x");', '$a = implode( /* c */ $b /* d */ , /* e */ "x" /* f */ );', '$a = \\implode($b, ",",);',
        '$f = fopen($p, "rt");', '$f = fopen($p, "wb+");', '$f = fopen($p, "bw");', '$f = fopen($p, "r+t", true);', '$f = fopen($p, b"tr");', '$f = fopen($p, "cetb");', '$f = \\FOPEN($p, /* c */ "tb" /* d */);',
        '$d = dirname(dirname($p));', '$d = dirname(dirname(dirname($p)));', '$d = dirname(dirname($p, 2));', '$d = dirname(dirname($p), 3, );', '$d = \\dirname(\\dirname(\\dirname($p, 2)));',
        '$d = dirname( /* a */ dirname( /* b */ $p /* c */ ) /* d */ );', '$d = dirname(dirname(foo($p, 1)));', '$c = get_class($a);', '$c = get_class($a->b);', '$c = \\get_class( /* c */ $a /* d */ );',
        '$c = [get_class($a), get_class($b)];', '$c = get_class(($a));'], 3);
}

function m_alias(array &$t): void
{
    inject($t, ['$a = chop($b);', 'close($b);', '$a = doubleval($b);', 'fputs($b, $c);', '$a = \\is_double($b);', '$a = JOIN($glue, $pieces);', '$a = key_exists($key, $array) + sizeof($b);',
        '$a = mktime() . mktime(1) . gmmktime();', '$a = imap_header($i, 1) . pg_exec($c, $d) . mbereg_search_getregs() . ini_alter($b, $c) . user_error($m);',
        'array_push($a, $b);', 'array_push($a->b["c"], $b, );', 'array_push($a, $b, $c);', 'array_push($a, ...$b);', '\\array_push(self::$a[1]->c, foo($b, $c) + 1);', '$x = array_push($a, 1);',
        'array_push($a, $b or $c);', 'array_push($a /* c */ , /* d */ $b /* e */ );', 'array_push(static::$a, 1);', 'array_push($a::$b, fn() => 1);'], 3);
}

function m_ops(array &$t): void
{
    inject($t, ['$a = $a + 1;', '$a = $a . "x" . "y";', '$a = 2 * $a;', '$a->b["c"] = $a->b["c"] . "x";', '$a[foo()] = $a[foo()] + 2;', '$a = $a /* c */ + /* d */ 1;', '$$a = $$a + 1;', '$a = $a + ($b ? 1 : 2);',
        '$a = 1 | $a;', '$a = $a & $b;', 'if ($x) $a = $a + 1;', 'foo($a = $a + 1, $b = $b . "x");', '$a = $a - $b - $c;', '$a = $a + foo($a);',
        'if (strlen($a) === 0) {}', 'if (0 === strlen($a)) {}', 'if (strlen($a) > 0) {}', 'if (strlen($a) >= 1) {}', 'if (strlen($a) < 1) {}', 'if (1 <= strlen($a)) {}', 'if (0 >= \\strlen($a->b)) {}',
        'if (strlen((string) $a) === 0) {}', 'if (strlen($a . $b) === 0) {}', 'if (strlen( /* c */ $a /* d */ ) === 0) {}', 'if (strlen($a) + 1 === 0) {}', '$x = strlen($a) > 0 ? 1 : 2;'], 3);
}

function m_pow(array &$t): void
{
    inject($t, ['$a = pow($a, 2);', '$a = \\pow($a, 2,);', '$a = pow(2, $b + 1);', '$a = pow($a->b, Foo::BAR);', '$a = pow(-1, 2);', '$a = pow(pow($a, 2), 3);', '$a = pow($a, pow($b, 2));',
        '$a = pow(foo(1, 2), 3);', '$a = pow($a, ...$b);', '$a = pow( /* a */ $a /* b */ , /* c */ 2 /* d */ );', '$a = pow($a[1], (int) $b);', '$a = 2 * pow($a, 2) + 3;', '$a = POW(1.5, 2) . pow(__LINE__, 2);',
        'if (strpos($a, $b) === 0) {}', 'if (strpos($a, $b) !== false) {}', 'if (strpos($a, $b) === FALSE) {}', 'if (0 === strpos($a, $b)) {}', 'if (false !== strpos($a, $b)) {}', 'if (\\strpos($a, $b) === 0) {}',
        'if (!\\strpos($a, $b) === 0) {}', 'if (strpos($a, $b) === 0 + 1) {}', 'if ($x . strpos($a, $b) === 0) {}', 'if (\\stripos($a, $b) === 0) {}', 'if (stripos( $a , $b ) !== 0) {}',
        'if (stripos(foo(1, 2), $b[0]) !== false) {}', 'if (false === stripos($a, $b)) {}'], 3);
}

function m_phpunit(array &$t): void
{
    inject($t, ['$this->assertSame(true, $a);', '$this->assertEquals(false, $b);', '$this->assertNotEquals(null, $c);', '$this->assertNotSame(TRUE, $d, "m");', 'self::assertSame(null, $a);',
        '$this->assertSame($a, true);', '$m->method("some")->will($this->returnValue("example"));', '$m->method("some")->will(self::returnSelf());', '$m->method("some")->will($this->returnCallback("f"));',
        '$m->method("some")->will($this->returnValueMap(["a", "b"],));'], 6);
}

function m_ptest(array &$t): void
{
    $out = [];
    $n = count($t);
    for ($i = 0; $i < $n; ++$i) {
        if (T_FUNCTION === $t[$i][0] && chance(40)) {
            $a = nextSig($t, $i);
            if ($a >= 0 && T_STRING === $t[$a][0] && preg_match('/^test([A-Z]\w*)$/', $t[$a][1], $m)) {
                // walk back over the modifiers of the method and put a docblock in front of them
                $j = count($out) - 1;
                $k = $j;
                while ($k >= 0 && (in_array($out[$k][0], SKIP, true) || in_array($out[$k][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT], true))) {
                    --$k;
                }
                $first = $k + 1;
                while ($first <= $j && in_array($out[$first][0], SKIP, true)) {
                    ++$first;
                }
                $doc = [[T_DOC_COMMENT, pick(["/** @test */", "/**\n     * @test\n     * @depends testOther\n     */", "/**\n     * Text.\n     */", "/** @testdox x */"])], [T_WHITESPACE, "\n    "]];
                array_splice($out, $first, 0, $doc);
                $out[] = [$t[$i][0], $t[$i][1]];
                for ($x = $i + 1; $x < $a; ++$x) {
                    $out[] = $t[$x];
                }
                $out[] = [T_STRING, lcfirst($m[1])];
                $i = $a;
                continue;
            }
        }
        $out[] = $t[$i];
    }
    $t = $out;
}

function m_logical(array &$t): void
{
    foreach ($t as $i => [$id, $x]) {
        if (T_BOOLEAN_AND === $id && chance(30)) {
            $t[$i][1] = pick([' and ', ' AND ', ' And ']);
            $t[$i][0] = T_LOGICAL_AND;
        } elseif (T_BOOLEAN_OR === $id && chance(30)) {
            $t[$i][1] = pick([' or ', ' OR ']);
            $t[$i][0] = T_LOGICAL_OR;
        }
    }
}

function m_dirconst(array &$t): void
{
    foreach ($t as $i => [$id, $x]) {
        if (T_DIR === $id && chance(40)) {
            $t[$i][1] = pick(['', '\\']).pick(['dirname', 'DIRNAME', 'Dirname']).pick(['', ' ']).'('.sp().'__FILE__'.pick(['', '', ',', ' , ']).sp().')';
        }
    }
}

function m_isnull(array &$t): void
{
    $out = [];
    $n = count($t);
    for ($i = 0; $i < $n; ++$i) {
        [$id, $x] = $t[$i];
        if (T_VARIABLE === $id && chance(40)) {
            $a = nextSig($t, $i);
            $b = $a >= 0 ? nextSig($t, $a) : -1;
            if ($b >= 0 && in_array($t[$a][0], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL], true) && T_STRING === $t[$b][0] && 'null' === strtolower($t[$b][1])) {
                $p = prevSig($t, $i);
                if ($p >= 0 && !in_array($t[$p][1], ['!', '.', '-', '+', '*', '/', '%', '**', '<', '>', '&', '|', '^', '<<', '>>', '=', '?', ':', '@', '(', ',', '[', '{', ';', '&&', '||', 'return', 'and', 'or', 'xor', '=>', 'echo', 'print', 'if', 'elseif', 'while', 'case'], true) || $p < 0) {
                    $out[] = [$id, $x];
                    continue;
                }
                $neg = T_IS_NOT_IDENTICAL === $t[$a][0];
                $out[] = [T_STRING, ($neg ? '!' : '').pick(['', '\\']).'is_null('.sp().$x.sp().')'];
                $i = $b;
                continue;
            }
        }
        if (T_STRING === $id && 'null' === strtolower($x) && chance(40)) {
            $a = nextSig($t, $i);
            $b = $a >= 0 ? nextSig($t, $a) : -1;
            if ($b >= 0 && in_array($t[$a][0], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL], true) && T_VARIABLE === $t[$b][0]) {
                $nx = nextSig($t, $b);
                if ($nx >= 0 && in_array($t[$nx][1], [')', ';', '&&', '||', '?'], true)) {
                    $p = prevSig($t, $i);
                    if ($p >= 0 && in_array($t[$p][1], ['(', ';', '{', '}', '&&', '||', '=', 'return', '!', ','], true)) {
                        $neg = T_IS_NOT_IDENTICAL === $t[$a][0];
                        $out[] = [T_STRING, ($neg ? '!' : '').'is_null('.$t[$b][1].')'];
                        $i = $b;
                        continue;
                    }
                }
            }
        }
        $out[] = [$id, $x];
    }
    $t = $out;
}

function m_cast(array &$t): void
{
    $map = [T_INT_CAST => ['intval', 'INTVAL'], T_DOUBLE_CAST => ['floatval', 'doubleval'], T_STRING_CAST => ['strval'], T_BOOL_CAST => ['boolval']];
    $out = [];
    $n = count($t);
    for ($i = 0; $i < $n; ++$i) {
        [$id, $x] = $t[$i];
        if (isset($map[$id]) && chance(50)) {
            $a = nextSig($t, $i);
            $b = $a >= 0 ? nextSig($t, $a) : -1;
            if ($a >= 0 && T_VARIABLE === $t[$a][0] && $b >= 0 && in_array($t[$b][1], [';', ')', ',', ']', '.', '+', '-', '*'], true)) {
                $p = prevSig($t, $i);
                if ($p >= 0 && in_array($t[$p][1], ['=', '(', ',', '[', 'return', '.', '+', '=>', '?', ':'], true)) {
                    $out[] = [T_STRING, pick(['', '\\']).pick($map[$id]).pick(['', ' ']).'('.sp().$t[$a][1].pick(['', '', ',']).sp().')'];
                    $i = $a;
                    continue;
                }
            }
        }
        $out[] = [$id, $x];
    }
    $t = $out;
}

function m_sprintf(array &$t): void
{
    foreach ($t as $i => [$id, $x]) {
        if (T_CONSTANT_ENCAPSED_STRING === $id && !str_contains($x, '%') && !str_contains($x, "\n") && chance(8)) {
            $p = prevSig($t, $i);
            $nx = nextSig($t, $i);
            if ($p >= 0 && in_array($t[$p][1], ['=', '(', ',', 'return', '.'], true) && $nx >= 0 && in_array($t[$nx][1], [';', ')', ','], true)) {
                $t[$i][1] = pick(['', '\\']).pick(['sprintf', 'SPRINTF']).'('.sp().$x.pick(['', '', ',']).sp().')';
            }
        }
    }
}

function m_strings(array &$t): void
{
    foreach ($t as $i => [$id, $x]) {
        if (in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true) && str_contains($x, "\n") && chance(25)) {
            $mode = mt_rand(0, 2);
            if (0 === $mode) {
                $x = preg_replace('/\n/', pick(["  \n", "\t\n", " \t \n"]), $x, mt_rand(1, 3));
            } elseif (1 === $mode) {
                $x = preg_replace("/\n/", T_ENCAPSED_AND_WHITESPACE === $id ? "\r\n" : pick(["\r\n", "\r", "\n\r"]), $x, mt_rand(1, 3)); // a lone CR before a heredoc end marker: tests/repro/heredoc_lone_cr_end_marker.php
            } else {
                $x = preg_replace('/ *\n/', " \n", $x);
            }
            $t[$i][1] = $x;
        }
    }
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
    fwrite(STDERR, "usage: php bench/mutate_risky.php <src> <dst> [seed] [family,...]\n");
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
