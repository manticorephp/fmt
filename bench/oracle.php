<?php

declare(strict_types=1);

// usage: php -d xdebug.mode=off bench/oracle.php <corpus-dir> <rules>
// Runs upstream php-cs-fixer under Zend and bin/manticore-fmt, each on its own COPY of the corpus (real `fix`, not
// dry-run), then compares every resulting file byte for byte and the set of changed files.
// Prints `IDENTICAL: N changed files` and exits 0, or the first differing file and exits 1.

require __DIR__.'/lib.php';

[$self, $corpus, $rules] = $argv + [null, null, null];
if (null === $corpus || null === $rules || false === realpath($corpus)) {
    fwrite(STDERR, "usage: php bench/oracle.php <corpus-dir> <rules>\n");
    exit(2);
}
$corpus = realpath($corpus);
if (!is_file(ZEND_FIXER)) {
    fwrite(STDERR, "upstream worktree missing: ".ZEND_FIXER."\n");
    exit(3);
}
$native = getenv('FMT_BIN') ?: __DIR__.'/../bin/manticore-fmt';

$tmp = sys_get_temp_dir().'/mfmt-oracle-'.getmypid();
$zendDir = $tmp.'/zend';
$natDir = $tmp.'/native';
copyTree($corpus, $zendDir);
copyTree($corpus, $natDir);

function run(array $cmd): int
{
    // cwd = temp dir so no .php-cs-fixer.dist.php is auto-loaded
    $p = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, sys_get_temp_dir());

    return proc_close($p);
}

$zc = run(['php', '-d', 'xdebug.mode=off', '-d', 'memory_limit=-1', ZEND_FIXER, 'fix', '--using-cache=no', '--sequential', '--rules='.$rules, $zendDir]);
$nc = run([$native, 'fix', '--rules='.$rules, $natDir]);
if (!in_array($zc, [0, 8, 64, 72], true) || 0 !== $nc) { // 64 (+8): upstream left a file whose result did not lint; the per-file comparison still applies
    fwrite(STDERR, "unexpected exit: zend=$zc native=$nc\n");
    rmTree($tmp);
    exit(3);
}

$changed = 0;
$bad = null;
foreach (phpFiles($corpus) as $rel) {
    $orig = file_get_contents($corpus.'/'.$rel);
    $z = file_get_contents($zendDir.'/'.$rel);
    $n = file_get_contents($natDir.'/'.$rel);
    if ($z !== $orig) {
        ++$changed;
    }
    if ($z !== $n && null === $bad) {
        $bad = [$rel, $orig, $z, $n];
    }
}
if (null === $bad) {
    echo "IDENTICAL: $changed changed files\n";
    rmTree($tmp);
    exit(0);
}
[$rel, $orig, $z, $n] = $bad;
echo "DIFF at $rel (zend ".($z === $orig ? 'unchanged' : 'changed').', native '.($n === $orig ? 'unchanged' : 'changed').")\n";
$at = 0;
$max = min(strlen($z), strlen($n));
while ($at < $max && $z[$at] === $n[$at]) {
    ++$at;
}
$from = max(0, $at - 40);
echo "first differing byte $at (of zend ".strlen($z).', native '.strlen($n).")\n  zend  : ".json_encode(substr($z, $from, 90))."\n  native: ".json_encode(substr($n, $from, 90))."\n";
echo "kept: $tmp\n";
exit(1);
