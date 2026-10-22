<?php

declare(strict_types=1);

// usage: php bench/mutate_new.php <src-corpus> <dst-dir> [seed] [family,...]
// Dirt for the rules of the last wave (attributes, heredocs, numeric literals, Doctrine annotations, interface lists, small statements). Same contract as
// mutate_risky.php: a mutator edits the token list ([id|null, text] pairs) in place; the result is kept only when it still parses.

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
define('FAMILIES', ['attrs', 'heredoc', 'numeric', 'doctrine', 'ifaces', 'stmts']);

/** indentation of the line the token at $i starts / is on */
function lineIndent(array $t, int $i): string
{
    for ($j = $i; $j >= 0; --$j) {
        $x = $t[$j][1];
        $p = strrpos($x, "\n");
        if (false !== $p) {
            return substr($x, $p + 1, strspn($x, " \t", $p + 1));
        }
    }

    return '';
}

/** index of the first token of the declaration that starts with the modifiers/attributes before $i (stops after `;` `{` `}` or a doc comment) */
function declStart(array $t, int $i): int
{
    $m = $i;
    for ($j = $i - 1; $j >= 0; --$j) {
        $id = $t[$j][0];
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_ABSTRACT, T_FINAL, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_READONLY, T_VAR], true)) {
            if (T_WHITESPACE !== $id && T_COMMENT !== $id) {
                $m = $j;
            }
            continue;
        }
        break;
    }

    return $m;
}

function m_attrs(array &$t): void
{
    $pool = ['#[Attr]', '#[Attr()]', '#[Zed, Alpha(1), \\Beta\\Gamma()]', '#[A(x: 1), B()]', "#[Foo\\Bar]\n#[Baz()]", '#[Attr(
    1,
    2,
)]', '#[Z] #[A]', '#[Al, Z(), Q]', '#[\\Zed\\Z, Alpha\\A]', '#[Alpha\\A(), \\Zed\\Z]', '#[Al(1)]', '#[Z,]', '#[App\\Keep, App\\Drop]', '#[Beta, Alpha, Gamma]'];
    $edits = [];
    $depth = 0;
    $inClass = [];
    foreach ($t as $i => [$id, $x]) {
        if (null === $id && '{' === $x) {
            ++$depth;
        } elseif (null === $id && '}' === $x) {
            --$depth;
        }
        $isDecl = in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_FUNCTION], true) && $i > 0 && T_DOUBLE_COLON !== ($t[$i - 1][0] ?? 0) && T_NEW !== prevId($t, $i);
        if ($isDecl && chance(35)) {
            $m = declStart($t, $i);
            $indent = lineIndent($t, $m);
            $edits[] = [$m, [[T_COMMENT, pick($pool)], [T_WHITESPACE, "\n".$indent]]];
        }
    }
    usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
    foreach ($edits as [$m, $ins]) {
        array_splice($t, $m, 0, $ins);
    }
    // imports for the names above: behind the namespace statement (or the declare)
    if (!empty($edits)) {
        $at = -1;
        foreach ($t as $i => [$id, $x]) {
            if (T_NAMESPACE === $id && -1 === $at) {
                $j = $i;
                while (isset($t[$j]) && !(null === $t[$j][0] && in_array($t[$j][1], [';', '{'], true))) {
                    ++$j;
                }
                $at = isset($t[$j]) && ';' === $t[$j][1] ? $j + 1 : -2;
                break;
            }
        }
        if (-1 === $at) {
            foreach ($t as $i => [$id, $x]) {
                if (T_DECLARE === $id) {
                    $j = $i;
                    while (isset($t[$j]) && !(null === $t[$j][0] && ';' === $t[$j][1])) {
                        ++$j;
                    }
                    $at = $j + 1;
                }
            }
            if (-1 === $at) {
                $at = 1;
            }
        }
        if ($at > 0) {
            array_splice($t, $at, 0, [[T_WHITESPACE, "\n"], [null, 'use Alpha\\A as Al;'], [T_WHITESPACE, "\n"], [null, 'use Zed\\Z;']]);
        }
    }
}

function prevId(array $t, int $i): int
{
    for (--$i; $i >= 0; --$i) {
        if (!in_array($t[$i][0], SKIP, true)) {
            return (int) $t[$i][0];
        }
    }

    return -1;
}

