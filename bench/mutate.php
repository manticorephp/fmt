<?php

declare(strict_types=1);

// usage: php bench/mutate.php <src-corpus> <dst-dir> [seed]
// Writes a deterministically DIRTIED copy of a corpus (the clean corpus changes nothing under the whitespace rules, so
// the oracle on it proves little): trailing blanks, blank-only lines, CRLF/mixed line ends (never lone CR, see tests/repro), spaces after the open
// tag, whitespace at EOF, comment line breaks of every \R flavour, and invalid UTF-8 in a comment.

require __DIR__.'/lib.php';

[$self, $src, $dst, $seed] = $argv + [null, null, null, '7'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate.php <src> <dst> [seed]\n");
    exit(2);
}
mt_srand((int) $seed);
rmTree($dst);
foreach (phpFiles($src) as $n => $rel) {
    $code = file_get_contents($src.'/'.$rel);
    $lines = explode("\n", $code);
    foreach ($lines as $i => $l) {
        $r = mt_rand(0, 99);
        if ($r < 4) {
            $lines[$i] = $l.str_repeat(' ', mt_rand(1, 3));
        } elseif ($r < 6) {
            $lines[$i] = $l."\t";
        } elseif ($r < 9 && '' === trim($l)) {
            $lines[$i] = str_repeat(mt_rand(0, 1) ? ' ' : "\t", mt_rand(1, 4));
        }
    }
    $code = implode("\n", $lines);
    $mode = $n % 8;
    if (1 === $mode) {
        $code = str_replace("\n", "\r\n", $code);
    } elseif (2 === $mode) {
        $code = preg_replace_callback('/\n/', static fn (): string => mt_rand(0, 3) ? "\n" : "\r\n", $code);
    } elseif (3 === $mode) {
        $code = preg_replace_callback('/\n/', static fn (): string => mt_rand(0, 9) ? "\r\n" : "\r\n \t\r\n", $code); // lone CR is NOT generated: see tests/repro/lone_cr_line_comment.php
    } elseif (4 === $mode) {
        $code = preg_replace('/^<\?php\n/', "<?php  \n", $code, 1).'   ';
    } elseif (5 === $mode) {
        $code = preg_replace('/^<\?php\n/', "<?php\t\r\n   \r\n", $code, 1)."\n\n  \n";
    } elseif (6 === $mode) {
        // special line breaks only inside block comments: upstream line_ending turns them into \n, which would end a // comment
        $code = preg_replace('#^(\s*)//#m', "$1/* \x0b \x0c \u{2028} \u{85} x */\n$1//", $code, 3);
        $code = rtrim($code)."\n/* a\x85b\r\n   c */\n";
    } elseif (7 === $mode) {
        $code = rtrim($code)."\n/* bad \xff\xfe \x85 bytes */\n\$x = <<<EOT\r\n  a  \r\n \t\r\nEOT;\n";
    }
    @mkdir(dirname($dst.'/'.$rel), 0777, true);
    file_put_contents($dst.'/'.$rel, $code);
}
echo count(phpFiles($dst))." files -> $dst\n";
