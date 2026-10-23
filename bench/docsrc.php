<?php

declare(strict_types=1);

// usage: php bench/docsrc.php <out-dir>
// A docblock-rich corpus for the phpdoc rules, picked deterministically from third-party code on this machine (WordPress: @access,
// @package, @since, old-style docs; doctrine / twig / monolog / league / egulias / nette: typed annotations, generics, inheritdoc).
// Every k-th file of a sorted list, under 80 KB; copied flat as <source>-<n>.php.

require __DIR__.'/lib.php';

$out = $argv[1] ?? null;
if (null === $out) {
    fwrite(STDERR, "usage: php bench/docsrc.php <out-dir>\n");
    exit(2);
}
$v = getenv('VENDOR_DIR') ?: __DIR__.'/work/vendor'; // a composer vendor/ with doctrine, twig, monolog, ...
$picks = [
    ['wp', (getenv('WP_INCLUDES') ?: __DIR__.'/work/wp-includes'), 4, 100],
    ['doctrine', $v.'/doctrine', 12, 80],
    ['twig', $v.'/twig', 5, 40],
    ['monolog', $v.'/monolog', 4, 25],
    ['league', $v.'/league', 12, 25],
    ['egulias', $v.'/egulias', 4, 20],
    ['nette', $v.'/nette', 3, 15],
    ['pcftest', UPSTREAM.'/tests', 25, 30],
    ['pcfsrc', UPSTREAM.'/src', 20, 30],
];
@mkdir($out, 0777, true);
$total = 0;
foreach ($picks as [$tag, $dir, $every, $max]) {
    if (!is_dir($dir)) {
        fwrite(STDERR, "missing $dir\n");
        continue;
    }
    $n = 0;
    foreach (phpFiles($dir) as $k => $rel) {
        if ($k % $every !== 0 || filesize($dir.'/'.$rel) > 80000) {
            continue;
        }
        copy($dir.'/'.$rel, sprintf('%s/%s-%03d.php', $out, $tag, $n));
        if (++$n >= $max) {
            break;
        }
    }
    $total += $n;
}
echo "$total files\n";
