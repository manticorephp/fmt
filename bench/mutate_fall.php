<?php

declare(strict_types=1);

// usage: php bench/mutate_fall.php <src-corpus> <dst-dir> [seed]
// Switch dirt: `break;` / `return ...;` statements of switch cases are deleted (cases then fall through) and `// no break` style
// comments of several shapes are added after statements, so no_break_comment has something to add, edit and remove. Operators at the
// end of a line move to the start of the next one (and back) for operator_linebreak.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function mutate(string $code): string
{
    $toks = token_get_all($code);
    $n = count($toks);
    $out = [];
    $comments = ['// no break', '// NO BREAK', '/* no break */', '/** no break */', '# no break', '// no break because', '// something else', "/*\n * no break\n */"];
    for ($i = 0; $i < $n; ++$i) {
        $t = $toks[$i];
        if (is_array($t) && in_array($t[0], [T_BREAK, T_RETURN, T_CONTINUE], true) && mt_rand(0, 99) < 55) {
            // drop the statement up to its `;` (closures / arrays / calls inside are skipped by depth)
            $j = $i + 1;
            $depth = 0;
            while ($j < $n) {
                $x = $toks[$j];
                if ('(' === $x || '[' === $x || '{' === $x || (is_array($x) && in_array($x[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    ++$depth;
                } elseif (')' === $x || ']' === $x || '}' === $x) {
                    --$depth;
                } elseif (';' === $x && $depth <= 0) {
                    break;
                }
                ++$j;
            }
            if ($j < $n) {
                $i = $j;
                // keep a comment instead of the statement sometimes
                if (mt_rand(0, 99) < 40) {
                    $out[] = $comments[mt_rand(0, count($comments) - 1)];
                }
                continue;
            }
        }
        $out[] = is_array($t) ? $t[1] : $t;
    }

    return implode('', $out);
}

[$self, $src, $dst, $seed] = $argv + [null, null, null, '17'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_fall.php <src> <dst> [seed]\n");
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