function m_heredoc(array &$t): void
{
    $markers = ['EOT', 'EOD', 'HTML', 'SQL', 'FOO', 'JSON_', 'xml', 'TXT'];
    $edits = [];
    foreach ($t as $i => [$id, $x]) {
        if (T_CONSTANT_ENCAPSED_STRING !== $id || !chance(12)) {
            continue;
        }
        $indent = lineIndent($t, $i);
        $mk = pick($markers);
        $body = pick([indentText($indent, 2, ['alpha', 'beta {$x} gamma', '  deeper $y', '']), indentText($indent, 0, ['plain', 'text']), indentText($indent, 4, ["tab\there", '\\$escaped', ' leading space']), '']);
        $quote = pick(['', '', '"', "'"]);
        $open = '<<<'.$quote.$mk.$quote."\n";
        if ("'" === $quote) {
            $body = str_replace(['{$x}', '$y'], ['x', 'y'], $body);
        }
        $close = pick([$indent, $indent.'    ', '', $indent]).$mk;
        if ('' !== $body) {
            $body .= "\n";
        } else {
            $body = pick(['', "\n"]);
        }
        $edits[] = [$i, [[T_START_HEREDOC, $open.$body.$close]]];
    }
    usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
    foreach ($edits as [$i, $rep]) {
        array_splice($t, $i, 1, $rep);
    }
    // multi-line quoted strings
    foreach ($t as $i => [$id, $x]) {
        if (T_CONSTANT_ENCAPSED_STRING === $id && strlen($x) > 3 && !str_contains($x, "\n") && chance(10) && !str_contains($x, '\\')) {
            $q = $x[0];
            $inner = substr($x, 1, -1);
            if (preg_match('/^[\x20-\x7e]*$/', $inner) && "'" === $q || '"' === $q && !str_contains($inner, '$') && !str_contains($inner, '{')) {
                $p = mt_rand(0, strlen($inner));
                $t[$i][1] = $q.substr($inner, 0, $p).pick(["\n", "\n  ", "\r\n"]).substr($inner, $p).$q;
            }
        }
    }
}

/** @param list<string> $lines */
function indentText(string $indent, int $extra, array $lines): string
{
    return implode("\n", array_map(static fn (string $l): string => '' === $l ? '' : $indent.str_repeat(' ', $extra).$l, $lines));
}

function m_numeric(array &$t): void
{
    $ints = ['1000000', '1_000', '12345', '0xABCDEF', '0xff', '0b10101010101', '0777', '0o17', '0_7', '99999', '123456789', '1_2_3', '00', '0B11', '0XfF'];
    $floats = ['1234.5678', '100000.12345', '1e10', '1.5e-10', '0.0001', '123456.123456', '1_000.5', '.5', '7E3', '12345678.9'];
    foreach ($t as $i => [$id, $x]) {
        if (T_LNUMBER === $id && chance(25)) {
            $t[$i][1] = pick($ints);
        } elseif (T_DNUMBER === $id && chance(25)) {
            $t[$i][1] = pick($floats);
        }
    }
}

function m_doctrine(array &$t): void
{
    $pool = [
        "/**\n * @ORM\\Entity(repositoryClass = \"Repo\" , readOnly=true)\n * @Foo\n * @Bar( )\n */",
        "/**\n * @Route(\"/x\", name = \"y\",methods={\"GET\" , \"POST\"})\n * @var int\n */",
        "/**\n * @Assert\\Choice(choices={\"a\" = \"b\", \"c\":\"d\"}, strict = true)\n */",
        "/**\n * @Multi(\n *     a = 1,\n *   b= {\n *         \"x\" = 2,\n *     \"y\": 3\n *   },\n *       c = @Sub(\n *   d=4\n * )\n * )\n */",
        '/** @Foo(a = 1) */',
        "/**\n * @Nested(a=@Inner(b=1,c=@Deep()), d={@X, @Y(1)})\n * @param string \$x\n */",
        "/**\n * @Trailing(a=1,\n *  b=2) tail\n */",
        "/**\n * @Annot( \"str with \"\"quote\"\"\" , 12 , 1.5e3, -4, Foo\\Bar::BAZ, true )\n */",
    ];
    $edits = [];
    foreach ($t as $i => [$id, $x]) {
        if (in_array($id, [T_CLASS, T_FUNCTION, T_VARIABLE, T_CONST], true) && chance(14)) {
            $m = $id === T_VARIABLE ? declStartVar($t, $i) : declStart($t, $i);
            if (null === $m || T_DOUBLE_COLON === prevId($t, $m) || T_NEW === prevId($t, $m)) {
                continue;
            }
            $indent = lineIndent($t, $m);
            $doc = pick($pool);
            $doc = str_replace("\n ", "\n".$indent.' ', $doc);
            $edits[] = [$m, [[T_DOC_COMMENT, $doc], [T_WHITESPACE, "\n".$indent]]];
        }
    }
    usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
    foreach ($edits as [$m, $ins]) {
        array_splice($t, $m, 0, $ins);
    }
}

