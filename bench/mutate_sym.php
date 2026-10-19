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

const FAMILIES = ['case', 'misc1'];

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