function declStartVar(array $t, int $i): ?int
{
    $m = declStart($t, $i);
    if ($m === $i) {
        return null;
    }

    return $m;
}

function m_ifaces(array &$t): void
{
    $names = ['Countable', '\\Stringable', 'Foo\\Bar', 'aa', 'Zeta', '\\JsonSerializable', 'Alpha\\Beta\\Gamma', 'IteratorAggregate', 'Ab', 'AB'];
    $edits = [];
    foreach ($t as $i => [$id, $x]) {
        if (!in_array($id, [T_CLASS, T_INTERFACE], true) || T_NEW === prevId($t, $i) || T_DOUBLE_COLON === prevId($t, $i)) {
            continue;
        }
        // find the opening brace
        $j = $i + 1;
        $hasImpl = false;
        $hasExt = false;
        while (isset($t[$j]) && !(null === $t[$j][0] && '{' === $t[$j][1])) {
            $hasImpl = $hasImpl || T_IMPLEMENTS === $t[$j][0];
            $hasExt = $hasExt || T_EXTENDS === $t[$j][0];
            ++$j;
        }
        if (!isset($t[$j]) || !chance(30)) {
            continue;
        }
        $k = pick([2, 3, 4]);
        $list = [];
        for ($n = 0; $n < $k; ++$n) {
            $list[] = pick($names);
        }
        $list = array_values(array_unique($list));
        if (T_INTERFACE === $id) {
            if (!$hasExt) {
                $edits[] = [$j, ' extends '.implode(pick([', ', ',', " ,\n    "]), $list).' '];
            }
        } elseif (!$hasImpl) {
            $edits[] = [$j, ' implements '.implode(pick([', ', ',', ",\n    "]), $list).' '];
        }
    }
    usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
    foreach ($edits as [$j, $text]) {
        // the whitespace before `{` stays in front of the insertion: place it before the brace and keep a space
        array_splice($t, $j, 0, [[null, rtrim($text)], [T_WHITESPACE, ' ']]);
    }
}

function m_stmts(array &$t): void
{
    $pool = ['if ($a) { return true; } return false;', 'if ($b) return false; return true;', '$q = $q ?? [];', '$z = isset($m) ? $m : null;', 'list($a, $b) = $c;', '[$d, [$e]] = $f;',
        '(new \\stdClass())->x;', 'echo ! $a, !$b, !  $c;', '$r = !$s;', '$n = isset($m[1]) ? $m[1] : 5;', 'foreach ($items as list($k, $v)) {}', 'foreach ($items as [$k, $v]) {}',
        'if ($x) { return null; }', '$w = $w ?? ($w2 ?? 3);', '$u = 0777 + 1_000;', 'return null;', '$l = (new Foo)->bar();', '$m = new class { public function __toString() { return ""; } };'];
    $out = [];
    foreach ($t as $i => [$id, $x]) {
        $out[] = [$id, $x];
        if (null === $id && ';' === $x && isset($t[$i + 1]) && T_WHITESPACE === $t[$i + 1][0] && str_contains($t[$i + 1][1], "\n") && chance(3)) {
            $out[] = [T_WHITESPACE, "\n".lineIndent($t, $i)];
            $out[] = [null, pick($pool)];
        }
    }
    $t = $out;
}

function lints(string $code): bool
{
    $p = proc_open(['php', '-d', 'xdebug.mode=off', '-d', 'display_errors=0', '-l'], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    fwrite($pipes[0], $code);
    fclose($pipes[0]);

    return 0 === proc_close($p);
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
        if (lints($new)) {
            $code = $new;
        }
    }

    return $code;
}

[$self, $src, $dst, $seed, $fams] = $argv + [null, null, null, '29', null];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_new.php <src> <dst> [seed] [family,...]\n");
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
